<?php
// Test script to check RequestType records using Yii
require_once 'index.php';

try {
    echo "Testing RequestType query...\n";
    
    // Use Yii's RequestType model to check records
    $count = RequestType::model()->count();
    echo "Total RequestType records (via model): " . $count . "\n\n";
    
    $records = RequestType::model()->findAll(array('order' => 'request_type DESC', 'limit' => 10));
    echo "Latest RequestType records:\n";
    if (empty($records)) {
        echo "No records found!\n";
    } else {
        foreach ($records as $record) {
            echo "request_type: " . $record->request_type . ", title_full: " . $record->title_full . ", title_short: " . $record->title_short . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
