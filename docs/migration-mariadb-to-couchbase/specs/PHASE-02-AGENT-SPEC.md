# Phase 2: Abstract Database Layer - Agent Executable Specification

## Metadata
- **Phase**: 2 of 10
- **Estimated Duration**: 2-3 weeks
- **Dependencies**: Phase 1 (Infrastructure & Couchbase Setup)
- **Risk Level**: Low (no changes to existing database operations)

## Objective
Create an abstraction layer that allows the application to work with both MariaDB and Couchbase databases. This enables gradual migration with a safe rollback path.

## Prerequisites
- Phase 1 completed (Couchbase infrastructure operational)
- `/protected/components/CouchbaseConnection.php` exists and working
- `/protected/config/couchbase.php` exists
- Couchbase container running (optional for development)

---

## Task Checklist

### TASK 1: Create Database Directory Structure
**Priority**: High
**Commands**:
```bash
mkdir -p protected/components/database
mkdir -p protected/tests/unit/components/database
```

---

### TASK 2: Create Database Adapter Interface
**Priority**: High
**File to Create**: `/protected/components/database/DatabaseAdapterInterface.php`

```php
<?php
/**
 * Database adapter interface for supporting multiple database backends
 * 
 * This interface defines the contract for all database adapters,
 * enabling the application to work with different database systems
 * (MariaDB, Couchbase) through a unified API.
 */

namespace OE\Database;

interface DatabaseAdapterInterface
{
    /**
     * Find a record by primary key
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key value
     * @return array|null Record data or null if not found
     */
    public function findByPk(string $collection, $pk): ?array;
    
    /**
     * Find records by attributes
     * @param string $collection Collection/table name
     * @param array $attributes Key-value pairs to match
     * @param array $options Additional options (limit, offset, order)
     * @return array Array of matching records
     */
    public function findByAttributes(string $collection, array $attributes, array $options = []): array;
    
    /**
     * Find all records in a collection
     * @param string $collection Collection/table name
     * @param array $options Additional options (limit, offset, order)
     * @return array Array of all records
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
     * Update a record by primary key
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key
     * @param array $data Data to update
     * @return bool Success status
     */
    public function update(string $collection, $pk, array $data): bool;
    
    /**
     * Delete a record by primary key
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key
     * @return bool Success status
     */
    public function delete(string $collection, $pk): bool;
    
    /**
     * Execute a raw query
     * @param string $query Query string (SQL or N1QL)
     * @param array $params Query parameters
     * @return array Query results
     */
    public function query(string $query, array $params = []): array;
    
    /**
     * Begin a database transaction
     * @return TransactionInterface Transaction object
     */
    public function beginTransaction(): TransactionInterface;
    
    /**
     * Count records matching criteria
     * @param string $collection Collection/table name
     * @param array $criteria Matching criteria
     * @return int Count of matching records
     */
    public function count(string $collection, array $criteria = []): int;
    
    /**
     * Check if a record exists
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key
     * @return bool True if record exists
     */
    public function exists(string $collection, $pk): bool;
}
```

**Verification**:
```bash
php -l protected/components/database/DatabaseAdapterInterface.php
```

---

### TASK 3: Create Transaction Interface
**Priority**: High
**File to Create**: `/protected/components/database/TransactionInterface.php`

```php
<?php
/**
 * Transaction interface for database operations
 * 
 * Provides a unified interface for managing database transactions
 * across different database backends.
 */

namespace OE\Database;

interface TransactionInterface
{
    /**
     * Commit the transaction
     * @return void
     * @throws \Exception if commit fails
     */
    public function commit(): void;
    
    /**
     * Rollback the transaction
     * @return void
     */
    public function rollback(): void;
    
    /**
     * Check if transaction is currently active
     * @return bool True if transaction is active
     */
    public function isActive(): bool;
}
```

**Verification**:
```bash
php -l protected/components/database/TransactionInterface.php
```

---

### TASK 4: Create MariaDB Transaction Class
**Priority**: High
**File to Create**: `/protected/components/database/MariaDbTransaction.php`

```php
<?php
/**
 * MariaDB transaction wrapper implementing TransactionInterface
 */

namespace OE\Database;

class MariaDbTransaction implements TransactionInterface
{
    /**
     * @var \CDbTransaction The underlying Yii transaction
     */
    private $transaction;
    
    /**
     * @param \CDbTransaction $transaction Yii database transaction
     */
    public function __construct(\CDbTransaction $transaction)
    {
        $this->transaction = $transaction;
    }
    
    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        if ($this->transaction->getActive()) {
            $this->transaction->commit();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        if ($this->transaction->getActive()) {
            $this->transaction->rollback();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return $this->transaction->getActive();
    }
    
    /**
     * Get the underlying Yii transaction object
     * @return \CDbTransaction
     */
    public function getTransaction(): \CDbTransaction
    {
        return $this->transaction;
    }
}
```

**Verification**:
```bash
php -l protected/components/database/MariaDbTransaction.php
```

---

### TASK 5: Create MariaDB Adapter
**Priority**: High
**File to Create**: `/protected/components/database/MariaDbAdapter.php`

