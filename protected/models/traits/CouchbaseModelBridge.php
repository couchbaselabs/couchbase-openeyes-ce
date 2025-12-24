<?php
/**
 * Trait to add Couchbase support to existing CActiveRecord models
 * This allows gradual migration without breaking existing functionality
 */

namespace OE\Models\Traits;

use OE\Database\DatabaseAdapterFactory;
use OE\Couchbase\Transformers\TypeTransformer;

trait CouchbaseModelBridge
{
    /**
     * @var bool Flag to temporarily disable Couchbase sync
     */
    protected $_couchbaseSyncDisabled = false;
    
    /**
     * Get the Couchbase scope for this model
     * Override in model if different from 'core'
     * @return string
     */
    public function couchbaseScope()
    {
        return 'core';
    }
    
    /**
     * Get the Couchbase collection name for this model
     * Default is the table name
     * @return string
     */
    public function couchbaseCollection()
    {
        return $this->tableName();
    }
    
    /**
     * Get the Couchbase document type
     * @return string
     */
    public function couchbaseDocumentType()
    {
        return $this->tableName();
    }
    
    /**
     * Check if this model should use Couchbase for reads
     * @return bool
     */
    public function shouldUseCouchbase()
    {
        return DatabaseAdapterFactory::shouldUseCouchbase($this->tableName());
    }
    
    /**
     * Check if dual-write is enabled
     * Uses CouchbaseCutoverManager if available, falls back to params
     * @return bool
     */
    protected function isDualWriteEnabled()
    {
        // Check CouchbaseCutoverManager first (Phase 16+)
        if (class_exists('CouchbaseCutoverManager')) {
            try {
                $manager = \CouchbaseCutoverManager::getInstance();
                $config = $manager->getConfig();
                $writeMode = $config['write_mode'] ?? 'mariadb_only';
                
                // dual_write mode = write to both MariaDB and Couchbase
                // couchbase_primary mode = write to Couchbase only (no MariaDB)
                return in_array($writeMode, ['dual_write', 'couchbase_primary']);
            } catch (\Exception $e) {
                // Fall back to params if manager fails
            }
        }
        
        // Legacy fallback
        return \Yii::app()->params['enable_dual_write'] ?? false;
    }
    
    /**
     * Check if we should write to MariaDB
     * In couchbase_primary mode, MariaDB writes are skipped
     * @return bool
     */
    protected function shouldWriteToMariaDB()
    {
        if (class_exists('CouchbaseCutoverManager')) {
            try {
                $manager = \CouchbaseCutoverManager::getInstance();
                $config = $manager->getConfig();
                $writeMode = $config['write_mode'] ?? 'mariadb_only';
                
                // In couchbase_primary mode, don't write to MariaDB
                return $writeMode !== 'couchbase_primary';
            } catch (\Exception $e) {
                // Fall back to true (always write to MariaDB on error)
                return true;
            }
        }
        
        // Default: always write to MariaDB
        return true;
    }
    
    /**
     * Get current write mode from CouchbaseCutoverManager
     * @return string 'mariadb_only', 'dual_write', or 'couchbase_primary'
     */
    protected function getWriteMode()
    {
        if (class_exists('CouchbaseCutoverManager')) {
            try {
                $manager = \CouchbaseCutoverManager::getInstance();
                $config = $manager->getConfig();
                return $config['write_mode'] ?? 'mariadb_only';
            } catch (\Exception $e) {
                // Fall back
            }
        }
        
        // Legacy: check params
        if (\Yii::app()->params['enable_dual_write'] ?? false) {
            return 'dual_write';
        }
        
        return 'mariadb_only';
    }
    
    /**
     * Temporarily disable Couchbase sync
     * Useful during batch operations or data fixes
     * @return $this
     */
    public function disableCouchbaseSync()
    {
        $this->_couchbaseSyncDisabled = true;
        return $this;
    }
    
