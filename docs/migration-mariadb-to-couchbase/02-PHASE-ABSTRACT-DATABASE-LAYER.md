# Phase 2: Abstract Database Layer

## Overview
This phase creates an abstraction layer that allows the application to work with both MariaDB and Couchbase. This enables gradual migration and provides a rollback path.

## Prerequisites
- Phase 1 completed (Couchbase infrastructure operational)
- Couchbase connection component working
- All existing tests passing

## Dependencies
- Phase 1: Infrastructure & Couchbase Setup

## Tasks

### 2.1 Database Adapter Interface

#### 2.1.1 Core Interface Definition
**File**: `/protected/components/database/DatabaseAdapterInterface.php` (create)

```php
<?php
/**
 * Database adapter interface for supporting multiple database backends
 */

namespace OE\Database;

interface DatabaseAdapterInterface
{
    /**
     * Find a record by primary key
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key value
     * @return array|null
     */
    public function findByPk(string $collection, $pk): ?array;
    
    /**
     * Find records by attributes
     * @param string $collection Collection/table name
     * @param array $attributes Key-value pairs to match
     * @param array $options Additional options (limit, offset, order)
     * @return array
     */
    public function findByAttributes(string $collection, array $attributes, array $options = []): array;
    
    /**
     * Find all records
     * @param string $collection Collection/table name
     * @param array $options Additional options
     * @return array
     */
    public function findAll(string $collection, array $options = []): array;
    
    /**
     * Insert a new record
     * @param string $collection Collection/table name
     * @param array $data Data to insert
     * @return mixed Inserted record ID/key
     */
    public function insert(string $collection, array $data);
    
    /**
     * Update a record
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key
     * @param array $data Data to update
     * @return bool Success status
     */
    public function update(string $collection, $pk, array $data): bool;
    
    /**
     * Delete a record
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key
     * @return bool Success status
     */
    public function delete(string $collection, $pk): bool;
    
    /**
     * Execute a raw query
     * @param string $query Query string (SQL or N1QL)
     * @param array $params Query parameters
     * @return array Results
     */
    public function query(string $query, array $params = []): array;
    
    /**
     * Begin a transaction
     * @return TransactionInterface
     */
    public function beginTransaction();
    
    /**
     * Count records matching criteria
     * @param string $collection Collection/table name
     * @param array $criteria Matching criteria
     * @return int
     */
    public function count(string $collection, array $criteria = []): int;
    
    /**
     * Check if a record exists
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key
     * @return bool
     */
    public function exists(string $collection, $pk): bool;
}
```

**Acceptance Criteria**:
- [ ] Interface defines all essential CRUD operations
- [ ] Interface is generic enough for both SQL and NoSQL
- [ ] Transaction support is included

#### 2.1.2 Transaction Interface
**File**: `/protected/components/database/TransactionInterface.php` (create)

```php
<?php
/**
 * Transaction interface for database operations
 */

namespace OE\Database;

interface TransactionInterface
{
    /**
     * Commit the transaction
     * @return void
     */
    public function commit(): void;
    
    /**
     * Rollback the transaction
     * @return void
     */
    public function rollback(): void;
    
    /**
     * Check if transaction is active
     * @return bool
     */
    public function isActive(): bool;
}
```

### 2.2 MariaDB Adapter

#### 2.2.1 MariaDB Adapter Implementation
**File**: `/protected/components/database/MariaDbAdapter.php` (create)

