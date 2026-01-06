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
        
        try {
            // Try to insert using raw SQL
            $db = Yii::app()->db;
            $sql = "INSERT INTO trial (name, trial_type_id, owner_user_id, is_open, started_date, created_user_id, created_date) 
                    VALUES (:name, :trial_type_id, :owner_user_id, :is_open, :started_date, :created_user_id, :created_date)";
            $command = $db->createCommand($sql);
            $command->bindValue(':name', $trial->name, PDO::PARAM_STR);
            $command->bindValue(':trial_type_id', $trial->trial_type_id, PDO::PARAM_INT);
            $command->bindValue(':owner_user_id', $trial->owner_user_id, PDO::PARAM_INT);
            $command->bindValue(':is_open', $trial->is_open, PDO::PARAM_INT);
            $command->bindValue(':started_date', $trial->started_date, PDO::PARAM_STR);
            $command->bindValue(':created_user_id', Yii::app()->user->id, PDO::PARAM_INT);
            $command->bindValue(':created_date', date('Y-m-d H:i:s'), PDO::PARAM_STR);
            
            $numRows = $command->execute();
            echo "Insert command executed, rows affected: $numRows" . PHP_EOL;
            
            // Get last insert ID by querying the database directly
            $maxId = Yii::app()->db->createCommand('SELECT MAX(id) as max_id FROM trial')->queryScalar();
            $trial->id = $maxId;
            echo "Created trial with ID: " . $trial->id . " (via raw SQL insert)" . PHP_EOL;
            
            // Now create the user trial assignment
            $command2 = Yii::app()->db->createCommand();
            $permission = TrialPermission::model()->find('code = ?', array('MANAGE'));
            $command2->insert('user_trial_assignment', array(
                'user_id' => Yii::app()->user->id,
                'trial_id' => $trial->id,
                'trial_permission_id' => $permission->id,
                'role' => 'Trial Owner',
                'is_principal_investigator' => 1,
            ));
            echo "Created user trial assignment for user 1" . PHP_EOL;
            
        } catch (Exception $e) {
            echo "Exception while creating trial: " . $e->getMessage() . PHP_EOL;
            echo "Exception trace: " . $e->getTraceAsString() . PHP_EOL;
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
