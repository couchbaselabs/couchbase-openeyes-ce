<?php
// Check if medication_common table exists

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
    
    if (in_array('medication_common', $tables)) {
        echo "medication_common table exists\n";
    } else {
        echo "medication_common table DOES NOT exist\n";
        echo "Available medication-related tables:\n";
        foreach ($tables as $table) {
            if (strpos($table, 'medication') !== false || strpos($table, 'drug') !== false) {
                echo "  - " . $table . "\n";
            }
        }
    }
    
    // Check for a table that might store common medications
    echo "\nAll tables:\n";
    foreach ($tables as $table) {
        echo "  - " . $table . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
