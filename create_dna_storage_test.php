<?php
// Create a test DNA extraction storage record

define('YII_DEBUG', true);
require_once('/var/www/openeyes/index.php');

try {
    // Create a storage instance
    $storage = new OphInDnaextraction_DnaExtraction_Storage();
    $storage->box_id = 1; // Use ID 1
    $storage->letter = '1'; // Use numeric value instead of letter
    $storage->number = 1;
    $storage->display_order = 1;
    
    // Try to save
    if ($storage->save()) {
        echo "Storage record created successfully with ID: " . $storage->id . "\n";
        echo "Box ID: " . $storage->box_id . "\n";
        echo "Letter: " . $storage->letter . "\n";
        echo "Number: " . $storage->number . "\n";
    } else {
        echo "Failed to save storage record\n";
        echo "Errors:\n";
        print_r($storage->getErrors());
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
