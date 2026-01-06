<?php
// Set cookies from browser session
$cookies = [];
$url = 'http://localhost:7777/OphCiExamination/risksAdmin/search';

// Make an AJAX request without term parameter to trigger the bug
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Requested-With: XMLHttpRequest'
]);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=fb7149b313717a16cf4af40630c38243');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . substr($response, 0, 500) . "\n";
?>
