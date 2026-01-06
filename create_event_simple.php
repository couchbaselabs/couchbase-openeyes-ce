<?php
require_once 'index.php';

try {
    // Step 1: Create a patient using the model
    $patient = new Patient();
    $patient->hos_num = 'TEST-' . time();
    $patient->nhs_num = 'NHS-' . time();
    $patient->dob = '1980-01-15';
    $patient->gender = 'M';
    $patient->first_name = 'Test';
    $patient->last_name = 'Patient';
    
    if (!$patient->save()) {
        die("Error creating patient: " . json_encode($patient->getErrors()) . "\n");
    }
    echo "Created Patient ID: " . $patient->id . "\n";
    
    // Step 2: Create an episode
    $episode = new Episode();
    $episode->patient_id = $patient->id;
    $episode->eye_id = Eye::BOTH;
    $episode->status = 'active';
    
    if (!$episode->save()) {
        die("Error creating episode: " . json_encode($episode->getErrors()) . "\n");
    }
    echo "Created Episode ID: " . $episode->id . "\n";
    
    // Step 3: Get or create the event type
    $eventType = EventType::model()->find('class_name = ?', array('OphCoTherapyapplication'));
    if (!$eventType) {
        $eventType = new EventType();
        $eventType->class_name = 'OphCoTherapyapplication';
        $eventType->name = 'Therapy Application';
        if (!$eventType->save()) {
            die("Error creating event type: " . json_encode($eventType->getErrors()) . "\n");
        }
        echo "Created Event Type ID: " . $eventType->id . "\n";
    } else {
        echo "Using existing Event Type ID: " . $eventType->id . "\n";
    }
    
    // Step 4: Create an event
    $event = new Event();
    $event->event_type_id = $eventType->getPrimaryKey();
    $event->episode_id = $episode->id;
    $event->event_date = date('Y-m-d');
    $event->created_date = date('Y-m-d H:i:s');
    $event->last_modified_date = date('Y-m-d H:i:s');
    $event->institution_id = 1;
    
    if (!$event->save()) {
        die("Error creating event: " . json_encode($event->getErrors()) . "\n");
    }
    echo "Created Event ID: " . $event->id . "\n";
    
    echo "\nSuccess!\n";
    echo "Test URL: http://localhost:7777/OphCoTherapyapplication/default/view?id=" . $event->id . "\n";
    
} catch (Exception $e) {
    die("Exception: " . $e->getMessage() . "\n");
}
?>
