<?php
// Check database connection and query
require_once 'index.php';

try {
    // Test basic query
    $result = Yii::app()->db->createCommand("SELECT * FROM ophciexamination_history_macro LIMIT 1")->queryAll();
    echo "Database connection OK\n";
    echo "Query result: " . json_encode($result) . "\n";
    
    // Check if table exists
    $tables = Yii::app()->db->createCommand("SHOW TABLES LIKE 'ophciexamination_history_macro'")->queryAll();
    echo "Table exists: " . (count($tables) > 0 ? "YES" : "NO") . "\n";
    
    // Try to insert a test record
    $model = new OEModule\OphCiExamination\models\HistoryMacro();
    $model->name = "Test Insert" . time();
    $model->body = "Test body";
    $model->active = 1;
    $model->display_order = 1;
    
    echo "Before save - Errors: " . json_encode($model->getErrors()) . "\n";
    $saved = $model->save();
    echo "After save - Saved: " . ($saved ? "YES" : "NO") . "\n";
    echo "After save - Errors: " . json_encode($model->getErrors()) . "\n";
    if ($saved) {
        echo "Model ID: " . $model->id . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
?>
