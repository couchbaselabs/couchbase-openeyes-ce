<?php
// Direct Couchbase REST API insertion
$host = 'host.docker.internal';
$port = 8091;
$bucket = 'openeyes';
$username = 'Administrator';
$password = 'password';

// Create the document
$doc_id = 'unique_codes::1';
$doc_data = json_encode([
    'code' => 'TEST-CODE-001',
    'active' => 1,
    'id' => 1
]);

// Try using the Search API endpoint (alternative)
$url = "http://{$host}:{$port}/api/v1/b/{$bucket}/scopes/reference/collections/unique_codes/docs/{$doc_id}";

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($curl, CURLOPT_USERPWD, "{$username}:{$password}");
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($curl, CURLOPT_POSTFIELDS, $doc_data);
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_VERBOSE, true);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);

curl_close($curl);

echo "HTTP Status: {$http_code}<br>";
echo "Response: {$response}<br>";
if ($error) {
    echo "Error: {$error}<br>";
}
?>
