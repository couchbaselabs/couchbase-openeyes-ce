<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bootstrap Yii
require_once dirname(__FILE__) . '/index.php';

echo "=== Testing Team Creation ===\n\n";

// Create a new team
$team = new Team();
$team->name = 'Test Team Direct';
$team->active = 1;
$team->institution_id = Yii::app()->session->get('selected_institution_id');

echo "Team name: " . $team->name . "\n";
echo "Team active: " . $team->active . "\n";
echo "Team institution_id: " . $team->institution_id . "\n";

// Try to save
echo "\nAttempting to save...\n";
$result = $team->save();

echo "Save result: " . ($result ? 'TRUE' : 'FALSE') . "\n";

if (!$result) {
    echo "\nErrors:\n";
    $errors = $team->getErrors();
    var_dump($errors);
} else {
    echo "\nTeam saved successfully!\n";
    echo "Team ID: " . $team->id . "\n";
    
    // Verify it was saved
    $verify = Team::model()->resetScope()->findByPk($team->id);
    if ($verify) {
        echo "Verified in database: YES\n";
        echo "  Name: " . $verify->name . "\n";
        echo "  Institution: " . $verify->institution_id . "\n";
    } else {
        echo "Verified in database: NO\n";
    }
}

// Check total teams
echo "\nTotal teams in database: " . count(Team::model()->resetScope()->findAll()) . "\n";
?>
