<?php
/**
 * Script to create a test OphInBiometry event
 */
require_once 'index.php';

try {
    // Get the first patient or create one if needed
    $patient = Patient::model()->find();
    
    if (!$patient) {
        // Create a test patient if none exists
        $patient = new Patient();
        $patient->first_name = 'Test';
        $patient->last_name = 'Biometry Patient';
        $patient->dob = date('Y-m-d', strtotime('-30 years'));
        $patient->gender = 'M';
        
        if (!$patient->save()) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create patient', 'errors' => $patient->getErrors()]);
            exit(1);
        }
    }
    
    // Create or get an episode
    $episode = Episode::model()->find('patient_id=?', array($patient->id));
    if (!$episode) {
        $episode = new Episode();
        $episode->patient_id = $patient->id;
        $episode->eye_id = 3; // Both eyes
        $episode->episode_status_id = 1; // Open
        
        if (!$episode->save()) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create episode', 'errors' => $episode->getErrors()]);
            exit(1);
        }
    }
    
    // Get or create the OphInBiometry event type
    $eventType = EventType::model()->find('class_name=?', array('OphInBiometry'));
    if (!$eventType) {
        echo json_encode(['status' => 'error', 'message' => 'OphInBiometry EventType not found. Please run the init script first.']);
        exit(1);
    }
    
    // Create the event
    $event = new Event();
    $event->event_type_id = $eventType->id;
    $event->patient_id = $patient->id;
    $event->episode_id = $episode->id;
    $event->created_user_id = Yii::app()->user->id ?: 1;
    $event->created_date = date('Y-m-d H:i:s');
    $event->event_date = date('Y-m-d');
    
    if (!$event->save()) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create event', 'errors' => $event->getErrors()]);
        exit(1);
    }
    
    // Get the Measurement element type
    $measurementElementType = ElementType::model()->find(
        'event_type_id=? AND class_name=?',
        array($eventType->id, 'Element_OphInBiometry_Measurement')
    );
    
    if (!$measurementElementType) {
        echo json_encode(['status' => 'error', 'message' => 'Measurement ElementType not found']);
        exit(1);
    }
    
    // Create measurement element
    $measurement = new Element_OphInBiometry_Measurement();
    $measurement->event_id = $event->id;
    $measurement->eye_id = 3; // Both eyes
    $measurement->axial_length_left = 24.5;
    $measurement->axial_length_right = 24.3;
    $measurement->k1_left = 43.5;
    $measurement->k2_left = 44.0;
    $measurement->k1_right = 43.2;
    $measurement->k2_right = 43.8;
    $measurement->snr_left = 20;
    $measurement->snr_right = 20;
    $measurement->al_modified_left = 0;
    $measurement->al_modified_right = 0;
    $measurement->k_modified_left = 0;
    $measurement->k_modified_right = 0;
    
    if (!$measurement->save()) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create measurement', 'errors' => $measurement->getErrors()]);
        exit(1);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Test biometry event created successfully',
        'patient_id' => $patient->id,
        'episode_id' => $episode->id,
        'event_id' => $event->id,
        'view_url' => '/OphInBiometry/default/view/' . $event->id
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()]);
    exit(1);
}
?>
