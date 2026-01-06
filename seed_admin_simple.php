<?php
// Simple script to seed admin user into Couchbase

$cb_host = getenv('COUCHBASE_HOST') ?: 'host.docker.internal';
$cb_port = getenv('COUCHBASE_PORT') ?: '8091';
$cb_user = getenv('COUCHBASE_USER') ?: 'Administrator';
$cb_pass = getenv('COUCHBASE_PASSWORD') ?: 'password';
$cb_bucket = getenv('COUCHBASE_BUCKET') ?: 'openeyes';
$cb_query_port = 8093;

echo "Seeding admin user to Couchbase at $cb_host:$cb_port\n";
echo "Bucket: $cb_bucket\n\n";

function executeN1QL($query, $cb_host, $cb_query_port, $cb_user, $cb_pass) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://$cb_host:$cb_query_port/query");
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
    
    if ($http_code !== 200) {
        echo "Query failed with HTTP Code: $http_code\n";
        echo "Error: $error\n";
        echo "Response: $response\n";
        return null;
    }
    
    return json_decode($response, true);
}

// Step 1: Create admin institution
echo "Step 1: Creating admin institution...\n";
$inst_doc = array(
    'id' => 1,
    'name' => 'Test Institution',
    'remote_id' => 'TEST',
    'short_name' => 'Test',
    'active' => true,
    '_type' => 'institution',
    '_mysql_id' => 1,
);

$inst_key = 'admin::institution::1';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$cb_host:8092/$cb_bucket/$inst_key");
curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($inst_doc));
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 201 || $http_code === 200) {
    echo "✓ Institution created\n";
} else {
    echo "✗ Failed to create institution (HTTP $http_code)\n";
}

// Step 2: Create user authentication method
echo "\nStep 2: Creating user authentication method...\n";
$method_doc = array(
    'code' => 'LOCAL',
    '_type' => 'user_authentication_method',
);

$method_key = 'admin::user_authentication_method::LOCAL';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$cb_host:8092/$cb_bucket/$method_key");
curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($method_doc));
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 201 || $http_code === 200) {
    echo "✓ User authentication method created\n";
} else {
    echo "✗ Failed to create method (HTTP $http_code)\n";
}

// Step 3: Create institution authentication
echo "\nStep 3: Creating institution authentication...\n";
$inst_auth_doc = array(
    'id' => 1,
    'institution_id' => 1,
    'user_authentication_method' => 'LOCAL',
    'active' => true,
    '_type' => 'institution_authentication',
    '_mysql_id' => 1,
);

$inst_auth_key = 'admin::institution_authentication::1';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$cb_host:8092/$cb_bucket/$inst_auth_key");
curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($inst_auth_doc));
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 201 || $http_code === 200) {
    echo "✓ Institution authentication created\n";
} else {
    echo "✗ Failed to create institution authentication (HTTP $http_code)\n";
}

// Step 4: Create user
echo "\nStep 4: Creating user...\n";
$user_doc = array(
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

$user_key = 'admin::user::1';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$cb_host:8092/$cb_bucket/$user_key");
curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($user_doc));
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 201 || $http_code === 200) {
    echo "✓ User created\n";
} else {
    echo "✗ Failed to create user (HTTP $http_code)\n";
}

// Step 5: Create user authentication with password hash
echo "\nStep 5: Creating user authentication...\n";
$password_hash = password_hash('admin', PASSWORD_DEFAULT);
$user_auth_doc = array(
    'id' => 1,
    'user_id' => 1,
    'username' => 'admin',
    'password' => $password_hash,
    'institution_authentication_id' => 1,
    'active' => true,
    '_type' => 'user_authentication',
    '_mysql_id' => 1,
);

$user_auth_key = 'admin::user_authentication::1';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$cb_host:8092/$cb_bucket/$user_auth_key");
curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($user_auth_doc));
curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 201 || $http_code === 200) {
    echo "✓ User authentication created\n";
} else {
    echo "✗ Failed to create user authentication (HTTP $http_code)\n";
}

echo "\n✓ Admin user seeded successfully!\n";
echo "You can now login with:\n";
echo "  Username: admin\n";
echo "  Password: admin\n";

?>
