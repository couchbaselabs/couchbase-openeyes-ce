<?php
// Find a correspondence event to test with

$config = require('/var/www/openeyes/protected/config/main.php');
Yii::createWebApplication($config);

// Get the first correspondence event
$event = Event::model()->with(
    array(
        'eventType' => array(
            'condition' => 'class_name = "OphCoCorrespondence"'
        )
    )
)->find();

if ($event) {
    echo 'Event ID: ' . $event->id . PHP_EOL;
    echo 'Patient ID: ' . $event->episode->patient_id . PHP_EOL;
    echo 'Event Type: ' . $event->eventType->name . PHP_EOL;
} else {
    echo 'No correspondence event found' . PHP_EOL;
    echo 'Creating test event...' . PHP_EOL;
    
    // Try to get first patient
    $patient = Patient::model()->find();
    if ($patient) {
        echo 'Found patient: ' . $patient->id . PHP_EOL;
    }
}
?>
