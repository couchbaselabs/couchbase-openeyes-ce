<?php
require_once 'index.php';

try {
    echo "Checking for existing OphInDnasample events...\n\n";
    
    // Get the event type
    $event_type = EventType::model()->find('class_name = ?', array('OphInDnasample'));
    if (!$event_type) {
        throw new Exception("OphInDnasample event type not found");
    }
    
    echo "Event Type ID: " . $event_type->id . "\n";
    echo "Event Type Name: " . $event_type->name . "\n\n";
    
    // Try to find events using SQL
    $db = Yii::app()->db;
    $sql = "SELECT id, episode_id, event_date FROM event WHERE event_type_id = ? LIMIT 5";
    $type_id_str = (string) $event_type->id;
    
    try {
        $events = $db->createCommand($sql)->queryAll(array($type_id_str));
        echo "Found " . count($events) . " events via SQL:\n";
        foreach ($events as $evt) {
            echo "  ID: " . $evt['id'] . ", Episode: " . $evt['episode_id'] . ", Date: " . $evt['event_date'] . "\n";
        }
    } catch (Exception $e) {
        echo "SQL query failed: " . $e->getMessage() . "\n";
    }
    
    // Try to find events using model
    echo "\nTrying to find events using model...\n";
    $events_model = Event::model()->findAll('event_type_id = ?', array($event_type->id));
    echo "Found " . count($events_model) . " events via model\n";
    if (count($events_model) > 0) {
        foreach (array_slice($events_model, 0, 5) as $evt) {
            echo "  ID: " . $evt->id . ", Episode: " . $evt->episode_id . "\n";
        }
        $test_event_id = $events_model[0]->id;
    }
    
    // If we found events, provide the URL
    if (isset($test_event_id)) {
        echo "\n===========================================\n";
        echo "Test URL: http://localhost:7777/OphInDnasample/default/view/" . $test_event_id . "\n";
        echo "===========================================\n";
    } else {
        echo "\nNo events found. The OphInDnasample event type exists but has no associated events.\n";
        echo "This module may need migration or test data seeding.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
