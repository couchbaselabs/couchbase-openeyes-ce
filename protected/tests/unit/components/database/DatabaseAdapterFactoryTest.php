<?php
/**
 * Unit tests for DatabaseAdapterFactory
 */

class DatabaseAdapterFactoryTest extends CDbTestCase
{
	protected function setUp(): void
    {
        parent::setUp();
        \OE\Database\DatabaseAdapterFactory::clearInstances();
    }
    
	protected function tearDown(): void
    {
        parent::tearDown();
        \OE\Database\DatabaseAdapterFactory::clearInstances();
    }
    
    public function testGetAdapterReturnsMariaDbByDefault()
    {
        // Ensure default settings
        Yii::app()->params['database_adapter'] = 'mariadb';
        Yii::app()->params['enable_dual_write'] = false;
        
        $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter();
        
        $this->assertInstanceOf(\OE\Database\MariaDbAdapter::class, $adapter);
    }
    
    public function testGetAdapterReturnsSpecificType()
    {
        $mariadb = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_MARIADB
        );
        
        $this->assertInstanceOf(\OE\Database\MariaDbAdapter::class, $mariadb);
    }
    
    public function testGetAdapterReturnsCachedInstance()
    {
        $adapter1 = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_MARIADB
        );
        $adapter2 = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_MARIADB
        );
        
        $this->assertSame($adapter1, $adapter2);
    }
    
    public function testClearInstancesRemovesCache()
    {
        $adapter1 = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_MARIADB
        );
        
        \OE\Database\DatabaseAdapterFactory::clearInstances();
        
        $adapter2 = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_MARIADB
        );
        
        $this->assertNotSame($adapter1, $adapter2);
    }
    
    public function testGetAdapterThrowsForUnknownType()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        \OE\Database\DatabaseAdapterFactory::getAdapter('unknown_type');
    }
    
    public function testShouldUseCouchbaseReturnsFalseByDefault()
    {
        Yii::app()->params['enable_couchbase_read'] = false;
        Yii::app()->params['couchbase_migrated_collections'] = [];
        
        $result = \OE\Database\DatabaseAdapterFactory::shouldUseCouchbase('patient');
        
        $this->assertFalse($result);
    }
    
    public function testShouldUseCouchbaseReturnsTrueForMigratedCollection()
    {
        Yii::app()->params['enable_couchbase_read'] = true;
        Yii::app()->params['couchbase_migrated_collections'] = ['patient'];
        
        $result = \OE\Database\DatabaseAdapterFactory::shouldUseCouchbase('patient');
        
        $this->assertTrue($result);
    }
    
    public function testGetAdapterForCollectionReturnsMariaDbByDefault()
    {
        Yii::app()->params['enable_dual_write'] = false;
        Yii::app()->params['enable_couchbase_read'] = false;
        
        $adapter = \OE\Database\DatabaseAdapterFactory::getAdapterForCollection('patient');
        
        $this->assertInstanceOf(\OE\Database\MariaDbAdapter::class, $adapter);
    }
    
    public function testIsDualWriteEnabledReturnsBool()
    {
        Yii::app()->params['enable_dual_write'] = true;
        $this->assertTrue(\OE\Database\DatabaseAdapterFactory::isDualWriteEnabled());
        
        Yii::app()->params['enable_dual_write'] = false;
        $this->assertFalse(\OE\Database\DatabaseAdapterFactory::isDualWriteEnabled());
    }
}
