<?php
// This script runs the migration to restore the DNA investigator table

// Set up the environment
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL', 3);

// Include Yii
require_once 'protected/yiibase.php';

// Load configuration
$config = require_once 'protected/config/main.php';

// Create application instance
$app = Yii::createWebApplication($config);

// Create migration manager
$migrationPath = 'protected/modules/OphInDnaextraction/migrations';
$migration = new CDbMigration();
$migration->setDbConnection($app->db);

// Load the migration class
$migrationClass = 'm2026_01_06_restore_investigator_table';
$migrationFile = $migrationPath . '/' . $migrationClass . '.php';

if (file_exists($migrationFile)) {
    require_once $migrationFile;
    
    // Create an instance
    $migration = new $migrationClass('up');
    
    try {
        echo "Running migration: {$migrationClass}\n";
        $migration->setDbConnection(Yii::app()->db);
        $migration->up();
        echo "Migration completed successfully!\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} else {
    echo "Migration file not found: {$migrationFile}\n";
    exit(1);
}
