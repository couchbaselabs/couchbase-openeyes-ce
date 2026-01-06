<?php
// Test HistoryMacro model save
require_once 'index.php';

echo "<pre>\n";

// Test creating and saving a HistoryMacro
echo "Creating HistoryMacro model...\n";
$model = new OEModule\OphCiExamination\models\HistoryMacro();
$model->name = "Test Macro " . time();
$model->body = "Test body content";
$model->active = 1;
$model->display_order = 999;

echo "Model created. Attributes:\n";
echo "  name: " . $model->name . "\n";
echo "  body: " . $model->body . "\n";
echo "  active: " . $model->active . "\n";
echo "  display_order: " . $model->display_order . "\n";

echo "\nValidating model...\n";
if ($model->validate()) {
    echo "  Validation: PASSED\n";
} else {
    echo "  Validation: FAILED\n";
    echo "  Errors: " . json_encode($model->getErrors()) . "\n";
}

echo "\nSaving model...\n";
$saveResult = $model->save();
echo "  Save result: " . ($saveResult ? 'TRUE' : 'FALSE') . "\n";
echo "  Model ID after save: " . (isset($model->id) ? $model->id : 'NOT SET') . "\n";
echo "  Errors after save: " . json_encode($model->getErrors()) . "\n";

if ($saveResult) {
    echo "\nVerifying insert in database...\n";
    $db = Yii::app()->db;
    $count = $db->createCommand("SELECT COUNT(*) FROM ophciexamination_history_macro WHERE name = :name")->bindParam(':name', $model->name)->queryScalar();
    echo "  Record count for name '" . $model->name . "': " . $count . "\n";
    
    if ($count > 0) {
        echo "\n✓ SUCCESS: Model was saved to database!\n";
    } else {
        echo "\n✗ FAILURE: Model save returned true but record not found in database!\n";
    }
} else {
    echo "\n✗ FAILURE: Model save returned false!\n";
}

echo "</pre>\n";
?>
