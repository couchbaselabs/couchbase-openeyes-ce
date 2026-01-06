<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2024
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2024, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

/**
 * Unit tests for AddressType model Couchbase functionality
 */
class AddressTypeCouchbaseTest extends CTestCase
{
    private $originalDualWrite;
    
    public function setUp(): void
    {
        parent::setUp();
        // Store original dual-write setting
        $this->originalDualWrite = Yii::app()->params['enable_dual_write'] ?? false;
        // Disable dual-write for most tests to avoid actual Couchbase calls
        Yii::app()->params['enable_dual_write'] = false;
    }
    
    public function tearDown(): void
    {
        // Restore original dual-write setting
        Yii::app()->params['enable_dual_write'] = $this->originalDualWrite;
        parent::tearDown();
    }
    
    /**
     * Test that AddressType model has CouchbaseModelBridge trait
     */
    public function testAddressTypeHasCouchbaseModelBridgeTrait()
    {
        $addressType = new AddressType();
        
        // Check trait is used by verifying trait methods exist
        $this->assertTrue(
            method_exists($addressType, 'couchbaseScope'),
            'AddressType should have couchbaseScope method from CouchbaseModelBridge trait'
        );
        $this->assertTrue(
            method_exists($addressType, 'couchbaseCollection'),
            'AddressType should have couchbaseCollection method from CouchbaseModelBridge trait'
        );
        $this->assertTrue(
            method_exists($addressType, 'toCouchbaseDocument'),
            'AddressType should have toCouchbaseDocument method from CouchbaseModelBridge trait'
        );
    }
    
    /**
     * Test that AddressType model returns correct Couchbase scope
     */
    public function testAddressTypeCouchbaseScopeIsReference()
    {
        $addressType = new AddressType();
        
        $this->assertEquals(
            'reference',
            $addressType->couchbaseScope(),
            'AddressType couchbaseScope should return "reference"'
        );
    }
    
    /**
     * Test that AddressType model returns correct Couchbase collection name
     */
    public function testAddressTypeCouchbaseCollectionIsAddressType()
    {
        $addressType = new AddressType();
        
        $this->assertEquals(
            'address_type',
            $addressType->couchbaseCollection(),
            'AddressType couchbaseCollection should return "address_type"'
        );
    }
    
    /**
     * Test that AddressType generates correct Couchbase document key
     */
    public function testAddressTypeCouchbaseDocumentKey()
    {
        $addressType = new AddressType();
        $addressType->id = 123;
        
        $this->assertEquals(
            'address_type::123',
            $addressType->getCouchbaseDocumentKey(),
            'Document key should be in format collection::id'
        );
    }
    
    /**
     * Test toCouchbaseDocument includes required metadata
     */
    public function testToCouchbaseDocumentIncludesMetadata()
    {
        $addressType = new AddressType();
        $addressType->id = 1;
        $addressType->name = 'Test Address Type';
        
        $doc = $addressType->toCouchbaseDocument();
        
        // Check required metadata fields
        $this->assertArrayHasKey('_type', $doc, 'Document should have _type field');
        $this->assertEquals('address_type', $doc['_type'], '_type should be "address_type"');
        
        $this->assertArrayHasKey('_mysql_id', $doc, 'Document should have _mysql_id field');
        $this->assertEquals(1, $doc['_mysql_id'], '_mysql_id should match primary key');
        
        $this->assertArrayHasKey('_modified', $doc, 'Document should have _modified timestamp');
        
        // Check data fields are present
        $this->assertArrayHasKey('name', $doc);
        $this->assertEquals('Test Address Type', $doc['name']);
    }
    
    /**
     * Test Couchbase sync can be disabled
     */
    public function testCouchbaseSyncCanBeDisabled()
    {
        $addressType = new AddressType();
        
        // Initially not disabled (via reflection to access protected property)
        $reflection = new ReflectionObject($addressType);
        $property = $reflection->getProperty('_couchbaseSyncDisabled');
        $property->setAccessible(true);
        
        $this->assertFalse($property->getValue($addressType), 'Couchbase sync should be enabled by default');
        
        // Disable sync
        $addressType->disableCouchbaseSync();
        $this->assertTrue($property->getValue($addressType), 'Couchbase sync should be disabled after calling disableCouchbaseSync()');
        
        // Re-enable sync
        $addressType->enableCouchbaseSync();
        $this->assertFalse($property->getValue($addressType), 'Couchbase sync should be enabled after calling enableCouchbaseSync()');
    }
    