```php
<?php
/**
 * MariaDB/MySQL adapter implementing DatabaseAdapterInterface
 */

namespace OE\Database;

class MariaDbAdapter implements DatabaseAdapterInterface
{
    private $connection;
    
    public function __construct(\CDbConnection $connection = null)
    {
        $this->connection = $connection ?: \Yii::app()->db;
    }
    
    public function findByPk(string $collection, $pk): ?array
    {
        $result = $this->connection->createCommand()
            ->select('*')
            ->from($collection)
            ->where('id = :id', [':id' => $pk])
            ->queryRow();
            
        return $result ?: null;
    }
    
    public function findByAttributes(string $collection, array $attributes, array $options = []): array
    {
        $command = $this->connection->createCommand()
            ->select('*')
            ->from($collection);
        
        // Build WHERE clause
        $conditions = [];
        $params = [];
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                $conditions[] = "$key IS NULL";
            } else {
                $conditions[] = "$key = :$key";
                $params[":$key"] = $value;
            }
        }
        
        if (!empty($conditions)) {
            $command->where(implode(' AND ', $conditions), $params);
        }
        
        // Apply options
        if (isset($options['order'])) {
            $command->order($options['order']);
        }
        if (isset($options['limit'])) {
            $command->limit($options['limit']);
        }
        if (isset($options['offset'])) {
            $command->offset($options['offset']);
        }
        
        return $command->queryAll();
    }
    
    public function findAll(string $collection, array $options = []): array
    {
        return $this->findByAttributes($collection, [], $options);
    }
    
    public function insert(string $collection, array $data)
    {
        $this->connection->createCommand()
            ->insert($collection, $data);
            
        return $this->connection->getLastInsertID();
    }
    
    public function update(string $collection, $pk, array $data): bool
    {
        $rows = $this->connection->createCommand()
            ->update($collection, $data, 'id = :id', [':id' => $pk]);
            
        return $rows > 0;
    }
    
    public function delete(string $collection, $pk): bool
    {
        $rows = $this->connection->createCommand()
            ->delete($collection, 'id = :id', [':id' => $pk]);
            
        return $rows > 0;
    }
    
    public function query(string $query, array $params = []): array
    {
        return $this->connection->createCommand($query)
            ->queryAll($params);
    }
    
    public function beginTransaction()
    {
        $transaction = $this->connection->beginTransaction();
        return new MariaDbTransaction($transaction);
    }
    
    public function count(string $collection, array $criteria = []): int
    {
        $command = $this->connection->createCommand()
            ->select('COUNT(*)')
            ->from($collection);
        
        if (!empty($criteria)) {
            $conditions = [];
            $params = [];
            foreach ($criteria as $key => $value) {
                $conditions[] = "$key = :$key";
                $params[":$key"] = $value;
            }
            $command->where(implode(' AND ', $conditions), $params);
        }
        
        return (int) $command->queryScalar();
    }
    
    public function exists(string $collection, $pk): bool
    {
        return $this->findByPk($collection, $pk) !== null;
    }
}
```

#### 2.2.2 MariaDB Transaction
**File**: `/protected/components/database/MariaDbTransaction.php` (create)

```php
<?php
/**
 * MariaDB transaction wrapper
 */

namespace OE\Database;

class MariaDbTransaction implements TransactionInterface
{
    private $transaction;
    
    public function __construct(\CDbTransaction $transaction)
    {
        $this->transaction = $transaction;
    }
    
    public function commit(): void
    {
        if ($this->transaction->getActive()) {
            $this->transaction->commit();
        }
    }
    
    public function rollback(): void
    {
        if ($this->transaction->getActive()) {
            $this->transaction->rollback();
        }
    }
    
    public function isActive(): bool
    {
        return $this->transaction->getActive();
    }
}
```

**Acceptance Criteria**:
- [ ] All interface methods implemented
- [ ] Existing queries work through adapter
- [ ] Transaction handling works correctly

### 2.3 Couchbase Adapter

#### 2.3.1 Couchbase Adapter Implementation
**File**: `/protected/components/database/CouchbaseAdapter.php` (create)

