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
    
    // Test database connection
    echo "Testing database connection...\n";
    $tables = $db->createCommand("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->queryScalar();
    echo "Database tables found: " . $tables . "\n\n";
    
    // Try a simple count
    echo "Checking element_type table:\n";
    $count = $db->createCommand("SELECT COUNT(*) FROM element_type")->queryScalar();
    echo "Element types in database: " . $count . "\n\n";
    
    // List all DNA-related element types
    echo "DNA-related element types:\n";
    $dna_types = $db->createCommand("SELECT id, name, class_name, event_type_id, `default` FROM element_type WHERE class_name LIKE '%Dna%' OR class_name LIKE '%DNA%'")->queryAll();
    
    if (empty($dna_types)) {
        echo "No DNA element types found.\n";
        echo "Let's try to update anyway based on class name.\n";
    } else {
        echo "Found " . count($dna_types) . " DNA element types:\n";
        foreach ($dna_types as $et) {
            echo "  - {$et['class_name']} (ID: {$et['id']}, Default: {$et['default']})\n";
        }
    }
    
    echo "\nAttempting to update Element_OphInDnaextraction_DnaExtraction to be default...\n";
    $result = $db->createCommand()->update(
        'element_type',
        array('`default`' => 1),
        "class_name = :className",
        array(':className' => 'Element_OphInDnaextraction_DnaExtraction')
    );
    
    echo "Updated " . $result . " rows.\n";
    
    // Verify the update
    $updated = $db->createCommand("SELECT id, `default` FROM element_type WHERE class_name = 'Element_OphInDnaextraction_DnaExtraction'")->queryRow();
    if ($updated && $updated['default'] == 1) {
        echo "SUCCESS: DNA extraction element type is now marked as default.\n";
    } else {
        echo "WARNING: Update may have failed.\n";
    }
    
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<pre>";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "</pre>";
    exit(1);
}
