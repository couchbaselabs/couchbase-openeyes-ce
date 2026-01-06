<?php
/**
 * Script to clear Yii's schema cache for the database
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

echo "<h2>Clearing Yii Schema Cache</h2>\n";
echo "<pre>\n";

try {
    // Get database connection
    $db = Yii::app()->db;
    
    // Clear the schema cache by accessing and clearing the metadata
    echo "Getting schema...\n";
    $schema = $db->getSchema();
    
    // Clear table metadata cache
    echo "Clearing table metadata cache...\n";
    $schema->getTables();
    
    // For CDbSchema, we can also refresh by clearing the _tables property
    // Get reflection to access private properties
    $reflection = new ReflectionClass($schema);
    
    // Clear the tables cache property
    if ($reflection->hasProperty('_tables')) {
        $tablesProperty = $reflection->getProperty('_tables');
        $tablesProperty->setAccessible(true);
        $tablesProperty->setValue($schema, array());
        echo "✓ Cleared _tables cache\n";
    }
    
    // Re-fetch tables to rebuild cache
    echo "Rebuilding schema cache...\n";
    $tables = $schema->getTableNames();
    echo "✓ Schema cache rebuilt\n";
    echo "Found " . count($tables) . " tables\n";
    
    // Specifically check for our table
    $external_source_exists = in_array('ophingeneticresults_external_source', $tables);
    echo "\nExternal source table exists: " . ($external_source_exists ? "YES ✓" : "NO ✗") . "\n";
    
} catch (Exception $e) {
    echo "✗ Error clearing cache: " . $e->getMessage() . "\n";
    echo "\n" . $e->getTraceAsString();
}

echo "</pre>\n";
?>