```php
<?php
/**
 * Couchbase adapter implementing DatabaseAdapterInterface
 */

namespace OE\Database;

use Couchbase\Collection;
use Couchbase\QueryOptions;
use Couchbase\MutationResult;
use Couchbase\GetResult;

class CouchbaseAdapter implements DatabaseAdapterInterface
{
    private $connection;
    private $scopeMapping = [];
    private $bucket;
    
    public function __construct(\CouchbaseConnection $connection = null)
    {
        $this->connection = $connection ?: \Yii::app()->couchbase;
        $this->bucket = $this->connection->config['bucket'];
        $this->initializeScopeMapping();
    }
    
    /**
     * Map collections to scopes
     */
    private function initializeScopeMapping(): void
    {
        // Core entities
        $this->scopeMapping = [
            'patient' => 'core',
            'user' => 'core',
            'episode' => 'core',
            'event' => 'core',
            'firm' => 'core',
            'site' => 'core',
            'institution' => 'core',
            'contact' => 'core',
            'address' => 'core',
            // Clinical
            'et_ophciexamination_*' => 'clinical',
            'diagnosis' => 'clinical',
            'procedure' => 'clinical',
            // Add more mappings as collections are created
        ];
    }
    
    /**
     * Get scope for a collection
     */
    private function getScopeForCollection(string $collection): string
    {
        if (isset($this->scopeMapping[$collection])) {
            return $this->scopeMapping[$collection];
        }
        
        // Check wildcard patterns
        foreach ($this->scopeMapping as $pattern => $scope) {
            if (strpos($pattern, '*') !== false) {
                $regex = '/^' . str_replace('*', '.*', $pattern) . '$/';
                if (preg_match($regex, $collection)) {
                    return $scope;
                }
            }
        }
        
        return '_default'; // Fallback
    }
    
    /**
     * Get collection object
     */
    private function getCollection(string $collection): Collection
    {
        $scope = $this->getScopeForCollection($collection);
        return $this->connection->getCollection($scope, $collection);
    }
    
    /**
     * Generate document key
     */
    private function generateKey(string $collection, $pk = null): string
    {
        if ($pk !== null) {
            return "{$collection}::{$pk}";
        }
        return "{$collection}::" . uniqid('', true);
    }
    
    /**
     * Extract ID from document key
     */
    private function extractId(string $key): string
    {
        $parts = explode('::', $key);
        return end($parts);
    }
    
    public function findByPk(string $collection, $pk): ?array
    {
        try {
            $key = $this->generateKey($collection, $pk);
            $result = $this->getCollection($collection)->get($key);
            
            $data = $result->content();
            $data['id'] = $pk;
            
            return $data;
        } catch (\Couchbase\Exception\DocumentNotFoundException $e) {
            return null;
        }
    }
    
    public function findByAttributes(string $collection, array $attributes, array $options = []): array
    {
        $scope = $this->getScopeForCollection($collection);
        
        // Build N1QL query
        $query = "SELECT META().id, * FROM `{$this->bucket}`.`{$scope}`.`{$collection}`";
        
        $conditions = [];
        $params = [];
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                $conditions[] = "$key IS NULL";
            } else {
                $conditions[] = "$key = \${$key}";
                $params[$key] = $value;
            }
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Apply options
        if (isset($options['order'])) {
            $query .= " ORDER BY " . $options['order'];
        }
        if (isset($options['limit'])) {
            $query .= " LIMIT " . (int)$options['limit'];
        }
        if (isset($options['offset'])) {
            $query .= " OFFSET " . (int)$options['offset'];
        }
        
        $queryOptions = new QueryOptions();
        if (!empty($params)) {
            $queryOptions->namedParameters($params);
        }
        
        $result = $this->connection->getCluster()->query($query, $queryOptions);
        
        // Transform results
        $rows = [];
        foreach ($result->rows() as $row) {
            $data = (array)$row[$collection];
            $data['id'] = $this->extractId($row['id']);
            $rows[] = $data;
        }
        
        return $rows;
    }
    
    public function findAll(string $collection, array $options = []): array
    {
        return $this->findByAttributes($collection, [], $options);
    }
    
    public function insert(string $collection, array $data)
    {
        // Generate ID if not provided
        $id = $data['id'] ?? uniqid('', true);
        unset($data['id']); // Don't store id in document body
        
        $key = $this->generateKey($collection, $id);
        
        // Add metadata
        $data['_type'] = $collection;
        $data['_created'] = date('c');
        $data['_modified'] = date('c');
        
        $this->getCollection($collection)->insert($key, $data);
        
        return $id;
    }
    
    public function update(string $collection, $pk, array $data): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            unset($data['id']); // Don't store id in document body
            
            // Add modified timestamp
            $data['_modified'] = date('c');
            
            $this->getCollection($collection)->replace($key, $data);
            return true;
        } catch (\Exception $e) {
            \Yii::log("Couchbase update failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return false;
        }
    }
    
    public function delete(string $collection, $pk): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            $this->getCollection($collection)->remove($key);
            return true;
        } catch (\Exception $e) {
            \Yii::log("Couchbase delete failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return false;
        }
    }
    
    public function query(string $query, array $params = []): array
    {
        $options = new QueryOptions();
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        $result = $this->connection->getCluster()->query($query, $options);
        
        return array_map(function($row) {
            return (array)$row;
        }, $result->rows());
    }
    
    public function beginTransaction()
    {
        // Couchbase 7.x supports transactions
        return new CouchbaseTransaction($this->connection->getCluster());
    }
    
    public function count(string $collection, array $criteria = []): int
    {
        $scope = $this->getScopeForCollection($collection);
        
        $query = "SELECT COUNT(*) as cnt FROM `{$this->bucket}`.`{$scope}`.`{$collection}`";
        
        $params = [];
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $key => $value) {
                $conditions[] = "$key = \${$key}";
                $params[$key] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $options = new QueryOptions();
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        $result = $this->connection->getCluster()->query($query, $options);
        $rows = $result->rows();
        
        return (int)($rows[0]['cnt'] ?? 0);
    }
    
    public function exists(string $collection, $pk): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            $this->getCollection($collection)->exists($key);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

#### 2.3.2 Couchbase Transaction
**File**: `/protected/components/database/CouchbaseTransaction.php` (create)

```php
<?php
/**
 * Couchbase transaction wrapper using Distributed ACID Transactions
 */

