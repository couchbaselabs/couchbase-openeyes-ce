<?php
/**
 * Unit tests for MariaDbAdapter
 */

class MariaDbAdapterTest extends CDbTestCase
{
    /**
     * @var \OE\Database\MariaDbAdapter
     */
    private $adapter;
    
	protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new \OE\Database\MariaDbAdapter();
    }
    
    public function testFindByPkReturnsArrayWhenFound()
    {
        // User with ID 1 should exist in most test databases
        $result = $this->adapter->findByPk('user', 1);
        
        if ($result !== null) {
            $this->assertIsArray($result);
            $this->assertArrayHasKey('id', $result);
            $this->assertEquals(1, $result['id']);
        } else {
            $this->markTestSkipped('No user with ID 1 found in database');
        }
    }
    
    public function testFindByPkReturnsNullWhenNotFound()
    {
        $result = $this->adapter->findByPk('user', 999999999);
        $this->assertNull($result);
    }
    
    public function testFindByAttributesReturnsArray()
    {
        $result = $this->adapter->findByAttributes('user', []);
        $this->assertIsArray($result);
    }
    
    public function testFindByAttributesWithLimitOption()
    {
        $result = $this->adapter->findByAttributes('user', [], ['limit' => 5]);
        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(5, count($result));
    }
    
    public function testFindAllReturnsArray()
    {
        $result = $this->adapter->findAll('user', ['limit' => 10]);
        $this->assertIsArray($result);
    }
    
    public function testCountReturnsInteger()
    {
        $count = $this->adapter->count('user');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
    
    public function testCountWithCriteriaReturnsInteger()
    {
        $count = $this->adapter->count('user', ['id' => 1]);
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
    
    public function testExistsReturnsBool()
    {
        $exists = $this->adapter->exists('user', 1);
        $this->assertIsBool($exists);
    }
    
    public function testExistsReturnsFalseForMissingRecord()
    {
        $exists = $this->adapter->exists('user', 999999999);
        $this->assertFalse($exists);
    }
    
    public function testBeginTransactionReturnsTransactionInterface()
    {
        $transaction = $this->adapter->beginTransaction();
        $this->assertInstanceOf(\OE\Database\TransactionInterface::class, $transaction);
        $this->assertTrue($transaction->isActive());
        $transaction->rollback();
    }
    
    public function testQueryExecutesSuccessfully()
    {
        $result = $this->adapter->query('SELECT 1 as test');
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertEquals(1, $result[0]['test']);
    }
    
    public function testGetConnectionReturnsDbConnection()
    {
        $connection = $this->adapter->getConnection();
        $this->assertInstanceOf(CDbConnection::class, $connection);
    }
}
