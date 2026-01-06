<?php
// Initialize OpenEyes
require_once 'protected/yiic.php';

$app = new CConsoleApplication(dirname(__FILE__) . '/protected/config/console.php');

// Get the selected institution ID from the first user's institution
$first_institution = Institution::model()->find();
if (!$first_institution) {
    die("No institutions found\n");
}

// Create a new team
$team = new Team();
$team->name = 'Test Team For Delete';
$team->active = 1;
$team->institution_id = $first_institution->id;

if ($team->save()) {
    echo "Team created successfully with ID: " . $team->id . "\n";
} else {
    echo "Failed to create team. Errors: " . json_encode($team->getErrors()) . "\n";
}
