<?php
// Simple script to check Couchbase data without complex models

// Check if curl is available
if (!function_exists('curl_init')) {
    die("cURL is not available\n");
}

// Couchbase connection details
$cb_host = getenv('COUCHBASE_HOST') ?: 'localhost';
$cb_port = getenv('COUCHBASE_PORT') ?: '8091';
$cb_user = getenv('COUCHBASE_USER') ?: 'Administrator';
$cb_pass = getenv('COUCHBASE_PASSWORD') ?: 'password';
$cb_bucket = getenv('COUCHBASE_BUCKET') ?: 'openeyes';

echo "Connecting to Couchbase at $cb_host:$cb_port\n";
echo "Bucket: $cb_bucket\n\n";

// Try to ping the cluster
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$cb_host:$cb_port/pools");
curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    die("Failed to connect to Couchbase. HTTP Code: $http_code\n");
}

echo "Connected to Couchbase successfully!\n\n";

// Try a simple N1QL query to count documents
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$cb_host:8093/query");
curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'statement=SELECT COUNT(*) as cnt FROM `' . $cb_bucket . '` WHERE _type="user_authentication" AND active=true');
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    echo "Active User Authentications Count:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "Query failed with HTTP Code: $http_code\n";
    echo "Response: $response\n\n";
}

// Try to fetch some user_authentication documents
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$cb_host:8093/query");
curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'statement=SELECT username, active, id FROM `' . $cb_bucket . '` WHERE _type="user_authentication" LIMIT 10');
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    echo "Sample User Authentications:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "Query failed with HTTP Code: $http_code\n";
}

?>
