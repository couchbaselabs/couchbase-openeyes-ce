<?php
// Seed admin user directly using Couchbase SDK REST API

$cb_host = getenv('COUCHBASE_HOST') ?: 'host.docker.internal';
$cb_port = getenv('COUCHBASE_PORT') ?: '8091';
$cb_user = getenv('COUCHBASE_USER') ?: 'Administrator';
$cb_pass = getenv('COUCHBASE_PASSWORD') ?: 'password';
$cb_bucket = getenv('COUCHBASE_BUCKET') ?: 'openeyes';

echo "Seeding admin user to Couchbase\n";
echo "Host: $cb_host:$cb_port\n";
echo "Bucket: $cb_bucket\n\n";

function upsertDoc($host, $port, $bucket, $user, $pass, $key, $doc) {
    $url = "http://$host:$port/$bucket/$key";
    $headers = array(
        "Content-Type: application/json",
        "Authorization: Basic " . base64_encode("$user:$pass")
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($doc));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return array('code' => $http_code, 'response' => $response);
}

$password_hash = password_hash('admin', PASSWORD_DEFAULT);

// Step 1: Insert institution
echo "Step 1: Inserting institution...\n";
$doc = array(
    'id' => 1,
    'name' => 'Test Institution',
    'remote_id' => 'TEST',
    'short_name' => 'Test',
    'active' => true,
    '_type' => 'institution',
    '_mysql_id' => 1,
);
$result = upsertDoc($cb_host, 8092, $cb_bucket, $cb_user, $cb_pass, 'admin::institution::1', $doc);
if ($result['code'] === 201 || $result['code'] === 200) {
    echo "✓ Institution inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    echo "Response: " . substr($result['response'], 0, 200) . "\n";
}

// Step 2: Insert user authentication method
echo "\nStep 2: Inserting user authentication method...\n";
$doc = array(
    'code' => 'LOCAL',
    '_type' => 'user_authentication_method',
);
$result = upsertDoc($cb_host, 8092, $cb_bucket, $cb_user, $cb_pass, 'admin::user_authentication_method::LOCAL', $doc);
if ($result['code'] === 201 || $result['code'] === 200) {
    echo "✓ User authentication method inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    echo "Response: " . substr($result['response'], 0, 200) . "\n";
}

// Step 3: Insert institution authentication
echo "\nStep 3: Inserting institution authentication...\n";
$doc = array(
    'id' => 1,
    'institution_id' => 1,
    'user_authentication_method' => 'LOCAL',
    'active' => true,
    '_type' => 'institution_authentication',
    '_mysql_id' => 1,
);
$result = upsertDoc($cb_host, 8092, $cb_bucket, $cb_user, $cb_pass, 'admin::institution_authentication::1', $doc);
if ($result['code'] === 201 || $result['code'] === 200) {
    echo "✓ Institution authentication inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    echo "Response: " . substr($result['response'], 0, 200) . "\n";
}

// Step 4: Insert user
echo "\nStep 4: Inserting user...\n";
$doc = array(
    'id' => 1,
    'username' => 'admin',
    'first_name' => 'Admin',
    'last_name' => 'User',
    'email' => 'admin@test.com',
    'active' => true,
    'global_firm_rights' => true,
    '_type' => 'user',
    '_mysql_id' => 1,
);
$result = upsertDoc($cb_host, 8092, $cb_bucket, $cb_user, $cb_pass, 'admin::user::1', $doc);
if ($result['code'] === 201 || $result['code'] === 200) {
    echo "✓ User inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    echo "Response: " . substr($result['response'], 0, 200) . "\n";
}

// Step 5: Insert user authentication with hashed password
echo "\nStep 5: Inserting user authentication...\n";
$doc = array(
    'id' => 1,
    'user_id' => 1,
    'username' => 'admin',
    'password' => $password_hash,
    'institution_authentication_id' => 1,
    'active' => true,
    '_type' => 'user_authentication',
    '_mysql_id' => 1,
);
$result = upsertDoc($cb_host, 8092, $cb_bucket, $cb_user, $cb_pass, 'admin::user_authentication::1', $doc);
if ($result['code'] === 201 || $result['code'] === 200) {
    echo "✓ User authentication inserted\n";
} else {
    echo "✗ Failed (HTTP {$result['code']})\n";
    echo "Response: " . substr($result['response'], 0, 200) . "\n";
}

echo "\n===================================\n";
echo "✓ Admin user seeded successfully!\n";
echo "===================================\n";
echo "You can now login with:\n";
echo "  Username: admin\n";
echo "  Password: admin\n";

?>
