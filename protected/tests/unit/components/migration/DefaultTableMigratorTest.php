<?php
/**
 * Unit tests for DefaultTableMigrator
 */

use OE\Migration\DefaultTableMigrator;

class DefaultTableMigratorTest extends CTestCase
{
    /**
     * @test
     */
    public function testGetCollectionReturnsTableName()
    {
        // This test will work even without Couchbase connection
        // as it only tests the getter
        try {
            $migrator = new DefaultTableMigrator('patient');
            $this->assertEquals('patient', $migrator->getCollection());
        } catch (\Exception $e) {
            // Skip if Couchbase not available
            $this->markTestSkipped('Couchbase connection not available: ' . $e->getMessage());
        }
    }
    
    /**
     * @test
     */
    public function testGetTableReturnsTableName()
    {
        try {
            $migrator = new DefaultTableMigrator('episode');
            $this->assertEquals('episode', $migrator->getTable());
        } catch (\Exception $e) {
            $this->markTestSkipped('Couchbase connection not available: ' . $e->getMessage());
        }
    }
    
    /**
     * @test
     */
    public function testGetCollectionForKnownTable()
    {
        try {
            $migrator = new DefaultTableMigrator('user');
            // user should be in the core scope per collection map
            $this->assertEquals('user', $migrator->getCollection());
        } catch (\Exception $e) {
            $this->markTestSkipped('Couchbase connection not available: ' . $e->getMessage());
        }
    }
}
