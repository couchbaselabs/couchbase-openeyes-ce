<?php
require_once 'index.php';

// Try to find the latest decision tree
$trees = OphCoTherapyapplication_DecisionTree::model()->findAll();
echo "Found " . count($trees) . " decision trees:\n";
foreach ($trees as $tree) {
    echo "ID: {$tree->id}, Name: {$tree->name}, Institution: {$tree->institution_id}\n";
}

// Try to find by ID
$id = 1767712454150;
$tree = OphCoTherapyapplication_DecisionTree::model()->findByPk($id);
if ($tree) {
    echo "\nFound tree with ID $id: " . $tree->name . "\n";
} else {
    echo "\nTree with ID $id not found\n";
    // Try to query the database directly
    $sql = "SELECT * FROM ophcotherapya_decisiontree WHERE id = $id";
    $result = Yii::app()->db->createCommand($sql)->queryRow();
    if ($result) {
        echo "Direct DB query found the record: " . json_encode($result) . "\n";
    } else {
        echo "Direct DB query also didn't find the record\n";
    }
}