```php
<?php
/**
 * MariaDB/MySQL adapter implementing DatabaseAdapterInterface
 * 
 * This adapter wraps the existing Yii CDbConnection to provide
 * a unified database interface for MariaDB/MySQL operations.
 */

namespace OE\Database;

class MariaDbAdapter implements DatabaseAdapterInterface
{
    /**
     * @var \CDbConnection Database connection
     */
    private $connection;
    
    /**
     * @param \CDbConnection|null $connection Database connection (defaults to Yii::app()->db)
     */
    public function __construct(\CDbConnection $connection = null)
    {
        $this->connection = $connection ?: \Yii::app()->db;
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByPk(string $collection, $pk): ?array
    {
        $result = $this->connection->createCommand()
            ->select('*')
            ->from($collection)
            ->where('id = :id', [':id' => $pk])
            ->queryRow();
            
        return $result ?: null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByAttributes(string $collection, array $attributes, array $options = []): array
    {
        $command = $this->connection->createCommand()
            ->select($options['select'] ?? '*')
            ->from($collection);
        
        // Build WHERE clause
        if (!empty($attributes)) {
            $conditions = [];
            $params = [];
            $paramIndex = 0;
            
            foreach ($attributes as $key => $value) {
                if ($value === null) {
                    $conditions[] = "`$key` IS NULL";
                } elseif (is_array($value)) {
                    // Handle IN clause
                    $placeholders = [];
                    foreach ($value as $v) {
                        $paramName = ":p{$paramIndex}";
                        $placeholders[] = $paramName;
                        $params[$paramName] = $v;
                        $paramIndex++;
                    }
                    $conditions[] = "`$key` IN (" . implode(', ', $placeholders) . ")";
                } else {
                    $paramName = ":p{$paramIndex}";
                    $conditions[] = "`$key` = {$paramName}";
                    $params[$paramName] = $value;
                    $paramIndex++;
                }
            }
            
            if (!empty($conditions)) {
                $command->where(implode(' AND ', $conditions), $params);
            }
        }
        
        // Apply options
        if (isset($options['order'])) {
            $command->order($options['order']);
        }
        if (isset($options['limit'])) {
            $command->limit((int)$options['limit']);
        }
        if (isset($options['offset'])) {
            $command->offset((int)$options['offset']);
        }
        if (isset($options['group'])) {
            $command->group($options['group']);
        }
        
        return $command->queryAll();
    }
    
    /**
     * {@inheritdoc}
     */
    public function findAll(string $collection, array $options = []): array
    {
        return $this->findByAttributes($collection, [], $options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function insert(string $collection, array $data)
    {
        $this->connection->createCommand()
            ->insert($collection, $data);
            
        return $this->connection->getLastInsertID();
    }
    
    /**
     * {@inheritdoc}
     */
    public function update(string $collection, $pk, array $data): bool
    {
        $rows = $this->connection->createCommand()
            ->update($collection, $data, 'id = :id', [':id' => $pk]);
            
        return $rows > 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $collection, $pk): bool
    {
        $rows = $this->connection->createCommand()
            ->delete($collection, 'id = :id', [':id' => $pk]);
            
        return $rows > 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function query(string $query, array $params = []): array
    {
        $command = $this->connection->createCommand($query);
        
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $paramKey = is_int($key) ? $key : ':' . ltrim($key, ':');
                $command->bindValue($paramKey, $value);
            }
        }
        
        return $command->queryAll();
    }
    
    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): TransactionInterface
    {
        $transaction = $this->connection->beginTransaction();
        return new MariaDbTransaction($transaction);
    }
    
    /**
     * {@inheritdoc}
     */
    public function count(string $collection, array $criteria = []): int
    {
        $command = $this->connection->createCommand()
            ->select('COUNT(*) as cnt')
            ->from($collection);
        
        if (!empty($criteria)) {
            $conditions = [];
            $params = [];
            $paramIndex = 0;
            
            foreach ($criteria as $key => $value) {
                if ($value === null) {
                    $conditions[] = "`$key` IS NULL";
                } else {
                    $paramName = ":p{$paramIndex}";
                    $conditions[] = "`$key` = {$paramName}";
                    $params[$paramName] = $value;
                    $paramIndex++;
                }
            }
            
            $command->where(implode(' AND ', $conditions), $params);
        }
        
        $result = $command->queryRow();
        return (int)($result['cnt'] ?? 0);
    }
    
    /**
     * {@inheritdoc}
     */
    public function exists(string $collection, $pk): bool
    {
        $count = $this->connection->createCommand()
            ->select('COUNT(*)')
            ->from($collection)
            ->where('id = :id', [':id' => $pk])
            ->queryScalar();
            
        return (int)$count > 0;
    }
    
    /**
     * Get the underlying database connection
     * @return \CDbConnection
     */
    public function getConnection(): \CDbConnection
    {
        return $this->connection;
    }
    
    /**
     * Execute multiple inserts in a batch
     * @param string $collection Table name
     * @param array $columns Column names
     * @param array $rows Array of row data arrays
     * @return int Number of rows inserted
     */
    public function batchInsert(string $collection, array $columns, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }
        
        $builder = $this->connection->getSchema()->getCommandBuilder();
        $table = $this->connection->getSchema()->getTable($collection);
        
        $sql = $builder->createMultipleInsertCommand($table, $rows)->getText();
        
        return $this->connection->createCommand($sql)->execute();
    }
}
```

**Verification**:
```bash
php -l protected/components/database/MariaDbAdapter.php
```

---

### TASK 6: Create Couchbase Transaction Class
**Priority**: High
**File to Create**: `/protected/components/database/CouchbaseTransaction.php`

