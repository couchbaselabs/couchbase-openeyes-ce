<?php
/**
 * Unit tests for CouchbaseAdapter
 * 
 * Note: These tests require a running Couchbase instance.
 * Tests will be skipped if Couchbase is not available.
 */

class CouchbaseAdapterTest extends CDbTestCase
{
    /**
     * @var \OE\Database\CouchbaseAdapter
     */
    private $adapter;
    
    /**
     * @var bool Whether Couchbase is available
     */
    private $couchbaseAvailable = false;
    
	protected function setUp(): void
    {
        parent::setUp();
        
        // Check if Couchbase is configured and available
        try {
            if (isset(Yii::app()->couchbase) && Yii::app()->couchbase->isEnabled()) {
                $this->adapter = new \OE\Database\CouchbaseAdapter();
                Yii::app()->couchbase->ping();
                $this->couchbaseAvailable = true;
            }
        } catch (\Exception $e) {
            $this->couchbaseAvailable = false;
        }
    }
    
    private function skipIfNoCouchbase()
    {
        if (!$this->couchbaseAvailable) {
            $this->markTestSkipped('Couchbase is not available');
        }
    }
    
    public function testGetScopeForCollectionReturnsCorrectScope()
    {
        $this->skipIfNoCouchbase();
        
        $this->assertEquals('core', $this->adapter->getScopeForCollection('patient'));
        $this->assertEquals('core', $this->adapter->getScopeForCollection('user'));
        $this->assertEquals('clinical', $this->adapter->getScopeForCollection('examination'));
        $this->assertEquals('booking', $this->adapter->getScopeForCollection('operation'));
    }
    
    public function testFindByPkReturnsNullForMissingDocument()
    {
        $this->skipIfNoCouchbase();
        
        $result = $this->adapter->findByPk('patient', 'nonexistent_id_12345');
        $this->assertNull($result);
    }
    
    public function testInsertAndFindByPk()
    {
        $this->skipIfNoCouchbase();
        
        $testId = 'test_' . uniqid();
        $testData = [
            'id' => $testId,
            'test_field' => 'test_value',
            'created' => date('c'),
        ];
        
        try {
            // Insert
            $insertedId = $this->adapter->insert('patient', $testData);
            $this->assertEquals($testId, $insertedId);
            
            // Find
            $found = $this->adapter->findByPk('patient', $testId);
            $this->assertNotNull($found);
            $this->assertEquals('test_value', $found['test_field']);
            
        } finally {
            // Cleanup
            $this->adapter->delete('patient', $testId);
        }
    }
    
    public function testUpdateDocument()
    {
        $this->skipIfNoCouchbase();
        
        $testId = 'test_update_' . uniqid();
        $testData = [
            'id' => $testId,
            'field' => 'original_value',
        ];
        
        try {
            // Insert
            $this->adapter->insert('patient', $testData);
            
            // Update
            $updated = $this->adapter->update('patient', $testId, [
                'field' => 'updated_value',
            ]);
            $this->assertTrue($updated);
            
            // Verify
            $found = $this->adapter->findByPk('patient', $testId);
            $this->assertEquals('updated_value', $found['field']);
            
        } finally {
            // Cleanup
            $this->adapter->delete('patient', $testId);
        }
    }
    
    public function testDeleteDocument()
    {
        $this->skipIfNoCouchbase();
        
        $testId = 'test_delete_' . uniqid();
        $testData = ['id' => $testId, 'field' => 'value'];
        
        // Insert
        $this->adapter->insert('patient', $testData);
        
        // Delete
        $deleted = $this->adapter->delete('patient', $testId);
        $this->assertTrue($deleted);
        
        // Verify deleted
        $found = $this->adapter->findByPk('patient', $testId);
        $this->assertNull($found);
    }
    
    public function testExistsReturnsBool()
    {
        $this->skipIfNoCouchbase();
        
        $exists = $this->adapter->exists('patient', 'definitely_not_exists_12345');
        $this->assertFalse($exists);
    }
    
    public function testCountReturnsInteger()
    {
        $this->skipIfNoCouchbase();
        
        $count = $this->adapter->count('patient');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
    
    public function testBeginTransactionReturnsTransactionInterface()
    {
        $this->skipIfNoCouchbase();
        
        $transaction = $this->adapter->beginTransaction();
        $this->assertInstanceOf(\OE\Database\TransactionInterface::class, $transaction);
        $transaction->rollback();
    }
}
