<?php
/**
 * Script to check if the external source table exists
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

echo "<h2>Checking External Source Table</h2>\n";
echo "<pre>\n";

try {
    // Get database connection
    $db = Yii::app()->db;
    
    echo "Getting table list...\n";
    
    // Get all tables
    $tables = $db->getSchema()->getTableNames();
    
    // Search for tables with 'external' in the name
    echo "\nTables containing 'external':\n";
    foreach ($tables as $table) {
        if (stripos($table, 'external') !== false) {
            echo "  - " . $table . "\n";
        }
    }
    
    // Try to create the table directly using raw SQL
    echo "\n--- Attempting to create table via raw SQL ---\n";
    
    $tableName = 'ophingeneticresults_external_source';
    $tableVersionName = 'ophingeneticresults_external_source_version';
    
    // Check if tables exist
    try {
        $result = $db->createCommand("DESCRIBE {$tableName}")->execute();
        echo "✓ Table '{$tableName}' already exists\n";
    } catch (Exception $e) {
        echo "Table '{$tableName}' does not exist, attempting to create it...\n";
        
        try {
            // Create the table
            $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
                `last_modified_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                `created_user_id` int(10),
                `last_modified_user_id` int(10),
                `deleted` tinyint(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            
            $db->createCommand($sql)->execute();
            echo "✓ Created table '{$tableName}'\n";
        } catch (Exception $e2) {
            echo "✗ Failed to create table: " . $e2->getMessage() . "\n";
        }
        
        // Try to create version table too
        try {
            $sql = "CREATE TABLE IF NOT EXISTS `{$tableVersionName}` (
                `id` int(11) NOT NULL,
                `version_id` int(10) NOT NULL,
                `name` varchar(255),
                PRIMARY KEY (`id`, `version_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            
            $db->createCommand($sql)->execute();
            echo "✓ Created table '{$tableVersionName}'\n";
        } catch (Exception $e3) {
            echo "Note: Version table already exists or creation skipped\n";
        }
    }
    
    // Verify table exists now
    echo "\n--- Verifying table creation ---\n";
    $tables = $db->getSchema()->getTableNames();
    $exists = in_array($tableName, $tables);
    echo "Table '{$tableName}' exists: " . ($exists ? "YES ✓" : "NO ✗") . "\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "\n" . $e->getTraceAsString();
}

echo "</pre>\n";
?>
