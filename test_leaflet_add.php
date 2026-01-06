<?php
// Test script to create a leaflet and test the add functionality
require_once dirname(__FILE__) . '/index.php';

// Get database connection
$db = Yii::app()->db;

// First, check if there are any leaflets
$count = $db->createCommand('SELECT COUNT(*) FROM ophtrconsent_leaflet')->queryScalar();
echo "Current leaflets: $count\n";

// Create a test leaflet if none exist
if ($count == 0) {
    $now = date('Y-m-d H:i:s');
    $db->createCommand("
        INSERT INTO ophtrconsent_leaflet 
        (name, display_order, last_modified_user_id, last_modified_date, created_user_id, created_date) 
        VALUES ('Test Leaflet 1', 1, 1, '{$now}', 1, '{$now}')
    ")->execute();
    echo "Created test leaflet\n";
}

// Get the first leaflet
$leaflet = Yii::app()->db->createCommand('SELECT id FROM ophtrconsent_leaflet LIMIT 1')->queryRow();
echo "First leaflet ID: " . $leaflet['id'] . "\n";

// Check if there are any firms
$firmCount = $db->createCommand('SELECT COUNT(*) FROM firm')->queryScalar();
echo "Current firms: $firmCount\n";

// Get the first firm
$firm = Yii::app()->db->createCommand('SELECT id FROM firm LIMIT 1')->queryRow();
if ($firm) {
    echo "First firm ID: " . $firm['id'] . "\n";
} else {
    echo "No firms found!\n";
}

// Check if the mapping already exists
if ($leaflet && $firm) {
    $mapping = $db->createCommand(
        'SELECT id FROM ophtrconsent_leaflet_firm WHERE leaflet_id = :leaflet_id AND firm_id = :firm_id'
    )->bindValues([':leaflet_id' => $leaflet['id'], ':firm_id' => $firm['id']])->queryRow();
    
    if ($mapping) {
        echo "Mapping already exists: " . $mapping['id'] . "\n";
    } else {
        echo "No existing mapping\n";
    }
}
?>
