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
