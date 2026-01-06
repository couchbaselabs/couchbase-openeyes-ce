<?php
// Set up Yii
$yiiPath = __DIR__ . '/protected/yiic.php';
require_once $yiiPath;

// This will boot up Yii
$app = Yii::createWebApplication(__DIR__ . '/protected/config/main.php');

// Import the model
Yii::import('application.modules.OphCoTherapyapplication.models.OphCoTherapyapplication_DecisionTree');
Yii::import('application.modules.OphCoTherapyapplication.models.OphCoTherapyapplication_DecisionTreeNode');

// Create a test decision tree
$tree = new OphCoTherapyapplication_DecisionTree();
$tree->name = 'Test Decision Tree';
$tree->institution_id = 1;

// Try to save the tree
if ($tree->save()) {
    echo "Decision Tree created successfully with ID: " . $tree->id . "\n";
    
    // Create a root node
    $node = new OphCoTherapyapplication_DecisionTreeNode();
    $node->decisiontree_id = $tree->id;
    $node->question = 'Test Question';
    $node->response_type_id = 1;
    
    if ($node->save()) {
        echo "Root node created successfully with ID: " . $node->id . "\n";
    } else {
        echo "Failed to create root node: " . print_r($node->getErrors(), true) . "\n";
    }
} else {
    echo "Failed to create decision tree: " . print_r($tree->getErrors(), true) . "\n";
}
