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
    
    // Check if event_group table exists
    echo "Checking event_group table...\n";
    try {
        $tables = $db->createCommand("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_group'")->queryAll();
        if (empty($tables)) {
            echo "  event_group table DOES NOT EXIST\n";
        } else {
            echo "  event_group table EXISTS\n";
            
            // Get columns
            echo "\n  Columns:\n";
            $columns = $db->createCommand("SELECT COLUMN_NAME, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_group'")->queryAll();
            foreach ($columns as $col) {
                echo "    - " . $col['COLUMN_NAME'] . " (" . $col['COLUMN_TYPE'] . ")\n";
            }
            
            // Check if 'name' column exists
            $name_col = $db->createCommand("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_group' AND COLUMN_NAME='name'")->queryScalar();
            echo "\n  'name' column exists: " . ($name_col > 0 ? "YES" : "NO") . "\n";
            
            // List all data in event_group
            echo "\n  Data in event_group:\n";
            $data = $db->createCommand("SELECT * FROM event_group")->queryAll();
            if (empty($data)) {
                echo "    (empty)\n";
            } else {
                foreach ($data as $row) {
                    echo "    " . var_export($row, true) . "\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "  Error checking event_group: " . $e->getMessage() . "\n";
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<pre>";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "</pre>";
    exit(1);
}
