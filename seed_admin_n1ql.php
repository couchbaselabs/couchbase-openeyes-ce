<?php
// Seed admin user using N1QL INSERT

$cb_host = getenv('COUCHBASE_HOST') ?: 'host.docker.internal';
$cb_port = 8091;
$cb_user = getenv('COUCHBASE_USER') ?: 'Administrator';
$cb_pass = getenv('COUCHBASE_PASSWORD') ?: 'password';
$cb_bucket = getenv('COUCHBASE_BUCKET') ?: 'openeyes';
$cb_query_port = 8093;

echo "Seeding admin user to Couchbase\n";
echo "Host: $cb_host:$cb_port\n";
echo "Bucket: $cb_bucket\n\n";

function executeN1QL($query, $cb_host, $cb_query_port, $cb_user, $cb_pass) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://$cb_host:$cb_query_port/query/service");
    curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'statement=' . urlencode($query));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return array(
        'code' => $http_code,
        'response' => $response,
        'error' => $error
    );
}

// Step 1: Insert institution
echo "Step 1: Inserting institution...\n";
$password_hash = password_hash('admin', PASSWORD_DEFAULT);
$query = "INSERT INTO `$cb_bucket` (KEY \"admin::institution::1\", VALUE {
    \"id\": 1,
    \"name\": \"Test Institution\",
    \"remote_id\": \"TEST\",
    \"short_name\": \"Test\",
    \"active\": true,
    \"_type\": \"institution\",
    \"_mysql_id\": 1
})";

$result = executeN1QL($query, $cb_host, $cb_query_port, $cb_user, $cb_pass);
if ($result['code'] === 200) {
    echo "✓ Institution inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    if ($result['error']) echo "Error: {$result['error']}\n";
    // Try to parse response for error details
    $data = json_decode($result['response'], true);
    if ($data && isset($data['errors'])) {
        foreach ($data['errors'] as $err) {
            echo "  " . $err['msg'] . "\n";
        }
    }
}

// Step 2: Insert user authentication method
echo "\nStep 2: Inserting user authentication method...\n";
$query = "INSERT INTO `$cb_bucket` (KEY \"admin::user_authentication_method::LOCAL\", VALUE {
    \"code\": \"LOCAL\",
    \"_type\": \"user_authentication_method\"
})";

$result = executeN1QL($query, $cb_host, $cb_query_port, $cb_user, $cb_pass);
if ($result['code'] === 200) {
    echo "✓ User authentication method inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    if ($result['error']) echo "Error: {$result['error']}\n";
}

// Step 3: Insert institution authentication
echo "\nStep 3: Inserting institution authentication...\n";
$query = "INSERT INTO `$cb_bucket` (KEY \"admin::institution_authentication::1\", VALUE {
    \"id\": 1,
    \"institution_id\": 1,
    \"user_authentication_method\": \"LOCAL\",
    \"active\": true,
    \"_type\": \"institution_authentication\",
    \"_mysql_id\": 1
})";

$result = executeN1QL($query, $cb_host, $cb_query_port, $cb_user, $cb_pass);
if ($result['code'] === 200) {
    echo "✓ Institution authentication inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    if ($result['error']) echo "Error: {$result['error']}\n";
}

// Step 4: Insert user
echo "\nStep 4: Inserting user...\n";
$query = "INSERT INTO `$cb_bucket` (KEY \"admin::user::1\", VALUE {
    \"id\": 1,
    \"username\": \"admin\",
    \"first_name\": \"Admin\",
    \"last_name\": \"User\",
    \"email\": \"admin@test.com\",
    \"active\": true,
    \"global_firm_rights\": true,
    \"_type\": \"user\",
    \"_mysql_id\": 1
})";

$result = executeN1QL($query, $cb_host, $cb_query_port, $cb_user, $cb_pass);
if ($result['code'] === 200) {
    echo "✓ User inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    if ($result['error']) echo "Error: {$result['error']}\n";
}

// Step 5: Insert user authentication with hashed password
echo "\nStep 5: Inserting user authentication...\n";
$query = "INSERT INTO `$cb_bucket` (KEY \"admin::user_authentication::1\", VALUE {
    \"id\": 1,
    \"user_id\": 1,
    \"username\": \"admin\",
    \"password\": \"" . str_replace('"', '\\"', $password_hash) . "\",
    \"institution_authentication_id\": 1,
    \"active\": true,
    \"_type\": \"user_authentication\",
    \"_mysql_id\": 1
})";

$result = executeN1QL($query, $cb_host, $cb_query_port, $cb_user, $cb_pass);
if ($result['code'] === 200) {
    echo "✓ User authentication inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    if ($result['error']) echo "Error: {$result['error']}\n";
    // Try to parse response for error details
    $data = json_decode($result['response'], true);
    if ($data && isset($data['errors'])) {
        foreach ($data['errors'] as $err) {
            echo "  " . $err['msg'] . "\n";
        }
    }
}

echo "\n===================================\n";
echo "✓ Admin user seeded successfully!\n";
echo "===================================\n";
echo "You can now login with:\n";
echo "  Username: admin\n";
echo "  Password: admin\n";

?>
