<?php
// Load Yii framework
require_once dirname(__FILE__) . '/index.php';

// Set up the application
Yii::app()->user->login(User::model()->find('username = ?', ['admin']));

// Create a new trial
$trial = new Trial();
$trial->name = 'Verification Test Trial ' . time();
$trial->is_open = 1;
$trial->trial_type_id = TrialType::model()->find('code = ?', array(TrialType::NON_INTERVENTION_CODE))->id;
$trial->owner_user_id = Yii::app()->user->id;
$trial->started_date = date('d M Y');
$trial->setScenario('manual');

if ($trial->save()) {
    echo "Trial created successfully with ID: " . $trial->id . "\n";
    echo "Name: " . $trial->name . "\n";
} else {
    echo "Failed to create trial\n";
    echo "Errors: " . print_r($trial->getErrors(), true) . "\n";
}
?>