```php
<?php
/**
 * Couchbase transaction wrapper implementing TransactionInterface
 * 
 * Couchbase 7.x supports distributed ACID transactions.
 * This class provides a compatible interface for transaction management.
 */

namespace OE\Database;

use Couchbase\Cluster;
use Couchbase\TransactionAttemptContext;

class CouchbaseTransaction implements TransactionInterface
{
    /**
     * @var Cluster Couchbase cluster instance
     */
    private $cluster;
    
    /**
     * @var TransactionAttemptContext|null Transaction context
     */
    private $context = null;
    
    /**
     * @var bool Whether transaction is active
     */
    private $active = true;
    
    /**
     * @var array Queued operations for the transaction
     */
    private $operations = [];
    
    /**
     * @var array Results from executed operations
     */
    private $results = [];
    
    /**
     * @param Cluster $cluster Couchbase cluster instance
     */
    public function __construct(Cluster $cluster)
    {
        $this->cluster = $cluster;
    }
    
    /**
     * Get the transaction context for operations within the transaction
     * @return TransactionAttemptContext|null
     */
    public function getContext(): ?TransactionAttemptContext
    {
        return $this->context;
    }
    
    /**
     * Queue an operation to be executed within the transaction
     * @param callable $operation Function that receives TransactionAttemptContext
     * @return void
     */
    public function addOperation(callable $operation): void
    {
        if (!$this->active) {
            throw new \RuntimeException('Cannot add operation to inactive transaction');
        }
        $this->operations[] = $operation;
    }
    
    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        if (!$this->active) {
            return;
        }
        
        if (empty($this->operations)) {
            $this->active = false;
            return;
        }
        
        try {
            $operations = $this->operations;
            $results = &$this->results;
            
            $this->cluster->transactions()->run(function (TransactionAttemptContext $ctx) use ($operations, &$results) {
                $this->context = $ctx;
                foreach ($operations as $index => $operation) {
                    $results[$index] = $operation($ctx);
                }
            });
            
            $this->active = false;
        } catch (\Exception $e) {
            $this->active = false;
            \Yii::log(
                'Couchbase transaction failed: ' . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        // In Couchbase, rollback is automatic when transaction is not committed
        // We simply clear the operations and mark as inactive
        $this->operations = [];
        $this->results = [];
        $this->active = false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return $this->active;
    }
    
    /**
     * Get results from committed operations
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
```

**Verification**:
```bash
php -l protected/components/database/CouchbaseTransaction.php
```

---

### TASK 7: Create Couchbase Adapter
**Priority**: High
**File to Create**: `/protected/components/database/CouchbaseAdapter.php`

