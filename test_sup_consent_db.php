<?php
// Test script to check supplementary consent data in database
require_once 'protected/yii.php';
$config = require_once 'protected/config/main.php';

$app = Yii::createWebApplication($config);

// Check if any supplementary consent questions exist
$questions = Ophtrconsent_SupplementaryConsentQuestion::model()->findAll();

echo "Total supplementary consent questions: " . count($questions) . "\n";

if (!empty($questions)) {
    echo "\nQuestions:\n";
    foreach ($questions as $q) {
        echo "ID: " . $q->id . "\n";
        echo "  Name: " . $q->name . "\n";
        echo "  Description: " . $q->description . "\n";
        echo "  Question Type ID: " . $q->question_type_id . "\n";
        echo "  Question Type: " . ($q->question_type ? $q->question_type->name : 'NULL') . "\n";
        echo "  Assignments: " . count($q->question_assignment) . "\n";
        echo "\n";
    }
} else {
    echo "No questions found in database.\n";
}

// Also check raw database
echo "\nRaw database query:\n";
$sql = "SELECT id, name, description, question_type_id FROM ophtrconsent_sup_consent_question ORDER BY id DESC LIMIT 5";
$rows = Yii::app()->db->createCommand($sql)->queryAll();
echo "Rows returned: " . count($rows) . "\n";
foreach ($rows as $row) {
    echo json_encode($row) . "\n";
}
?>
