<?php
require_once 'index.php';

// List all event types
$sql = "SELECT id, class_name, name FROM event_type";
$results = Yii::app()->db->createCommand($sql)->queryAll();

echo "Found " . count($results) . " event types:\n";
foreach ($results as $row) {
    echo "ID: " . $row['id'] . ", Class: " . $row['class_name'] . ", Name: " . $row['name'] . "\n";
}

// Check specifically for OphCoTherapyapplication
$sql2 = "SELECT * FROM event_type WHERE class_name LIKE '%Therapy%'";
$results2 = Yii::app()->db->createCommand($sql2)->queryAll();

if (count($results2) > 0) {
    echo "\nTherapy-related event types found:\n";
    foreach ($results2 as $row) {
        echo "ID: " . $row['id'] . ", Class: " . $row['class_name'] . ", Name: " . $row['name'] . "\n";
    }
} else {
    echo "\nNo Therapy-related event types found\n";
}
?>
