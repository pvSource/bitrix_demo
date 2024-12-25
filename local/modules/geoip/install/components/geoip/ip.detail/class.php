<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Web\HttpClient;
use \Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Mail\Event;

Loader::IncludeModule('highloadblock');
Loader::IncludeModule('geoip');

class IpDetail extends \CBitrixComponent implements Controllerable
{
    const URL_SYPEXGEO = "http://api.sypexgeo.net/json/#IP#";

    public function configureActions()
    {
        return []; //todo: при необходимости сконфигурировать в дальнейшем
    }

    public function executeComponent()
    {
        $this->IncludeComponentTemplate(); //todo: в контексте данного компонента - использование кэширования компонента - не будет иметь никакого значения
    }

    public function getIpInfoAction(string $ip): array
    {
        try {
            // 1. Проверяем валидность Ip
            $isValidIp = $this->isValidIp($ip);
            if (!$isValidIp) {
                throw new \Exception("Некорректный IP-адрес");
            }

            // -. Получаем entity hl-блока
            $repositoryEntity = $this->getRepositoryEntity();

            // 2. Проверяем нет ли записи в БД и если есть - возвращаем результат
            $ipInfo = $this->getIpInfoFromDb($ip, $repositoryEntity);
            if ($ipInfo) {
                $ipInfo['source'] = 'Внутренняя база данных';
                return $ipInfo;
            }

            // 3. Получаем данные с внешнего сервиса
            $ipInfo = $this->getIpInfoFromGeo($ip);
            $ipInfo['source'] = 'Внешний источник данных';

            if (!$ipInfo['country']) {
                throw new \Exception('Указанный IP не принадлежит ни одной стране');
            }

            // 4. Сохраняем информацию с внешнего сервиса
            $this->saveIpInfo($ipInfo, $repositoryEntity);

            return $ipInfo;
        } catch (\Throwable $throwable) {
            $this->sendErrorMail($throwable);
            throw new \Exception('Некорректный IP-адрес'); //todo: добавить новые классы исключений и в зависимости от типа исключения реагировать по разному
        }
    }

    private function sendErrorMail(\Throwable $throwable)
    {
        //todo: реализовать отправку писем используя \Bitrix\Main\Mail\Event::send
        //todo: предварительно создав новый тип почтового события
    }

    /**
     * Получить entity HL-хранилища
     * @return mixed
     */
    private function getRepositoryEntity()
    {
        $hldata = HL\HighloadBlockTable::getById(
            Option::get("geoip", "HL_GEOIPS_ID")
        )->fetch();
        //затем инициализировать класс сущности
        return HL\HighloadBlockTable::compileEntity($hldata);
    }

    /**
     * Корректный ли IP
     * @param $ip
     * @return bool
     */
    private function isValidIp($ip): bool
    {
        return (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
    }

    /**
     * Сохранить информацию об IP в хранилище
     * @param array $ipInfo Геоинформация об IP
     * @param $repositoryEntity
     * @return void
     */
    private function saveIpInfo(array $ipInfo, $repositoryEntity)
    {
        $strEntityDataClass = $repositoryEntity->getDataClass();
        $strEntityDataClass::add([
            'UF_IP' => $ipInfo['ip'],
            'UF_CITY_NAME_RU' => $ipInfo['city'],
            'UF_REGION_NAME_RU' => $ipInfo['region'],
            'UF_COUNTRY_NAME_RU' => $ipInfo['country'],
            'UF_DATE_ADD' => new \Bitrix\Main\Type\DateTime()
        ]);
    }

    /**
     * Получить информацию об IP из внутреннего хранилища
     * @param string $ip
     * @param $repositoryEntity
     * @return array|null
     */
    private function getIpInfoFromDb(string $ip, $repositoryEntity): ?array
    {
        $strEntityDataClass = $repositoryEntity->getDataClass();

        $arIp = $strEntityDataClass::getList([
            'select' => ['*'],
            'filter' => [
                'UF_IP' => $ip
            ],
            'limit' => 1
        ])->fetch();

        if (!$arIp) {
            return null;
        }

        return [
            'ip' => $arIp['UF_IP'],
            'city' => $arIp['UF_CITY_NAME_RU'],
            'region' => $arIp['UF_REGION_NAME_RU'],
            'country' => $arIp['UF_COUNTRY_NAME_RU']
        ];
    }

    private function getIpInfoFromGeo(string $ip): array
    {
        $httpClient = new HttpClient();
        $response = json_decode( //todo: разбить на отдельные строки кода, проверять на нетипичные ситуации
            $httpClient->get(
                str_replace("#IP#", $ip, self::URL_SYPEXGEO)
            )
        );

        return [
            'ip' => $ip,
            'city' => $response->city?->name_ru,
            'region' => $response->region?->name_ru,
            'country' => $response->country?->name_ru,
        ];
    }
}
