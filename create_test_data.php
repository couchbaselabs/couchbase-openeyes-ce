<?php
// Bootstrap the application
$basePath = dirname(__FILE__);
// Handle both local and Docker paths
if (!file_exists($basePath . '/protected/tests/bootstrap.php')) {
    $basePath = '/var/www/openeyes';
}

require_once $basePath . '/protected/tests/bootstrap.php';

try {
    // Create a patient
    $patient = new Patient();
    $patient->first_name = 'Test';
    $patient->last_name = 'Genetics Patient';
    $patient->dob = '1980-01-01';
    $patient->gender = 'M';
    
    if ($patient->save()) {
        echo "Created patient with ID: " . $patient->id . "\n";
        $patient_id = $patient->id;
    } else {
        echo "Failed to create patient\n";
        print_r($patient->getErrors());
        exit(1);
    }
    
    // Create a genetics patient linked to this patient
    $genetics_patient = new GeneticsPatient();
    $genetics_patient->patient_id = $patient_id;
    $genetics_patient->gender_id = 1; // Male
    
    if ($genetics_patient->save()) {
        echo "Created genetics patient with ID: " . $genetics_patient->id . "\n";
        $genetics_patient_id = $genetics_patient->id;
    } else {
        echo "Failed to create genetics patient\n";
        print_r($genetics_patient->getErrors());
        exit(1);
    }
    
    // Create a study
    $study = new GeneticsStudy();
    $study->name = 'Test Genetics Study';
    $study->criteria = 'Test Criteria';
    
    if ($study->save()) {
        echo "Created study with ID: " . $study->id . "\n";
        $study_id = $study->id;
    } else {
        echo "Failed to create study\n";
        print_r($study->getErrors());
        exit(1);
    }
    
    // Create the pivot record
    $pivot = new GeneticsStudySubject();
    $pivot->subject_id = $genetics_patient_id;
    $pivot->study_id = $study_id;
    $pivot->participation_status_id = 1; // Assuming 1 is a valid status
    
    if ($pivot->save()) {
        echo "Created genetics study subject pivot with ID: " . $pivot->id . "\n";
        echo "Test data creation completed successfully!\n";
        echo "\nYou can now test the page at:\n";
        echo "http://localhost:7777/Genetics/subject/editStudyStatus/" . $pivot->id . "\n";
    } else {
        echo "Failed to create pivot\n";
        print_r($pivot->getErrors());
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
?>