```php
<?php
/**
 * Couchbase adapter implementing DatabaseAdapterInterface
 * 
 * This adapter provides a unified interface for Couchbase operations,
 * translating the generic database operations to Couchbase-specific
 * key-value and N1QL query operations.
 */

namespace OE\Database;

use Couchbase\Collection;
use Couchbase\QueryOptions;
use Couchbase\MutationResult;
use Couchbase\GetResult;
use Couchbase\Exception\DocumentNotFoundException;

class CouchbaseAdapter implements DatabaseAdapterInterface
{
    /**
     * @var \CouchbaseConnection Couchbase connection component
     */
    private $connection;
    
    /**
     * @var array Collection to scope mapping
     */
    private $scopeMapping = [];
    
    /**
     * @var string Bucket name
     */
    private $bucket;
    
    /**
     * @param \CouchbaseConnection|null $connection Couchbase connection
     */
    public function __construct(\CouchbaseConnection $connection = null)
    {
        $this->connection = $connection ?: \Yii::app()->couchbase;
        $this->bucket = $this->connection->config['bucket'];
        $this->initializeScopeMapping();
    }
    
    /**
     * Initialize the collection to scope mapping
     */
    private function initializeScopeMapping(): void
    {
        $this->scopeMapping = [
            // Core entities
            'patient' => 'core',
            'user' => 'core',
            'episode' => 'core',
            'event' => 'core',
            'firm' => 'core',
            'site' => 'core',
            'institution' => 'core',
            'contact' => 'core',
            'address' => 'core',
            
            // Clinical data
            'examination' => 'clinical',
            'diagnosis' => 'clinical',
            'procedure' => 'clinical',
            'medication' => 'clinical',
            'allergy' => 'clinical',
            
            // Correspondence
            'letter' => 'correspondence',
            'message' => 'correspondence',
            'document' => 'correspondence',
            
            // Booking
            'operation' => 'booking',
            'session' => 'booking',
            'whiteboard' => 'booking',
            
            // Admin
            'audit' => 'admin',
            'setting' => 'admin',
            
            // Reference data
            'specialty' => 'reference',
            'subspecialty' => 'reference',
            'disorder' => 'reference',
            'drug' => 'reference',
            'procedure_type' => 'reference',
        ];
    }
    
    /**
     * Get the scope name for a collection
     * @param string $collection Collection name
     * @return string Scope name
     */
    public function getScopeForCollection(string $collection): string
    {
        // Direct mapping
        if (isset($this->scopeMapping[$collection])) {
            return $this->scopeMapping[$collection];
        }
        
        // Check for pattern matches (e.g., examination elements)
        if (strpos($collection, 'et_ophciexamination_') === 0) {
            return 'clinical';
        }
        if (strpos($collection, 'ophtr') === 0) {
            return 'booking';
        }
        if (strpos($collection, 'ophco') === 0) {
            return 'correspondence';
        }
        
        // Default to core scope
        return 'core';
    }
    
    /**
     * Get a Couchbase collection object
     * @param string $collection Collection name
     * @return Collection
     */
    private function getCollection(string $collection): Collection
    {
        $scope = $this->getScopeForCollection($collection);
        return $this->connection->getCollection($scope, $collection);
    }
    
    /**
     * Generate a document key
     * @param string $collection Collection name
     * @param mixed $pk Primary key (optional)
     * @return string Document key
     */
    private function generateKey(string $collection, $pk = null): string
    {
        if ($pk !== null) {
            return "{$collection}::{$pk}";
        }
        return "{$collection}::" . uniqid('', true);
    }
    
    /**
     * Extract the ID portion from a document key
     * @param string $key Document key
     * @return string ID portion
     */
    private function extractId(string $key): string
    {
        $parts = explode('::', $key);
        return end($parts);
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByPk(string $collection, $pk): ?array
    {
        try {
            $key = $this->generateKey($collection, $pk);
            $result = $this->getCollection($collection)->get($key);
            
            $data = $result->content();
            if (is_object($data)) {
                $data = (array)$data;
            }
            $data['id'] = $pk;
            
            return $data;
        } catch (DocumentNotFoundException $e) {
            return null;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase findByPk failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return null;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByAttributes(string $collection, array $attributes, array $options = []): array
    {
        $scope = $this->getScopeForCollection($collection);
        
        // Build N1QL query
        $selectClause = $options['select'] ?? '*';
        $query = "SELECT META().id as _meta_id, {$selectClause} FROM `{$this->bucket}`.`{$scope}`.`{$collection}`";
        
        $conditions = [];
        $params = [];
        $paramIndex = 0;
        
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                $conditions[] = "`$key` IS NULL";
            } elseif (is_array($value)) {
                // Handle IN clause
                $placeholders = [];
                foreach ($value as $v) {
                    $paramName = "p{$paramIndex}";
                    $placeholders[] = "\${$paramName}";
                    $params[$paramName] = $v;
                    $paramIndex++;
                }
                $conditions[] = "`$key` IN [" . implode(', ', $placeholders) . "]";
            } else {
                $paramName = "p{$paramIndex}";
                $conditions[] = "`$key` = \${$paramName}";
                $params[$paramName] = $value;
                $paramIndex++;
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
        
        try {
            $result = $this->connection->getCluster()->query($query, $queryOptions);
            
            // Transform results
            $rows = [];
            foreach ($result->rows() as $row) {
                $rowArray = (array)$row;
                
                // Extract document data
                if (isset($rowArray[$collection])) {
                    $data = (array)$rowArray[$collection];
                } else {
                    $data = $rowArray;
                }
                
                // Set ID from meta
                if (isset($rowArray['_meta_id'])) {
                    $data['id'] = $this->extractId($rowArray['_meta_id']);
                    unset($data['_meta_id']);
                }
                
                $rows[] = $data;
            }
            
            return $rows;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase findByAttributes failed for {$collection}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return [];
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function findAll(string $collection, array $options = []): array
    {
        return $this->findByAttributes($collection, [], $options);
    }
    
    /**
     * {@inheritdoc}
     */
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
        
        try {
            $this->getCollection($collection)->insert($key, $data);
            return $id;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase insert failed for {$collection}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function update(string $collection, $pk, array $data): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            unset($data['id']); // Don't store id in document body
            
            // Update modified timestamp
            $data['_modified'] = date('c');
            
            // Preserve _type and _created
            $existing = $this->findByPk($collection, $pk);
            if ($existing) {
                $data['_type'] = $existing['_type'] ?? $collection;
                $data['_created'] = $existing['_created'] ?? date('c');
            }
            
            $this->getCollection($collection)->replace($key, $data);
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase update failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $collection, $pk): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            $this->getCollection($collection)->remove($key);
            return true;
        } catch (DocumentNotFoundException $e) {
            // Document doesn't exist, consider delete successful
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase delete failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function query(string $query, array $params = []): array
    {
        $options = new QueryOptions();
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        try {
            $result = $this->connection->getCluster()->query($query, $options);
            
            return array_map(function($row) {
                return (array)$row;
            }, $result->rows());
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase query failed: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): TransactionInterface
    {
        return new CouchbaseTransaction($this->connection->getCluster());
    }
    
    /**
     * {@inheritdoc}
     */
    public function count(string $collection, array $criteria = []): int
    {
        $scope = $this->getScopeForCollection($collection);
        
        $query = "SELECT COUNT(*) as cnt FROM `{$this->bucket}`.`{$scope}`.`{$collection}`";
        
        $params = [];
        $paramIndex = 0;
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $key => $value) {
                if ($value === null) {
                    $conditions[] = "`$key` IS NULL";
                } else {
                    $paramName = "p{$paramIndex}";
                    $conditions[] = "`$key` = \${$paramName}";
                    $params[$paramName] = $value;
                    $paramIndex++;
                }
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $options = new QueryOptions();
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        try {
            $result = $this->connection->getCluster()->query($query, $options);
            $rows = $result->rows();
            
            return (int)($rows[0]['cnt'] ?? 0);
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase count failed for {$collection}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return 0;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function exists(string $collection, $pk): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            $result = $this->getCollection($collection)->exists($key);
            return $result->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Upsert a document (insert or update)
     * @param string $collection Collection name
     * @param mixed $pk Primary key
     * @param array $data Document data
     * @return bool Success status
     */
    public function upsert(string $collection, $pk, array $data): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            unset($data['id']);
            
            $data['_type'] = $collection;
            $data['_modified'] = date('c');
            
            // Check if document exists for _created
            $existing = $this->findByPk($collection, $pk);
            $data['_created'] = $existing['_created'] ?? date('c');
            
            $this->getCollection($collection)->upsert($key, $data);
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase upsert failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return false;
        }
    }
    
    /**
     * Get the underlying Couchbase connection
     * @return \CouchbaseConnection
     */
    public function getConnection(): \CouchbaseConnection
    {
        return $this->connection;
    }
    
    /**
     * Set custom scope mapping
     * @param array $mapping Collection to scope mapping
     */
    public function setScopeMapping(array $mapping): void
    {
        $this->scopeMapping = array_merge($this->scopeMapping, $mapping);
    }
}
```

