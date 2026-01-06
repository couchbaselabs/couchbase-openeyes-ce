<?php
require_once 'index.php';

try {
    // First, let's try using the EventType model
    echo "Searching for EventType using model...\n";
    $types = EventType::model()->findAll();
    
    echo "Found " . count($types) . " event types using model\n";
    if (count($types) > 0) {
        echo "First 10 event types:\n";
        foreach (array_slice($types, 0, 10) as $type) {
            echo "  ID: " . $type->id . ", Name: " . $type->name . ", Class: " . $type->class_name . "\n";
        }
    }
    
    // Check OphInDnasample specifically
    echo "\nSearching for OphInDnasample specifically...\n";
    $result = EventType::model()->find('class_name = ?', array('OphInDnasample'));
    if ($result) {
        echo "Found: " . $result->id . " - " . $result->name . "\n";
    } else {
        echo "Not found\n";
        
        // Try to find similar types
        echo "\nSearching for DNA-related types...\n";
        $dna_types = EventType::model()->findAll('class_name LIKE ?', array('%Dna%'));
        echo "Found " . count($dna_types) . " DNA-related types\n";
        foreach ($dna_types as $type) {
            echo "  " . $type->class_name . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
