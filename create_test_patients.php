<?php
/**
 * Script to create test patients for patient merge request testing
 */

// Initialize Yii with proper configuration
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

require_once($yii);
$app = Yii::createWebApplication($config);

echo "Creating test patients...\n";

try {
    // Create first test patient using raw SQL
    $db = Yii::app()->db;
    
    // Patient 1
    $sql = "INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, title, is_local, created_user_id, created_date) 
            VALUES (:hos_num, :nhs_num, :dob, :gender, :first_name, :last_name, :title, :is_local, :created_user_id, :created_date)";
    $command = $db->createCommand($sql);
    $command->bindValue(':hos_num', 'HOS001', PDO::PARAM_STR);
    $command->bindValue(':nhs_num', 'NHS001', PDO::PARAM_STR);
    $command->bindValue(':dob', '1980-01-15', PDO::PARAM_STR);
    $command->bindValue(':gender', 'M', PDO::PARAM_STR);
    $command->bindValue(':first_name', 'John', PDO::PARAM_STR);
    $command->bindValue(':last_name', 'Smith', PDO::PARAM_STR);
    $command->bindValue(':title', 'Mr', PDO::PARAM_STR);
    $command->bindValue(':is_local', 1, PDO::PARAM_INT);
    $command->bindValue(':created_user_id', 1, PDO::PARAM_INT);
    $command->bindValue(':created_date', date('Y-m-d H:i:s'), PDO::PARAM_STR);
    
    $numRows = $command->execute();
    echo "Patient 1 inserted, rows affected: $numRows\n";
    $patient1_id = $db->lastInsertID;
    echo "Patient 1 ID: $patient1_id\n";
    
    // Patient 2
    $sql = "INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, title, is_local, created_user_id, created_date) 
            VALUES (:hos_num, :nhs_num, :dob, :gender, :first_name, :last_name, :title, :is_local, :created_user_id, :created_date)";
    $command = $db->createCommand($sql);
    $command->bindValue(':hos_num', 'HOS002', PDO::PARAM_STR);
    $command->bindValue(':nhs_num', 'NHS002', PDO::PARAM_STR);
    $command->bindValue(':dob', '1980-01-15', PDO::PARAM_STR);
    $command->bindValue(':gender', 'M', PDO::PARAM_STR);
    $command->bindValue(':first_name', 'John', PDO::PARAM_STR);
    $command->bindValue(':last_name', 'Smith', PDO::PARAM_STR);
    $command->bindValue(':title', 'Mr', PDO::PARAM_STR);
    $command->bindValue(':is_local', 1, PDO::PARAM_INT);
    $command->bindValue(':created_user_id', 1, PDO::PARAM_INT);
    $command->bindValue(':created_date', date('Y-m-d H:i:s'), PDO::PARAM_STR);
    
    $numRows = $command->execute();
    echo "Patient 2 inserted, rows affected: $numRows\n";
    $patient2_id = $db->lastInsertID;
    echo "Patient 2 ID: $patient2_id\n";
    
    echo "\nTest patients created successfully!\n";
    echo "Patient 1 ID: $patient1_id\n";
    echo "Patient 2 ID: $patient2_id\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\nDone!\n";
?>
