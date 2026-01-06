<?php
/**
 * Check the trial table structure
 */

// Include Yii
require_once dirname(__FILE__) . '/index.php';

try {
    $db = Yii::app()->db;
    
    // Check if the trial table exists
    $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trial'";
    $result = $db->createCommand($sql)->queryScalar();
    
    if ($result) {
        echo "Trial table exists\n";
        
        // Get the table structure
        $sql = "DESCRIBE trial";
        $columns = $db->createCommand($sql)->queryAll();
        
        echo json_encode(['table_exists' => true, 'columns' => $columns], JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['table_exists' => false]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
