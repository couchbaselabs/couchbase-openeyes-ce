<?php
// Check if the investigator table exists and diagnostic information

require_once 'index.php';

echo "=== DNA Investigator Table Status ===\n\n";

$db = Yii::app()->db;

// Direct SQL check
echo "1. Direct SQL Check:\n";
try {
    $result = $db->createCommand("SELECT 1 FROM `ophindnaextraction_dnatests_investigator` LIMIT 1")->execute();
    echo "✓ Table exists and can be queried\n";
} catch (\Exception $e) {
    echo "✗ Cannot query table: " . $e->getMessage() . "\n";
}

// Check database schema
echo "\n2. Yii Schema Check:\n";
$schema = $db->getSchema();
$tables = $schema->getTableNames();
if (in_array('ophindnaextraction_dnatests_investigator', $tables)) {
    echo "✓ Table found in Yii schema\n";
} else {
    echo "✗ Table NOT found in Yii schema\n";
    echo "Available tables: " . implode(", ", array_slice($tables, 0, 5)) . "...\n";
}

// Check table info
echo "\n3. Table Information:\n";
try {
    $tableInfo = $schema->getTable('ophindnaextraction_dnatests_investigator');
    if ($tableInfo === null) {
        echo "✗ Table info is NULL\n";
    } else {
        echo "✓ Table info retrieved\n";
        echo "  - Columns: " . count($tableInfo->columns) . "\n";
        echo "  - Column names: " . implode(", ", array_keys($tableInfo->columns)) . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Error getting table info: " . $e->getMessage() . "\n";
}

// Try loading the model
echo "\n4. Model Loading:\n";
try {
    $model = OphInDnaextraction_DnaTests_Investigator::model();
    echo "✓ Model loaded successfully\n";
    
    // Try to count records
    echo "\n5. Model Query Test:\n";
    $count = $model->count();
    echo "✓ Count: $count records\n";
} catch (\Exception $e) {
    echo "✗ Error with model: " . $e->getMessage() . "\n";
}

echo "\n=== End Diagnostic ===\n";
