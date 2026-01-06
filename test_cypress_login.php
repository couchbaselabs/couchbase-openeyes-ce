<?php
// Try to login using CypressHelper endpoint

echo "Testing CypressHelper login endpoint...\n\n";

// First, let's create a user using the createUser endpoint
echo "Step 1: Creating admin user via CypressHelper...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:7777/CypressHelper/Default/createUser");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
    'authitems' => 'OprnLogin,Admin',
    'username' => 'admin',
    'password' => 'admin',
)));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n\n";

// Now try to login
echo "Step 2: Logging in via CypressHelper...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:7777/CypressHelper/Default/login");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(array(
    'username' => 'admin',
    'password' => 'admin',
    'site_id' => 1,
    'institution_id' => 1,
)));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/cookies.txt');

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n\n";

if ($http_code === 200) {
    echo "✓ Login successful!\n";
    echo "\nYou can now test the pages with the admin user.\n";
} else {
    echo "✗ Login failed\n";
}

?>
