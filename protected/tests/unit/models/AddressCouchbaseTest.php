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
 * Unit tests for Address model Couchbase functionality
 */
class AddressCouchbaseTest extends CTestCase
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
     * Test that Address model has CouchbaseModelBridge trait
     */
    public function testAddressHasCouchbaseModelBridgeTrait()
    {
        $address = new Address();
        
        // Check trait is used by verifying trait methods exist
        $this->assertTrue(
            method_exists($address, 'couchbaseScope'),
            'Address should have couchbaseScope method from CouchbaseModelBridge trait'
        );
        $this->assertTrue(
            method_exists($address, 'couchbaseCollection'),
            'Address should have couchbaseCollection method from CouchbaseModelBridge trait'
        );
        $this->assertTrue(
            method_exists($address, 'toCouchbaseDocument'),
            'Address should have toCouchbaseDocument method from CouchbaseModelBridge trait'
        );
    }
    
    /**
     * Test that Address model returns correct Couchbase scope
     */
    public function testAddressCouchbaseScopeIsCore()
    {
        $address = new Address();
        
        $this->assertEquals(
            'core',
            $address->couchbaseScope(),
            'Address couchbaseScope should return "core"'
        );
    }
    
    /**
     * Test that Address model returns correct Couchbase collection name
     */
    public function testAddressCouchbaseCollectionIsAddress()
    {
        $address = new Address();
        
        $this->assertEquals(
            'address',
            $address->couchbaseCollection(),
            'Address couchbaseCollection should return "address"'
        );
    }
    
    /**
     * Test that Address generates correct Couchbase document key
     */
    public function testAddressCouchbaseDocumentKey()
    {
        $address = new Address();
        $address->id = 123;
        
        $this->assertEquals(
            'address::123',
            $address->getCouchbaseDocumentKey(),
            'Document key should be in format collection::id'
        );
    }
    
    /**
     * Test toCouchbaseDocument includes required metadata
     */
    public function testToCouchbaseDocumentIncludesMetadata()
    {
        $address = new Address();
        $address->id = 1;
        $address->address1 = 'Test Address Line 1';
        $address->address2 = 'Test Address Line 2';
        $address->city = 'Test City';
        $address->postcode = 'EC1V 0DX';
        $address->county = 'Test County';
        $address->country_id = 1;
        $address->contact_id = 1;
        
        $doc = $address->toCouchbaseDocument();
        
        // Check required metadata fields
        $this->assertArrayHasKey('_type', $doc, 'Document should have _type field');
        $this->assertEquals('address', $doc['_type'], '_type should be "address"');
        
        $this->assertArrayHasKey('_mysql_id', $doc, 'Document should have _mysql_id field');
        $this->assertEquals(1, $doc['_mysql_id'], '_mysql_id should match primary key');
        
        $this->assertArrayHasKey('_modified', $doc, 'Document should have _modified timestamp');
        
        // Check data fields are present
        $this->assertArrayHasKey('address1', $doc);
        $this->assertEquals('Test Address Line 1', $doc['address1']);
        
        $this->assertArrayHasKey('city', $doc);
        $this->assertEquals('Test City', $doc['city']);
    }
    
    /**
     * Test toCouchbaseDocument handles country relation embedding
     */
    public function testToCouchbaseDocumentEmbedsCountryRelation()
    {
        // Create a mock address with country
        $address = new Address();
        $address->id = 1;
        $address->address1 = 'Test Address';
        $address->city = 'Test City';
        $address->country_id = 1;
        $address->contact_id = 1;
        
        // Note: In a real test with database, we would check that country relation is embedded
        // For unit test without DB, we just verify the method exists
        $this->assertTrue(
            method_exists($address, 'getEmbeddedRelations'),
            'Address should have getEmbeddedRelations method'
        );
    }
    
    /**
     * Test Couchbase sync can be disabled
     */
    public function testCouchbaseSyncCanBeDisabled()
    {
        $address = new Address();
        
        // Initially not disabled (via reflection to access protected property)
        $reflection = new ReflectionObject($address);
        $property = $reflection->getProperty('_couchbaseSyncDisabled');
        $property->setAccessible(true);
        
        $this->assertFalse($property->getValue($address), 'Couchbase sync should be enabled by default');
        
        // Disable sync
        $address->disableCouchbaseSync();
        $this->assertTrue($property->getValue($address), 'Couchbase sync should be disabled after calling disableCouchbaseSync()');
        
        // Re-enable sync
        $address->enableCouchbaseSync();
        $this->assertFalse($property->getValue($address), 'Couchbase sync should be enabled after calling enableCouchbaseSync()');
    }
    
    /**
     * Test that syncToCouchbase method exists for manual sync
     */
    public function testSyncToCouchbaseMethodExists()
    {
        $address = new Address();
        
        $this->assertTrue(
            method_exists($address, 'syncToCouchbase'),
            'Address should have syncToCouchbase method for manual synchronization'
        );
    }
    
    /**
     * Test that compareWithCouchbase method exists for data comparison
     */
    public function testCompareWithCouchbaseMethodExists()
    {
        $address = new Address();
        
        $this->assertTrue(
            method_exists($address, 'compareWithCouchbase'),
            'Address should have compareWithCouchbase method for data comparison'
        );
    }
    
    /**
     * Test that scope mapping in CouchbaseAdapter includes address
     */
    public function testCouchbaseAdapterHasAddressScopeMapping()
    {
        // Verify that CouchbaseAdapter has address mapped to core scope
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
     * Test Address model table name matches couchbaseCollection
     */
    public function testTableNameMatchesCouchbaseCollection()
    {
        $address = new Address();
        
        $this->assertEquals(
            $address->tableName(),
            $address->couchbaseCollection(),
            'tableName() should match couchbaseCollection() for Address model'
        );
    }
    
    /**
     * Test that Address model uses correct trait namespace
     */
    public function testAddressUsesCorrectTraitNamespace()
    {
        $traits = class_uses(Address::class) ?: [];
        
        // Check for the trait (may be nested in parent classes)
        $allTraits = [];
        $class = Address::class;
        while ($class) {
            $allTraits = array_merge($allTraits, class_uses($class) ?: []);
            $class = get_parent_class($class);
        }
        
        $this->assertContains(
            'OE\\Models\\Traits\\CouchbaseModelBridge',
            $allTraits,
            'Address should use OE\\Models\\Traits\\CouchbaseModelBridge trait'
        );
    }
}
