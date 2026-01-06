<?php
// Set up environment variables for CLI
$_SERVER['HTTP_HOST'] = 'localhost:7777';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '7777';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

// Initialize Yii application
require_once(__DIR__ . '/index.php');

try {
    // Get or create trial data
    $trial = Trial::model()->find();
    if (!$trial) {
        echo "Creating trial..." . PHP_EOL;
        $trial = TrialPatient::factory()->create()->trial;
    }
    echo "Trial ID: " . $trial->id . ", Name: " . $trial->name . PHP_EOL;
    
    // Get or create trial patient
    $trialPatient = TrialPatient::model()->findByAttributes(['trial_id' => $trial->id]);
    if (!$trialPatient) {
        echo "Creating trial patient..." . PHP_EOL;
        $trialPatient = TrialPatient::factory()->create(['trial_id' => $trial->id]);
    }
    echo "Trial Patient ID: " . $trialPatient->id . ", External ID: " . $trialPatient->external_trial_identifier . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
?>
