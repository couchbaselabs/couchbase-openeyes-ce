<?php
// Direct test of RequestRoutine model with Couchbase
define('STDIN', fopen("php://stdin", "r"));
require_once dirname(__FILE__) . '/protected/config/main.php';

// Test if model can load
echo "Testing RequestRoutine model...\n";

$model = RequestRoutine::model();
echo "Model class: " . get_class($model) . "\n";
echo "Table name: " . $model->tableName() . "\n";

// Check trait
$reflection = new ReflectionClass($model);
$traits = $reflection->getTraits();
echo "Traits: " . implode(", ", array_keys($traits)) . "\n";

// Try to find the record
echo "Trying to find record with ID 1000...\n";
$record = RequestRoutine::model()->findByPk(1000);

if ($record) {
    echo "Found! ID: " . $record->id . "\n";
    echo "Status: " . $record->status . "\n";
} else {
    echo "Not found!\n";
    echo "shouldUseCouchbase: " . ($model->shouldUseCouchbase() ? 'true' : 'false') . "\n";
    echo "isMariaDbUnavailable: " . ($model->isMariaDbUnavailable() ? 'true' : 'false') . "\n";
}
?>
