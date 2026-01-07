<?php
// Quick script to create test queue sets
chdir(dirname(__FILE__));
require_once('protected/config/core/common.php');
require_once('protected/config/core/main.php');

// Set up Yii app
$app = Yii::createWebApplication('protected/config/main.php');

// Import models
Yii::import('application.modules.PatientTicketing.models.*');

// Create a test Queue first (since Queue Set needs an initial queue)
$queue = new OEModule\PatientTicketing\models\Queue();
$queue->name = 'Initial Test Queue';
$queue->description = 'Test initial queue for verification';
$queue->is_initial = true;

if ($queue->save()) {
    echo "Queue created with ID: " . $queue->id . "\n";
    
    // Now create the Queue Set
    $queueset = new OEModule\PatientTicketing\models\QueueSet();
    $queueset->name = 'Test Queue Set 001';
    $queueset->description = 'Test queue set for page verification';
    
    // Get the first category or create one
    $category = OEModule\PatientTicketing\models\QueueSetCategory::model()->find();
    if (!$category) {
        $category = new OEModule\PatientTicketing\models\QueueSetCategory();
        $category->name = 'General';
        $category->save();
        echo "Created category with ID: " . $category->id . "\n";
    }
    
    $queueset->category_id = $category->id;
    $queueset->initial_queue_id = $queue->id;
    $queueset->allow_null_priority = '1';
    $queueset->summary_link = '1';
    $queueset->filter_priority = '1';
    $queueset->filter_subspecialty = '1';
    
    if ($queueset->save()) {
        echo "Queue Set created with ID: " . $queueset->id . "\n";
        echo "Success! Test data created.\n";
    } else {
        echo "Failed to create Queue Set\n";
        var_dump($queueset->getErrors());
    }
} else {
    echo "Failed to create Queue\n";
    var_dump($queue->getErrors());
}
?>
