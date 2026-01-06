<?php
// Direct test of the SiteSubspecialtyAnaestheticAgent model
// This script should be accessible via http://localhost:7777/test_mapping_query.php

// Define YII_DEBUG
if (!defined('YII_DEBUG')) {
    define('YII_DEBUG', true);
}
if (!defined('YII_TRACE_LEVEL')) {
    define('YII_TRACE_LEVEL', 3);
}

$basePath = dirname(__FILE__);

// Require autoloader
if (file_exists($basePath . '/vendor/autoload.php')) {
    require_once($basePath . '/vendor/autoload.php');
}

// Require Yii
require_once($basePath . '/vendor/yiisoft/yii/framework/yii.php');

// Get the config
$config = require($basePath . '/protected/config/main.php');

// Create the app
Yii::createWebApplication($config);

// Now query the model
$mappings = SiteSubspecialtyAnaestheticAgent::model()->findAll(
    array(
        'order' => 'id DESC',
        'limit' => 10,
    )
);

header('Content-Type: text/plain');
echo "Total mappings found: " . count($mappings) . "\n";
echo "========================================\n";

foreach ($mappings as $mapping) {
    echo "ID: " . $mapping->id;
    echo ", Site ID: " . $mapping->site_id;
    echo ", Subspecialty ID: " . $mapping->subspecialty_id;
    echo ", Agent ID: " . $mapping->anaesthetic_agent_id;
    
    // Try to get related names
    if ($mapping->agents) {
        echo ", Agent Name: " . $mapping->agents->name;
    }
    echo "\n";
}
?>
