<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=openeyes', 'openeyes', 'openeyes');

$result = $pdo->query('SELECT id FROM patient LIMIT 1');
$patient = $result->fetch(PDO::FETCH_ASSOC);
echo 'Patient ID: ' . $patient['id'] . PHP_EOL;

$result = $pdo->query('SELECT id FROM ophcotherapya_treatment LIMIT 1');
$treatment = $result->fetch(PDO::FETCH_ASSOC);
if ($treatment) {
    echo 'Treatment ID: ' . $treatment['id'] . PHP_EOL;
} else {
    echo 'No treatment found' . PHP_EOL;
    // Try to find any treatment
    $result = $pdo->query('SELECT COUNT(*) as cnt FROM ophcotherapya_treatment');
    $count = $result->fetch(PDO::FETCH_ASSOC);
    echo 'Total treatments in DB: ' . $count['cnt'] . PHP_EOL;
}
?>