    /**
     * Re-enable Couchbase sync
     * @return $this
     */
    public function enableCouchbaseSync()
    {
        $this->_couchbaseSyncDisabled = false;
        return $this;
    }
    
    /**
     * Get adapter for this model
     * @return \OE\Database\DatabaseAdapterInterface
     */
    protected function getDatabaseAdapter()
    {
        return DatabaseAdapterFactory::getAdapterForCollection($this->tableName());
    }
    
    /**
     * Get the Couchbase adapter directly
     * @return \OE\Database\CouchbaseAdapter
     */
    protected function getCouchbaseAdapter()
    {
        return DatabaseAdapterFactory::getAdapter(DatabaseAdapterFactory::ADAPTER_COUCHBASE);
    }
    
    /**
     * Generate the Couchbase document key
     * @return string
     */
    public function getCouchbaseDocumentKey()
    {
        return $this->couchbaseCollection() . '::' . $this->getPrimaryKey();
    }
    
    /**
     * Convert model attributes to Couchbase document format
     * Override in models for custom conversion logic
     * @return array
     */
    public function toCouchbaseDocument()
    {
        $doc = [];
        $schema = $this->getMetaData()->columns;
        
        // Transform each attribute
        foreach ($this->attributes as $attr => $value) {
            if (isset($schema[$attr])) {
                $doc[$attr] = TypeTransformer::toJson(
                    $value,
                    $schema[$attr]->dbType,
                    $attr
                );
            } else {
                $doc[$attr] = $value;
            }
        }
        
        // Add document metadata
        $doc['_type'] = $this->couchbaseDocumentType();
        $doc['_mysql_id'] = $this->getPrimaryKey();
        $doc['_modified'] = date('c');
        
        if ($this->isNewRecord) {
            $doc['_created'] = date('c');
            $doc['_version'] = 1;
        } else {
            // Increment version if exists
            $doc['_version'] = isset($doc['_version']) ? $doc['_version'] + 1 : 1;
        }
        
        // Handle embedded relations if defined
        if (method_exists($this, 'getEmbeddedRelations')) {
            $embeddedData = $this->getEmbeddedRelations();
            // Merge embedded data directly into document
            if (is_array($embeddedData)) {
                $doc = array_merge($doc, $embeddedData);
            }
        }
        
        return $doc;
    }
    
    /**
     * Populate model from Couchbase document
     * @param array $doc Document data
     * @return void
     */
    public function fromCouchbaseDocument($doc)
    {
        $schema = $this->getMetaData()->columns;
        
        foreach ($doc as $attr => $value) {
            // Skip metadata fields
            if (strpos($attr, '_') === 0) {
                continue;
            }
            
            if (isset($schema[$attr])) {
                $this->$attr = TypeTransformer::toMysql(
                    $value,
                    $schema[$attr]->dbType,
                    $attr
                );
            } elseif ($this->hasAttribute($attr)) {
                $this->$attr = $value;
            }
        }
        
        // Set primary key from document
        if (isset($doc['_mysql_id'])) {
            $this->setPrimaryKey($doc['_mysql_id']);
        }
    }
    
