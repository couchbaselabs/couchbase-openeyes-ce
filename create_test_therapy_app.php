<?php
// Simple script to create a test therapy application event

// Require the main Yii application
require_once dirname(__FILE__) . '/index.php';

// Get the required models
$eventType = EventType::model()->findByAttributes(['class_name' => 'OphCoTherapyapplication']);
if (!$eventType) {
    die("Therapy application event type not found\n");
}

$patient = Patient::model()->findByPk(1);
if (!$patient) {
    die("Patient 1 not found\n");
}

// Get the episode for the patient (or create one)
$episode = Episode::model()->findByAttributes(['patient_id' => $patient->id]);
if (!$episode) {
    $episode = new Episode();
    $episode->patient_id = $patient->id;
    $episode->eye_id = Eye::RIGHT;
    $episode->disorder_id = null;
    $episode->status = 'active';
    if (!$episode->save()) {
        die("Failed to create episode: " . json_encode($episode->getErrors()) . "\n");
    }
}

// Create the event
$event = new Event();
$event->event_type_id = $eventType->id;
$event->episode_id = $episode->id;
$event->created_date = date('Y-m-d H:i:s');

if (!$event->save()) {
    die("Failed to create event: " . json_encode($event->getErrors()) . "\n");
}

echo "Success! Created therapy application event with ID: " . $event->id . "\n";
echo "Test URL: http://localhost:7777/OphCoTherapyapplication/default/view?id=" . $event->id . "\n";
?>
