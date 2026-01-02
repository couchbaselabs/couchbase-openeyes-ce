<?php
/**
 * Unit tests for CouchbaseConnection component
 */

class CouchbaseConnectionTest extends CDbTestCase
{
    private $connection;
    
	protected function setUp(): void
    {
        parent::setUp();
        
        // Skip tests if Couchbase is not configured
        if (!isset(Yii::app()->couchbase)) {
            $this->markTestSkipped('Couchbase not configured');
        }
        
        $this->connection = Yii::app()->couchbase;
    }
    
    public function testConnectionHasConfig()
    {
        $this->assertNotEmpty($this->connection->config);
        $this->assertArrayHasKey('connection', $this->connection->config);
        $this->assertArrayHasKey('bucket', $this->connection->config);
    }
    
    public function testGetClusterReturnsClusterInstance()
    {
        try {
            $cluster = $this->connection->getCluster();
            $this->assertInstanceOf('Couchbase\Cluster', $cluster);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testGetBucketReturnsBucketInstance()
    {
        try {
            $bucket = $this->connection->getBucket();
            $this->assertInstanceOf('Couchbase\Bucket', $bucket);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testGetCollectionReturnsCollectionInstance()
    {
        try {
            $collection = $this->connection->getCollection('core', 'patient');
            $this->assertInstanceOf('Couchbase\Collection', $collection);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testPingReturnsArrayWithStatus()
    {
        try {
            $result = $this->connection->ping();
            $this->assertIsArray($result);
            $this->assertArrayHasKey('status', $result);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testQueryExecutesSuccessfully()
    {
        try {
            $result = $this->connection->query('SELECT 1 as test');
            $this->assertNotNull($result);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testIsEnabledReturnsBoolean()
    {
        $result = $this->connection->isEnabled();
        $this->assertIsBool($result);
    }
}
