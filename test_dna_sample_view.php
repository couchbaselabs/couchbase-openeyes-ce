<?php
require_once 'index.php';

try {
    // First, get the OphInDnasample event type ID
    $sql = "SELECT id FROM event_type WHERE class_name = 'OphInDnasample'";
    $result = Yii::app()->db->createCommand($sql)->queryRow();
    
    if (!$result) {
        die("OphInDnasample event type not found. Module may not be installed correctly.\n");
    }
    
    $event_type_id = $result['id'];
    echo "OphInDnasample event type ID: " . $event_type_id . "\n";
    
    // Check if any OphInDnasample events exist
    $sql2 = "SELECT id, episode_id FROM event WHERE event_type_id = ? LIMIT 1";
    $existing_event = Yii::app()->db->createCommand($sql2)->queryRow(array($event_type_id));
    
    if ($existing_event) {
        echo "Found existing OphInDnasample event ID: " . $existing_event['id'] . "\n";
        echo "Episode ID: " . $existing_event['episode_id'] . "\n";
        $event_id = $existing_event['id'];
    } else {
        echo "No existing OphInDnasample events found. Creating test event...\n";
        
        // Get or create a patient
        $patient = Patient::model()->find();
        if (!$patient) {
            // Create a new test patient
            $patient = new Patient();
            $patient->hos_num = 'TEST_DNA_' . time();
            $patient->nhs_num = 'NHS_' . time();
            $patient->dob = '1980-01-15';
            $patient->gender = 'M';
            $patient->first_name = 'TestDNA';
            $patient->last_name = 'Patient';
            if ($patient->save()) {
                echo "Created patient ID: " . $patient->id . "\n";
            } else {
                die("Failed to create patient\n");
            }
        } else {
            echo "Using existing patient ID: " . $patient->id . "\n";
        }
        
        // Get or create an episode
        $episode = Episode::model()->find('patient_id = ?', array($patient->id));
        if (!$episode) {
            $episode = new Episode();
            $episode->patient_id = $patient->id;
            $episode->start_date = date('Y-m-d H:i:s');
            $episode->eye_id = 3; // Both eyes
            if ($episode->save()) {
                echo "Created episode ID: " . $episode->id . "\n";
            } else {
                die("Failed to create episode\n");
            }
        } else {
            echo "Using existing episode ID: " . $episode->id . "\n";
        }
        
        // Create an event
        $event = new Event();
        $event->episode_id = $episode->id;
        $event->event_type_id = $event_type_id;
        $event->event_date = date('Y-m-d H:i:s');
        $event->institution_id = 1;
        if ($event->save()) {
            echo "Created event ID: " . $event->id . "\n";
            $event_id = $event->id;
        } else {
            die("Failed to create event\n");
        }
    }
    
    echo "\nTest URL: http://localhost:7777/OphInDnasample/default/view/" . $event_id . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
