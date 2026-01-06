<?php
// Direct hotlist creation for testing
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set the YII_DEBUG and YII_TRACE_LEVEL 
defined('YII_DEBUG') or define('YII_DEBUG',true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);

// This will include index.php which will invoke Yii
require dirname(__FILE__).'/index.php';

try {
    // Force flush of buffers
    ob_end_clean();
    
    // Get admin user - user ID 1
    $admin_user = User::model()->findByPk(1);
    if (!$admin_user) {
        throw new Exception("Admin user (ID 1) not found!");
    }

    // Get first patient
    $patient = Patient::model()->find();
    if (!$patient) {
        throw new Exception("No patients found in the system!");
    }

    // Create hotlist item
    $hotlist_item = new UserHotlistItem();
    $hotlist_item->patient_id = $patient->id;
    $hotlist_item->is_open = 1;
    $hotlist_item->user_comment = "Test comment from verification script";
    $hotlist_item->created_user_id = $admin_user->id;

    if ($hotlist_item->save()) {
        $response = [
            'success' => true,
            'hotlist_item_id' => $hotlist_item->id,
            'patient_id' => $patient->id,
            'admin_user_id' => $admin_user->id,
            'message' => "Hotlist item created successfully!"
        ];
    } else {
        $response = [
            'success' => false,
            'errors' => $hotlist_item->errors,
            'message' => "Error creating hotlist item"
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}
?>
