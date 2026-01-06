<?php

// Define Yii constants
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL', 3);

// Get the dirname
$dirname = dirname(__FILE__);

// Load Yii framework
if (file_exists($dirname . '/vendor/autoload.php')) {
    require_once($dirname . '/vendor/autoload.php');
}
$yii = $dirname . '/vendor/yiisoft/yii/framework/yii.php';
require_once($yii);

// Load Yii configuration
$config = require_once $dirname . '/protected/config/main.php';
$app = Yii::createWebApplication($config);

// Get the database connection
$db = Yii::app()->db;

try {
    echo "<pre>";
    
    // Get the element_type table schema
    echo "Checking element_type table schema...\n";
    $schema = $db->schema;
    $table = $schema->getTable('element_type', true);
    
    if (!$table) {
        echo "ERROR: element_type table not found.\n";
    } else {
        echo "Table found. Columns:\n";
        foreach ($table->columns as $name => $column) {
            echo "  - " . $name . " (" . $column->dbType . ")\n";
        }
        
        echo "\nChecking for 'default' column:\n";
        if (isset($table->columns['default'])) {
            echo "  'default' column EXISTS\n";
        } else {
            echo "  'default' column DOES NOT EXIST\n";
        }
    }
    
    // Also try to query the element_type table directly
    echo "\nElement types in database:\n";
    $elements = $db->createCommand("SELECT id, class_name, name FROM element_type LIMIT 10")->queryAll();
    
    if (empty($elements)) {
        echo "  No element types found.\n";
    } else {
        echo "  Found " . count($elements) . " element types:\n";
        foreach ($elements as $et) {
            echo "    - {$et['class_name']} (Name: {$et['name']})\n";
        }
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<pre>";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "</pre>";
    exit(1);
}
