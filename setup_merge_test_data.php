<?php
/**
 * Setup script for patient merge request test data
 */

// Get database connection from Yii without loading debug
define('YII_DEBUG', false);
require_once(__DIR__ . '/index.php');

try {
    $db = Yii::app()->db;
    
    echo "Creating test patients...\n";
    
    // Create first patient
    $sql1 = "INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, title, is_local, created_user_id, created_date) 
             VALUES ('TEST001', 'NHS001', '1980-01-15', 'M', 'John', 'Smith', 'Mr', 1, 1, NOW())";
    $db->createCommand($sql1)->execute();
    echo "Patient 1 created\n";
    
    // Create second patient
    $sql2 = "INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, title, is_local, created_user_id, created_date) 
             VALUES ('TEST002', 'NHS002', '1980-01-15', 'M', 'John', 'Smith', 'Mr', 1, 1, NOW())";
    $db->createCommand($sql2)->execute();
    echo "Patient 2 created\n";
    
    // Get the IDs
    $patient1_id = $db->createCommand("SELECT id FROM patient WHERE hos_num = 'TEST001' LIMIT 1")->queryScalar();
    $patient2_id = $db->createCommand("SELECT id FROM patient WHERE hos_num = 'TEST002' LIMIT 1")->queryScalar();
    
    echo "Patient 1 ID: $patient1_id\n";
    echo "Patient 2 ID: $patient2_id\n";
    
    // Create merge request
    echo "Creating merge request...\n";
    $sql3 = "INSERT INTO patient_merge_request (primary_id, secondary_id, status, created_user_id, created_date) 
             VALUES ($patient1_id, $patient2_id, 0, 1, NOW())";
    $db->createCommand($sql3)->execute();
    echo "Merge request created\n";
    
    // Verify
    $count = $db->createCommand("SELECT COUNT(*) FROM patient_merge_request")->queryScalar();
    echo "Total merge requests: $count\n";
    
    echo "\n✓ Test data created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Done!\n";
?>
