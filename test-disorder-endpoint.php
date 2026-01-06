<?php
// Test the /disorder/getCommonlyUsedDiagnoses endpoint
// First, let's simulate the environment by bootstrap the application

chdir(__DIR__);
require_once('index.php');

// Now simulate a logged-in user
Yii::app()->user->setId(1);
Yii::app()->session['selected_firm_id'] = 1;

// Create a controller instance
$controller = new DisorderController('DisorderController');

// Try to call the action
try {
    echo "Testing actionGetCommonlyUsedDiagnoses with type='systemic'\n";
    ob_start();
    $controller->actionGetCommonlyUsedDiagnoses('systemic');
    $output = ob_get_clean();
    echo "Response: " . $output . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

// Try to call with ophthalmic type
try {
    echo "\nTesting actionGetCommonlyUsedDiagnoses with type='ophthalmic'\n";
    ob_start();
    $controller->actionGetCommonlyUsedDiagnoses('ophthalmic');
    $output = ob_get_clean();
    echo "Response: " . $output . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
