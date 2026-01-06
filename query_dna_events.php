<?php
defined('YII_DEBUG') or define('YII_DEBUG', true);
require_once('/var/www/openeyes/index.php');

// Couchbase connection is now the default
// We need to use the Couchbase API to check for DNA Extraction events

try {
    // Check if OphInDnaextraction event type exists
    $eventType = EventType::model()->findByAttributes(array('class_name' => 'OphInDnaextraction'));
    
    if ($eventType) {
        echo "OphInDnaextraction EventType found:\n";
        echo "  ID: " . $eventType->id . "\n";
        echo "  Class: " . $eventType->class_name . "\n";
        echo "  Name: " . $eventType->name . "\n";
        
        // Try to find events with this type
        $events = Event::model()->findAllByAttributes(array('event_type_id' => $eventType->id));
        echo "\nFound " . count($events) . " events of this type\n";
        
        foreach ($events as $event) {
            echo "  Event ID: " . $event->id . ", Patient ID: " . $event->patient_id . ", Date: " . $event->event_date . "\n";
        }
    } else {
        echo "OphInDnaextraction EventType not found\n";
        
        // List all event types
        $allTypes = EventType::model()->findAll();
        echo "\nAvailable event types:\n";
        foreach ($allTypes as $type) {
            echo "  " . $type->class_name . " (ID: " . $type->id . ")\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
