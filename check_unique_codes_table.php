<?php
// Check unique_codes table

$dirname = dirname(__FILE__);
if (file_exists($dirname . '/vendor/autoload.php')) {
    require_once($dirname . '/vendor/autoload.php');
}

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

// Check if the table exists
$db = Yii::app()->db;
$schema = $db->schema;

try {
    $tables = $schema->tableNames;
    echo "Total tables: " . count($tables) . "\n";
    
    if (in_array('unique_codes', $tables)) {
        echo "unique_codes table EXISTS\n";
        
        // Get columns
        $table = $schema->getTable('unique_codes');
        echo "\nColumns:\n";
        foreach ($table->columns as $col) {
            echo "  - " . $col->name . " (" . $col->dbType . ")\n";
        }
        
        // Get records
        echo "\nRecords:\n";
        $records = UniqueCodes::model()->findAll();
        echo "Total records: " . count($records) . "\n";
        foreach ($records as $record) {
            echo "  - ID: " . $record->id . ", Code: " . $record->code . ", Active: " . $record->active . "\n";
        }
    } else {
        echo "unique_codes table DOES NOT exist\n";
        echo "\nAvailable tables:\n";
        foreach ($tables as $table) {
            echo "  - " . $table . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