namespace OE\Database;

use Couchbase\Cluster;
use Couchbase\TransactionAttemptContext;

class CouchbaseTransaction implements TransactionInterface
{
    private $cluster;
    private $context = null;
    private $active = true;
    private $operations = [];
    
    public function __construct(Cluster $cluster)
    {
        $this->cluster = $cluster;
    }
    
    /**
     * Get transaction context for operations
     */
    public function getContext(): ?TransactionAttemptContext
    {
        return $this->context;
    }
    
    /**
     * Queue an operation for transaction
     */
    public function addOperation(callable $operation): void
    {
        $this->operations[] = $operation;
    }
    
    public function commit(): void
    {
        if (!$this->active) {
            return;
        }
        
        try {
            $this->cluster->transactions()->run(function (TransactionAttemptContext $ctx) {
                $this->context = $ctx;
                foreach ($this->operations as $operation) {
                    $operation($ctx);
                }
            });
            $this->active = false;
        } catch (\Exception $e) {
            $this->active = false;
            throw $e;
        }
    }
    
    public function rollback(): void
    {
        // In Couchbase transactions, rollback is automatic on exception
        $this->operations = [];
        $this->active = false;
    }
    
    public function isActive(): bool
    {
        return $this->active;
    }
}
```

**Acceptance Criteria**:
- [ ] All interface methods implemented for Couchbase
- [ ] Document keys follow consistent pattern
- [ ] N1QL queries generated correctly
- [ ] Scope mapping works for collections

### 2.4 Adapter Factory

#### 2.4.1 Factory Implementation
**File**: `/protected/components/database/DatabaseAdapterFactory.php` (create)

```php
<?php
/**
 * Factory for creating database adapters
 */

namespace OE\Database;

class DatabaseAdapterFactory
{
    const ADAPTER_MARIADB = 'mariadb';
    const ADAPTER_COUCHBASE = 'couchbase';
    
    private static $instances = [];
    
    /**
     * Get adapter instance
     * @param string $type Adapter type
     * @return DatabaseAdapterInterface
     */
    public static function getAdapter(string $type = null): DatabaseAdapterInterface
    {
        // Default to MariaDB during migration
        $type = $type ?? self::getDefaultAdapter();
        
        if (!isset(self::$instances[$type])) {
            self::$instances[$type] = self::createAdapter($type);
        }
        
        return self::$instances[$type];
    }
    
    /**
     * Create adapter instance
     */
    private static function createAdapter(string $type): DatabaseAdapterInterface
    {
        switch ($type) {
            case self::ADAPTER_MARIADB:
                return new MariaDbAdapter();
            case self::ADAPTER_COUCHBASE:
                return new CouchbaseAdapter();
            default:
                throw new \InvalidArgumentException("Unknown adapter type: $type");
        }
    }
    
