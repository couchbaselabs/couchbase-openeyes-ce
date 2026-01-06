<?php
// Simple script to create a test prescription event

// Require the main Yii application
require_once dirname(__FILE__) . '/index.php';

// Get the required models
$eventType = EventType::model()->findByAttributes(['class_name' => 'OphDrPrescription']);
if (!$eventType) {
    die("Prescription event type not found\n");
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
$event->user_id = Yii::app()->session['user']->id ?? 1;

if (!$event->save()) {
    die("Failed to create event: " . json_encode($event->getErrors()) . "\n");
}

// Create the prescription element
$element = new Element_OphDrPrescription_Details();
$element->event_id = $event->id;
$element->draft = 1; // Start as draft
$element->examination_event_id = null;
$element->indication = 'Test prescription';
$element->issued_date = date('Y-m-d');

if (!$element->save()) {
    die("Failed to create prescription element: " . json_encode($element->getErrors()) . "\n");
}

echo "Success! Created prescription event with ID: " . $event->id . "\n";
echo "Prescription element ID: " . $element->id . "\n";
echo "Test URL: http://localhost:7777/OphDrPrescription/default/update?id=" . $event->id . "\n";
?>