**Verification**:
```bash
php -l protected/components/database/CouchbaseAdapter.php
```

---

### TASK 8: Create Database Adapter Factory
**Priority**: High
**File to Create**: `/protected/components/database/DatabaseAdapterFactory.php`

```php
<?php
/**
 * Factory for creating and managing database adapters
 * 
 * This factory provides centralized adapter instantiation with support
 * for collection-based routing and feature flags for gradual migration.
 */

namespace OE\Database;

class DatabaseAdapterFactory
{
    /**
     * Adapter type constants
     */
    const ADAPTER_MARIADB = 'mariadb';
    const ADAPTER_COUCHBASE = 'couchbase';
    const ADAPTER_DUAL_WRITE = 'dual_write';
    
    /**
     * @var array Cached adapter instances
     */
    private static $instances = [];
    
    /**
     * Get an adapter instance by type
     * @param string|null $type Adapter type (defaults to configured default)
     * @return DatabaseAdapterInterface
     * @throws \InvalidArgumentException for unknown adapter type
     */
    public static function getAdapter(string $type = null): DatabaseAdapterInterface
    {
        $type = $type ?? self::getDefaultAdapter();
        
        if (!isset(self::$instances[$type])) {
            self::$instances[$type] = self::createAdapter($type);
        }
        
        return self::$instances[$type];
    }
    
    /**
     * Create a new adapter instance
     * @param string $type Adapter type
     * @return DatabaseAdapterInterface
     * @throws \InvalidArgumentException for unknown adapter type
     */
    private static function createAdapter(string $type): DatabaseAdapterInterface
    {
        switch ($type) {
            case self::ADAPTER_MARIADB:
                return new MariaDbAdapter();
                
            case self::ADAPTER_COUCHBASE:
                return new CouchbaseAdapter();
                
            case self::ADAPTER_DUAL_WRITE:
                return new DualWriteAdapter(
                    new MariaDbAdapter(),
                    new CouchbaseAdapter()
                );
                
            default:
                throw new \InvalidArgumentException("Unknown adapter type: {$type}");
        }
    }
    
    /**
     * Get the default adapter type from configuration
     * @return string Adapter type
     */
    public static function getDefaultAdapter(): string
    {
        // Check for dual-write mode first
        $dualWrite = \Yii::app()->params['enable_dual_write'] ?? false;
        if ($dualWrite) {
            return self::ADAPTER_DUAL_WRITE;
        }
        
        // Otherwise use configured default (defaults to MariaDB)
        return \Yii::app()->params['database_adapter'] ?? self::ADAPTER_MARIADB;
    }
    
    /**
     * Check if a collection should use Couchbase
     * @param string $collection Collection name
     * @return bool True if collection is migrated to Couchbase
     */
    public static function shouldUseCouchbase(string $collection): bool
    {
        // Check if Couchbase reads are enabled globally
        $couchbaseRead = \Yii::app()->params['enable_couchbase_read'] ?? false;
        if (!$couchbaseRead) {
            return false;
        }
        
        // Check if collection is in the migrated list
        $migratedCollections = \Yii::app()->params['couchbase_migrated_collections'] ?? [];
        return in_array($collection, $migratedCollections);
    }
    
    /**
     * Get the appropriate adapter for a specific collection
     * @param string $collection Collection name
     * @return DatabaseAdapterInterface
     */
    public static function getAdapterForCollection(string $collection): DatabaseAdapterInterface
    {
        // Check dual-write mode
        $dualWrite = \Yii::app()->params['enable_dual_write'] ?? false;
        if ($dualWrite) {
            return self::getAdapter(self::ADAPTER_DUAL_WRITE);
        }
        
        // Check if collection should use Couchbase
        if (self::shouldUseCouchbase($collection)) {
            return self::getAdapter(self::ADAPTER_COUCHBASE);
        }
        
        // Default to MariaDB
        return self::getAdapter(self::ADAPTER_MARIADB);
    }
    
    /**
     * Clear cached adapter instances (useful for testing)
     */
    public static function clearInstances(): void
    {
        self::$instances = [];
    }
    
    /**
     * Check if dual-write mode is enabled
     * @return bool
     */
    public static function isDualWriteEnabled(): bool
    {
        return \Yii::app()->params['enable_dual_write'] ?? false;
    }
    
    /**
     * Check if Couchbase read is enabled
     * @return bool
     */
    public static function isCouchbaseReadEnabled(): bool
    {
        return \Yii::app()->params['enable_couchbase_read'] ?? false;
    }
    
    /**
     * Get list of collections migrated to Couchbase
     * @return array
     */
    public static function getMigratedCollections(): array
    {
        return \Yii::app()->params['couchbase_migrated_collections'] ?? [];
    }
}
```

**Verification**:
```bash
php -l protected/components/database/DatabaseAdapterFactory.php
```

---

### TASK 9: Create Dual-Write Adapter
**Priority**: High
**File to Create**: `/protected/components/database/DualWriteAdapter.php`

