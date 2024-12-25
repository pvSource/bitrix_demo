<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;
use Bitrix\Main\IO\Directory;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Loader;

Loader::IncludeModule('highloadblock');

final class GeoIp extends CModule
{
    var $MODULE_ID = "geoip";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;
    function __construct()
    {
        $arModuleVersion = array();
        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include($path."/version.php");
        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
        {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        }
        $this->MODULE_NAME = "geoip_module – модуль для геопоиска";
        $this->MODULE_DESCRIPTION = "После установки вы сможете пользоваться компонентом geoip:ip.detail";
    }

    function doInstall()
    {
        global $APPLICATION;

        try {
            if (!CheckVersion(ModuleManager::getVersion('main'), '14.00.00')) {
                throw new \Exception('Для использования модуля требуется ядро D7, обновите main до 14+ версии');
            }

            $this
                ->installFiles() // копируем файлы, необходимые для работы модуля
                ->registerModule() // регистрируем модуль в системе
                ->installDB() // создаем таблицы БД, необходимые для работы модуля
                ->installEvents(); // регистрируем обработчики событий

            $APPLICATION->IncludeAdminFile("Установка модуля",  __DIR__ ."/step.php");
            return true;

        } catch (\Throwable $e) {
            CAdminMessage::showMessage($e->getMessage());
            return;
        }

    }
    function doUninstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;

        try {
            $this
                ->unInstallFiles() // копируем файлы, необходимые для работы модуля
                ->unInstallDB() // создаем таблицы БД, необходимые для работы модуля
                ->unRegisterModule() // регистрируем модуль в системе
                ->unInstallEvents(); // регистрируем обработчики событий

            $APPLICATION->IncludeAdminFile("Деинсталляция модуля", $DOCUMENT_ROOT."/local/modules/geoip/install/unstep.php");
            return true;

        } catch (\Throwable $e) {
            CAdminMessage::showMessage($e->getMessage());
            return;
        }
    }

    function installEvents()
    {
        return $this;
    }

    function unInstallEvents()
    {
        return $this;
    }

    /**
     * @return geoip
     */
    function installDB(): self
    {
        //создание hl-блока
        $result = HL\HighloadBlockTable::add(array(
            'NAME' => 'GeoIps',//должно начинаться с заглавной буквы и состоять только из латинских букв и цифр
            'TABLE_NAME' => 'geo_ips',//должно состоять только из строчных латинских букв, цифр и знака подчеркивания
        ));
        if (!$result->isSuccess()) {
            $errors = $result->getErrorMessages();
            throw new \Exception($errors); //todo
        }

        $id = $result->getId();
        Option::set($this->MODULE_ID, "HL_GEOIPS_ID", $id);

        $arLangs = Array(
            'ru' => 'Геолокация IP-адресов',
            'en' => 'Geo Ips'
        );

        foreach($arLangs as $lang_key => $lang_val){
            HL\HighloadBlockLangTable::add(array(
                'ID' => $id,
                'LID' => $lang_key,
                'NAME' => $lang_val
            ));
        }

        $entityId = 'HLBLOCK_'.$id;

        $fields = [
            'UF_IP' => [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_IP',
                'USER_TYPE_ID' => 'string',
                'MANDATORY' => 'Y',
                "LABEL" => Array('ru'=>'IPv4', 'en'=>'IPv4')
            ],

            'UF_CITY_NAME_RU' => [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_CITY_NAME_RU',
                'USER_TYPE_ID' => 'string',
                'MANDATORY' => 'N',
                "LABEL" => Array('ru'=>'Город (рус)', 'en'=>'City (ru)')
            ],

            'UF_REGION_NAME_RU' => [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_REGION_NAME_RU',
                'USER_TYPE_ID' => 'string',
                'MANDATORY' => 'N',
                "LABEL" => Array('ru'=>'Регион (рус)', 'en'=>'Region (ru)')
            ],

            'UF_COUNTRY_NAME_RU' => [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_COUNTRY_NAME_RU',
                'USER_TYPE_ID' => 'string',
                'MANDATORY' => 'N',
                "LABEL" => Array('ru'=>'Страна (рус)', 'en'=>'Country (ru)')
            ],

            'UF_DATE_ADD' => [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => 'UF_DATE_ADD',
                'USER_TYPE_ID' => 'datetime',
                'MANDATORY' => 'Y',
                "LABEL" => Array('ru'=>'Дата добавления записи', 'en'=>'Date add')
            ]
        ];

        $arSavedFieldsRes = Array();
        foreach($fields as $field){
            $obUserField  = new CUserTypeEntity;

            $field['EDIT_FORM_LABEL'] = $field['LABEL'];
            $field['LIST_COLUMN_LABEL'] = $field['LABEL'];
            $field['LIST_FILTER_LABEL'] = $field['LABEL'];
            $field['ERROR_MESSAGE'] = $field['LABEL'];
            $field['HELP_MESSAGE'] = $field['LABEL'];
            unset($field['LABEL']);

            $fieldId = $obUserField->add($field);
            $arSavedFieldsRes[$field['FIELD_NAME']] = $fieldId; //todo
        }

        return $this;
    }

    function unInstallDB()
    {
        $hlGeoipsId = Option::get($this->MODULE_ID, "HL_GEOIPS_ID");

        if (!$hlGeoipsId) {
            throw new \Exception('Не обнаружен ID Highload-блока GEOIPS');
        }

        HL\HighloadBlockTable::delete($hlGeoipsId);
        return $this;
    }

    /**
     * Скопировать файлы необходимые для работ модуля
     * @return $this
     */
    function installFiles()
    {
        CopyDirFiles(
            __DIR__ . "/components",
            Application::getDocumentRoot() . "/bitrix/components",
            true,
            true
        );

        return $this;
    }

    /**
     * Удалить файлы ранее установленные модулем
     * @return $this
     */
    function unInstallFiles()
    {
        DeleteDirFilesEx(
            Application::getDocumentRoot() . "/bitrix/components/geoip"
        );
        return $this;
    }


    /**
     * Регистрация модуля в системе
     * @return void
     */
    private function registerModule(): self
    {
        ModuleManager::registerModule($this->MODULE_ID);
        return $this;
    }

    private function unRegisterModule(): self
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
        return $this;
    }
}
?>