    /**
     * Save to Couchbase (for dual-write support)
     * @return bool Success status
     */
    protected function saveToCouchbase()
    {
        // Check if sync is disabled or dual-write not enabled
        if ($this->_couchbaseSyncDisabled || !$this->isDualWriteEnabled()) {
            if ($this->isCouchbaseWriteMandatory()) {
                throw new \RuntimeException('Couchbase write is mandatory for this model, but sync is disabled or dual-write is off.');
            }
            return true;
        }
        
        // Check hook
        if (method_exists($this, 'beforeCouchbaseSync') && !$this->beforeCouchbaseSync()) {
            return true; // Skip sync but don't fail
        }
        
        try {
            $adapter = $this->getCouchbaseAdapter();
            $doc = $this->toCouchbaseDocument();
            $collection = $this->couchbaseCollection();
            $pk = $this->getPrimaryKey();
            // Upsert to avoid document_exists errors when a document already exists in Couchbase
            $adapter->upsert($collection, $pk, $doc);
            
            // Call hook if exists
            if (method_exists($this, 'afterCouchbaseSync')) {
                $this->afterCouchbaseSync();
            }
            
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase save failed for {$this->tableName()} #{$this->getPrimaryKey()}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.couchbase'
            );
            if ($this->isCouchbaseWriteMandatory()) {
                throw new \RuntimeException('Couchbase save failed for mandatory model: ' . $e->getMessage(), 0, $e);
            }
            // Don't fail the main operation - Couchbase sync is secondary
            return false;
        }
    }
    
    /**
     * Delete from Couchbase (for dual-write support)
     * @return bool Success status
     */
    protected function deleteFromCouchbase()
    {
        if ($this->_couchbaseSyncDisabled || !$this->isDualWriteEnabled()) {
            if ($this->isCouchbaseWriteMandatory()) {
                throw new \RuntimeException('Couchbase delete is mandatory for this model, but sync is disabled or dual-write is off.');
            }
            return true;
        }
        
        try {
            $adapter = $this->getCouchbaseAdapter();
            $adapter->delete($this->couchbaseCollection(), $this->getPrimaryKey());
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase delete failed for {$this->tableName()} #{$this->getPrimaryKey()}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.couchbase'
            );
            if ($this->isCouchbaseWriteMandatory()) {
                throw new \RuntimeException('Couchbase delete failed for mandatory model: ' . $e->getMessage(), 0, $e);
            }
            return false;
        }
    }

    /**
     * Determine if Couchbase writes/deletes are mandatory for this model.
     * Currently enforced for patient collection when configured.
     *
     * @return bool
     */
    protected function isCouchbaseWriteMandatory(): bool
    {
        $require = \Yii::app()->params['require_couchbase_patient_writes'] ?? false;
        return $require && method_exists($this, 'tableName') && $this->tableName() === 'patient';
    }
    
    /**
     * Sync this record to Couchbase manually
     * Useful for initial data migration or fixing sync issues
     * @return bool Success status
     */
    public function syncToCouchbase()
    {
        $wasDisabled = $this->_couchbaseSyncDisabled;
        $this->_couchbaseSyncDisabled = false;
        
        // Temporarily force dual-write
        $originalSetting = \Yii::app()->params['enable_dual_write'];
        \Yii::app()->params['enable_dual_write'] = true;
        
        $result = $this->saveToCouchbase();
        
        // Restore settings
        \Yii::app()->params['enable_dual_write'] = $originalSetting;
        $this->_couchbaseSyncDisabled = $wasDisabled;
        
        return $result;
    }
    
    /**
     * Compare this record with its Couchbase version
     * @return array|null Differences or null if not found
     */
    public function compareWithCouchbase()
    {
        try {
            $adapter = $this->getCouchbaseAdapter();
            $cbDoc = $adapter->findByPk($this->couchbaseCollection(), $this->getPrimaryKey());
            
            if (!$cbDoc) {
                return ['status' => 'missing', 'message' => 'Document not found in Couchbase'];
            }
            
            $myDoc = $this->toCouchbaseDocument();
            $differences = [];
            
            // Compare fields (excluding metadata)
            foreach ($myDoc as $key => $value) {
                if (strpos($key, '_') === 0) continue;
                
                $cbValue = isset($cbDoc[$key]) ? $cbDoc[$key] : null;
                if ($value !== $cbValue) {
                    $differences[$key] = [
                        'mysql' => $value,
                        'couchbase' => $cbValue,
                    ];
                }
            }
            
            return empty($differences) 
                ? ['status' => 'match', 'message' => 'Documents are identical']
                : ['status' => 'mismatch', 'differences' => $differences];
                
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
