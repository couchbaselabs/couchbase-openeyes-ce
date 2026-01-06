<?php
// Test diagnosis validation endpoint
require_once 'protected/config/main.php';
$app = new CWebApplication(require 'protected/config/main.php');

// Check if secondary diagnosis was created for patient 1
echo "=== Checking Secondary Diagnoses for Patient 1 ===\n";
$diagnoses = SecondaryDiagnosis::model()->findAll('patient_id = 1');
echo "Count: " . count($diagnoses) . "\n";
foreach ($diagnoses as $d) {
    echo "ID: {$d->id}, Disorder ID: {$d->disorder_id}, Eye ID: {$d->eye_id}, Date: {$d->date}\n";
}

echo "\n=== Checking Disorders with Specialty Code 130 ===\n";
$ophthalmic_disorders = Disorder::model()->findAll(
    'join specialty on disorder.specialty_id = specialty.id and specialty.code = 130'
);
echo "Count: " . count($ophthalmic_disorders) . "\n";
foreach (array_slice($ophthalmic_disorders, 0, 5) as $d) {
    echo "ID: {$d->id}, Term: {$d->term}\n";
}

echo "\n=== Checking Disorder 1 ===\n";
$disorder = Disorder::model()->findByPk(1);
if ($disorder) {
    echo "Disorder 1 found: {$disorder->term}\n";
    echo "Specialty ID: {$disorder->specialty_id}\n";
    if ($disorder->specialty) {
        echo "Specialty Code: {$disorder->specialty->code}\n";
    }
} else {
    echo "Disorder 1 not found\n";
}

echo "\n=== Testing getOphthalmicDiagnoses() for Patient 1 ===\n";
$patient = Patient::model()->findByPk(1);
if ($patient) {
    $ophthalmic_diagnoses = $patient->getOphthalmicDiagnoses();
    echo "Count: " . count($ophthalmic_diagnoses) . "\n";
    foreach ($ophthalmic_diagnoses as $d) {
        echo "ID: {$d->id}, Disorder ID: {$d->disorder_id}, Eye ID: {$d->eye_id}\n";
    }
}
?>
