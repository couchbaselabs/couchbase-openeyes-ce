<?php
// Simple script to check operation name rules
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to database
$dbconfig = include('/Users/asahu/Desktop/untitled\ folder/openeyes/protected/config/database.php');
$db_settings = $dbconfig['components']['db'];

$conn = new mysqli($db_settings['connectionString'], $db_settings['username'], $db_settings['password']);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select the database
$db_name = str_replace('mysql:dbname=', '', $db_settings['connectionString']);
$conn->select_db($db_name);

// Query the operation name rules table
$result = $conn->query("SELECT * FROM ophtroperationbooking_operation_name_rule");
if (!$result) {
    die("Query failed: " . $conn->error);
}

echo "Total records: " . $result->num_rows . "\n";
while ($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

$conn->close();
?>
