<?php
/**
 * Check admin user authentication details in Couchbase
 */

// Get Couchbase connection details
$cb_host = getenv('COUCHBASE_HOST') ?: 'localhost';
$cb_query_port = getenv('COUCHBASE_QUERY_PORT') ?: '8093';
$cb_user = getenv('COUCHBASE_USER') ?: 'Administrator';
$cb_pass = getenv('COUCHBASE_PASSWORD') ?: 'password';
$cb_bucket = getenv('COUCHBASE_BUCKET') ?: 'openeyes';

// Function to execute a Couchbase N1QL query
function executeN1QL($host, $port, $user, $pass, $query) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://$host:$port/query");
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'statement=' . urlencode($query));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    } else {
        echo "Query failed with HTTP Code: $http_code\n";
        echo "Response: $response\n";
        return null;
    }
}

echo "Checking admin user authentications in Couchbase\n";
echo "================================================\n\n";

// Get all authentication records for admin user
$query = "SELECT META().id as doc_id, * FROM `$cb_bucket` WHERE _type='user_authentication' AND username='admin'";
echo "Query: $query\n\n";
$result = executeN1QL($cb_host, $cb_query_port, $cb_user, $cb_pass, $query);

if ($result && isset($result['results'])) {
    echo "Found " . count($result['results']) . " record(s):\n";
    foreach ($result['results'] as $i => $auth) {
        echo "\n[Record " . ($i + 1) . "]\n";
        echo "  ID: " . (isset($auth['id']) ? $auth['id'] : 'N/A') . "\n";
        echo "  Username: " . (isset($auth['username']) ? $auth['username'] : 'N/A') . "\n";
        echo "  User ID: " . (isset($auth['user_id']) ? $auth['user_id'] : 'N/A') . "\n";
        echo "  Active: " . (isset($auth['active']) ? json_encode($auth['active']) : 'N/A') . " (type: " . (isset($auth['active_type']) ? $auth['active_type'] : 'N/A') . ")\n";
        echo "  Institution Auth ID: " . (isset($auth['institution_authentication_id']) ? $auth['institution_authentication_id'] : 'NULL') . "\n";
        echo "  Full record: " . json_encode($auth, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "No records found or error occurred\n";
}

echo "\n\n";

// Also check for any user with username 'admin'
$query2 = "SELECT _type, username, COUNT(*) as count FROM `$cb_bucket` WHERE username='admin' GROUP BY _type, username";
echo "Query: $query2\n\n";
$result2 = executeN1QL($cb_host, $cb_query_port, $cb_user, $cb_pass, $query2);

if ($result2 && isset($result2['results'])) {
    echo "Found documents:\n";
    echo json_encode($result2['results'], JSON_PRETTY_PRINT) . "\n";
}

?>
