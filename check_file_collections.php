<?php
// Check if file collections exist in the database
// Run this from command line: php check_file_collections.php

// Include Yii
require_once dirname(__FILE__) . '/yii.php';

// Load configuration
$config = require dirname(__FILE__) . '/protected/config/main.php';

// Create application
$app = Yii::createApplication('CWebApplication', $config);

// Query the database directly
try {
    $connection = Yii::app()->db;
    
    // Get all file collections
    echo "=== ALL FILE COLLECTIONS IN DATABASE ===\n";
    $sql = "SELECT id, name, summary, institution_id FROM ophcotherapya_filecoll ORDER BY id DESC";
    $command = $connection->createCommand($sql);
    $records = $command->queryAll();
    
    echo "Total count: " . count($records) . "\n\n";
    
    foreach ($records as $record) {
        echo "ID: " . $record['id'] . "\n";
        echo "Name: " . $record['name'] . "\n";
        echo "Summary: " . $record['summary'] . "\n";
        echo "Institution ID: " . ($record['institution_id'] ?? 'NULL') . "\n";
        echo "---\n";
    }
    
    // Check session institution
    echo "\n=== SESSION INFORMATION ===\n";
    if (isset(Yii::app()->session['selected_institution_id'])) {
        echo "Selected Institution ID: " . Yii::app()->session['selected_institution_id'] . "\n";
    } else {
        echo "Selected Institution ID: NOT SET\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
