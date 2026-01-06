<?php
// Bootstrap the application
$basePath = dirname(__FILE__);
// Handle both local and Docker paths
if (!file_exists($basePath . '/protected/tests/bootstrap.php')) {
    $basePath = '/var/www/openeyes';
}

require_once $basePath . '/protected/tests/bootstrap.php';

try {
    // Find or create a patient
    $patient = Patient::model()->find();
    
    if (!$patient) {
        $patient = new Patient();
        $patient->first_name = 'Test';
        $patient->last_name = 'Biometry';
        $patient->dob = '1980-01-01';
        $patient->gender = 'M';
        
        if (!$patient->save()) {
            echo "Failed to create patient\n";
            print_r($patient->getErrors());
            exit(1);
        }
        echo "Created patient with ID: " . $patient->id . "\n";
    } else {
        echo "Using existing patient with ID: " . $patient->id . "\n";
    }
    
    // Find or create an episode
    $episode = Episode::model()->find('patient_id = ?', array($patient->id));
    
    if (!$episode) {
        $episode = new Episode();
        $episode->patient_id = $patient->id;
        $episode->start_date = date('Y-m-d');
        $episode->eye_id = 3; // Both eyes
        
        if (!$episode->save()) {
            echo "Failed to create episode\n";
            print_r($episode->getErrors());
            exit(1);
        }
        echo "Created episode with ID: " . $episode->id . "\n";
    } else {
        echo "Using existing episode with ID: " . $episode->id . "\n";
    }
    
    // Find OphInBiometry event type
    $event_type = EventType::model()->find('class_name = ?', array('OphInBiometry'));
    
    if (!$event_type) {
        echo "Failed to find OphInBiometry event type\n";
        exit(1);
    }
    echo "Found OphInBiometry event type with ID: " . $event_type->id . "\n";
    
    // Create an event
    $event = new Event();
    $event->patient_id = $patient->id;
    $event->episode_id = $episode->id;
    $event->event_type_id = $event_type->id;
    $event->created_user_id = 1;
    $event->created_date = date('Y-m-d H:i:s');
    
    if (!$event->save()) {
        echo "Failed to create event\n";
        print_r($event->getErrors());
        exit(1);
    }
    echo "Created event with ID: " . $event->id . "\n";
    
    // Create a measurement element
    $measurement = new Element_OphInBiometry_Measurement();
    $measurement->event_id = $event->id;
    $measurement->eye_id = 3; // Both eyes
    $measurement->axial_length_left = 23.5;
    $measurement->axial_length_right = 23.6;
    $measurement->k1_left = 43.0;
    $measurement->k2_left = 44.0;
    $measurement->k1_right = 43.1;
    $measurement->k2_right = 44.1;
    $measurement->snr_left = 20;
    $measurement->snr_right = 20;
    
    if (!$measurement->save()) {
        echo "Failed to create measurement\n";
        print_r($measurement->getErrors());
        exit(1);
    }
    echo "Created measurement element\n";
    
    echo "\n=== SUCCESS ===\n";
    echo "OphInBiometry event created with ID: " . $event->id . "\n";
    echo "Patient ID: " . $patient->id . "\n";
    echo "Episode ID: " . $episode->id . "\n";
    echo "\nYou can now test the page at:\n";
    echo "http://localhost:7777/OphInBiometry/default/update?id=" . $event->id . "\n";
    echo "or\n";
    echo "http://localhost:7777/OphInBiometry/default/view?id=" . $event->id . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>
