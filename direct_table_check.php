<?php
/**
 * Script to directly query the external source table
 */

// Define Yii DEBUG mode before including config
define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

$dirname = dirname(__FILE__);

// Setup autoloader
if (file_exists($dirname . '/vendor/autoload.php')) {
    require_once($dirname . '/vendor/autoload.php');
}

// Create Yii app
$yiiFile = $dirname . '/vendor/yiisoft/yii/framework/yii.php';
require_once($yiiFile);

// Create web app (this initializes Yii properly)
$config = require($dirname . '/protected/config/main.php');
$app = Yii::createWebApplication($config);

echo "<h2>Direct Table Query Check</h2>\n";
echo "<pre>\n";

try {
    // Get database connection
    $db = Yii::app()->db;
    
    $tableName = 'ophingeneticresults_external_source';
    
    echo "Attempting to query table directly...\n";
    
    try {
        $result = $db->createCommand("SELECT COUNT(*) as count FROM {$tableName}")->queryRow();
        echo "✓ Successfully queried table!\n";
        if (is_array($result)) {
            echo "  Row count: " . $result['count'] . "\n";
        } else {
            echo "  Query returned: " . var_export($result, true) . "\n";
        }
    } catch (Exception $e) {
        echo "✗ Query failed: " . $e->getMessage() . "\n";
        
        // Try with backticks
        try {
            echo "\nTrying with backticks...\n";
            $result = $db->createCommand("SELECT COUNT(*) as count FROM `{$tableName}`")->queryRow();
            echo "✓ Successfully queried table with backticks!\n";
            echo "  Row count: " . $result['count'] . "\n";
        } catch (Exception $e2) {
            echo "✗ Query with backticks also failed: " . $e2->getMessage() . "\n";
        }
    }
    
    // Try using the model
    echo "\nAttempting to use OphInGeneticresults_External_Source model...\n";
    
    try {
        // Include the model
        require_once($dirname . '/protected/modules/OphInGeneticresults/models/OphInGeneticresults_External_Source.php');
        
        // Clear Yii's metadata cache for this model
        Yii::app()->db->getSchema()->refresh();
        
        // Try to get the model
        $model = OphInGeneticresults_External_Source::model();
        echo "✓ Model instantiated\n";
        
        // Try to find all records
        $records = $model->findAll();
        echo "✓ Model query executed successfully!\n";
        echo "  Records found: " . count($records) . "\n";
        
    } catch (Exception $e) {
        echo "✗ Model query failed: " . $e->getMessage() . "\n";
        echo "\nTrace:\n" . $e->getTraceAsString();
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\n" . $e->getTraceAsString();
}

echo "</pre>\n";
?>
