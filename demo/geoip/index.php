<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php"); ?>
<?php $APPLICATION->SetTitle("GeoIp"); ?>
<?php
$APPLICATION->IncludeComponent(
    "geoip:ip.detail",
    ".default",
    []
);
?>

<?php require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>