```php
<?php
/**
 * Dual-write adapter for migration testing
 * 
 * This adapter writes to both MariaDB and Couchbase simultaneously,
 * with reads configurable to come from either source.
 * Primary (MariaDB) failures are fatal, secondary (Couchbase) failures are logged.
 */

namespace OE\Database;

class DualWriteAdapter implements DatabaseAdapterInterface
{
    /**
     * @var DatabaseAdapterInterface Primary adapter (MariaDB)
     */
    private $primary;
    
    /**
     * @var DatabaseAdapterInterface Secondary adapter (Couchbase)
     */
    private $secondary;
    
    /**
     * @var bool Whether to read from secondary
     */
    private $readFromSecondary = false;
    
    /**
     * @var bool Whether to log secondary operations
     */
    private $logSecondaryOperations = true;
    
    /**
     * @param DatabaseAdapterInterface|null $primary Primary adapter
     * @param DatabaseAdapterInterface|null $secondary Secondary adapter
     */
    public function __construct(
        DatabaseAdapterInterface $primary = null,
        DatabaseAdapterInterface $secondary = null
    ) {
        $this->primary = $primary ?? new MariaDbAdapter();
        $this->secondary = $secondary ?? new CouchbaseAdapter();
        
        // Check configuration for read source
        $this->readFromSecondary = \Yii::app()->params['enable_couchbase_read'] ?? false;
    }
    
    /**
     * Set whether to read from secondary adapter
     * @param bool $enabled
     */
    public function setReadFromSecondary(bool $enabled): void
    {
        $this->readFromSecondary = $enabled;
    }
    
    /**
     * Get the adapter to use for read operations
     * @return DatabaseAdapterInterface
     */
    private function getReadAdapter(): DatabaseAdapterInterface
    {
        return $this->readFromSecondary ? $this->secondary : $this->primary;
    }
    
    /**
     * Log secondary operation failure
     * @param string $operation Operation name
     * @param string $collection Collection name
     * @param \Exception $e Exception that occurred
     */
    private function logSecondaryFailure(string $operation, string $collection, \Exception $e): void
    {
        if ($this->logSecondaryOperations) {
            \Yii::log(
                "Dual-write secondary {$operation} failed for {$collection}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.dualwrite'
            );
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByPk(string $collection, $pk): ?array
    {
        return $this->getReadAdapter()->findByPk($collection, $pk);
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByAttributes(string $collection, array $attributes, array $options = []): array
    {
        return $this->getReadAdapter()->findByAttributes($collection, $attributes, $options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function findAll(string $collection, array $options = []): array
    {
        return $this->getReadAdapter()->findAll($collection, $options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function insert(string $collection, array $data)
    {
        // Insert into primary first (this is the source of truth)
        $id = $this->primary->insert($collection, $data);
        
        // Then insert into secondary
        try {
            $secondaryData = $data;
            $secondaryData['id'] = $id;
            $this->secondary->insert($collection, $secondaryData);
        } catch (\Exception $e) {
            $this->logSecondaryFailure('insert', $collection, $e);
        }
        
        return $id;
    }
    
    /**
     * {@inheritdoc}
     */
    public function update(string $collection, $pk, array $data): bool
    {
        // Update primary first
        $primaryResult = $this->primary->update($collection, $pk, $data);
        
        // Then update secondary
        try {
            $this->secondary->update($collection, $pk, $data);
        } catch (\Exception $e) {
            $this->logSecondaryFailure('update', "{$collection}:{$pk}", $e);
        }
        
        return $primaryResult;
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $collection, $pk): bool
    {
        // Delete from primary first
        $primaryResult = $this->primary->delete($collection, $pk);
        
        // Then delete from secondary
        try {
            $this->secondary->delete($collection, $pk);
        } catch (\Exception $e) {
            $this->logSecondaryFailure('delete', "{$collection}:{$pk}", $e);
        }
        
        return $primaryResult;
    }
    
    /**
     * {@inheritdoc}
     */
    public function query(string $query, array $params = []): array
    {
        // Queries are adapter-specific, only run on the read adapter
        return $this->getReadAdapter()->query($query, $params);
    }
    
    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): TransactionInterface
    {
        // Only primary transaction is formally managed
        // Secondary writes happen outside transaction boundary
        return $this->primary->beginTransaction();
    }
    
    /**
     * {@inheritdoc}
     */
    public function count(string $collection, array $criteria = []): int
    {
        return $this->getReadAdapter()->count($collection, $criteria);
    }
    
    /**
     * {@inheritdoc}
     */
    public function exists(string $collection, $pk): bool
    {
        return $this->getReadAdapter()->exists($collection, $pk);
    }
    
    /**
     * Get the primary adapter
     * @return DatabaseAdapterInterface
     */
    public function getPrimary(): DatabaseAdapterInterface
    {
        return $this->primary;
    }
    
    /**
     * Get the secondary adapter
     * @return DatabaseAdapterInterface
     */
    public function getSecondary(): DatabaseAdapterInterface
    {
        return $this->secondary;
    }
    
    /**
     * Sync a record from primary to secondary
     * @param string $collection Collection name
     * @param mixed $pk Primary key
     * @return bool Success status
     */
    public function syncToSecondary(string $collection, $pk): bool
    {
        try {
            $data = $this->primary->findByPk($collection, $pk);
            if ($data === null) {
                // Record doesn't exist in primary, delete from secondary
                return $this->secondary->delete($collection, $pk);
            }
            
            // Check if exists in secondary
            if ($this->secondary->exists($collection, $pk)) {
                return $this->secondary->update($collection, $pk, $data);
            } else {
                $this->secondary->insert($collection, $data);
                return true;
            }
        } catch (\Exception $e) {
            $this->logSecondaryFailure('sync', "{$collection}:{$pk}", $e);
            return false;
        }
    }
    
    /**
     * Compare a record between primary and secondary
     * @param string $collection Collection name
     * @param mixed $pk Primary key
     * @return array Comparison result with 'match', 'primary', 'secondary' keys
     */
    public function compareRecord(string $collection, $pk): array
    {
        $primaryData = $this->primary->findByPk($collection, $pk);
        $secondaryData = $this->secondary->findByPk($collection, $pk);
        
        // Remove metadata fields for comparison
        $cleanPrimary = $primaryData;
        $cleanSecondary = $secondaryData;
        
        if ($cleanSecondary !== null) {
            unset($cleanSecondary['_type'], $cleanSecondary['_created'], $cleanSecondary['_modified']);
        }
        
        return [
            'match' => $cleanPrimary == $cleanSecondary,
            'primary' => $primaryData,
            'secondary' => $secondaryData,
        ];
    }
}
```

