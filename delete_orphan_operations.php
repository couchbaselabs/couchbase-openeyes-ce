<?php
// Bootstrap the Yii application
$yiic = dirname(__FILE__) . '/protected/yiic.php';
require_once($yiic);

// We need to boot the console application
$config = dirname(__FILE__) . '/protected/config/console.php';
$app = Yii::createConsoleApplication($config);

// Find all orphaned operation booking events for patient 13
$patient_id = 13;

// Get all events for this patient that are operation bookings but missing the operation element
$events = Yii::app()->db->createCommand()
    ->select('e.id, e.event_type_id, et.name')
    ->from('event e')
    ->join('episode ep', 'e.episode_id = ep.id')
    ->join('event_type et', 'e.event_type_id = et.id')
    ->where('ep.patient_id = :patient_id AND et.class_name = :class_name')
    ->queryAll(array(':patient_id' => $patient_id, ':class_name' => 'OphTrOperationbooking'));

echo "Found " . count($events) . " operation booking events for patient $patient_id:\n";

foreach ($events as $event) {
    $event_id = $event['id'];
    
    // Check if operation element exists
    $operation = Yii::app()->db->createCommand()
        ->select('id')
        ->from('et_ophtroperationbooking_operation')
        ->where('event_id = :event_id')
        ->queryRow(array(':event_id' => $event_id));
    
    if (!$operation) {
        echo "Event ID $event_id is ORPHANED (no operation element)\n";
    } else {
        echo "Event ID $event_id has operation element ID {$operation['id']}\n";
    }
}
