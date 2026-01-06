<?php
require_once dirname(__FILE__) . '/index.php';

$teams = Team::model()->resetScope()->findAll();
echo "Total teams (with resetScope): " . count($teams) . "\n";
foreach ($teams as $team) {
    echo "Team: {$team->name}, ID: {$team->id}, Institution: {$team->institution_id}, Active: {$team->active}\n";
}

echo "\n--- Without resetScope ---\n";
$teams2 = Team::model()->findAll();
echo "Total teams (default scope): " . count($teams2) . "\n";
foreach ($teams2 as $team) {
    echo "Team: {$team->name}, ID: {$team->id}, Institution: {$team->institution_id}, Active: {$team->active}\n";
}

echo "\n--- Session Info ---\n";
echo "Selected Institution ID: " . Yii::app()->session->get('selected_institution_id') . "\n";