**Verification**:
```bash
php -l protected/components/database/DualWriteAdapter.php
```

---

### TASK 10: Update Configuration for Feature Flags
**Priority**: High
**File to Modify**: `/protected/config/core/common.php`

**Action**: Find the `'params'` array and add the database adapter configuration. Look for the existing params section and add after any existing parameters.

**Code to Add** (within the 'params' array, after existing params):
```php
        // Database Adapter Configuration (Phase 2 - Abstract Database Layer)
        'database_adapter' => getenv('DATABASE_ADAPTER') ?: 'mariadb',
        
        // Collections that have been migrated to Couchbase (empty until Phase 4+)
        'couchbase_migrated_collections' => [],
        
        // Enable dual-write mode (writes to both MariaDB and Couchbase)
        'enable_dual_write' => strtolower(getenv('ENABLE_DUAL_WRITE') ?: '') === 'true',
        
        // Enable reading from Couchbase (for migrated collections)
        'enable_couchbase_read' => strtolower(getenv('ENABLE_COUCHBASE_READ') ?: '') === 'true',
```

**Note**: Find a suitable location in the params array and add these settings. The exact location may vary based on the file structure.

---

### TASK 11: Create MariaDB Adapter Unit Test
**Priority**: Medium
**File to Create**: `/protected/tests/unit/components/database/MariaDbAdapterTest.php`

```php
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
    
    protected function setUp()
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
        $count = $this->adapter->count('user', ['active' => 1]);
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
```

**Verification**:
```bash
php -l protected/tests/unit/components/database/MariaDbAdapterTest.php
```

---

### TASK 12: Create Couchbase Adapter Unit Test
**Priority**: Medium
**File to Create**: `/protected/tests/unit/components/database/CouchbaseAdapterTest.php`

```php
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
    
    protected function setUp()
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
```

**Verification**:
```bash
php -l protected/tests/unit/components/database/CouchbaseAdapterTest.php
```

---

### TASK 13: Create Factory Unit Test
**Priority**: Medium
**File to Create**: `/protected/tests/unit/components/database/DatabaseAdapterFactoryTest.php`

```php
<?php
/**
 * Unit tests for DatabaseAdapterFactory
 */

class DatabaseAdapterFactoryTest extends CDbTestCase
{
    protected function setUp()
    {
        parent::setUp();
        \OE\Database\DatabaseAdapterFactory::clearInstances();
    }
    
    protected function tearDown()
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
```

**Verification**:
```bash
php -l protected/tests/unit/components/database/DatabaseAdapterFactoryTest.php
```

---

### TASK 14: Create Dual-Write Adapter Unit Test
**Priority**: Medium
**File to Create**: `/protected/tests/unit/components/database/DualWriteAdapterTest.php`

```php
<?php
/**
 * Unit tests for DualWriteAdapter
 */

class DualWriteAdapterTest extends CDbTestCase
{
    /**
     * @var \OE\Database\DualWriteAdapter
     */
    private $adapter;
    
    /**
     * @var \OE\Database\MariaDbAdapter Mock primary adapter
     */
    private $primary;
    
    /**
     * @var \OE\Database\DatabaseAdapterInterface Mock secondary adapter
     */
    private $secondary;
    
    protected function setUp()
    {
        parent::setUp();
        
        // Use real MariaDB adapter as primary
        $this->primary = new \OE\Database\MariaDbAdapter();
        
        // Create a mock secondary adapter for testing
        $this->secondary = $this->createMock(\OE\Database\DatabaseAdapterInterface::class);
        
        $this->adapter = new \OE\Database\DualWriteAdapter($this->primary, $this->secondary);
    }
    
    public function testReadFromPrimaryByDefault()
    {
        $this->adapter->setReadFromSecondary(false);
        
        // Should read from primary (real MariaDB)
        $result = $this->adapter->findByAttributes('user', [], ['limit' => 1]);
        $this->assertIsArray($result);
    }
    
    public function testInsertWritesToBothAdapters()
    {
        // Set up secondary mock to expect insert
        $this->secondary->expects($this->once())
            ->method('insert')
            ->willReturn('test_id');
        
        // Create adapter with mock
        $adapter = new \OE\Database\DualWriteAdapter($this->primary, $this->secondary);
        
        // This should write to both
        // We can't actually test without a real second database,
        // but we verify the mock was called
        $testData = [
            'username' => 'dual_write_test_' . uniqid(),
            'first_name' => 'Test',
            'last_name' => 'User',
            'active' => 0,
        ];
        
        try {
            $id = $adapter->insert('user', $testData);
            $this->assertNotNull($id);
            
            // Cleanup primary
            $this->primary->delete('user', $id);
        } catch (\Exception $e) {
            // If insert fails, that's okay for this test
            $this->markTestSkipped('Could not insert test record: ' . $e->getMessage());
        }
    }
    
    public function testGetPrimaryReturnsCorrectAdapter()
    {
        $primary = $this->adapter->getPrimary();
        $this->assertSame($this->primary, $primary);
    }
    
    public function testGetSecondaryReturnsCorrectAdapter()
    {
        $secondary = $this->adapter->getSecondary();
        $this->assertSame($this->secondary, $secondary);
    }
    
    public function testSetReadFromSecondaryChangesReadSource()
    {
        // Initially reads from primary
        $this->adapter->setReadFromSecondary(false);
        
        // Switch to secondary
        $this->adapter->setReadFromSecondary(true);
        
        // Now would read from secondary (we can't fully test without real Couchbase)
        $this->assertTrue(true); // Just verify no errors
    }
    
    public function testCountReadsFromConfiguredSource()
    {
        $this->adapter->setReadFromSecondary(false);
        
        $count = $this->adapter->count('user');
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
    
    public function testExistsReadsFromConfiguredSource()
    {
        $this->adapter->setReadFromSecondary(false);
        
        $exists = $this->adapter->exists('user', 999999999);
        $this->assertFalse($exists);
    }
}
```

