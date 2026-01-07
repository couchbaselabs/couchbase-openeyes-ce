<?php
// Test script to check the getPatientAlert action
chdir('/Users/asahu/Desktop/untitled\ folder/openeyes');
require_once('index.php');

// Simulate the request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['patient_id'] = 1;

// Test if we can load the controller
try {
    $controller = new OEModule\PatientTicketing\controllers\DefaultController('PatientTicketing');
    echo "Controller loaded successfully\n";
    
    // Check if the action method exists
    if (method_exists($controller, 'actionGetPatientAlert')) {
        echo "Action method exists\n";
    } else {
        echo "Action method MISSING\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
