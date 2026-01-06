<?php
require_once 'index.php';

try {
    // Get the OphInDnasample event type
    echo "Getting OphInDnasample event type...\n";
    $event_type = EventType::model()->find('class_name = ?', array('OphInDnasample'));
    
    if (!$event_type) {
        throw new Exception("OphInDnasample event type not found!");
    }
    
    echo "Event Type ID: " . $event_type->id . "\n";
    
    // Try to find an existing OphInDnasample event
    echo "\nSearching for existing OphInDnasample events...\n";
    $event = Event::model()->find('event_type_id = ?', array($event_type->id));
    
    if ($event) {
        echo "Found existing event ID: " . $event->id . "\n";
        echo "Episode ID: " . $event->episode_id . "\n";
        $event_id = $event->id;
    } else {
        echo "No existing events found. Creating test event...\n";
        
        // Get or create a patient
        $patient = Patient::model()->find();
        if (!$patient) {
            throw new Exception("No patients found in system. Cannot create event without a patient.");
        }
        
        echo "Using patient ID: " . $patient->id . "\n";
        
        // Get or create an episode for this patient
        $episode = Episode::model()->find('patient_id = ?', array($patient->id));
        if (!$episode) {
            echo "Creating new episode for patient...\n";
            $episode = new Episode();
            $episode->patient_id = $patient->id;
            $episode->start_date = date('Y-m-d H:i:s');
            $episode->eye_id = 3; // Both
            if ($episode->save()) {
                echo "Episode created with ID: " . $episode->id . "\n";
            } else {
                throw new Exception("Failed to create episode: " . implode(", ", $episode->getErrors()));
            }
        } else {
            echo "Using existing episode ID: " . $episode->id . "\n";
        }
        
        // Create the event
        echo "Creating new event...\n";
        
        // Note: The Event model expects numeric IDs for Couchbase compatibility
        // event_type_id from Couchbase is a long string, so we need to use a numeric reference
        // Try to find a numeric reference or use direct SQL
        
        $db = Yii::app()->db;
        $event_date = date('Y-m-d H:i:s');
        
        // Extract IDs as strings to handle Couchbase overloaded properties
        $episode_id_str = (string) $episode->id;
        $event_type_id_str = (string) $event_type->id;
        
        $sql = "INSERT INTO event (episode_id, event_type_id, event_date, institution_id, created_date, last_modified_date) 
                VALUES (:episode_id, :event_type_id, :event_date, 1, NOW(), NOW())";
        $cmd = $db->createCommand($sql);
        $cmd->bindParam(':episode_id', $episode_id_str);
        $cmd->bindParam(':event_type_id', $event_type_id_str);
        $cmd->bindParam(':event_date', $event_date);
        
        try {
            $result = $cmd->execute();
            echo "Execute result: " . ($result ? "true" : "false") . "\n";
            
            // Try to get the last insert ID
            try {
                $event_id = $db->getLastInsertID();
                echo "Event created with ID: " . $event_id . "\n";
            } catch (Exception $e2) {
                echo "Warning: Could not get last insert ID: " . $e2->getMessage() . "\n";
                // Query for the most recent event
                $recent = $db->createCommand("SELECT id FROM event WHERE event_type_id = :etype_id ORDER BY id DESC LIMIT 1")
                    ->bindParam(':etype_id', $event_type_id_str)
                    ->queryRow();
                if ($recent) {
                    $event_id = $recent['id'];
                    echo "Found recent event ID: " . $event_id . "\n";
                } else {
                    throw new Exception("Could not determine created event ID");
                }
            }
        } catch (Exception $e) {
            throw new Exception("Failed to create event via SQL: " . $e->getMessage());
        }
    }
    
    echo "\n===========================================\n";
    echo "Test URL: http://localhost:7777/OphInDnasample/default/view/" . $event_id . "\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