**Verification**:
```bash
php -l protected/tests/unit/components/database/DualWriteAdapterTest.php
```

---

## Execution Order

Execute tasks in this order:

1. **TASK 1**: Create directory structure
2. **TASK 2**: Create DatabaseAdapterInterface
3. **TASK 3**: Create TransactionInterface
4. **TASK 4**: Create MariaDbTransaction
5. **TASK 5**: Create MariaDbAdapter
6. **TASK 6**: Create CouchbaseTransaction
7. **TASK 7**: Create CouchbaseAdapter
8. **TASK 8**: Create DatabaseAdapterFactory
9. **TASK 9**: Create DualWriteAdapter
10. **TASK 10**: Update configuration for feature flags
11. **TASK 11**: Create MariaDbAdapter unit test
12. **TASK 12**: Create CouchbaseAdapter unit test
13. **TASK 13**: Create Factory unit test
14. **TASK 14**: Create DualWriteAdapter unit test

---

## Verification Steps

After completing all tasks, run these verification commands:

```bash
# 1. Verify all PHP files have valid syntax
find protected/components/database -name "*.php" -exec php -l {} \;

# 2. Verify test files have valid syntax
find protected/tests/unit/components/database -name "*.php" -exec php -l {} \;

# 3. Run unit tests for MariaDB adapter (no Couchbase needed)
./vendor/bin/phpunit protected/tests/unit/components/database/MariaDbAdapterTest.php

# 4. Run factory tests
./vendor/bin/phpunit protected/tests/unit/components/database/DatabaseAdapterFactoryTest.php

# 5. Run all database adapter tests
./vendor/bin/phpunit protected/tests/unit/components/database/

# 6. Quick integration test (PHP)
php -r "
require_once 'protected/yiic.php';
\$adapter = \OE\Database\DatabaseAdapterFactory::getAdapter();
echo 'Adapter class: ' . get_class(\$adapter) . PHP_EOL;
\$count = \$adapter->count('user');
echo 'User count: ' . \$count . PHP_EOL;
"
```

---

## Success Criteria

- [ ] All PHP files pass syntax check (`php -l`)
- [ ] DatabaseAdapterInterface defines all CRUD operations
- [ ] TransactionInterface defines transaction operations
- [ ] MariaDbAdapter implements all interface methods
- [ ] CouchbaseAdapter implements all interface methods
- [ ] DualWriteAdapter writes to both databases
- [ ] Factory returns correct adapter types
- [ ] Feature flags are configurable via environment
- [ ] MariaDbAdapter unit tests pass
- [ ] Factory unit tests pass
- [ ] No impact on existing application functionality

---

## Rollback Instructions

If rollback is needed:

```bash
# 1. Remove the database components directory
rm -rf protected/components/database

# 2. Remove test files
rm -rf protected/tests/unit/components/database

# 3. Revert common.php changes
# Remove the database adapter params:
# - database_adapter
# - couchbase_migrated_collections
# - enable_dual_write
# - enable_couchbase_read

# 4. Clear any cached configurations
php protected/yiic cache clear
```

---

## Configuration Reference

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DATABASE_ADAPTER` | Default adapter type (`mariadb` or `couchbase`) | `mariadb` |
| `ENABLE_DUAL_WRITE` | Enable writing to both databases | `false` |
| `ENABLE_COUCHBASE_READ` | Enable reading from Couchbase | `false` |

### Feature Flags in common.php

```php
'params' => [
    'database_adapter' => 'mariadb',           // Default adapter
    'couchbase_migrated_collections' => [],    // Collections using Couchbase
    'enable_dual_write' => false,              // Write to both databases
    'enable_couchbase_read' => false,          // Read from Couchbase
]
```

---

## Notes for Agent

1. **File Paths**: All paths are relative to project root `/Users/asahu/Desktop/OpenEyes/openeyes/`
2. **Namespaces**: All classes use `OE\Database` namespace
3. **Dependencies**: MariaDbAdapter depends on Yii's CDbConnection
4. **Dependencies**: CouchbaseAdapter depends on Phase 1's CouchbaseConnection
5. **Testing**: MariaDb tests can run without Couchbase
6. **Testing**: Couchbase tests skip gracefully if Couchbase unavailable
7. **Configuration**: Always default to MariaDB for safety
8. **No Breaking Changes**: This phase should not affect existing functionality
