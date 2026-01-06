<?php
/**
 * Unit tests for AnaestheticComplication Couchbase integration
 */

class AnaestheticComplicationCouchbaseTest extends CTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Ensure dual-write is disabled for tests by default
        Yii::app()->params['enable_dual_write'] = false;
    }
    
    public function tearDown(): void
    {
        Yii::app()->params['enable_dual_write'] = false;
        parent::tearDown();
    }
    
    /**
     * @test
     * Verify that the model uses the CouchbaseModelBridge trait
     */
    public function testHasCouchbaseModelBridgeTrait()
    {
        $model = new AnaestheticComplication();
        $traits = class_uses($model);
        
        $this->assertArrayHasKey(
            'OE\Models\Traits\CouchbaseModelBridge',
            $traits,
            'AnaestheticComplication should use CouchbaseModelBridge trait'
        );
    }
    
    /**
     * @test
     * Verify that couchbaseScope returns 'reference'
     */
    public function testCouchbaseScopeReturnsReference()
    {
        $model = new AnaestheticComplication();
        
        $this->assertEquals(
            'reference',
            $model->couchbaseScope(),
            'couchbaseScope() should return "reference"'
        );
    }
    
    /**
     * @test
     * Verify that couchbaseCollection returns the table name
     */
    public function testCouchbaseCollectionReturnsTableName()
    {
        $model = new AnaestheticComplication();
        
        $this->assertEquals(
            'anaesthetic_complication',
            $model->couchbaseCollection(),
            'couchbaseCollection() should return "anaesthetic_complication"'
        );
    }
    
    /**
     * @test
     * Verify that tableName returns the correct table
     */
    public function testTableNameReturnsCorrectTable()
    {
        $model = new AnaestheticComplication();
        
        $this->assertEquals(
            'anaesthetic_complication',
            $model->tableName(),
            'tableName() should return "anaesthetic_complication"'
        );
    }
    
    /**
     * @test
     * Verify that the model has afterSave method
     */
    public function testHasAfterSaveMethod()
    {
        $model = new AnaestheticComplication();
        $reflectionClass = new ReflectionClass($model);
        
        $this->assertTrue(
            $reflectionClass->hasMethod('afterSave'),
            'AnaestheticComplication should have afterSave method'
        );
        
        // Check that afterSave is in the class itself (not just inherited)
        $method = $reflectionClass->getMethod('afterSave');
        $this->assertEquals(
            'AnaestheticComplication',
            $method->getDeclaringClass()->getName(),
            'afterSave should be declared in AnaestheticComplication class'
        );
    }
    
    /**
     * @test
     * Verify that the model has afterDelete method
     */
    public function testHasAfterDeleteMethod()
    {
        $model = new AnaestheticComplication();
        $reflectionClass = new ReflectionClass($model);
        
        $this->assertTrue(
            $reflectionClass->hasMethod('afterDelete'),
            'AnaestheticComplication should have afterDelete method'
        );
        
        // Check that afterDelete is in the class itself (not just inherited)
        $method = $reflectionClass->getMethod('afterDelete');
        $this->assertEquals(
            'AnaestheticComplication',
            $method->getDeclaringClass()->getName(),
            'afterDelete should be declared in AnaestheticComplication class'
        );
    }
    
    /**
     * @test
     * Verify that getCouchbaseDocumentKey returns correct format
     */
    public function testGetCouchbaseDocumentKeyFormat()
    {
        $model = new AnaestheticComplication();
        $model->id = 123;
        
        $expectedKey = 'anaesthetic_complication::123';
        $actualKey = $model->getCouchbaseDocumentKey();
        
        $this->assertEquals(
            $expectedKey,
            $actualKey,
            'getCouchbaseDocumentKey() should return "collection::id" format'
        );
    }
    
    /**
     * @test
     * Verify that toCouchbaseDocument includes required fields
     */
    public function testToCouchbaseDocumentHasRequiredFields()
    {
        $model = new AnaestheticComplication();
        $model->id = 1;
        $model->name = 'Test Complication';
        $model->display_order = 99;
        
        $doc = $model->toCouchbaseDocument();
        
        $this->assertArrayHasKey('_type', $doc, 'Document should have _type field');
        $this->assertArrayHasKey('_mysql_id', $doc, 'Document should have _mysql_id field');
        $this->assertArrayHasKey('_modified', $doc, 'Document should have _modified field');
        $this->assertEquals('anaesthetic_complication', $doc['_type'], '_type should be "anaesthetic_complication"');
        $this->assertEquals(1, $doc['_mysql_id'], '_mysql_id should match model id');
    }
    
    /**
     * @test
     * Verify disableCouchbaseSync and enableCouchbaseSync work correctly
     */
    public function testCouchbaseSyncToggle()
    {
        $model = new AnaestheticComplication();
        
        // Initially not disabled
        $model->disableCouchbaseSync();
        
        // Use reflection to access protected property
        $reflection = new ReflectionClass($model);
        $property = $reflection->getProperty('_couchbaseSyncDisabled');
        $property->setAccessible(true);
        
        $this->assertTrue($property->getValue($model), 'Sync should be disabled');
        
        $model->enableCouchbaseSync();
        $this->assertFalse($property->getValue($model), 'Sync should be enabled');
    }
    
    /**
     * @test
     * Verify model extends BaseActiveRecordVersioned
     */
    public function testExtendsBaseActiveRecordVersioned()
    {
        $model = new AnaestheticComplication();
        
        $this->assertInstanceOf(
            'BaseActiveRecordVersioned',
            $model,
            'AnaestheticComplication should extend BaseActiveRecordVersioned'
        );
    }
}
