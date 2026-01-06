<?php
$db = Yii::app()->db;

// Query for therapy application events
$sql = "SELECT e.id, e.event_type_id, e.episode_id, p.id as patient_id 
        FROM event e
        JOIN episode ep ON e.episode_id = ep.id
        JOIN patient p ON ep.patient_id = p.id
        WHERE e.event_type_id = (SELECT id FROM event_type WHERE class_name = 'OphCoTherapyapplication')
        LIMIT 1";

$result = $db->createCommand($sql)->queryRow();

if ($result) {
    echo "Event ID: " . $result['id'] . "\n";
    echo "Patient ID: " . $result['patient_id'] . "\n";
    echo "Episode ID: " . $result['episode_id'] . "\n";
} else {
    echo "No therapy application events found\n";
}
?>
