<?php
require_once 'index.php';

// Test element set data
try {
    $restClient = Yii::app()->couchbaseRest;
    
    // Query all element sets
    $n1ql = "SELECT s.* FROM `openeyes`.`reference`.`ophciexamination_element_set` s LIMIT 10";
    echo "Query: " . $n1ql . "\n";
    $rows = $restClient->query($n1ql);
    
    echo "\nElement Sets Found: " . count($rows) . "\n";
    foreach ($rows as $row) {
        echo json_encode($row) . "\n";
    }
    
    // Now test the specific query for workflow 30
    echo "\n\nTesting specific query for workflow 30:\n";
    $n1ql2 = "SELECT s.* FROM `openeyes`.`reference`.`ophciexamination_element_set` s WHERE s.workflow_id=\$workflow_id ORDER BY s.position, s.id";
    echo "Query: " . $n1ql2 . "\n";
    $rows2 = $restClient->query($n1ql2, ['workflow_id' => 30]);
    echo "Results: " . count($rows2) . "\n";
    foreach ($rows2 as $row) {
        echo json_encode($row) . "\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
?>
