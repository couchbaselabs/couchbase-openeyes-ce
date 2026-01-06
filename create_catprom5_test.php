<?php
// Minimal test data creation for CATPROM5

// Get the base path
$base_path = getcwd();
$yii_path = $base_path . '/yii.php';

// Try to load Yii framework
if (file_exists($base_path . '/protected/yiic.php')) {
    require_once($base_path . '/protected/yiic.php');
} elseif (file_exists($base_path . '/index.php')) {
    // Try to use the index.php approach
    $_GET['route'] = 'test';
    require_once($base_path . '/index.php');
} else {
    echo "Could not find Yii framework\n";
    exit(1);
}

// Check if we can access the database
if (!isset(Yii::app()->db)) {
    echo "Could not connect to database\n";
    exit(1);
}

$db = Yii::app()->db;

// Get event type
$sql = "SELECT id FROM event_type WHERE class LIKE '%OphOuCatprom5%' LIMIT 1";
$result = $db->createCommand($sql)->queryRow();

if ($result) {
    echo "Found OphOuCatprom5 event type with ID: " . $result['id'] . "\n";
} else {
    echo "OphOuCatprom5 event type not found\n";
}

// Try to list existing events
$sql = "SELECT id, episode_id FROM event LIMIT 5";
$rows = $db->createCommand($sql)->queryAll();
echo "First few events:\n";
foreach ($rows as $row) {
    echo "  Event ID: " . $row['id'] . ", Episode ID: " . $row['episode_id'] . "\n";
}
?>
