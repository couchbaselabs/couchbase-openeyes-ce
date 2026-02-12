<?php
/**
 * Script to create WorklistPatient entries for testing the worklist view
 * Uses direct SQL to avoid model loading issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "=== Creating WorklistPatient Entries (Direct SQL) ===\n\n";

// Read database config from the common.php
$configFile = dirname(__FILE__) . '/protected/config/local/common.php';
if (!file_exists($configFile)) {
    $configFile = dirname(__FILE__) . '/protected/config/common.php';
}

if (!file_exists($configFile)) {
    die("ERROR: Config file not found\n");
}

$config = require($configFile);

// Extract database connection info
$dbConfig = isset($config['components']['db']) ? $config['components']['db'] : null;

if (!$dbConfig) {
    die("ERROR: Database config not found\n");
}

// Get database credentials from environment or secrets (Docker style)
$host = getenv('DATABASE_HOST') ?: 'db';  // Docker usually uses 'db' as MySQL service name
$port = getenv('DATABASE_PORT') ?: '3306';
$dbname = getenv('DATABASE_NAME') ?: 'openeyes';
$username = getenv('DATABASE_USER') ?: 'openeyes';
$password = getenv('DATABASE_PASS') ?: 'openeyes';

// Try to read from Docker secrets if env vars are empty
if (empty($username) || $username === 'openeyes') {
    $secretUser = @file_get_contents('/run/secrets/DATABASE_USER');
    if ($secretUser) $username = trim($secretUser);
}
if (empty($password) || $password === 'openeyes') {
    $secretPass = @file_get_contents('/run/secrets/DATABASE_PASS');
    if ($secretPass) $password = trim($secretPass);
}

echo "Connecting to database: $dbname on $host:$port\n";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully!\n\n";
} catch (PDOException $e) {
    die("ERROR: Database connection failed: " . $e->getMessage() . "\n");
}

// Get worklist ID 4 (Test Worklist Today) or first available
$stmt = $pdo->query("SELECT id, name FROM worklist WHERE id = 4 OR 1=1 LIMIT 1");
$worklist = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$worklist) {
    die("ERROR: No worklist found\n");
}

$worklistId = $worklist['id'];
echo "Using Worklist: {$worklist['name']} (ID: $worklistId)\n";

// Get some patients
$stmt = $pdo->query("SELECT id, CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name FROM patient LIMIT 5");
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($patients)) {
    die("ERROR: No patients found\n");
}

echo "Found " . count($patients) . " patients\n\n";

$now = date('Y-m-d H:i:s');
$created = 0;

foreach ($patients as $patient) {
    $patientId = $patient['id'];
    $patientName = trim($patient['name']);
    
    // Check if already exists
    $stmt = $pdo->prepare("SELECT id FROM worklist_patient WHERE patient_id = ? AND worklist_id = ?");
    $stmt->execute([$patientId, $worklistId]);
    if ($stmt->fetch()) {
        echo "SKIP: Patient $patientId ($patientName) already in worklist\n";
        continue;
    }
    
    // Insert WorklistPatient
    $stmt = $pdo->prepare("INSERT INTO worklist_patient (patient_id, worklist_id, `when`, created_date, last_modified_date) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$patientId, $worklistId, $now, $now, $now]);
    $wpId = $pdo->lastInsertId();
    
    echo "CREATED: WorklistPatient ID $wpId for patient $patientId ($patientName)\n";
    
    // Create a Pathway for this patient (required for worklist view display)
    $stmt = $pdo->prepare("INSERT INTO pathway (worklist_patient_id, status, start_time, created_date, last_modified_date) VALUES (?, 1, ?, ?, ?)");
    $stmt->execute([$wpId, $now, $now, $now]);
    $pathwayId = $pdo->lastInsertId();
    
    echo "  -> Created Pathway ID $pathwayId\n";
    
    $created++;
}

echo "\n=== Summary ===\n";
echo "Created $created WorklistPatient entries with Pathways\n";
echo "Worklist view URL: http://localhost:7777/worklist/view\n";