    /**
     * Get default adapter type from configuration
     */
    public static function getDefaultAdapter(): string
    {
        // Check environment/configuration for default
        $default = \Yii::app()->params['database_adapter'] ?? self::ADAPTER_MARIADB;
        return $default;
    }
    
    /**
     * Check if Couchbase adapter should be used for a collection
     */
    public static function shouldUseCouchbase(string $collection): bool
    {
        // Feature flag check
        $migratedCollections = \Yii::app()->params['couchbase_migrated_collections'] ?? [];
        return in_array($collection, $migratedCollections);
    }
    
    /**
     * Get appropriate adapter for a collection
     */
    public static function getAdapterForCollection(string $collection): DatabaseAdapterInterface
    {
        if (self::shouldUseCouchbase($collection)) {
            return self::getAdapter(self::ADAPTER_COUCHBASE);
        }
        return self::getAdapter(self::ADAPTER_MARIADB);
    }
    
    /**
     * Clear cached instances (for testing)
     */
    public static function clearInstances(): void
    {
        self::$instances = [];
    }
}
```

**Acceptance Criteria**:
- [ ] Factory returns correct adapter types
- [ ] Collection-based routing works
- [ ] Feature flags control adapter selection

### 2.5 Configuration Updates

#### 2.5.1 Feature Flags
**File**: `/protected/config/core/common.php` (update params section)

Add to params array:
```php
'params' => array(
    // ... existing params ...
    
    // Database adapter configuration
    'database_adapter' => getenv('DATABASE_ADAPTER') ?: 'mariadb', // 'mariadb' or 'couchbase'
    
    // Collections migrated to Couchbase (empty during Phase 2)
    'couchbase_migrated_collections' => [],
    
    // Enable dual-write for testing (write to both databases)
    'enable_dual_write' => strtolower(getenv('ENABLE_DUAL_WRITE')) === 'true',
    
    // Enable read from Couchbase for testing
    'enable_couchbase_read' => strtolower(getenv('ENABLE_COUCHBASE_READ')) === 'true',
),
```

**Acceptance Criteria**:
- [ ] Feature flags configurable via environment
- [ ] Default to MariaDB
- [ ] Dual-write can be enabled

### 2.6 Dual-Write Support

#### 2.6.1 Dual-Write Adapter
**File**: `/protected/components/database/DualWriteAdapter.php` (create)

```php
<?php
/**
 * Dual-write adapter for migration testing
 * Writes to both MariaDB and Couchbase, reads from primary
 */

namespace OE\Database;

class DualWriteAdapter implements DatabaseAdapterInterface
{
    private $primary;
    private $secondary;
    private $readFromSecondary = false;
    
    public function __construct(
        DatabaseAdapterInterface $primary = null,
        DatabaseAdapterInterface $secondary = null
    ) {
        $this->primary = $primary ?? new MariaDbAdapter();
        $this->secondary = $secondary ?? new CouchbaseAdapter();
    }
    
    public function setReadFromSecondary(bool $enabled): void
    {
        $this->readFromSecondary = $enabled;
    }
    
    public function findByPk(string $collection, $pk): ?array
    {
        $adapter = $this->readFromSecondary ? $this->secondary : $this->primary;
        return $adapter->findByPk($collection, $pk);
    }
    
    public function findByAttributes(string $collection, array $attributes, array $options = []): array
    {
        $adapter = $this->readFromSecondary ? $this->secondary : $this->primary;
        return $adapter->findByAttributes($collection, $attributes, $options);
    }
    
    public function findAll(string $collection, array $options = []): array
    {
        $adapter = $this->readFromSecondary ? $this->secondary : $this->primary;
        return $adapter->findAll($collection, $options);
    }
    
    public function insert(string $collection, array $data)
    {
        // Insert into primary first
        $id = $this->primary->insert($collection, $data);
        
        // Then insert into secondary
        try {
            $data['id'] = $id;
            $this->secondary->insert($collection, $data);
        } catch (\Exception $e) {
            \Yii::log(
                "Dual-write secondary insert failed for {$collection}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING
            );
        }
        
        return $id;
    }
    
    public function update(string $collection, $pk, array $data): bool
    {
        $primaryResult = $this->primary->update($collection, $pk, $data);
        
        try {
            $this->secondary->update($collection, $pk, $data);
        } catch (\Exception $e) {
            \Yii::log(
                "Dual-write secondary update failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING
            );
        }
        
        return $primaryResult;
    }
    
