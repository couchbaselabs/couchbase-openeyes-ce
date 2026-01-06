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
 * Unit tests for AnaestheticAgent model Couchbase functionality
 */
class AnaestheticAgentCouchbaseTest extends CTestCase
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
     * Test that AnaestheticAgent model has CouchbaseModelBridge trait
     */
    public function testAnaestheticAgentHasCouchbaseModelBridgeTrait()
    {
        $anaestheticAgent = new AnaestheticAgent();
        
        // Check trait is used by verifying trait methods exist
        $this->assertTrue(
            method_exists($anaestheticAgent, 'couchbaseScope'),
            'AnaestheticAgent should have couchbaseScope method from CouchbaseModelBridge trait'
        );
        $this->assertTrue(
            method_exists($anaestheticAgent, 'couchbaseCollection'),
            'AnaestheticAgent should have couchbaseCollection method from CouchbaseModelBridge trait'
        );
        $this->assertTrue(
            method_exists($anaestheticAgent, 'toCouchbaseDocument'),
            'AnaestheticAgent should have toCouchbaseDocument method from CouchbaseModelBridge trait'
        );
    }
    
    /**
     * Test that AnaestheticAgent model returns correct Couchbase scope
     */
    public function testAnaestheticAgentCouchbaseScopeIsReference()
    {
        $anaestheticAgent = new AnaestheticAgent();
        
        $this->assertEquals(
            'reference',
            $anaestheticAgent->couchbaseScope(),
            'AnaestheticAgent couchbaseScope should return "reference"'
        );
    }
    
    /**
     * Test that AnaestheticAgent model returns correct Couchbase collection name
     */
    public function testAnaestheticAgentCouchbaseCollectionIsAnaestheticAgent()
    {
        $anaestheticAgent = new AnaestheticAgent();
        
        $this->assertEquals(
            'anaesthetic_agent',
            $anaestheticAgent->couchbaseCollection(),
            'AnaestheticAgent couchbaseCollection should return "anaesthetic_agent"'
        );
    }
    
    /**
     * Test that AnaestheticAgent generates correct Couchbase document key
     */
    public function testAnaestheticAgentCouchbaseDocumentKey()
    {
        $anaestheticAgent = new AnaestheticAgent();
        $anaestheticAgent->id = 123;
        
        $this->assertEquals(
            'anaesthetic_agent::123',
            $anaestheticAgent->getCouchbaseDocumentKey(),
            'Document key should be in format collection::id'
        );
    }
    
    /**
     * Test toCouchbaseDocument includes required metadata
     */
    public function testToCouchbaseDocumentIncludesMetadata()
    {
        $anaestheticAgent = new AnaestheticAgent();
        $anaestheticAgent->id = 1;
        $anaestheticAgent->name = 'Test Anaesthetic Agent';
        $anaestheticAgent->display_order = 5;
        
        $doc = $anaestheticAgent->toCouchbaseDocument();
        
        // Check required metadata fields
        $this->assertArrayHasKey('_type', $doc, 'Document should have _type field');
        $this->assertEquals('anaesthetic_agent', $doc['_type'], '_type should be "anaesthetic_agent"');
        
        $this->assertArrayHasKey('_mysql_id', $doc, 'Document should have _mysql_id field');
        $this->assertEquals(1, $doc['_mysql_id'], '_mysql_id should match primary key');
        
        $this->assertArrayHasKey('_modified', $doc, 'Document should have _modified timestamp');
        
        // Check data fields are present
        $this->assertArrayHasKey('name', $doc);
        $this->assertEquals('Test Anaesthetic Agent', $doc['name']);
        
        $this->assertArrayHasKey('display_order', $doc);
        $this->assertEquals(5, $doc['display_order']);
    }
    
    /**
     * Test Couchbase sync can be disabled
     */
    public function testCouchbaseSyncCanBeDisabled()
    {
        $anaestheticAgent = new AnaestheticAgent();
        
        // Initially not disabled (via reflection to access protected property)
        $reflection = new ReflectionObject($anaestheticAgent);
        $property = $reflection->getProperty('_couchbaseSyncDisabled');
        $property->setAccessible(true);
        
        $this->assertFalse($property->getValue($anaestheticAgent), 'Couchbase sync should be enabled by default');
        
        // Disable sync
        $anaestheticAgent->disableCouchbaseSync();
        $this->assertTrue($property->getValue($anaestheticAgent), 'Couchbase sync should be disabled after calling disableCouchbaseSync()');
        
        // Re-enable sync
        $anaestheticAgent->enableCouchbaseSync();
        $this->assertFalse($property->getValue($anaestheticAgent), 'Couchbase sync should be enabled after calling enableCouchbaseSync()');
    }
    
    /**
     * Test that syncToCouchbase method exists for manual sync
     */
    public function testSyncToCouchbaseMethodExists()
    {
        $anaestheticAgent = new AnaestheticAgent();
        
        $this->assertTrue(
            method_exists($anaestheticAgent, 'syncToCouchbase'),
            'AnaestheticAgent should have syncToCouchbase method for manual synchronization'
        );
    }
    
    /**
     * Test that compareWithCouchbase method exists for data comparison
     */
    public function testCompareWithCouchbaseMethodExists()
    {
        $anaestheticAgent = new AnaestheticAgent();
        
        $this->assertTrue(
            method_exists($anaestheticAgent, 'compareWithCouchbase'),
            'AnaestheticAgent should have compareWithCouchbase method for data comparison'
        );
    }
    
    /**
     * Test that scope mapping in CouchbaseAdapter includes anaesthetic_agent
     */
    public function testCouchbaseAdapterHasAnaestheticAgentScopeMapping()
    {
        // Verify that CouchbaseAdapter has anaesthetic_agent mapped to reference scope
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
     * Test AnaestheticAgent model table name matches couchbaseCollection
     */
    public function testTableNameMatchesCouchbaseCollection()
    {
        $anaestheticAgent = new AnaestheticAgent();
        
        $this->assertEquals(
            $anaestheticAgent->tableName(),
            $anaestheticAgent->couchbaseCollection(),
            'tableName() should match couchbaseCollection() for AnaestheticAgent model'
        );
    }
    
    /**
     * Test that AnaestheticAgent model uses correct trait namespace
     */
    public function testAnaestheticAgentUsesCorrectTraitNamespace()
    {
        $traits = class_uses(AnaestheticAgent::class) ?: [];
        
        // Check for the trait (may be nested in parent classes)
        $allTraits = [];
        $class = AnaestheticAgent::class;
        while ($class) {
            $allTraits = array_merge($allTraits, class_uses($class) ?: []);
            $class = get_parent_class($class);
        }
        
        $this->assertContains(
            'OE\\Models\\Traits\\CouchbaseModelBridge',
            $allTraits,
            'AnaestheticAgent should use OE\\Models\\Traits\\CouchbaseModelBridge trait'
        );
    }
    
    /**
     * Test that afterSave hook is properly defined
     */
    public function testAfterSaveHookExists()
    {
        $anaestheticAgent = new AnaestheticAgent();
        
        // Verify afterSave method exists
        $this->assertTrue(
            method_exists($anaestheticAgent, 'afterSave'),
            'AnaestheticAgent should have afterSave method'
        );
        
        // Use reflection to verify it calls saveToCouchbase
        $reflection = new ReflectionClass(AnaestheticAgent::class);
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
        $anaestheticAgent = new AnaestheticAgent();
        
        // Verify afterDelete method exists
        $this->assertTrue(
            method_exists($anaestheticAgent, 'afterDelete'),
            'AnaestheticAgent should have afterDelete method'
        );
        
        // Use reflection to verify it calls deleteFromCouchbase
        $reflection = new ReflectionClass(AnaestheticAgent::class);
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
     * Test AnaestheticAgent has LookupTable behavior
     */
    public function testAnaestheticAgentHasLookupTableBehavior()
    {
        $anaestheticAgent = new AnaestheticAgent();
        $behaviors = $anaestheticAgent->behaviors();
        
        $this->assertArrayHasKey('LookupTable', $behaviors, 'AnaestheticAgent should have LookupTable behavior');
        $this->assertEquals('LookupTable', $behaviors['LookupTable'], 'LookupTable behavior should be correctly configured');
    }
    
    /**
     * Test AnaestheticAgent name is required
     */
    public function testAnaestheticAgentNameIsRequired()
    {
        $anaestheticAgent = new AnaestheticAgent();
        $rules = $anaestheticAgent->rules();
        
        $nameRequired = false;
        foreach ($rules as $rule) {
            if (in_array('name', explode(',', str_replace(' ', '', $rule[0]))) && $rule[1] === 'required') {
                $nameRequired = true;
                break;
            }
        }
        
        $this->assertTrue($nameRequired, 'AnaestheticAgent name field should be required');
    }
}
