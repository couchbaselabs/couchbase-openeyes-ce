<?php
/**
 * Test script to create a test medication for Common Medications
 */

// Include Yii framework and configuration
$yii = dirname(__FILE__).'/framework/yii.php';
$config = dirname(__FILE__).'/protected/config/main.php';

require_once($yii);
Yii::createWebApplication($config);

try {
    // Get database connection
    $db = Yii::app()->db;
    
    // Create a test medication
    $sql = 'INSERT INTO medication_drug (name, active) VALUES (?, ?)';
    $cmd = $db->createCommand($sql);
    $cmd->bindValue(1, 'Test Aspirin', PDO::PARAM_STR);
    $cmd->bindValue(2, 1, PDO::PARAM_INT);
    $result = $cmd->execute();
    
    if ($result > 0) {
        echo 'Successfully inserted test medication<br>';
        
        // Verify
        $sql = 'SELECT id, name FROM medication_drug WHERE name = ?';
        $cmd = $db->createCommand($sql);
        $cmd->bindValue(1, 'Test Aspirin', PDO::PARAM_STR);
        $row = $cmd->queryRow();
        
        if ($row) {
            echo 'Verified: ID=' . $row['id'] . ', Name=' . $row['name'] . '<br>';
            echo '<a href="/commonMedications/add">Go to Common Medications</a>';
        } else {
            echo 'ERROR: Could not verify insertion';
        }
    } else {
        echo 'ERROR: Insert failed';
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
}
?>
