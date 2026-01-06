<?php
// Force Yii to refresh the database schema completely

require_once 'index.php';

echo "=== Force Schema Refresh ===\n\n";

// Get the database connection
$db = Yii::app()->db;
$schema = $db->getSchema();

// Method 1: Clear the schema cache directly
echo "1. Clearing schema cache...\n";

// The schema keeps two properties we need to clear:
// - _tables (list of table names)
// - _tableNames (cached table names)

// Use reflection to clear private properties
$reflection = new ReflectionClass($schema);

// Try to get and clear the _tables property
if ($reflection->hasProperty('_tables')) {
    $property = $reflection->getProperty('_tables');
    $property->setAccessible(true);
    $property->setValue($schema, []);
    echo "✓ Cleared _tables\n";
}

// Try to get and clear the _tableNames property
if ($reflection->hasProperty('_tableNames')) {
    $property = $reflection->getProperty('_tableNames');
    $property->setAccessible(true);
    $property->setValue($schema, null);
    echo "✓ Cleared _tableNames\n";
}

// Method 2: Call refresh on the schema
echo "\n2. Calling schema refresh...\n";
try {
    $schema->refresh();
    echo "✓ Schema refresh called\n";
} catch (\Exception $e) {
    echo "✗ Error during refresh: " . $e->getMessage() . "\n";
}

// Method 3: Check the result
echo "\n3. Verifying schema was refreshed...\n";
$tables = $schema->getTableNames();
if (in_array('ophindnaextraction_dnatests_investigator', $tables)) {
    echo "✓ Table found in schema after refresh!\n";
    
    // Try getting the table info
    $tableInfo = $schema->getTable('ophindnaextraction_dnatests_investigator');
    if ($tableInfo !== null) {
        echo "✓ Table information retrieved successfully!\n";
        echo "  - Table name: " . $tableInfo->name . "\n";
        echo "  - Number of columns: " . count($tableInfo->columns) . "\n";
    } else {
        echo "✗ Table info still null\n";
    }
} else {
    echo "✗ Table still not found after refresh\n";
    echo "  First 10 tables: " . implode(", ", array_slice($tables, 0, 10)) . "\n";
}

// Method 4: Try querying with the model again
echo "\n4. Testing model after refresh...\n";
try {
    // Create a fresh instance to avoid any cached metadata
    $model = OphInDnaextraction_DnaTests_Investigator::model();
    $count = $model->count();
    echo "✓ Model count successful: $count records\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== End Schema Refresh ===\n";
