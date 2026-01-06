<?php
// Initialize Yii application
require_once(__DIR__ . '/index.php');

try {
    // Check if trials exist
    $existingTrial = Trial::model()->find();
    if ($existingTrial) {
        echo "Trial already exists with ID: " . $existingTrial->id . PHP_EOL;
        $trial = $existingTrial;
    } else {
        // Create a new trial
        $trial = new Trial();
        $trial->name = 'Test Trial ' . time();
        $trial->trial_type_id = TrialType::model()->find('code = ?', array(TrialType::NON_INTERVENTION_CODE))->id;
        $trial->owner_user_id = 1; // Admin user
        $trial->is_open = 1;
        $trial->started_date = date('Y-m-d H:i:s');
        
        if ($trial->save()) {
            echo "Created trial with ID: " . $trial->id . PHP_EOL;
        } else {
            echo "Failed to create trial: " . print_r($trial->getErrors(), true) . PHP_EOL;
            exit(1);
        }
    }
    
    // Check if trial patients exist
    $existingTrialPatient = TrialPatient::model()->findByAttributes(array('trial_id' => $trial->id));
    if ($existingTrialPatient) {
        echo "Trial Patient already exists with ID: " . $existingTrialPatient->id . PHP_EOL;
        echo "Trial ID: " . $existingTrialPatient->trial_id . PHP_EOL;
        echo "External ID: " . $existingTrialPatient->external_trial_identifier . PHP_EOL;
    } else {
        // Get a patient
        $patient = Patient::model()->find();
        if (!$patient) {
            echo "No patients found in database" . PHP_EOL;
            exit(1);
        }
        
        // Get the default status
        $status = TrialPatientStatus::model()->find('code = ?', array(TrialPatientStatus::PENDING_CODE));
        if (!$status) {
            $status = TrialPatientStatus::model()->find();
        }
        
        // Create a trial patient
        $trialPatient = new TrialPatient();
        $trialPatient->trial_id = $trial->id;
        $trialPatient->patient_id = $patient->id;
        $trialPatient->status_id = $status->id;
        $trialPatient->external_trial_identifier = 'TEST-EXT-ID-' . time();
        $trialPatient->created_user_id = 1;
        $trialPatient->last_modified_user_id = 1;
        
        if ($trialPatient->save()) {
            echo "Created trial patient with ID: " . $trialPatient->id . PHP_EOL;
            echo "Patient ID: " . $trialPatient->patient_id . PHP_EOL;
            echo "Trial ID: " . $trialPatient->trial_id . PHP_EOL;
        } else {
            echo "Failed to create trial patient: " . print_r($trialPatient->getErrors(), true) . PHP_EOL;
            exit(1);
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
?>
