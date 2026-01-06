<?php
/**
 * Direct test of /analytics/medicalRetina endpoint
 * This script tests the controller action directly without going through HTTP/session
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize Yii
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

require_once($yii);
$app = Yii::createWebApplication($config);

echo "===== Testing /analytics/medicalRetina =====\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Try to create mock user session
try {
    // Attempt to set up Yii user
    echo "Step 1: Setting up mock user...\n";
    
    // Check if admin user exists and create if needed
    $admin_user = User::model()->findByAttributes(['username' => 'admin']);
    if (!$admin_user) {
        echo "  Creating admin user...\n";
        $admin_user = new User();
        $admin_user->username = 'admin';
        $admin_user->first_name = 'Test';
        $admin_user->last_name = 'Admin';
        $admin_user->active = 1;
        
        if ($admin_user->save()) {
            echo "  Admin user created with ID: " . $admin_user->id . "\n";
        } else {
            throw new Exception("Failed to create admin user: " . json_encode($admin_user->getErrors()));
        }
    } else {
        echo "  Admin user found with ID: " . $admin_user->id . "\n";
    }
    
    // Mock the user login
    Yii::app()->user->id = $admin_user->id;
    Yii::app()->user->setState('_user', $admin_user);
    echo "  User session set to: " . Yii::app()->user->id . "\n\n";
    
    // Step 2: Set up request parameters
    echo "Step 2: Setting up request parameters...\n";
    $_REQUEST['specialty'] = 'Medical Retina';
    echo "  specialty: Medical Retina\n";
    
    // Step 3: Create controller and test
    echo "\nStep 3: Creating AnalyticsController...\n";
    $controller = new AnalyticsController('analytics');
    $controller->init();
    echo "  Controller initialized successfully\n\n";
    
    // Step 4: Call actionMedicalRetina
    echo "Step 4: Calling actionMedicalRetina()...\n";
    
    ob_start();
    try {
        $controller->actionMedicalRetina();
        $output = ob_get_clean();
        
        // The action should render JSON and exit
        // If we get here, something went wrong with the exit
        echo "  Action completed and rendered output\n";
        echo "  Output length: " . strlen($output) . " bytes\n";
        
        // Try to parse output
        if (strpos($output, '{') === 0) {
            $json = json_decode($output, true);
            if ($json !== null) {
                echo "  Valid JSON response received\n";
                echo "  Top-level keys: " . implode(', ', array_keys($json)) . "\n";
                if (isset($json['data'])) {
                    echo "  Data sub-keys: " . implode(', ', array_keys($json['data'])) . "\n";
                }
                if (isset($json['dom'])) {
                    echo "  DOM sub-keys: " . implode(', ', array_keys($json['dom'])) . "\n";
                }
                echo "\n✓ SUCCESS: Page rendered successfully!\n";
            } else {
                echo "  Invalid JSON: " . json_last_error_msg() . "\n";
                echo "  First 300 chars: " . substr($output, 0, 300) . "\n";
            }
        } else {
            echo "  Output does not appear to be JSON\n";
            echo "  First 300 chars: " . substr($output, 0, 300) . "\n";
        }
    } catch (CHttpException $e) {
        ob_end_clean();
        echo "  HTTP Exception: " . $e->getMessage() . "\n";
        echo "  Status code: " . $e->statusCode . "\n";
        if ($e->statusCode >= 400) {
            echo "\n✗ ERROR: Page returned HTTP error\n";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "  Exception: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "\n✗ ERROR DETAILS:\n";
        echo $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n===== Test Complete =====\n";
?>