    public function delete(string $collection, $pk): bool
    {
        $primaryResult = $this->primary->delete($collection, $pk);
        
        try {
            $this->secondary->delete($collection, $pk);
        } catch (\Exception $e) {
            \Yii::log(
                "Dual-write secondary delete failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING
            );
        }
        
        return $primaryResult;
    }
    
    public function query(string $query, array $params = []): array
    {
        // Queries are adapter-specific, only run on primary
        return $this->primary->query($query, $params);
    }
    
    public function beginTransaction()
    {
        // Only primary transaction is managed
        return $this->primary->beginTransaction();
    }
    
    public function count(string $collection, array $criteria = []): int
    {
        $adapter = $this->readFromSecondary ? $this->secondary : $this->primary;
        return $adapter->count($collection, $criteria);
    }
    
    public function exists(string $collection, $pk): bool
    {
        $adapter = $this->readFromSecondary ? $this->secondary : $this->primary;
        return $adapter->exists($collection, $pk);
    }
}
```

**Acceptance Criteria**:
- [ ] Writes go to both databases
- [ ] Primary failures stop operation
- [ ] Secondary failures are logged but don't stop operation
- [ ] Read source is configurable

### 2.7 Unit Tests

#### 2.7.1 Adapter Tests
**File**: `/protected/tests/unit/components/database/MariaDbAdapterTest.php` (create)

```php
<?php
/**
 * Unit tests for MariaDbAdapter
 */

namespace OE\Tests\Unit\Database;

use OE\Database\MariaDbAdapter;

class MariaDbAdapterTest extends \CDbTestCase
{
    private $adapter;
    
    protected function setUp()
    {
        parent::setUp();
        $this->adapter = new MariaDbAdapter();
    }
    
    public function testFindByPkReturnsRecord()
    {
        // Test with existing record
        $result = $this->adapter->findByPk('user', 1);
        $this->assertNotNull($result);
        $this->assertEquals(1, $result['id']);
    }
    
    public function testFindByPkReturnsNullForMissing()
    {
        $result = $this->adapter->findByPk('user', 999999);
        $this->assertNull($result);
    }
    
    public function testFindByAttributesReturnsArray()
    {
        $result = $this->adapter->findByAttributes('user', ['active' => 1]);
        $this->assertIsArray($result);
    }
    
    public function testInsertReturnsId()
    {
        $data = [
            'username' => 'test_' . uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'active' => 0,
        ];
        
        $id = $this->adapter->insert('user', $data);
        $this->assertNotNull($id);
        $this->assertIsNumeric($id);
        
        // Cleanup
        $this->adapter->delete('user', $id);
    }
    
    public function testCountReturnsInteger()
    {
        $count = $this->adapter->count('user');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
}
```

**Acceptance Criteria**:
- [ ] All adapter methods have test coverage
- [ ] Tests pass against existing database
- [ ] No test data persists after test runs

## Testing Criteria

### Unit Tests
- [ ] MariaDbAdapter all methods tested
- [ ] CouchbaseAdapter all methods tested
- [ ] DualWriteAdapter all methods tested
- [ ] Factory returns correct adapters

### Integration Tests
- [ ] MariaDB adapter works with real database
- [ ] Couchbase adapter works with real cluster
- [ ] Dual-write correctly updates both databases
- [ ] Feature flags correctly route requests

### Compatibility Tests
- [ ] Existing application still functions
- [ ] All existing tests pass
- [ ] No regression in performance

## Rollback Plan

1. Set `database_adapter` back to `mariadb`
2. Disable dual-write: `ENABLE_DUAL_WRITE=false`
3. Clear `couchbase_migrated_collections` array
4. Remove new component files if needed

## Definition of Done

- [ ] All interface methods implemented for both adapters
- [ ] Factory correctly routes requests
- [ ] Dual-write works without affecting existing functionality
- [ ] All tests passing
- [ ] Documentation complete
- [ ] Code reviewed and approved

---

*Phase 2 Completion Sign-off:*
- [ ] Technical Lead
- [ ] QA

*Estimated Duration: 2-3 weeks*
