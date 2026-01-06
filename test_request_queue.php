<?php
// Test script to check request_queue table
$ini = parse_ini_file('/var/www/openeyes/protected/config/local/common.php');
$db_type = $ini['db']['type'];
$db_name = $ini['db']['name'];
$db_host = $ini['db']['host'];
$db_user = $ini['db']['user'];
$db_passwd = $ini['db']['passwd'];

// Connect to the database
$mysqli = new mysqli($db_host, $db_user, $db_passwd, $db_name);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Query the request_queue table
$result = $mysqli->query("SELECT * FROM request_queue");

if ($result) {
    echo "Rows in request_queue table: " . $result->num_rows . "\n";
    while ($row = $result->fetch_assoc()) {
        echo "- " . json_encode($row) . "\n";
    }
} else {
    echo "Error: " . $mysqli->error . "\n";
}

// Also check if the table exists
$tables = $mysqli->query("SHOW TABLES LIKE 'request_queue'");
echo "Table exists: " . ($tables->num_rows > 0 ? "yes" : "no") . "\n";

$mysqli->close();
?>
