<?php
require_once 'index.php';

// Find the event_type_id for OphCoTherapyapplication
$event_type = EventType::model()->find('class_name = ?', array('OphCoTherapyapplication'));

if (!$event_type) {
    echo "Error: OphCoTherapyapplication event type not found\n";
    exit(1);
}

// Find any therapy application event
$event_type_id = $event_type->getPrimaryKey();
$sql = "SELECT e.id FROM event e WHERE event_type_id = :event_type_id LIMIT 1";
$event = Yii::app()->db->createCommand($sql)
    ->bindParam(':event_type_id', $event_type_id)
    ->queryRow();

if ($event) {
    echo "Found therapy application event ID: " . $event['id'] . "\n";
} else {
    echo "No therapy application events found. Creating one...\n";
    
    // Get first patient and episode
    $patient = Patient::model()->find();
    if (!$patient) {
        echo "Error: No patients found\n";
        exit(1);
    }
    
    $episode = Episode::model()->find('patient_id = ?', array($patient->id));
    if (!$episode) {
        // Create an episode
        $episode = new Episode();
        $episode->patient_id = $patient->id;
        $episode->start_date = date('Y-m-d');
        
        if (!$episode->save()) {
            echo "Error creating episode: " . print_r($episode->getErrors(), true) . "\n";
            exit(1);
        }
    }
    
    // Create a therapy application event
    $event = new Event();
    $event->event_type_id = $event_type->id;
    $event->episode_id = $episode->id;
    $event->created_date = date('Y-m-d H:i:s');
    $event->last_modified_date = date('Y-m-d H:i:s');
    
    if (!$event->save()) {
        echo "Error creating event: " . print_r($event->getErrors(), true) . "\n";
        exit(1);
    }
    
    echo "Created therapy application event ID: " . $event->id . "\n";
}
?>
