<?php
// Set up the application
$basePath = dirname(__FILE__);
require_once $basePath . '/protected/yii_base.php';
require_once $basePath . '/protected/config/main.php';

// Create the application
$config = require_once $basePath . '/protected/config/main.php';
$app = Yii::createWebApplication($config);

// Get the user (for timestamps)
$user = User::model()->find("username = ?", ["admin"]);
$institution = Institution::model()->find("code = ?", ["PresInstitution"]);

// Create a new systemic diagnosis set
$set = new \OEModule\OphCiExamination\models\OphCiExaminationSystemicDiagnosesSet();
$set->name = "Test Systemic Diagnosis Set";
$set->institution_id = $institution ? $institution->id : 1;
$set->firm_id = null;
$set->subspecialty_id = null;
$set->created_user_id = $user ? $user->id : 1;
$set->last_modified_user_id = $user ? $user->id : 1;

if ($set->save()) {
    echo "Successfully created systemic diagnosis set with ID: " . $set->id . "\n";
    echo "Name: " . $set->name . "\n";
    echo "Institution ID: " . $set->institution_id . "\n";
} else {
    echo "Failed to save systemic diagnosis set\n";
    print_r($set->getErrors());
}
?>
