<?php
// This script tries to create a test DNA extraction event

defined('YII_DEBUG') or define('YII_DEBUG', true);

try {
    require_once('/var/www/openeyes/index.php');
    
    // We need to use Couchbase, but for now let's just check basic connectivity
    echo "Attempting to query event type...\n";
    
    // Try using the OphInDnaextraction API
    $api = Yii::app()->moduleAPI->get('OphInDnaextraction');
    if ($api) {
        echo "OphInDnaextraction API is available\n";
    } else {
        echo "OphInDnaextraction API not found\n";
    }
    
    // Check if EventType model is available
    if (class_exists('EventType')) {
        echo "EventType class found\n";
        
        // This might not work with Couchbase, but let's try
        try {
            $eventType = EventType::model()->find('class_name = ?', array('OphInDnaextraction'));
            if ($eventType) {
                echo "Found OphInDnaextraction EventType with ID: " . $eventType->id . "\n";
            } else {
                echo "EventType not found in database\n";
            }
        } catch (Exception $e) {
            echo "Error querying EventType: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "Setup error: " . $e->getMessage() . "\n";
    // This is expected if Couchbase is not accessible
}
?>
