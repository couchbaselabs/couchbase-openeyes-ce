<?php
// Simple script to insert a UniqueCodes record for testing

// Database connection using PDO
try {
    $pdo = new PDO('mysql:host=db;dbname=openeyes;charset=utf8mb4', 'openeyes', 'password');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERR_ATTR_RAISE_EXCEPTION);
    
    // Insert a test record
    $stmt = $pdo->prepare('INSERT INTO unique_codes (code, active) VALUES (?, ?)');
    $stmt->execute(['TEST-CODE-001', 1]);
    
    $id = $pdo->lastInsertId();
    echo "Success! Created UniqueCodes with ID: $id";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
