<?php
define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

// Get to the root directory
require_once dirname(__FILE__) . '/index.php';

// Create a test decision tree
$tree = new OphCoTherapyapplication_DecisionTree();
$tree->name = 'Test Decision Tree';
$tree->institution_id = 1;

if ($tree->save()) {
    echo "Decision Tree created with ID: " . $tree->id . "\n";
    $tree_id = $tree->id;
} else {
    echo "Failed to create decision tree\n";
    print_r($tree->getErrors());
    exit(1);
}

// Create response type
$response_type = new OphCoTherapyapplication_DecisionTreeNode_ResponseType();
$response_type->datatype = 'text';
$response_type->name = 'Text Response';
if (!$response_type->save()) {
    echo "Failed to create response type\n";
    print_r($response_type->getErrors());
    exit(1);
}

// Create root node
$node = new OphCoTherapyapplication_DecisionTreeNode();
$node->decisiontree_id = $tree_id;
$node->question = 'Test Question';
$node->response_type_id = $response_type->id;
if ($node->save()) {
    echo "Root Node created with ID: " . $node->id . "\n";
    $node_id = $node->id;
} else {
    echo "Failed to create root node\n";
    print_r($node->getErrors());
    exit(1);
}

// Create a child node
$child_node = new OphCoTherapyapplication_DecisionTreeNode();
$child_node->decisiontree_id = $tree_id;
$child_node->parent_id = $node_id;
$child_node->question = 'Child Question';
$child_node->response_type_id = $response_type->id;
if ($child_node->save()) {
    echo "Child Node created with ID: " . $child_node->id . "\n";
    $child_node_id = $child_node->id;
} else {
    echo "Failed to create child node\n";
    print_r($child_node->getErrors());
    exit(1);
}

// Create a rule for the child node
$rule = new OphCoTherapyapplication_DecisionTreeNodeRule();
$rule->node_id = $child_node_id;
$rule->parent_check = 'eq';
$rule->parent_check_value = 'test';

if ($rule->save()) {
    echo "Rule created with ID: " . $rule->id . "\n";
    echo "Tree ID: $tree_id, Node ID: $child_node_id, Rule ID: " . $rule->id . "\n";
} else {
    echo "Failed to create rule\n";
    print_r($rule->getErrors());
    exit(1);
}
