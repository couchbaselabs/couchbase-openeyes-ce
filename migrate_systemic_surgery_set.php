<?php
/**
 * This script adds missing columns to the ophciexamination_systemic_surgery_set table
 */

try {
    // Database credentials (from local environment)
    $hosts = ['127.0.0.1', 'localhost', 'mariadb', 'db', 'database'];
    $dbname = 'openeyes';
    $user = 'openeyes';
    $pass = 'openeyes';
    
    // Try to connect to database
    $pdo = null;
    foreach ($hosts as $host) {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connected to database at $host\n\n";
            break;
        } catch (PDOException $e) {
            // Continue to next host
        }
    }
    
    if (!$pdo) {
        throw new Exception("Could not connect to database on any host: " . implode(', ', $hosts));
    }
    
    // Get current columns
    $result = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'ophciexamination_systemic_surgery_set' AND TABLE_SCHEMA = '$dbname'")->fetchAll(PDO::FETCH_COLUMN);
    $columns = array_flip($result);
    
    echo "Current columns: " . implode(', ', $result) . "\n\n";
    
    // Add missing columns
    $changes = [];
    
    if (!isset($columns['institution_id'])) {
        $pdo->exec('ALTER TABLE ophciexamination_systemic_surgery_set ADD COLUMN institution_id INT(10) UNSIGNED NOT NULL DEFAULT 1');
        $changes[] = 'Added institution_id';
    }
    
    if (!isset($columns['created_user_id'])) {
        $pdo->exec('ALTER TABLE ophciexamination_systemic_surgery_set ADD COLUMN created_user_id INT(10)');
        $changes[] = 'Added created_user_id';
    }
    
    if (!isset($columns['created_date'])) {
        $pdo->exec('ALTER TABLE ophciexamination_systemic_surgery_set ADD COLUMN created_date DATETIME');
        $changes[] = 'Added created_date';
    }
    
    if (!isset($columns['last_modified_user_id'])) {
        $pdo->exec('ALTER TABLE ophciexamination_systemic_surgery_set ADD COLUMN last_modified_user_id INT(10)');
        $changes[] = 'Added last_modified_user_id';
    }
    
    if (!isset($columns['last_modified_date'])) {
        $pdo->exec('ALTER TABLE ophciexamination_systemic_surgery_set ADD COLUMN last_modified_date DATETIME');
        $changes[] = 'Added last_modified_date';
    }
    
    if (empty($changes)) {
        echo "No changes needed - all columns already exist!\n";
    } else {
        echo "Changes made:\n";
        foreach ($changes as $change) {
            echo "  - $change\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    die(1);
}
?>
