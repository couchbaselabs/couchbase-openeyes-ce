<?php
/**
 * Unit tests for CouchbaseModelBridge trait
 */

class CouchbaseModelBridgeTest extends CTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Ensure dual-write is disabled for tests
        Yii::app()->params['enable_dual_write'] = false;
    }
    
    public function tearDown(): void
    {
        Yii::app()->params['enable_dual_write'] = false;
        parent::tearDown();
    }
    
    /**
     * @test
     */
    public function testCouchbaseScopeDefault()
    {
        $model = new TestBridgeModel();
        $this->assertEquals('core', $model->couchbaseScope());
    }
    
    /**
     * @test
     */
    public function testCouchbaseCollectionDefault()
    {
        $model = new TestBridgeModel();
        $this->assertEquals('test_bridge', $model->couchbaseCollection());
    }
    
    /**
     * @test
     */
    public function testToCouchbaseDocumentBasic()
    {
        $model = new TestBridgeModel();
        $model->id = 1;
        $model->name = 'Test';
        $model->active = true;
        $model->created_date = '2023-12-19 10:00:00';
        
        $doc = $model->toCouchbaseDocument();
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('test_bridge', $doc['_type']);
        $this->assertArrayHasKey('_mysql_id', $doc);
        $this->assertEquals(1, $doc['_mysql_id']);
        $this->assertArrayHasKey('name', $doc);
        $this->assertEquals('Test', $doc['name']);
    }
    
    /**
     * @test
     */
    public function testSyncDisableEnable()
    {
        $model = new TestBridgeModel();
        
        $this->assertFalse($model->_couchbaseSyncDisabled);
        
        $model->disableCouchbaseSync();
        $this->assertTrue($model->_couchbaseSyncDisabled);
        
        $model->enableCouchbaseSync();
        $this->assertFalse($model->_couchbaseSyncDisabled);
    }
    
    /**
     * @test
     */
    public function testDocumentKey()
    {
        $model = new TestBridgeModel();
        $model->id = 123;
        
        $this->assertEquals('test_bridge::123', $model->getCouchbaseDocumentKey());
    }
    
    /**
     * @test
     */
    public function testDualWriteDisabledByDefault()
    {
        $model = new TestBridgeModel();
        $this->assertFalse($model->isDualWriteEnabled());
    }
    
    /**
     * @test
     */
    public function testSaveToCouchbaseSkipsWhenDisabled()
    {
        $model = new TestBridgeModel();
        $model->disableCouchbaseSync();
        
        // Should return true (skipped) without error
        $result = $model->saveToCouchbase();
        $this->assertTrue($result);
    }
}

/**
 * Test model class using the bridge trait
 */
class TestBridgeModel extends CActiveRecord
{
    use \OE\Models\Traits\CouchbaseModelBridge {
        saveToCouchbase as public;
        isDualWriteEnabled as public;
    }
    
    public $_couchbaseSyncDisabled = false;
    public $id;
    public $name;
    public $active;
    public $created_date;
    
    public function tableName()
    {
        return 'test_bridge';
    }
    
    public function getPrimaryKey()
    {
        return $this->id;
    }
    
    public function getMetaData()
    {
        // Return mock metadata
        $meta = new stdClass();
        $meta->columns = [
            'id' => (object)['dbType' => 'int(10) unsigned'],
            'name' => (object)['dbType' => 'varchar(100)'],
            'active' => (object)['dbType' => 'tinyint(1)'],
            'created_date' => (object)['dbType' => 'datetime'],
        ];
        return $meta;
    }
    
    public function hasAttribute($attr)
    {
        return in_array($attr, ['id', 'name', 'active', 'created_date']);
    }
    
    public function getAttributes($names = null)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
            'created_date' => $this->created_date,
        ];
    }
    
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        return parent::__get($name);
    }
}
