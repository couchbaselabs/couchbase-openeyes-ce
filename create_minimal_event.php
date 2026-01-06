<?php
require_once 'index.php';

$db = Yii::app()->db;

try {
    // Start transaction
    $transaction = $db->beginTransaction();
    
    // Create a patient
    $sql1 = "INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, created_user_id, created_date) 
             VALUES ('TEST-001', 'NHS-001', '1980-01-15', 'M', 'Test', 'Patient', 1, NOW())";
    $db->createCommand($sql1)->execute();
    $patient_id = $db->getLastInsertID();
    echo "Created Patient ID: " . $patient_id . "\n";
    
    // Create an episode
    $sql2 = "INSERT INTO episode (patient_id, start_date, eye_id, status, created_user_id, created_date) 
             VALUES (:patient_id, CURDATE(), 3, 'active', 1, NOW())";
    $cmd = $db->createCommand($sql2);
    $cmd->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $cmd->execute();
    $episode_id = $db->getLastInsertID();
    echo "Created Episode ID: " . $episode_id . "\n";
    
    // Get event_type for OphCoTherapyapplication
    $sql3 = "SELECT id FROM event_type WHERE class_name = 'OphCoTherapyapplication' LIMIT 1";
    $result = $db->createCommand($sql3)->queryRow();
    
    if (!$result) {
        // Create the event_type
        $sql4 = "INSERT INTO event_type (class_name, name) VALUES ('OphCoTherapyapplication', 'Therapy Application')";
        $db->createCommand($sql4)->execute();
        $event_type_id = $db->getLastInsertID();
        echo "Created Event Type ID: " . $event_type_id . "\n";
    } else {
        $event_type_id = $result['id'];
        echo "Using existing Event Type ID: " . $event_type_id . "\n";
    }
    
    // Create an event
    $sql5 = "INSERT INTO event (episode_id, event_type_id, event_date, created_date, last_modified_date, institution_id) 
             VALUES (:episode_id, :event_type_id, CURDATE(), NOW(), NOW(), 1)";
    $cmd = $db->createCommand($sql5);
    $cmd->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
    $cmd->bindParam(':event_type_id', $event_type_id, PDO::PARAM_INT);
    $cmd->execute();
    $event_id = $db->getLastInsertID();
    echo "Created Event ID: " . $event_id . "\n";
    
    // Commit transaction
    $transaction->commit();
    
    echo "\nSuccess! Test URL: http://localhost:7777/OphCoTherapyapplication/default/view?id=" . $event_id . "\n";
    
} catch (Exception $e) {
    if (isset($transaction)) {
        $transaction->rollback();
    }
    die("Error: " . $e->getMessage() . "\n");
}
?>
