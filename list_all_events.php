<?php
require_once 'index.php';

// Query all events
$sql = "SELECT e.id, e.episode_id, e.event_type_id, et.class_name 
        FROM event e
        LEFT JOIN event_type et ON e.event_type_id = et.id
        LIMIT 20";

$events = Yii::app()->db->createCommand($sql)->queryAll();

echo "Found " . count($events) . " events:\n";
foreach ($events as $event) {
    echo "Event ID: " . $event['id'] . 
         ", Episode ID: " . $event['episode_id'] . 
         ", Event Type: " . $event['class_name'] . "\n";
}

// Check specifically for OphCoTherapyapplication events
$sql2 = "SELECT e.id FROM event e 
         WHERE e.event_type_id = (SELECT id FROM event_type WHERE class_name = 'OphCoTherapyapplication')
         LIMIT 1";
         
$result = Yii::app()->db->createCommand($sql2)->queryRow();
if ($result) {
    echo "\nFound OphCoTherapyapplication event with ID: " . $result['id'] . "\n";
} else {
    echo "\nNo OphCoTherapyapplication events found\n";
}
?>
