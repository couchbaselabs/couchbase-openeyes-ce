<?php
// Test script for actionGetCustomPlot
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up the path to Yii
$yii = dirname(__FILE__).'/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__).'/protected/config/main.php';

// Initialize Yii
require_once($yii);

// Create web application
$app = Yii::createWebApplication($config);

// Log in as admin
Yii::app()->user->login(CBaseUserIdentity::getInstance(1)); // Assuming admin user ID is 1

// Test the endpoint
ob_start();
try {
    // Create a controller instance
    $controller = new AnalyticsController('analytics');
    $controller->init();
    
    // Set up mock request parameters
    $_GET['specialty'] = 'Glaucoma';
    $_GET['from'] = strtotime('-1 year');
    $_GET['to'] = time();
    $_GET['va_unit'] = 'logmar';
    $_GET['time_interval_num'] = '1';
    $_GET['time_interval_unit'] = 'month';
    
    // Call the action
    $controller->actionGetCustomPlot();
    
    $output = ob_get_clean();
    
    // Decode JSON to check if valid
    $decoded = json_decode($output, true);
    
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON Error: " . json_last_error_msg() . "\n";
        echo "Output: " . substr($output, 0, 500) . "\n";
    } else {
        echo "SUCCESS: Valid JSON response\n";
        echo "Keys: " . implode(', ', array_keys($decoded)) . "\n";
    }
} catch (Exception $e) {
    ob_end_clean();
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
}
?>
