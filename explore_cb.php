<?php
// Explore Couchbase to find working endpoints

$cb_host = getenv('COUCHBASE_HOST') ?: 'host.docker.internal';
$cb_user = getenv('COUCHBASE_USER') ?: 'Administrator';
$cb_pass = getenv('COUCHBASE_PASSWORD') ?: 'password';

echo "Exploring Couchbase at $cb_host\n\n";

// Test different endpoints
$endpoints = array(
    array('port' => 8091, 'path' => '/pools', 'desc' => 'Cluster'),
    array('port' => 8091, 'path' => '/pools/default/buckets', 'desc' => 'Buckets'),
    array('port' => 8093, 'path' => '/query/service', 'desc' => 'N1QL Query'),
    array('port' => 8094, 'path' => '/api/v1', 'desc' => 'Analytics'),
    array('port' => 8095, 'path' => '/', 'desc' => 'Search'),
    array('port' => 8092, 'path' => '/', 'desc' => 'KV REST'),
);

foreach ($endpoints as $endpoint) {
    $url = "http://$cb_host:{$endpoint['port']}{$endpoint['path']}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $status = ($http_code >= 200 && $http_code < 400) ? '✓' : '✗';
    echo "$status Port {$endpoint['port']}/{$endpoint['desc']}: HTTP $http_code\n";
    if ($error) echo "    Error: $error\n";
}

echo "\n\nTrying full bucket list:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://$cb_host:8091/pools/default/buckets");
curl_setopt($ch, CURLOPT_USERPWD, "$cb_user:$cb_pass");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    if ($data) {
        foreach ($data as $bucket) {
            echo "Bucket: {$bucket['name']}\n";
            echo "  - UUID: {$bucket['uuid']}\n";
            echo "  - RAM: {$bucket['basicStats']['memUsed']} bytes\n";
            echo "  - Scopes: " . (isset($bucket['scopes']) ? count($bucket['scopes']) : 'N/A') . "\n";
        }
    }
}

?>
