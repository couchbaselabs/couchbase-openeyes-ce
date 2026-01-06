<?php
require_once 'index.php';

try {
    $db = Yii::app()->db;
    
    // First, let's check if we can even query event_type table
    echo "Checking event_type table...\n";
    $types = $db->createCommand("SELECT id, name, class_name FROM event_type LIMIT 10")->queryAll();
    
    echo "Found " . count($types) . " event types:\n";
    foreach ($types as $type) {
        echo "  ID: " . $type['id'] . ", Name: " . $type['name'] . ", Class: " . $type['class_name'] . "\n";
    }
    
    // Check OphInDnasample specifically
    echo "\nSearching for OphInDnasample...\n";
    $result = $db->createCommand("SELECT id FROM event_type WHERE class_name = 'OphInDnasample'")->queryRow();
    if ($result) {
        echo "Found: " . $result['id'] . "\n";
    } else {
        echo "Not found\n";
    }
    
    // Try to describe the event_type table
    echo "\nChecking event_type table structure...\n";
    $columns = $db->getCommandBuilder()->getTableSchema('event_type')->getColumnNames();
    echo "Columns: " . implode(", ", $columns) . "\n";
    
    // Try direct insert with error handling
    echo "\nAttempting insert...\n";
    try {
        $cmd = $db->createCommand("INSERT INTO event_type (name, class_name, display_order) VALUES ('DNA Sample Test', 'OphInDnasampleTest', 1)");
        $result = $cmd->execute();
        echo "Insert result: " . ($result ? "true" : "false") . "\n";
        
        // Check if it was inserted
        $check = $db->createCommand("SELECT id FROM event_type WHERE class_name = 'OphInDnasampleTest'")->queryRow();
        if ($check) {
            echo "Verification: Found inserted record with ID " . $check['id'] . "\n";
        } else {
            echo "Verification: Record not found\n";
        }
    } catch (Exception $e) {
        echo "Insert error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
