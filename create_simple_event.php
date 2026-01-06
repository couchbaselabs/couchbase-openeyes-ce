<?php
require_once 'index.php';

// First, let's get the event_type_id for OphCoTherapyapplication
$sql = "SELECT id FROM event_type WHERE class_name = 'OphCoTherapyapplication'";
$result = Yii::app()->db->createCommand($sql)->queryRow();

if (!$result) {
    die("Event type OphCoTherapyapplication not found");
}

$event_type_id = $result['id'];
echo "Event Type ID: " . $event_type_id . "\n";

// Now let's get the first episode or create one
$sql2 = "SELECT id FROM episode LIMIT 1";
$episode_result = Yii::app()->db->createCommand($sql2)->queryRow();

if (!$episode_result) {
    // Create a patient first
    $db = Yii::app()->db;
    $sql3 = "INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, created_user_id, created_date) 
             VALUES ('TEST001', 'NHS001', '1980-01-15', 'M', 'Test', 'Patient', 1, NOW())";
    $db->createCommand($sql3)->execute();
    $patient_id = $db->lastInsertID;
    echo "Created Patient ID: " . $patient_id . "\n";
    
    // Create an episode
    $sql4 = "INSERT INTO episode (patient_id, start_date, eye_id, status, created_user_id, created_date) 
             VALUES (:patient_id, NOW(), 3, 'active', 1, NOW())";
    $cmd = $db->createCommand($sql4);
    $cmd->bindParam(':patient_id', $patient_id);
    $cmd->execute();
    $episode_id = $db->lastInsertID;
    echo "Created Episode ID: " . $episode_id . "\n";
} else {
    $episode_id = $episode_result['id'];
    echo "Using existing Episode ID: " . $episode_id . "\n";
}

// Now create the event
$db = Yii::app()->db;
$sql5 = "INSERT INTO event (episode_id, event_type_id, event_date, created_date, last_modified_date, institution_id) 
         VALUES (:episode_id, :event_type_id, NOW(), NOW(), NOW(), 1)";
$cmd = $db->createCommand($sql5);
$cmd->bindParam(':episode_id', $episode_id);
$cmd->bindParam(':event_type_id', $event_type_id);
$cmd->execute();
$event_id = $db->lastInsertID;
echo "Created Event ID: " . $event_id . "\n";

echo "\nTest URL: http://localhost:7777/OphCoTherapyapplication/default/view?id=" . $event_id . "\n";
?>
