<?php
require_once 'protected/yii.php';
require_once 'protected/config/main.php';

Yii::createApplication('CWebApplication', $config);

// Create a genetics subject for patient 1
$genetics_patient = new GeneticsPatient();
$genetics_patient->patient_id = 1;
$genetics_patient->gender_id = 1; // Male
$genetics_patient->is_deceased = 0;
$genetics_patient->comments = 'Test genetics subject';

if ($genetics_patient->save()) {
    echo "Genetics subject created with ID: " . $genetics_patient->id . "\n";
} else {
    echo "Failed to create genetics subject\n";
    print_r($genetics_patient->getErrors());
}
?>
