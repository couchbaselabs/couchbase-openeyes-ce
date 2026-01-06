<?php
/**
 * Script to run the external_source table restoration migration
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

// Now include and run the migration
echo "<h2>Restoring OphInGeneticresults External Source Table</h2>\n";
echo "<pre>\n";

try {
    // Include the migration file
    require_once($dirname . '/protected/modules/OphInGeneticresults/migrations/m260106_000000_restore_external_source_table.php');
    
    // Create and run the migration
    $migration = new m260106_000000_restore_external_source_table();
    $migration->setDbConnection(Yii::app()->db);
    
    echo "Starting migration...\n";
    $migration->up();
    echo "✓ Migration executed successfully!\n";
    echo "✓ External source table has been restored.\n";
    
} catch (Exception $e) {
    echo "✗ Error running migration: " . $e->getMessage() . "\n";
    echo "\n" . $e->getTraceAsString();
}

echo "</pre>\n";
?>

