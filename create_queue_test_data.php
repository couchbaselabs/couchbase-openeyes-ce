<?php
// Set up environment variables for CLI
$_SERVER['HTTP_HOST'] = 'localhost:7777';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '7777';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

// Initialize Yii application
require_once(__DIR__ . '/index.php');

try {
    // First try to find an existing queue
    $queue = OEModule\PatientTicketing\models\Queue::model()->find();
    if ($queue) {
        echo "Found existing queue ID: " . $queue->id . PHP_EOL;
        exit(0);
    }
    
    // If no queue exists, create a queue set with initial queue
    echo "No queues found. Creating queue set with initial queue..." . PHP_EOL;
    
    $queueset = new OEModule\PatientTicketing\models\QueueSet();
    $queueset->name = 'Test Queue Set';
    $queueset->description = 'Test queue set for verification';
    
    // Set default category if available
    $category = OEModule\PatientTicketing\models\QueueSetCategory::model()->find();
    if ($category) {
        $queueset->category_id = $category->id;
    }
    
    $queueset->allow_null_priority = 1;
    $queueset->link_tickets_to_episode = 0;
    
    if ($queueset->save()) {
        echo "Queue Set created with ID: " . $queueset->id . PHP_EOL;
        
        // Now create initial queue
        $queue = new OEModule\PatientTicketing\models\Queue();
        $queue->name = 'Test Queue';
        $queue->description = 'Test queue';
        $queue->queueset_id = $queueset->id;
        $queue->is_initial = 1;
        
        if ($queue->save()) {
            echo "Queue created with ID: " . $queue->id . PHP_EOL;
            exit(0);
        } else {
            echo "Error saving queue: ";
            print_r($queue->getErrors());
            exit(1);
        }
    } else {
        echo "Error saving queue set: ";
        print_r($queueset->getErrors());
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
?>
