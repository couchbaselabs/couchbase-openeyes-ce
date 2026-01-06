<?php
$dirname = dirname(__FILE__);
require_once($dirname . '/vendor/autoload.php');
$yii = $dirname . '/vendor/yiisoft/yii/framework/yii.php';
$config = $dirname . '/protected/config/main.php';
define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);
require_once($yii);
if (!class_exists('HTMLPurifier_Bootstrap', false)) {
    require_once(Yii::getPathOfAlias('system.vendors.htmlpurifier') . DIRECTORY_SEPARATOR . 'HTMLPurifier.standalone.php');
    HTMLPurifier_Bootstrap::registerAutoload();
}
$app = Yii::createWebApplication($config);

$drug = new MedicationDrug();
$drug->name = 'Test Drug ' . time();
$drug->external_source = 'TEST';
if ($drug->save()) {
    echo 'Drug ID: ' . $drug->id . PHP_EOL;
    $commonMed = new CommonMedications();
    $commonMed->medication_id = $drug->id;
    if ($commonMed->save()) {
        echo 'CommonMedication ID: ' . $commonMed->id . PHP_EOL;
    } else {
        echo 'Error: ' . print_r($commonMed->errors, true);
    }
} else {
    echo 'Error: ' . print_r($drug->errors, true);
}
?>
