<?php
// Load Yii framework
require_once('/var/www/openeyes/protected/components/System.php');
require_once('/var/www/openeyes/protected/../index.php');

// Test if we can access the database
$trialPatients = TrialPatient::model()->findAll();
echo "Number of trial patients: " . count($trialPatients) . "\n";

foreach ($trialPatients as $tp) {
    echo "ID: " . $tp->id . ", Trial ID: " . ($tp->trial_id ?? 'NULL') . ", Patient ID: " . ($tp->patient_id ?? 'NULL') . "\n";
}

// Check if there are any trials
$trials = Trial::model()->findAll();
echo "\nNumber of trials: " . count($trials) . "\n";

foreach ($trials as $trial) {
    echo "Trial ID: " . $trial->id . ", Name: " . $trial->name . "\n";
}
