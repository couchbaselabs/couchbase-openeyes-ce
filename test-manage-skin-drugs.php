<?php
// Test script to verify manageSkinDrugs action

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the base path
define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

// Include Yii
require_once(__DIR__ . '/protected/yiisoft/yii/yiic.php');

// Create the Yii application
$config = require(__DIR__ . '/protected/config/main.php');
$app = Yii::createApplication('CWebApplication', $config);

try {
    // Create the module
    $module = $app->getModule('OphTrIntravitrealinjection');
    
    // Create the controller
    $controller = new OphTrIntravitrealinjection\controllers\AdminController('OphTrIntravitrealinjection/Admin');
    $controller->setModule($module);
    
    // Set up action
    $action = 'manageSkinDrugs';
    
    echo "Testing actionManageSkinDrugs...\n";
    echo "Module: " . get_class($module) . "\n";
    echo "Controller: " . get_class($controller) . "\n";
    echo "Action: $action\n\n";
    
    // Try to get the model
    $model_class = 'OphTrIntravitrealinjection_SkinDrug';
    echo "Model class: $model_class\n";
    
    if (class_exists($model_class)) {
        echo "✓ Model class exists\n";
        
        $model = $model_class::model();
        echo "✓ Model instantiated\n";
        
        // Check if model has CouchbaseModelBridge
        $traits = class_uses($model);
        if (in_array('OE\Models\Traits\CouchbaseModelBridge', $traits)) {
            echo "✓ Model uses CouchbaseModelBridge trait\n";
        } else {
            echo "✗ Model does NOT use CouchbaseModelBridge trait\n";
        }
        
        // Check if table exists
        $table = $model->tableName();
        echo "Table name: $table\n";
        
        try {
            $columns = $model->tableSchema->columns;
            echo "✓ Table schema accessible (" . count($columns) . " columns)\n";
            
            // List columns
            foreach ($columns as $name => $column) {
                echo "  - $name (" . $column->dbType . ")\n";
            }
        } catch (Exception $e) {
            echo "✗ Error accessing table schema: " . $e->getMessage() . "\n";
        }
        
        // Try to find all items
        try {
            echo "\nTrying to fetch all items...\n";
            $items = $model_class::model()->findAll();
            echo "✓ Successfully fetched " . count($items) . " items\n";
            
            foreach ($items as $i => $item) {
                echo "  [$i] ID: " . $item->id . ", Name: " . $item->name . "\n";
            }
        } catch (Exception $e) {
            echo "✗ Error fetching items: " . $e->getMessage() . "\n";
            echo "Stack trace:\n";
            echo $e->getTraceAsString() . "\n";
        }
        
    } else {
        echo "✗ Model class does not exist\n";
    }
    
    echo "\n✓ Test completed successfully\n";
    
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>
