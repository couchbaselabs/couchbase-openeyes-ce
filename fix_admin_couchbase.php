<?php
/**
 * Script to fix duplicate admin user authentications in Couchbase
 */

// Get Couchbase connection details
$cb_host = getenv('COUCHBASE_HOST') ?: 'localhost';
$cb_port = getenv('COUCHBASE_PORT') ?: '8091';
$cb_user = getenv('COUCHBASE_USER') ?: 'Administrator';
$cb_pass = getenv('COUCHBASE_PASSWORD') ?: 'password';
$cb_bucket = getenv('COUCHBASE_BUCKET') ?: 'openeyes';
$cb_query_port = getenv('COUCHBASE_QUERY_PORT') ?: '8093';

echo "Connecting to Couchbase at $cb_host:$cb_port\n";
echo "Bucket: $cb_bucket\n\n";

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

// Find admin user
$query = "SELECT *, META().id as doc_id FROM `$cb_bucket` WHERE _type='user' AND username='admin' LIMIT 1";
echo "Executing: $query\n";
$result = executeN1QL($cb_host, $cb_query_port, $cb_user, $cb_pass, $query);

if (!$result || !isset($result['results']) || count($result['results']) == 0) {
    echo "ERROR: Admin user not found!\n";
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$admin_user = $result['results'][0];
$admin_id = isset($admin_user['id']) ? $admin_user['id'] : (isset($admin_user['doc_id']) ? $admin_user['doc_id'] : null);

if (!$admin_id) {
    echo "ERROR: Could not extract admin user ID!\n";
    echo "Admin user data: " . json_encode($admin_user, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

echo "Found admin user with ID: $admin_id\n\n";

// First, let's see what document types exist for user_authentication
$debug_query = "SELECT DISTINCT _type, COUNT(*) as count FROM `$cb_bucket` WHERE _type LIKE '%authentication%' GROUP BY _type";
echo "DEBUG: Checking document types...\n";
echo "Executing: $debug_query\n";
$debug_result = executeN1QL($cb_host, $cb_query_port, $cb_user, $cb_pass, $debug_query);
echo "Result: " . json_encode($debug_result, JSON_PRETTY_PRINT) . "\n\n";

// Find all user authentications for admin
$query = "SELECT *, META().id as doc_id FROM `$cb_bucket` WHERE _type='user_authentication' AND user_id='$admin_id' ORDER BY META().id";
echo "Executing: $query\n";
$result = executeN1QL($cb_host, $cb_query_port, $cb_user, $cb_pass, $query);

if (!$result || !isset($result['results'])) {
    echo "ERROR: Failed to fetch user authentications\n";
    echo "Response: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

$auths = $result['results'];
echo "Found " . count($auths) . " authentication record(s)\n";
if (count($auths) > 0) {
    echo "Response: " . json_encode($auths, JSON_PRETTY_PRINT) . "\n\n";
}

if (count($auths) > 1) {
    echo "Duplicate authentications found! Analyzing...\n";
    
    // Group by institution_authentication_id
    $grouped = [];
    foreach ($auths as $auth) {
        $inst_auth_id = $auth['institution_authentication_id'] ?? 'NULL';
        if (!isset($grouped[$inst_auth_id])) {
            $grouped[$inst_auth_id] = [];
        }
        $grouped[$inst_auth_id][] = $auth;
    }
    
    // Find and delete duplicates
    $deleted_count = 0;
    foreach ($grouped as $inst_auth_id => $auths_group) {
        if (count($auths_group) > 1) {
            echo "\nFound " . count($auths_group) . " duplicates for institution_authentication_id: $inst_auth_id\n";
            
            // Keep the first one, delete the rest
            for ($i = 1; $i < count($auths_group); $i++) {
                $auth_to_delete = $auths_group[$i];
                $auth_id = $auth_to_delete['id'];
                
                echo "Deleting auth ID: $auth_id\n";
                
                // Delete using Couchbase DELETE statement
                $delete_query = "DELETE FROM `$cb_bucket` WHERE id='$auth_id'";
                $delete_result = executeN1QL($cb_host, $cb_query_port, $cb_user, $cb_pass, $delete_query);
                
                if ($delete_result && isset($delete_result['status']) && $delete_result['status'] === 'success') {
                    echo "  ✓ Successfully deleted\n";
                    $deleted_count++;
                } else {
                    echo "  ✗ Failed to delete\n";
                }
            }
        }
    }
    
    echo "\n✓ Deleted $deleted_count duplicate records\n";
} else {
    echo "No duplicates found. Single authentication exists.\n";
}

echo "\nDone!\n";
?>
