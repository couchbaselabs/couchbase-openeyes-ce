<?php
// Test script to call getVfRaw action
require_once dirname(__FILE__) . '/index.php';

// First let's just try to navigate to the page and see what happens
// by manually calling the controller action

// Set up a test patient_id
$_GET['patient_id'] = 1;

// Simulate a request
$controller = new AnalyticsController('analytics');
try {
    ob_start();
    $controller->actionGetVfRaw();
    $output = ob_get_clean();
    echo "Success! Output:\n";
    echo $output;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString();
}
?>
