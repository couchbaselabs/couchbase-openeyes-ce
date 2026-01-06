<?php
// Test script to verify the /procedure/complications endpoint

$url = 'http://localhost:7777';
$cookieFile = '/tmp/oe_cookies.txt';

// Step 1: Login
echo "Step 1: Logging in...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . '/site/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'User[username]' => 'admin',
    'User[password]' => 'admin'
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
curl_close($ch);

// Step 2: Test the /procedure/complications endpoint without ID
echo "Step 2: Testing /procedure/complications (no ID)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . '/procedure/complications');
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Status: " . $info['http_code'] . "\n";
if ($info['http_code'] >= 400) {
    echo "ERROR: Page returned " . $info['http_code'] . "\n";
    // Extract error message from response
    if (preg_match('/<h1>(.*?)<\/h1>/i', $response, $matches)) {
        echo "Error Message: " . htmlspecialchars($matches[1]) . "\n";
    }
    // Look for "Missing required parameter"
    if (strpos($response, 'Missing required parameter') !== false) {
        echo "Found: Missing required parameter error\n";
    }
    // Look for CHttpException
    if (preg_match('/CHttpException.*?(Missing required parameter.*?\$id)/i', $response, $matches)) {
        echo "Exception Details: " . htmlspecialchars($matches[1]) . "\n";
    }
} else {
    echo "SUCCESS: Page returned " . $info['http_code'] . "\n";
}

// Step 3: Test with a valid procedure ID
echo "\nStep 3: Testing /procedure/complications/1 (with ID=1)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . '/procedure/complications/1');
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Status: " . $info['http_code'] . "\n";
if ($info['http_code'] >= 400) {
    echo "ERROR: Page returned " . $info['http_code'] . "\n";
} else {
    echo "SUCCESS: Page returned " . $info['http_code'] . "\n";
}

// Clean up
unlink($cookieFile);
?>
