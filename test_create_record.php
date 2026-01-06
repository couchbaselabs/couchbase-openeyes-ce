<?php
// Set up Yii
$yiiPath = __DIR__ . '/protected/yiic.php';
require_once $yiiPath;

// This will boot up Yii
$app = Yii::createWebApplication(__DIR__ . '/protected/config/main.php');

// Import the model
Yii::import('application.modules.OphCiExamination.models.SystemicSurgerySet');
Yii::import('application.modules.OphCiExamination.models.SystemicSurgerySetEntry');

// Create a test record
$model = new SystemicSurgerySet();
$model->name = 'Test Systemic Surgery Set';
$model->institution_id = 1;

// Try to save the model
if ($model->save()) {
    echo "Record created successfully with ID: " . $model->id . "\n";
    
    // Create an entry
    $entry = new SystemicSurgerySetEntry();
    $entry->set_id = $model->id;
    $entry->operation = 'Heart bypass';
    
    if ($entry->save()) {
        echo "Entry created successfully\n";
    } else {
        echo "Failed to create entry: " . print_r($entry->getErrors(), true) . "\n";
    }
} else {
    echo "Failed to create record: " . print_r($model->getErrors(), true) . "\n";
}
