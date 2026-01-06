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
    
    // Execute the SQL to set default=1 for DNA extraction element type
    echo "Setting DNA extraction element type as default...\n";
    
    $sql = "UPDATE element_type SET `default` = 1 WHERE `class_name` = 'Element_OphInDnaextraction_DnaExtraction'";
    
    $result = $db->createCommand($sql)->execute();
    
    echo "Updated " . $result . " rows.\n";
    
    if ($result > 0) {
        echo "SUCCESS: DNA extraction element type is now marked as default.\n";
    } else {
        echo "WARNING: No rows were updated. The element type may not exist or may already be set.\n";
    }
    
    // Verify the update
    $element = $db->createCommand("SELECT id, name, `default` FROM element_type WHERE `class_name` = 'Element_OphInDnaextraction_DnaExtraction'")->queryRow();
    
    if ($element) {
        echo "\nVerification:\n";
        echo "  Element ID: " . $element['id'] . "\n";
        echo "  Element Name: " . $element['name'] . "\n";
        echo "  Default Flag: " . $element['default'] . "\n";
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<pre>";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "</pre>";
    exit(1);
}
