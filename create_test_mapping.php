<?php
// Create a test mapping directly using direct database insertion
// This bypasses Yii initialization issues

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the database credentials from the environment or config
$db_host = getenv('MYSQL_HOST') ?: 'localhost';
$db_user = getenv('MYSQL_USER') ?: 'openeyes';
$db_pass = getenv('MYSQL_PASSWORD') ?: 'openeyes';
$db_name = getenv('MYSQL_DB') ?: 'openeyes';
$db_port = getenv('MYSQL_PORT') ?: '3306';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name",
        $db_user,
        $db_pass,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    // Insert a test mapping
    $stmt = $pdo->prepare("
        INSERT INTO site_subspecialty_anaesthetic_agent 
        (site_id, subspecialty_id, anaesthetic_agent_id) 
        VALUES (?, ?, ?)
    ");
    
    $result = $stmt->execute(array(2, 1767169130, 5));
    
    if ($result) {
        $lastId = $pdo->lastInsertId();
        echo "Successfully created mapping with ID: " . $lastId . "\n";
        
        // Verify the insert
        $stmt = $pdo->prepare("
            SELECT id, site_id, subspecialty_id, anaesthetic_agent_id 
            FROM site_subspecialty_anaesthetic_agent 
            WHERE id = ?
        ");
        $stmt->execute(array($lastId));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Verification: " . json_encode($row) . "\n";
    } else {
        echo "Failed to create mapping\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Trying to connect to: $db_host:$db_port/$db_name as $db_user\n";
}
?>
