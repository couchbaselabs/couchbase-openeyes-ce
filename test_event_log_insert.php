<?php
// Simple direct database insert for testing
// MySQL connection details
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'openeyes';
$pass = getenv('DB_PASS') ?: 'openeyes';
$dbname = getenv('DB_NAME') ?: 'openeyes';

// Try alternative hosts if localhost fails
$hosts = [$host, 'db', 'mysql', '127.0.0.1'];
$conn = null;

foreach ($hosts as $h) {
    $conn = @new mysqli($h, $user, $pass, $dbname);
    if (!$conn->connect_error) {
        break;
    }
    $conn = null;
}

try {
    if (!$conn) {
        die('Connection failed: Could not connect to database with any host');
    }

    // Check if table exists and get import_success status values
    $statusQuery = "SELECT id FROM import_status LIMIT 1";
    $statusResult = $conn->query($statusQuery);
    
    if (!$statusResult || $statusResult->num_rows === 0) {
        echo "Warning: No import_status records found. Attempting to use ID 1.\n";
        $status_id = 1;
    } else {
        $statusRow = $statusResult->fetch_assoc();
        $status_id = $statusRow['id'];
    }

    // Insert test event log
    $unique_code = 'TEST-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
    $examination_date = date('Y-m-d');
    $examination_data = json_encode(['test' => 'data', 'patient' => 'test']);

    $stmt = $conn->prepare("INSERT INTO automatic_examination_event_log (event_id, unique_code, examination_date, examination_data, import_success) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        die('Prepare failed: ' . $conn->error);
    }

    $event_id = 1;
    if (!$stmt->bind_param('isssi', $event_id, $unique_code, $examination_date, $examination_data, $status_id)) {
        die('Binding failed: ' . $stmt->error);
    }

    if ($stmt->execute()) {
        echo "Success! Created event log with ID: " . $conn->insert_id . "\n";
        echo "Unique Code: " . $unique_code . "\n";
    } else {
        echo "Execute failed: " . $stmt->error . "\n";
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
