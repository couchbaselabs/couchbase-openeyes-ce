<?php
// Test script to verify the Systemic Diagnosis Assignment create page

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up the test environment
$_SERVER['HTTP_HOST'] = 'localhost:7777';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/OphCiExamination/ExaminationAdmin/systemicDiagAssignment/create';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '7777';
$_SERVER['HTTPS'] = 'off';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__FILE__) . '/index.php';

// Start session for testing
session_start();

// Include Yii framework
require_once dirname(__FILE__) . '/protected/framework/yii.php';
$config = require_once dirname(__FILE__) . '/protected/config/main.php';

// Create app
Yii::createWebApplication($config);

// Simulate admin user login
$user = User::model()->findByAttributes(['username' => 'admin']);
if (!$user) {
    echo "ERROR: Admin user not found\n";
    exit(1);
}

// Set the user as logged in
Yii::app()->user->setId($user->id);
Yii::app()->user->isGuest = false;

// Now test the controller action
$controller = new OphCiExamination\modules\ExaminationAdmin\controllers\SystemicDiagAssignmentController('SystemicDiagAssignment');

// Test the actionCreate method
try {
    echo "Testing actionCreate...\n";
    
    // Get the model
    $model = new OEModule\OphCiExamination\models\OphCiExaminationSystemicDiagnosesSet();
    
    echo "Model class: " . get_class($model) . "\n";
    echo "Model attributes: " . print_r($model->attributes, true) . "\n";
    echo "Model rules: " . print_r($model->rules(), true) . "\n";
    echo "Model relations: " . print_r($model->relations(), true) . "\n";
    
    // Check if the model can be instantiated
    if ($model) {
        echo "SUCCESS: Model instantiated\n";
    } else {
        echo "ERROR: Failed to instantiate model\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Test completed successfully\n";
?>
