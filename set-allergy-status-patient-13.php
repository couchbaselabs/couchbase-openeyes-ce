#!/usr/bin/env php
<?php
/**
 * Set no allergies status for patient_id = 13
 */

// Bootstrap Yii
$basePath = dirname(__FILE__);
require_once($basePath . '/protected/yiic.php');

echo "===========================================\n";
echo "Setting No Allergies Status for Patient 13\n";
echo "===========================================\n\n";

// Load patient
$patientId = 13;
$patient = Patient::model()->findByPk($patientId);

if (!$patient) {
    echo "ERROR: Patient with ID {$patientId} not found.\n";
    exit(1);
}

echo "Patient Details:\n";
echo "  ID:           {$patient->id}\n";
echo "  Name:         {$patient->getFullName()}\n";
echo "  Hospital No:  {$patient->hos_num}\n";
echo "  DOB:          {$patient->dob}\n";
echo "\n";

echo "Current Allergy Status: ";

// Check current status
if ($patient->no_allergies_date) {
    echo "No allergies (set on " . date('Y-m-d H:i:s', strtotime($patient->no_allergies_date)) . ")\n";
    echo "\n";
    echo "✓ Patient already has 'no allergies' status set.\n";
    echo "✓ No action needed - patient can receive prescriptions.\n";
    exit(0);
}

$allergyCount = count($patient->allergyAssignments);
if ($allergyCount > 0) {
    echo "Has {$allergyCount} allergies:\n";
    foreach ($patient->allergyAssignments as $assignment) {
        echo "    - {$assignment->allergy->name}";
        if ($assignment->other) {
            echo " ({$assignment->other})";
        }
        if ($assignment->comments) {
            echo " - {$assignment->comments}";
        }
        echo "\n";
    }
    echo "\n";
    echo "WARNING: Cannot set 'no allergies' status while patient has allergies.\n";
    echo "Remove existing allergies first if you want to set 'no allergies' status.\n";
    exit(1);
}

echo "Unknown (not set)\n";
echo "\n";

// Set no allergies date
echo "Setting 'no allergies' status...\n";

$patient->no_allergies_date = date('Y-m-d H:i:s');

if ($patient->save()) {
    echo "✓ SUCCESS: No allergies status set successfully!\n";
    echo "\n";
    echo "Updated patient record:\n";
    echo "  no_allergies_date: {$patient->no_allergies_date}\n";
    echo "\n";
    
    // Verify the update
    $verifyPatient = Patient::model()->findByPk($patientId);
    if ($verifyPatient->no_allergies_date) {
        echo "✓ VERIFIED: Status confirmed in database\n";
    } else {
        echo "⚠ WARNING: Could not verify status in database\n";
    }
    
    echo "\n";
    echo "Patient {$patient->getFullName()} can now receive prescriptions.\n";
    echo "\n";
    echo "Test prescription creation at:\n";
    echo "  http://localhost/OphDrPrescription/default/create?patient_id={$patient->id}\n";
    echo "\n";
    echo "Or test simpler modules:\n";
    echo "  Biometry:       http://localhost/OphInBiometry/default/create?patient_id={$patient->id}\n";
    echo "  Correspondence: http://localhost/OphCoCorrespondence/default/create?patient_id={$patient->id}\n";
    echo "\n";
    
    exit(0);
} else {
    echo "ERROR: Failed to save patient:\n";
    foreach ($patient->getErrors() as $field => $errors) {
        foreach ($errors as $error) {
            echo "  - {$field}: {$error}\n";
        }
    }
    exit(1);
}
