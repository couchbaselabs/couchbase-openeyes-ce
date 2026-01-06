<?php
// Load Yii and config
require_once('protected/yiic.php');

try {
    // Get database connection
    $db = Yii::app()->db;
    
    // Insert test subspecialty subsection
    $sql = "INSERT INTO subspecialty_subsection (subspecialty_id, name, display_order) VALUES (:subspecialty_id, :name, :display_order)";
    $command = $db->createCommand($sql);
    $command->bindParam(':subspecialty_id', $subspecialty_id, PDO::PARAM_INT);
    $command->bindParam(':name', $name, PDO::PARAM_STR);
    $command->bindParam(':display_order', $display_order, PDO::PARAM_INT);
    
    $subspecialty_id = 3; // Glaucoma
    $name = 'Test Subsection for Edit';
    $display_order = 1;
    
    $result = $command->execute();
    
    // Get the last insert ID
    $lastId = $db->getLastInsertID();
    
    echo "Successfully created subspecialty subsection with ID: " . $lastId . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
