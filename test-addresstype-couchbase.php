<?php
/**
 * Test script for AddressType Couchbase functionality
 */

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set the base path
$basePath = dirname(__FILE__);
$config = require_once $basePath . '/protected/config/main.php';
$config['components']['log']['routes'] = array();

// Create the application instance
$app = Yii::createWebApplication($config);

// Test 1: Check if AddressType model exists and has trait
echo "=== AddressType Couchbase Verification ===\n\n";

$addressType = new AddressType();
echo "1. Model class exists: YES\n";

// Test 2: Check for trait
$traits = class_uses(AddressType::class) ?: [];
$allTraits = [];
$class = AddressType::class;
while ($class) {
    $allTraits = array_merge($allTraits, class_uses($class) ?: []);
    $class = get_parent_class($class);
}
echo "2. Has CouchbaseModelBridge trait: " . (in_array('OE\\Models\\Traits\\CouchbaseModelBridge', $allTraits) ? "YES" : "NO") . "\n";

// Test 3: Check for methods
echo "3. Has couchbaseScope method: " . (method_exists($addressType, 'couchbaseScope') ? "YES" : "NO") . "\n";
echo "4. Has couchbaseCollection method: " . (method_exists($addressType, 'couchbaseCollection') ? "YES" : "NO") . "\n";
echo "5. Has afterSave method: " . (method_exists($addressType, 'afterSave') ? "YES" : "NO") . "\n";
echo "6. Has afterDelete method: " . (method_exists($addressType, 'afterDelete') ? "YES" : "NO") . "\n";

// Test 4: Check returned values
echo "7. couchbaseScope returns: " . $addressType->couchbaseScope() . "\n";
echo "8. couchbaseCollection returns: " . $addressType->couchbaseCollection() . "\n";

// Test 5: Verify table name
echo "9. tableName returns: " . $addressType->tableName() . "\n";

// Test 6: Create a test AddressType record
echo "\n=== Creating Test Record ===\n";
$testAddressType = new AddressType();
$testAddressType->name = 'Test Address Type ' . time();
echo "Created AddressType with name: " . $testAddressType->name . "\n";

// Check if Couchbase is enabled
echo "\n=== Couchbase Configuration ===\n";
echo "Dual-write enabled: " . (Yii::app()->params['enable_dual_write'] ? "YES" : "NO") . "\n";

if (isset(Yii::app()->couchbase)) {
    echo "Couchbase connection exists: YES\n";
    try {
        // Try to get scope mapping
        if (class_exists('\\OE\\Database\\CouchbaseAdapter')) {
            $adapter = new \OE\\Database\\CouchbaseAdapter();
            echo "CouchbaseAdapter instantiated successfully\n";
        }
    } catch (Exception $e) {
        echo "Error with CouchbaseAdapter: " . $e->getMessage() . "\n";
    }
} else {
    echo "Couchbase connection exists: NO\n";
}

echo "\n=== Verification Complete ===\n";
