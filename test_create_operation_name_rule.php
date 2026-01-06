<?php
// Test script to create an operation name rule directly
require_once 'index.php';

try {
    // Create a new rule
    $rule = new OphTrOperationbooking_Operation_Name_Rule();
    $rule->theatre_id = 1;  // Use theatre_id = 1 (matching 'sdf' theatre)
    $rule->name = 'Test Lacrimal Duct Surgery';
    
    // Try to save
    if ($rule->save()) {
        echo "SUCCESS: Rule created with ID: " . $rule->id . "\n";
        echo "Theatre ID: " . $rule->theatre_id . "\n";
        echo "Name: " . $rule->name . "\n";
    } else {
        echo "FAILED: Could not save rule\n";
        echo "Errors: " . json_encode($rule->getErrors()) . "\n";
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
