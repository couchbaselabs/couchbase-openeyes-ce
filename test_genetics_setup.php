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
    $patient->first_name = 'TestGeneticsSubject';
    $patient->last_name = 'TestPatient';
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
    
    // Create a relationship between genetics patient and study
    $study_subject = new GeneticsStudySubject();
    $study_subject->study_id = $study_id;
    $study_subject->subject_id = $genetics_patient_id;
    $study_subject->is_consent_given = 0;
    $study_subject->consent_status_id = 1;
    
    if ($study_subject->save()) {
        echo "Created study-subject relationship with ID: " . $study_subject->id . "\n";
        echo "Study Subject ID for testing: " . $study_subject->id . "\n";
    } else {
        echo "Failed to create study-subject relationship\n";
        print_r($study_subject->getErrors());
        exit(1);
    }
    
    echo "\nSetup complete!\n";
    echo "Test the editStudyStatus page with: /Genetics/subject/editStudyStatus/" . $study_subject->id . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
