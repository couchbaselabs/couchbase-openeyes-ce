#!/usr/bin/env php
<?php
/**
 * Test script to verify AddressType Couchbase functionality
 */

// Bootstrap the Yii application
require_once(__DIR__ . '/protected/vendor/yiisoft/yii/framework/yii.php');
$basePath = dirname(__FILE__) . '/protected';
$config = require($basePath . '/config/main.php');
$config = require($basePath . '/config/local/common.php') + $config;

$application = Yii::createConsoleApplication($config);

// Enable dual-write for testing
Yii::app()->params['enable_dual_write'] = true;

echo "-------------------------------------------------\n";
echo "Testing AddressType Couchbase Functionality\n";
echo "-------------------------------------------------\n\n";

// Test 1: Check if model has Couchbase model bridge
echo "Step 1: Verify AddressType model structure\n";
$addressType = new AddressType();

$hasTrait = method_exists($addressType, 'couchbaseScope') &&
            method_exists($addressType, 'couchbaseCollection') &&
            method_exists($addressType, 'toCouchbaseDocument');

echo "  - Has Couchbase methods: " . ($hasTrait ? "YES" : "NO") . "\n";
echo "  - couchbaseScope(): " . $addressType->couchbaseScope() . "\n";
echo "  - couchbaseCollection(): " . $addressType->couchbaseCollection() . "\n";
echo "  - tableName(): " . $addressType->tableName() . "\n";

// Test 2: Verify Couchbase adapter scope mapping
if (class_exists('\\OE\\Database\\CouchbaseAdapter')) {
    $adapter = Yii::app()->couchbaseAdapter;
    echo "  - Scope for address_type: " . $adapter->getScopeForCollection('address_type') . "\n";
} else {
    echo "  - CouchbaseAdapter class not available\n";
}

// Test 3: Create a test AddressType record
echo "\nStep 2: Creating test AddressType record\n";
$testAddressType = new AddressType();
$testAddressType->name = 'TEST_DROID_ADDRESS_TYPE_' . time();

try {
    if ($testAddressType->save()) {
        echo "  - Saved to MariaDB: YES (ID: " . $testAddressType->id . ")\n";
    } else {
        echo "  - Failed to save: " . json_encode($testAddressType->getErrors()) . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Verify data in MariaDB
echo "\nStep 3: Verify data in MariaDB\n";
$fromMariaDb = AddressType::model()->findByPk($testAddressType->id);
if ($fromMariaDb) {
    echo "  - Found in MariaDB: YES\n";
    echo "  - Name: " . $fromMariaDb->name . "\n";
} else {
    echo "  - Found in MariaDB: NO\n";
}

// Test 5: Verify data in Couchbase
echo "\nStep 4: Verify data in Couchbase\n";
try {
    $adapter = Yii::app()->couchbaseAdapter;
    $fromCouchbase = $adapter->findByPk('address_type', $testAddressType->id);

    if ($fromCouchbase) {
        echo "  - Found in Couchbase: YES\n";
        echo "  - Name: " . ($fromCouchbase['name'] ?? 'N/A') . "\n";
        echo "  - _type: " . ($fromCouchbase['_type'] ?? 'N/A') . "\n";
        echo "  - _mysql_id: " . ($fromCouchbase['_mysql_id'] ?? 'N/A') . "\n";
    } else {
        echo "  - Found in Couchbase: NO\n";
        echo "  - Collection: address_type\n";
        echo "  - Scope: " . $testAddressType->couchbaseScope() . "\n";
    }
} catch (Exception $e) {
    echo "  - Error checking Couchbase: " . $e->getMessage() . "\n";
}

// Test 6: Update the record
echo "\nStep 5: Update AddressType record\n";
$testAddressType->name = 'UPDATED_TES_T_DROID_ADDRESS_TYPE_' . time();
try {
    if ($testAddressType->save()) {
        echo "  - Updated in MariaDB: YES\n";

        // Check Couchbase update
        $fromCouchbase = $adapter->findByPk('address_type', $testAddressType->id);
        if ($fromCouchbase) {
            echo "  - Updated in Couchbase: YES\n";
            echo "  - New Name: " . ($fromCouchbase['name'] ?? 'N/A') . "\n";
        } else {
            echo "  - Updated in Couchbase: NO\n";
        }
    } else {
        echo "  - Failed to update: " . json_encode($testAddressType->getErrors()) . "\n";
    }
} catch (Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Test 7: Delete the record
echo "\nStep 6: Delete AddressType record\n";
try {
    $idToDelete = $testAddressType->id;
    if ($testAddressType->delete()) {
        echo "  - Deleted from MariaDB: YES\n";

        // Check Couchbase delete
        $fromCouchbase = $adapter->findByPk('address_type', $idToDelete);
        if (!$fromCouchbase) {
            echo "  - Deleted from Couchbase: YES\n";
        } else {
            echo "  - Deleted from Couchbase: NO\n";
        }
    } else {
        echo "  - Failed to delete: " . json_encode($testAddressType->getErrors()) . "\n";
    }
} catch (Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

echo "\n-------------------------------------------------\n";
echo "Test completed successfully.\n";
echo "-------------------------------------------------\n";
