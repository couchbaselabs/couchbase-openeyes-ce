<?php
// Find a consent event
require_once('protected/config/main.php');
$config = require('protected/config/main.php');
$app = Yii::createConsoleApplication($config);

// Get database connection
$connection = Yii::app()->db;

// Find a consent event
$query = "SELECT e.id, e.episode_id, et.class FROM event e 
          JOIN event_type et ON e.event_type_id = et.id 
          WHERE et.class = 'OphTrConsent' 
          LIMIT 1";

try {
    $result = $connection->createCommand($query)->queryRow();
    if ($result) {
        echo "Event ID: " . $result['id'] . "\n";
        echo "Episode ID: " . $result['episode_id'] . "\n";
        echo "Event Type: " . $result['class'] . "\n";
    } else {
        echo "No consent events found. Looking for any event...\n";
        $query2 = "SELECT e.id, e.episode_id, et.class FROM event e 
                   JOIN event_type et ON e.event_type_id = et.id 
                   LIMIT 1";
        $result2 = $connection->createCommand($query2)->queryRow();
        if ($result2) {
            echo "First Event ID: " . $result2['id'] . "\n";
            echo "Episode ID: " . $result2['episode_id'] . "\n";
            echo "Event Type: " . $result2['class'] . "\n";
        } else {
            echo "No events found at all.\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
