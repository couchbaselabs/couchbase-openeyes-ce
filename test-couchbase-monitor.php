<?php
// Test script for CouchbaseMonitor metrics
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set up environment
$_SERVER['HTTP_HOST'] = 'localhost:7777';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '7777';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/couchbaseMonitor/metrics';

// Initialize Yii application
require_once(__DIR__ . '/index.php');

// Create a mock user with admin role
Yii::app()->user = new MockUser();

// Create controller instance
$controller = new CouchbaseMonitorController('couchbaseMonitor');

try {
    // Call the actionMetrics method directly
    echo "Testing CouchbaseMonitor::actionMetrics()...\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    // Suppress header output for testing
    ob_start();
    $controller->actionMetrics();
    $output = ob_get_clean();
    
    echo "Response:\n";
    echo $output;
    echo "\n" . "=" . str_repeat("=", 50) . "\n";
    echo "Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Mock user class for testing
 */
class MockUser extends CWebUser {
    public function init() {
        // Override parent init to avoid session issues
    }
    
    public function getIsGuest() {
        return false;
    }
    
    public function getId() {
        return 1;
    }
    
    public function getState($key, $defaultValue = null) {
        return $defaultValue;
    }
    
    public function setState($key, $value) {
        // Mock implementation
    }
    
    public function checkAccess($operation, $params = array()) {
        // Allow all operations for testing
        return true;
    }
}
?>
