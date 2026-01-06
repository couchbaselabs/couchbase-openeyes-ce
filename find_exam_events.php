<?php
// Initialize Yii application
require_once(__DIR__ . '/index.php');

try {
    // Find event type for OphCiExamination
    $eventType = EventType::model()->find('name = ?', array('Examination'));
    
    if ($eventType) {
        echo "Event Type found: " . $eventType->name . " (ID: " . $eventType->id . ")" . PHP_EOL;
        
        // Now find events of this type
        $events = Event::model()->findAll('event_type_id = ? LIMIT 5', array($eventType->id));
        
        if (empty($events)) {
            echo "No examination events found. Creating one..." . PHP_EOL;
            
            // Need to create a patient first
            $patient = Patient::model()->find('id > 0');
            if (!$patient) {
                echo "No patients found either." . PHP_EOL;
                exit;
            }
            
            echo "Using patient ID: " . $patient->id . PHP_EOL;
            
            // Create an examination event
            $event = new Event();
            $event->patient_id = $patient->id;
            $event->event_type_id = $eventType->id;
            $event->created_user_id = Yii::app()->user->id;
            $event->created_date = date('Y-m-d H:i:s');
            $event->last_modified_user_id = Yii::app()->user->id;
            $event->last_modified_date = date('Y-m-d H:i:s');
            
            if ($event->save()) {
                echo "Created examination event with ID: " . $event->id . PHP_EOL;
            } else {
                echo "Failed to create event: " . print_r($event->getErrors(), true) . PHP_EOL;
            }
        } else {
            echo "Found " . count($events) . " examination event(s):" . PHP_EOL;
            foreach ($events as $event) {
                echo "- Event ID: " . $event->id . ", Patient ID: " . $event->patient_id . PHP_EOL;
            }
        }
    } else {
        echo "Examination event type not found" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
?>
