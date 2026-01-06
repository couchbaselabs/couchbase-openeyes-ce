<?php
/**
 * Script to create a test trial directly in the database
 */

// Include Yii
require_once dirname(__FILE__) . '/index.php';

try {
    // First, let's try a direct SQL insert to see if there's a database issue
    $db = Yii::app()->db;
    
    $trial_name = 'Test Trial Direct SQL ' . date('Y-m-d H:i:s');
    $ethics_number = 'ETH-' . date('YmdHis');
    
    // Get the trial type ID
    $trial_type_id = TrialType::model()->find('code = ?', array(TrialType::NON_INTERVENTION_CODE))->id;
    
    $sql = "INSERT INTO trial (name, description, owner_user_id, trial_type_id, is_open, started_date, created_user_id, created_date, ethics_number) 
            VALUES (:name, :description, :owner_user_id, :trial_type_id, :is_open, :started_date, :created_user_id, :created_date, :ethics_number)";
    
    $cmd = $db->createCommand($sql);
    $cmd->bindValues([
        ':name' => $trial_name,
        ':description' => 'This is a test trial created for verification',
        ':owner_user_id' => 1,
        ':trial_type_id' => $trial_type_id,
        ':is_open' => 1,
        ':started_date' => '2026-01-06',
        ':created_user_id' => 1,
        ':created_date' => date('Y-m-d H:i:s'),
        ':ethics_number' => $ethics_number
    ]);
    
    try {
        $result = $cmd->execute();
        if ($result) {
            // Get the last inserted ID
            $trial_id = $db->getLastInsertID();
            echo json_encode([
                'status' => 'success',
                'message' => 'Trial created successfully via direct SQL',
                'trial_id' => $trial_id,
                'trial_name' => $trial_name
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Execute returned false',
                'cmd_text' => $cmd->getText()
            ]);
        }
    } catch (CDbException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'error_code' => $e->getCode(),
            'cmd_text' => $cmd->getText()
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
