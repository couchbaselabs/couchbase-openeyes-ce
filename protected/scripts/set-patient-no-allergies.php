#!/usr/bin/env php
<?php
/**
 * Helper script to set "no allergies" status for a patient
 * Usage: php set-patient-no-allergies.php [patient_id]
 * 
 * This script sets the no_allergies_date for a patient to enable prescription creation.
 * For testing purposes only.
 */

// Bootstrap Yii
$basePath = dirname(dirname(__FILE__));
require_once($basePath . '/yiic.php');

// Get patient ID from command line or show list
$patientId = isset($argv[1]) ? (int)$argv[1] : null;

if (!$patientId) {
    echo "===========================================\n";
    echo "Set Patient No Allergies Status\n";
    echo "===========================================\n\n";
    
    // Show first 10 patients
    echo "Available patients:\n";
    echo str_repeat('-', 80) . "\n";
    echo sprintf("%-6s %-15s %-20s %-20s\n", "ID", "Hospital No", "Name", "Allergy Status");
    echo str_repeat('-', 80) . "\n";
    
    $patients = Yii::app()->db->createCommand()
        ->select('p.id, p.hos_num, p.no_allergies_date, c.first_name, c.last_name')
        ->from('patient p')
        ->join('contact c', 'p.contact_id = c.id')
        ->limit(10)
        ->queryAll();
    
    foreach ($patients as $patient) {
        $name = $patient['first_name'] . ' ' . $patient['last_name'];
        
        // Check allergy status
        $allergyCount = Yii::app()->db->createCommand()
            ->select('COUNT(*) as count')
            ->from('patient_allergy_assignment')
            ->where('patient_id = :id', [':id' => $patient['id']])
            ->queryScalar();
        
        if ($patient['no_allergies_date']) {
            $status = 'No Allergies';
        } elseif ($allergyCount > 0) {
            $status = "Has {$allergyCount} allergies";
        } else {
            $status = 'Unknown';
        }
        
        echo sprintf(
            "%-6s %-15s %-20s %-20s\n",
            $patient['id'],
            $patient['hos_num'],
            substr($name, 0, 20),
            $status
        );
    }
    
    echo str_repeat('-', 80) . "\n\n";
    echo "Usage: php set-patient-no-allergies.php [patient_id]\n";
    echo "Example: php set-patient-no-allergies.php 1\n\n";
    exit(0);
}

// Load patient
$patient = Patient::model()->findByPk($patientId);

if (!$patient) {
    echo "ERROR: Patient with ID {$patientId} not found.\n";
    exit(1);
}

echo "===========================================\n";
echo "Setting No Allergies Status\n";
echo "===========================================\n\n";

echo "Patient Details:\n";
echo "  ID:           {$patient->id}\n";
echo "  Name:         {$patient->getFullName()}\n";
echo "  Hospital No:  {$patient->hos_num}\n";
echo "  Current Status: ";

// Check current status
if ($patient->no_allergies_date) {
    echo "No allergies (set on " . date('Y-m-d', strtotime($patient->no_allergies_date)) . ")\n";
} elseif (count($patient->allergyAssignments) > 0) {
    echo "Has " . count($patient->allergyAssignments) . " allergies\n";
    foreach ($patient->allergyAssignments as $assignment) {
        echo "    - {$assignment->allergy->name}\n";
    }
} else {
    echo "Unknown (not set)\n";
}

echo "\n";

// Check if already has allergies
if (count($patient->allergyAssignments) > 0) {
    echo "WARNING: Patient already has allergies assigned.\n";
    echo "Cannot set 'no allergies' status when allergies exist.\n";
    echo "Remove existing allergies first if you want to set 'no allergies' status.\n";
    exit(1);
}

// Check if already set
if ($patient->no_allergies_date) {
    echo "Patient already has 'no allergies' status set.\n";
    echo "No action needed.\n";
    exit(0);
}

// Set no allergies date
echo "Setting 'no allergies' status...\n";

$patient->no_allergies_date = date('Y-m-d H:i:s');

if ($patient->save()) {
    echo "✓ SUCCESS: No allergies status set successfully!\n";
    echo "\n";
    echo "Patient can now receive prescriptions.\n";
    echo "\n";
    echo "Test prescription creation at:\n";
    echo "  http://localhost/OphDrPrescription/default/create?patient_id={$patient->id}\n";
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
