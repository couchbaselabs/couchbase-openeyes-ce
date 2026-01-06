<?php
// Seed admin user via CypressHelper endpoint

echo "Creating admin user via CypressHelper...\n\n";

// Step 1: Create admin user with required auth items
echo "Step 1: Creating admin user...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:7777/CypressHelper/Default/createUser");
curl_setopt($ch, CURLOPT_POST, true);
$post_data = array(
    'username' => 'admin',
    'password' => 'admin',
    'institution_id' => 1,
    'authitems' => 'OprnLogin,Admin,OprnViewClinical',
    'attributes[first_name]' => 'Admin',
    'attributes[last_name]' => 'User',
    'attributes[email]' => 'admin@test.com',
);

// Build the POST data as form data
$post_string = http_build_query($post_data);

curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_VERBOSE, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Content-Type: application/x-www-form-urlencoded",
    "Content-Length: " . strlen($post_string)
));

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
if ($error) echo "Error: $error\n";
echo "Response: $response\n\n";

if ($http_code === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['user_id'])) {
        echo "✓ User created successfully!\n";
        echo "  User ID: " . $data['user_id'] . "\n";
        echo "  Username: " . ($data['username'] ?? 'admin') . "\n";
        echo "  Password: " . ($data['password'] ?? 'admin') . "\n\n";
        
        echo "You can now login with:\n";
        echo "  Username: " . ($data['username'] ?? 'admin') . "\n";
        echo "  Password: " . ($data['password'] ?? 'admin') . "\n";
    } else {
        echo "✗ Unexpected response format\n";
    }
} else {
    echo "✗ Failed to create user\n";
}

?>