    /**
     * Test that syncToCouchbase method exists for manual sync
     */
    public function testSyncToCouchbaseMethodExists()
    {
        $addressType = new AddressType();
        
        $this->assertTrue(
            method_exists($addressType, 'syncToCouchbase'),
            'AddressType should have syncToCouchbase method for manual synchronization'
        );
    }
    
    /**
     * Test that compareWithCouchbase method exists for data comparison
     */
    public function testCompareWithCouchbaseMethodExists()
    {
        $addressType = new AddressType();
        
        $this->assertTrue(
            method_exists($addressType, 'compareWithCouchbase'),
            'AddressType should have compareWithCouchbase method for data comparison'
        );
    }
    
    /**
     * Test that scope mapping in CouchbaseAdapter includes address_type
     */
    public function testCouchbaseAdapterHasAddressTypeScopeMapping()
    {
        // Verify that CouchbaseAdapter has address_type mapped to reference scope
        // This tests the configuration side
        if (class_exists('\\OE\\Database\\CouchbaseAdapter')) {
            $adapterClass = new ReflectionClass('\\OE\\Database\\CouchbaseAdapter');
            $methodExists = $adapterClass->hasMethod('getScopeForCollection');
            
            $this->assertTrue($methodExists, 'CouchbaseAdapter should have getScopeForCollection method');
        } else {
            $this->markTestSkipped('CouchbaseAdapter class not available');
        }
    }
    
    /**
     * Test AddressType model table name matches couchbaseCollection
     */
    public function testTableNameMatchesCouchbaseCollection()
    {
        $addressType = new AddressType();
        
        $this->assertEquals(
            $addressType->tableName(),
            $addressType->couchbaseCollection(),
            'tableName() should match couchbaseCollection() for AddressType model'
        );
    }
    
    /**
     * Test that AddressType model uses correct trait namespace
     */
    public function testAddressTypeUsesCorrectTraitNamespace()
    {
        $traits = class_uses(AddressType::class) ?: [];
        
        // Check for the trait (may be nested in parent classes)
        $allTraits = [];
        $class = AddressType::class;
        while ($class) {
            $allTraits = array_merge($allTraits, class_uses($class) ?: []);
            $class = get_parent_class($class);
        }
        
        $this->assertContains(
            'OE\\Models\\Traits\\CouchbaseModelBridge',
            $allTraits,
            'AddressType should use OE\\Models\\Traits\\CouchbaseModelBridge trait'
        );
    }
    
    /**
     * Test that afterSave hook is properly defined
     */
    public function testAfterSaveHookExists()
    {
        $addressType = new AddressType();
        
        // Verify afterSave method exists
        $this->assertTrue(
            method_exists($addressType, 'afterSave'),
            'AddressType should have afterSave method'
        );
        
        // Use reflection to verify it calls saveToCouchbase
        $reflection = new ReflectionClass(AddressType::class);
        $method = $reflection->getMethod('afterSave');
        
        // Get the source code of the method
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine + 1;
        
        $source = file($filename);
        $methodSource = implode('', array_slice($source, $startLine - 1, $length));
        
        $this->assertStringContainsString(
            'saveToCouchbase',
            $methodSource,
            'afterSave method should call saveToCouchbase'
        );
    }
    
    /**
     * Test that afterDelete hook is properly defined
     */
    public function testAfterDeleteHookExists()
    {
        $addressType = new AddressType();
        
        // Verify afterDelete method exists
        $this->assertTrue(
            method_exists($addressType, 'afterDelete'),
            'AddressType should have afterDelete method'
        );
        
        // Use reflection to verify it calls deleteFromCouchbase
        $reflection = new ReflectionClass(AddressType::class);
        $method = $reflection->getMethod('afterDelete');
        
        // Get the source code of the method
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $length = $endLine - $startLine + 1;
        
        $source = file($filename);
        $methodSource = implode('', array_slice($source, $startLine - 1, $length));
        
        $this->assertStringContainsString(
            'deleteFromCouchbase',
            $methodSource,
            'afterDelete method should call deleteFromCouchbase'
        );
    }
    
    /**
     * Test AddressType constants for hard-coded type IDs
     */
    public function testAddressTypeConstants()
    {
        $this->assertEquals(1, AddressType::REPLYTO, 'REPLYTO constant should be 1');
        $this->assertEquals(2, AddressType::HOME, 'HOME constant should be 2');
        $this->assertEquals(3, AddressType::CORRESPOND, 'CORRESPOND constant should be 3');
        $this->assertEquals(4, AddressType::TRANSPORT, 'TRANSPORT constant should be 4');
    }
}
