<?php
// Initialize Yii
$yii=dirname(__FILE__).'/protected/framework/yii.php';
$config=dirname(__FILE__).'/protected/config/main.php';
defined('YII_DEBUG') or define('YII_DEBUG',true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);
require_once($yii);
Yii::createWebApplication($config);

// Now we can use the database
$db = Yii::app()->db;

// Get the event type ID for OphOuCatprom5
$event_type_sql = "SELECT id FROM event_type WHERE class_name = 'OphOuCatprom5'";
$event_type_result = $db->createCommand($event_type_sql)->queryRow();

if (!$event_type_result) {
    echo "Event type OphOuCatprom5 not found\n";
    exit(1);
}

$event_type_id = $event_type_result['id'];
echo "Event Type ID: $event_type_id\n";

// Get any patient
$patient_sql = "SELECT id FROM patient LIMIT 1";
$patient_result = $db->createCommand($patient_sql)->queryRow();

if (!$patient_result) {
    echo "No patients found\n";
    exit(1);
}

$patient_id = $patient_result['id'];
echo "Patient ID: $patient_id\n";

// Create an event
$user_sql = "SELECT id FROM user LIMIT 1";
$user_result = $db->createCommand($user_sql)->queryRow();
$user_id = $user_result['id'];
echo "User ID: $user_id\n";

// Insert event
$insert_sql = "INSERT INTO event (patient_id, event_type_id, event_date, created_user_id, created_date, last_modified_user_id, last_modified_date) 
              VALUES (:patient_id, :event_type_id, NOW(), :user_id, NOW(), :user_id, NOW())";
$command = $db->createCommand($insert_sql);
$command->bindValue(':patient_id', $patient_id, PDO::PARAM_INT);
$command->bindValue(':event_type_id', $event_type_id, PDO::PARAM_INT);
$command->bindValue(':user_id', $user_id, PDO::PARAM_INT);

try {
    $command->execute();
    $event_id = $db->getLastInsertID();
    echo "Created Event ID: $event_id\n";
} catch (Exception $e) {
    echo "Error creating event: " . $e->getMessage() . "\n";
    exit(1);
}

// Now create a CatProm5EventResult element
$element_type_sql = "SELECT id FROM element_type WHERE class_name = 'CatProm5EventResult'";
$element_type_result = $db->createCommand($element_type_sql)->queryRow();

if (!$element_type_result) {
    echo "Element type CatProm5EventResult not found\n";
    exit(1);
}

$element_type_id = $element_type_result['id'];
echo "Element Type ID: $element_type_id\n";

// Insert CatProm5EventResult
$insert_element_sql = "INSERT INTO cat_prom5_event_result (event_id, total_raw_score, total_rasch_measure) 
                      VALUES (:event_id, :raw_score, :rasch_measure)";
$command = $db->createCommand($insert_element_sql);
$command->bindValue(':event_id', $event_id, PDO::PARAM_INT);
$command->bindValue(':raw_score', 10, PDO::PARAM_INT);
$command->bindValue(':rasch_measure', '-0.32', PDO::PARAM_STR);

try {
    $command->execute();
    $element_id = $db->getLastInsertID();
    echo "Created CatProm5EventResult ID: $element_id\n";
    echo "Event ID for testing: $event_id\n";
} catch (Exception $e) {
    echo "Error creating element: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Successfully created test CatProm5 event\n";
