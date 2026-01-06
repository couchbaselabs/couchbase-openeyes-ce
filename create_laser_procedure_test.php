<?php
// Create a test laser procedure directly using direct database insertion
// This bypasses Yii initialization issues

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the database credentials from the environment or config
$db_host = getenv('DATABASE_HOST') ?: (getenv('MYSQL_HOST') ?: 'host.docker.internal');
$db_user = getenv('DATABASE_USER') ?: (getenv('MYSQL_USER') ?: 'openeyes');
$db_pass = getenv('DATABASE_PASS') ?: (getenv('MYSQL_PASSWORD') ?: 'openeyes');
$db_name = getenv('DATABASE_NAME') ?: (getenv('MYSQL_DB') ?: 'openeyes');
$db_port = getenv('DATABASE_PORT') ?: (getenv('MYSQL_PORT') ?: '3306');

// Debug: output the connection details
echo "<!-- DB: $db_host:$db_port/$db_name as $db_user -->\n";

try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name",
        $db_user,
        $db_pass,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
    
    // First, check if there are any procedures
    $stmt = $pdo->prepare("SELECT id FROM proc WHERE term = ? LIMIT 1");
    $stmt->execute(['Test Laser Procedure']);
    $proc_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($proc_row) {
        $procedure_id = $proc_row['id'];
        echo "Found existing procedure with ID: $procedure_id\n";
    } else {
        // Create a new procedure
        $stmt = $pdo->prepare("
            INSERT INTO proc (term, short_term, snomed_code, snomed_term) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute(['Test Laser Procedure', 'Test Laser', 'TEST001', 'Test Laser Procedure']);
        $procedure_id = $pdo->lastInsertId();
        echo "Created new procedure with ID: $procedure_id\n";
    }
    
    // Now check if a laser procedure already exists
    $stmt = $pdo->prepare("SELECT id FROM ophtrlaser_laserprocedure WHERE procedure_id = ? LIMIT 1");
    $stmt->execute([$procedure_id]);
    $laser_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($laser_row) {
        $laser_procedure_id = $laser_row['id'];
        echo "Laser procedure already exists with ID: $laser_procedure_id\n";
    } else {
        // Create a new laser procedure
        $stmt = $pdo->prepare("
            INSERT INTO ophtrlaser_laserprocedure (procedure_id, last_modified_user_id, created_user_id) 
            VALUES (?, 1, 1)
        ");
        $stmt->execute([$procedure_id]);
        $laser_procedure_id = $pdo->lastInsertId();
        echo "Created new laser procedure with ID: $laser_procedure_id\n";
    }
    
    echo "\nTest URL: http://localhost:7777/OphTrLaser/admin/deleteLaserProcedure?id=$laser_procedure_id\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Trying to connect to: $db_host:$db_port/$db_name as $db_user\n";
}
?>
