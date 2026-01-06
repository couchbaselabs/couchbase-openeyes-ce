<?php
/**
 * Test script for AnaestheticComplication Couchbase integration
 * Usage: php test-anaesthetic-couchbase.php
 */

// Set up Yii application
$yiiPath = __DIR__ . '/protected';
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL', 3);

require_once $yiiPath . '/../vendor/autoload.php';
require_once $yiiPath . '/../vendor/yiisoft/yii/framework/yii.php';

// Load configuration
$config = require($yiiPath . '/config/main.php');

// Create application instance
$app = Yii::createWebApplication($config);

// Now test the model
echo "=== AnaestheticComplication Couchbase Test ===\n\n";

// Test 1: Check trait
echo "Test 1: Checking CouchbaseModelBridge trait...\n";
$model = new AnaestheticComplication();
$traits = class_uses($model);
$hasTrait = isset($traits['OE\Models\Traits\CouchbaseModelBridge']);
echo "Has CouchbaseModelBridge trait: " . ($hasTrait ? "YES" : "NO") . "\n";

// Test 2: Check methods
echo "\nTest 2: Checking required methods...\n";
$hasAfterSave = method_exists($model, 'afterSave');
$hasAfterDelete = method_exists($model, 'afterDelete');
$hasCouchbaseScope = method_exists($model, 'couchbaseScope');
$hasCouchbaseCollection = method_exists($model, 'couchbaseCollection');

echo "Has afterSave: " . ($hasAfterSave ? "YES" : "NO") . "\n";
echo "Has afterDelete: " . ($hasAfterDelete ? "YES" : "NO") . "\n";
echo "Has couchbaseScope: " . ($hasCouchbaseScope ? "YES" : "NO") . "\n";
echo "Has couchbaseCollection: " . ($hasCouchbaseCollection ? "YES" : "NO") . "\n";

// Test 3: Check scope and collection
echo "\nTest 3: Checking scope and collection names...\n";
$scope = $model->couchbaseScope();
$collection = $model->couchbaseCollection();
$table = $model->tableName();

echo "Scope: $scope\n";
echo "Collection: $collection\n";
echo "Table Name: $table\n";

// Test 4: Get existing records
echo "\nTest 4: Finding existing records...\n";
$existingRecords = AnaestheticComplication::model()->findAll();
echo "Total records in database: " . count($existingRecords) . "\n";

if (!empty($existingRecords)) {
    $firstRecord = $existingRecords[0];
    echo "First record ID: " . $firstRecord->id . "\n";
    echo "First record name: " . $firstRecord->name . "\n";
    
    // Test 5: Check if record exists in Couchbase
    echo "\nTest 5: Checking Couchbase for first record...\n";
    $comparison = $firstRecord->compareWithCouchbase();
    echo "Comparison result: " . json_encode($comparison, JSON_PRETTY_PRINT) . "\n";
}

// Test 6: Create a new record
echo "\nTest 6: Creating a new AnaestheticComplication record...\n";
$newModel = new AnaestheticComplication();
$newModel->name = 'Test Complication - ' . time();
$newModel->display_order = 999;

if ($newModel->save()) {
    echo "✓ Record saved successfully!\n";
    echo "  ID: " . $newModel->id . "\n";
    echo "  Name: " . $newModel->name . "\n";
    
    // Test 7: Check if it's in Couchbase
    echo "\nTest 7: Verifying record in Couchbase...\n";
    $comparison = $newModel->compareWithCouchbase();
    echo "Comparison result: " . json_encode($comparison, JSON_PRETTY_PRINT) . "\n";
    
    // Test 8: Delete the record
    echo "\nTest 8: Deleting the test record...\n";
    if ($newModel->delete()) {
        echo "✓ Record deleted successfully!\n";
    } else {
        echo "✗ Failed to delete record\n";
    }
} else {
    echo "✗ Failed to save record\n";
    echo "Errors: " . json_encode($newModel->getErrors()) . "\n";
}

echo "\n=== Test Complete ===\n";
