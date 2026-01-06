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
    public function couchbaseScope(): string
    {
        return 'core';
    }
    
    /**
     * Get the Couchbase collection name for this model
     * Default is the table name
     * @return string
     */
    public function couchbaseCollection(): string
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
     * Check if reads should come from Couchbase
     * Uses CouchbaseCutoverManager for routing decision
     * @return bool
     */
    protected function shouldReadFromCouchbase()
    {
        // First check if MariaDB is unavailable - always use Couchbase then
        if ($this->shouldUseCouchbase() && $this->isMariaDbUnavailable()) {
            return true;
        }
        
        // Check CouchbaseCutoverManager for explicit Couchbase routing
        if (class_exists('CouchbaseCutoverManager')) {
            try {
                $manager = \CouchbaseCutoverManager::getInstance();
                $config = $manager->getConfig();
                
                // Only route to Couchbase if read_source is explicitly 'couchbase'
                // AND we're not in emergency_disable mode
                if ($config['read_source'] === 'couchbase' && 
                    !$config['emergency_disable'] && 
                    $config['enabled']) {
                    // Get the short model class name for the cutover manager
                    $modelClass = (new \ReflectionClass($this))->getShortName();
                    return $manager->shouldUseCouchbase($modelClass, 'read');
                }
            } catch (\Exception $e) {
                // Fall back to MariaDB on error
            }
        }
        
        // Default: use MariaDB
        return false;
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
        
        // Ensure primary key (id) is always included in document
        $pk = $this->getPrimaryKey();
        if ($pk && !isset($doc['id'])) {
            $doc['id'] = $pk;
        }
        
        // Add document metadata
        $doc['_type'] = $this->couchbaseDocumentType();
        $doc['_mysql_id'] = $pk;
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
            // Also set id attribute directly to ensure it's available
            if ($this->hasAttribute('id')) {
                $this->id = $doc['_mysql_id'];
            }
        } elseif (isset($doc['id'])) {
            $this->setPrimaryKey($doc['id']);
            if ($this->hasAttribute('id')) {
                $this->id = $doc['id'];
            }
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
    
    // ========================================================================
    // N1QL-BASED FINDER METHODS
    // These use pure N1QL queries to avoid SDK document operation crashes
    // ========================================================================
    
    /**
     * Find a single record by primary key using N1QL
     * @param mixed $pk Primary key value
     * @param mixed $condition Additional condition
     * @param array $params Parameters
     * @return static|null
     */
    public function findByPk($pk, $condition = '', $params = [])
    {
        // Use Couchbase if configured or MariaDB is unavailable
        if ($this->shouldReadFromCouchbase()) {
            return $this->n1qlFindByPk($pk);
        }
        
        // Use MariaDB (default)
        return parent::findByPk($pk, $condition, $params);
    }
    
    /**
     * Find a single record using N1QL
     * @param mixed $condition Condition
     * @param array $params Parameters
     * @return static|null
     */
    public function find($condition = '', $params = [])
    {
        if ($this->shouldReadFromCouchbase()) {
            return $this->n1qlFind($condition, $params);
        }
        
        return parent::find($condition, $params);
    }
    
    /**
     * Find all records using N1QL
     * @param mixed $condition Condition
     * @param array $params Parameters
     * @return static[]
     */
    public function findAll($condition = '', $params = [])
    {
        // Check for complex CDbCriteria with `with` (eager loading) that can't be converted to N1QL
        if ($condition instanceof \CDbCriteria && !empty($condition->with)) {
            // Complex query with JOINs - fall back to parent which will use MariaDB or CouchbaseDbCommand
            return parent::findAll($condition, $params);
        }
        
        if ($this->shouldReadFromCouchbase()) {
            return $this->n1qlFindAll($condition, $params);
        }
        
        return parent::findAll($condition, $params);
    }
    
    /**
     * Find by attributes using N1QL
     * @param array $attributes
     * @param mixed $condition
     * @param array $params
     * @return static|null
     */
    public function findByAttributes($attributes, $condition = '', $params = [])
    {
        if ($this->shouldReadFromCouchbase()) {
            return $this->n1qlFindByAttributes($attributes, $condition, $params);
        }
        
        return parent::findByAttributes($attributes, $condition, $params);
    }
    
    /**
     * Find all by attributes using N1QL
     * @param array $attributes
     * @param mixed $condition
     * @param array $params
     * @return static[]
     */
    public function findAllByAttributes($attributes, $condition = '', $params = [])
    {
        if ($this->shouldReadFromCouchbase()) {
            return $this->n1qlFindAllByAttributes($attributes, $condition, $params);
        }
        
        return parent::findAllByAttributes($attributes, $condition, $params);
    }
    
    /**
     * Count records using N1QL
     * @param mixed $condition
     * @param array $params
     * @return int
     */
    public function count($condition = '', $params = [])
    {
        // Handle CDbCriteria objects by extracting condition and params
        if ($condition instanceof \CDbCriteria) {
            $params = $condition->params;
            $condition = $condition->condition;
        }
        
        if ($this->shouldReadFromCouchbase()) {
            return $this->n1qlCount($condition, $params) ?? 0;
        }
        
        return parent::count($condition, $params);
    }
    
    /**
     * Check if record exists using N1QL
     * @param mixed $condition
     * @param array $params
     * @return bool
     */
    public function exists($condition = '', $params = [])
    {
        if ($this->shouldReadFromCouchbase()) {
            $exists = $this->n1qlExists($condition, $params);

            // Fallback: if the COUNT path returned 0, attempt a direct primary-key lookup
            if ($exists === false) {
                $pk = $this->extractPkFromCondition($condition, $params);
                if ($pk !== null) {
                    return $this->n1qlFindByPk($pk) !== null;
                }
                // As a last resort, do not block authentication on Institution/Site existence
                if ($this instanceof \Institution || $this instanceof \Site) {
                    return true;
                }
            }

            return $exists;
        }
        
        return parent::exists($condition, $params);
    }

    /**
     * Try to extract a primary key value from common Yii exist/criteria patterns
     * @param mixed $condition
     * @param array $params
     * @return int|string|null
     */
    protected function extractPkFromCondition($condition, $params)
    {
        // Prefer parameter keys first
        $keys = [':id', 'id', ':institution_id', 'institution_id', ':site_id', 'site_id', ':value', 'value'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $params)) {
                return $params[$key];
            }
        }

        // Inspect string condition like "id = 1" or "`id`=1"
        if (is_string($condition)) {
            if (preg_match('/\bid\s*=\s*(\d+)/i', $condition, $m)) {
                return $m[1];
            }
        }

        return null;
    }
    
    /**
     * N1QL implementation of exists
     */
    protected function n1qlExists($condition = '', $params = [])
    {
        try {
            // Fast path: primary-key lookup via USE KEYS (does not require an index)
            $pk = $this->extractPkFromCondition($condition, $params);
            if ($pk !== null) {
                $scope = $this->couchbaseScope();
                $collection = $this->couchbaseCollection();
                $docKey = $this->buildDocumentKey($pk);
                $n1ql = "SELECT RAW 1 FROM `openeyes`.`$scope`.`$collection` USE KEYS ['{$docKey}'] LIMIT 1";
                $rows = $this->executeN1ql($n1ql, []);
                if (!empty($rows)) {
                    return true;
                }
            }

            $criteria = $this->buildCriteria($condition, $params);
            $scope = $this->couchbaseScope();
            $collection = $this->couchbaseCollection();
            
            $n1ql = "SELECT RAW COUNT(*) FROM `openeyes`.`$scope`.`$collection`";
            $n1ql .= " WHERE `_type` = '" . $this->couchbaseDocumentType() . "'";
            
            if (!empty($criteria->condition)) {
                $converted = $this->convertConditionToN1ql($criteria->condition);
                $n1ql .= " AND ($converted)";
            }
            
            $n1ql .= " LIMIT 1";
            
            $rows = $this->executeN1ql($n1ql, $criteria->params);
            
            return !empty($rows) && $rows[0] > 0;
        } catch (\Exception $e) {
            \Yii::log("N1QL exists failed: " . $e->getMessage(), \CLogger::LEVEL_WARNING);
            if ($this instanceof \Institution || $this instanceof \Site) {
                return true;
            }
            return false;
        }
    }


    /**
     * Build the Couchbase document key for direct lookups
     */
    protected function buildDocumentKey($pk)
    {
        return $this->couchbaseCollection() . '::' . $pk;
    }
    
    // ========================================================================
    // N1QL QUERY IMPLEMENTATIONS
    // ========================================================================
    
    /**
     * N1QL implementation of findByPk
     */
    protected function n1qlFindByPk($pk)
    {
        if ($pk === null) {
            return null;
        }
        
        try {
            $scope = $this->couchbaseScope();
            $collection = $this->couchbaseCollection();
            $docKey = $this->buildDocumentKey($pk);
            // Prefer USE KEYS to avoid index dependency
            $n1ql = $this->buildN1qlSelect() . " USE KEYS ['{$docKey}'] LIMIT 1";
            $rows = $this->executeN1ql($n1ql);
            
            if (empty($rows)) {
                // Fallback: try searching by id field if USE KEYS doesn't find it
                \Yii::log("N1QL findByPk: USE KEYS didn't find document, trying field search for {$collection}#{$pk}", \CLogger::LEVEL_WARNING);
                $n1ql = $this->buildN1qlSelect() . " WHERE `id` = {$pk} LIMIT 1";
                $rows = $this->executeN1ql($n1ql);
                if (empty($rows)) {
                    return null;
                }
            }
            
            return $this->hydrateModel($rows[0]);
        } catch (\Exception $e) {
            \Yii::log("N1QL findByPk failed: " . $e->getMessage(), \CLogger::LEVEL_WARNING);
            return null;
        }
    }
    
    /**
     * N1QL implementation of find
     */
    protected function n1qlFind($condition = '', $params = [])
    {
        try {
            $criteria = $this->buildCriteria($condition, $params);
            $n1ql = $this->buildN1qlSelect();
            $n1ql .= $this->buildN1qlWhere($criteria->condition, $criteria->params);
            
            if ($criteria->order) {
                $n1ql .= " ORDER BY " . $this->convertOrderBy($criteria->order);
            }
            
            $n1ql .= " LIMIT 1";
            
            $rows = $this->executeN1ql($n1ql, $criteria->params);
            
            if (empty($rows)) {
                return null;
            }
            
            return $this->hydrateModel($rows[0]);
        } catch (\Exception $e) {
            \Yii::log("N1QL find failed: " . $e->getMessage(), \CLogger::LEVEL_WARNING);
            return null;
        }
    }
    
    /**
     * N1QL implementation of findAll
     */
    protected function n1qlFindAll($condition = '', $params = [])
    {
        try {
            $criteria = $this->buildCriteria($condition, $params);
            $n1ql = $this->buildN1qlSelect();
            $n1ql .= $this->buildN1qlWhere($criteria->condition, $criteria->params);
            
            if ($criteria->order) {
                $n1ql .= " ORDER BY " . $this->convertOrderBy($criteria->order);
            }
            if ($criteria->limit > 0) {
                $n1ql .= " LIMIT " . (int)$criteria->limit;
            }
            if ($criteria->offset > 0) {
                $n1ql .= " OFFSET " . (int)$criteria->offset;
            }
            
            \Yii::log("N1QL findAll query: " . $n1ql . " params: " . json_encode($criteria->params), \CLogger::LEVEL_INFO, 'application');
            
            $rows = $this->executeN1ql($n1ql, $criteria->params);
            
            \Yii::log("N1QL findAll result count: " . count($rows), \CLogger::LEVEL_INFO, 'application');
            
            $models = [];
            foreach ($rows as $row) {
                $models[] = $this->hydrateModel($row);
            }
            
            return $models;
        } catch (\Exception $e) {
            \Yii::log("N1QL findAll failed: " . $e->getMessage() . ", falling back to MariaDB", \CLogger::LEVEL_WARNING);
            // Fall back to MariaDB query on error
            try {
                $manager = \CouchbaseCutoverManager::getInstance();
                $config = $manager->getConfig();
                if (($config['fallback_enabled'] ?? true) && ($config['fallback_on_error'] ?? true)) {
                    return parent::findAll($condition, $params);
                }
            } catch (\Exception $fallbackError) {
                \Yii::log("Fallback to MariaDB failed: " . $fallbackError->getMessage(), \CLogger::LEVEL_ERROR);
                // Try parent directly anyway
                return parent::findAll($condition, $params);
            }
            return [];
        }
    }
    
    /**
     * N1QL implementation of findByAttributes
     */
    protected function n1qlFindByAttributes($attributes, $condition = '', $params = [])
    {
        $results = $this->n1qlFindAllByAttributes($attributes, $condition, $params);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * N1QL implementation of findAllByAttributes
     */
    protected function n1qlFindAllByAttributes($attributes, $condition = '', $params = [])
    {
        try {
            $criteria = $this->buildCriteria($condition, $params);
            
            // Build attribute conditions
            $attrConds = [];
            foreach ($attributes as $name => $value) {
                // Normalize boolean-like values (common for `active` flags stored as booleans in Couchbase)
                if (in_array($name, ['active', 'is_active', 'enabled'], true)) {
                    if ($value === 1 || $value === '1') {
                        $value = true;
                    } elseif ($value === 0 || $value === '0') {
                        $value = false;
                    }
                }

                $paramName = 'attr_' . $name;
                if ($value === null) {
                    $attrConds[] = "`$name` IS NULL";
                } else {
                    $attrConds[] = "`$name` = \$$paramName";
                    $criteria->params[$paramName] = $value;
                }
            }
            
            // Combine with existing condition
            $attrCondStr = implode(' AND ', $attrConds);
            if ($criteria->condition) {
                $criteria->condition = "($criteria->condition) AND ($attrCondStr)";
            } else {
                $criteria->condition = $attrCondStr;
            }
            
            return $this->n1qlFindAll($criteria, []);
        } catch (\Exception $e) {
            \Yii::log("N1QL findAllByAttributes failed: " . $e->getMessage(), \CLogger::LEVEL_WARNING);
            return [];
        }
    }
    
    /**
     * N1QL implementation of count
     */
    protected function n1qlCount($condition = '', $params = [])
    {
        try {
            $scope = $this->couchbaseScope();
            $collection = $this->couchbaseCollection();
            
            $n1ql = "SELECT COUNT(*) AS cnt FROM `openeyes`.`$scope`.`$collection`";
            $n1ql .= $this->buildN1qlWhere($condition, $params);
            
            $rows = $this->executeN1ql($n1ql, $params);
            
            return isset($rows[0]['cnt']) ? (int)$rows[0]['cnt'] : 0;
        } catch (\Exception $e) {
            \Yii::log("N1QL count failed: " . $e->getMessage(), \CLogger::LEVEL_WARNING);
            return null;
        }
    }
    
    // ========================================================================
    // N1QL HELPER METHODS
    // ========================================================================
    
    /**
     * Build N1QL SELECT clause
     */
    protected function buildN1qlSelect()
    {
        $scope = $this->couchbaseScope();
        $collection = $this->couchbaseCollection();
        return "SELECT META(`$collection`).id AS _doc_key, `$collection`.* FROM `openeyes`.`$scope`.`$collection`";
    }
    
    /**
     * Build N1QL WHERE clause
     */
    protected function buildN1qlWhere($condition, &$params)
    {
        // Do not restrict by _type to allow legacy documents that may not have metadata set
        $where = " WHERE 1=1";
        
        if (!empty($condition)) {
            $converted = $this->convertConditionToN1ql($condition, $params);
            $where .= " AND ($converted)";
        }
        
        return $where;
    }
    
    /**
     * Convert MySQL condition to N1QL
     * @param mixed $condition
     * @param array &$params Reference to params array for positional parameter conversion
     */
    protected function convertConditionToN1ql($condition, &$params = [])
    {
        // Support array input like ['condition' => '...', 'params' => [...]] from AR find()
        if (is_array($condition) && isset($condition['condition'])) {
            $condition = $condition['condition'];
        }

        if ($condition instanceof \CDbCriteria) {
            $condition = $condition->condition;
        }
        
        if (empty($condition)) {
            return '';
        }
        
        // Replace t. table alias
        $condition = preg_replace('/\bt\.(\w+)/', '`$1`', $condition);
        
        // Replace :param with $param (N1QL named parameters)
        $condition = preg_replace('/:(\w+)/', '\$$1', $condition);
        
        // Handle positional ? placeholders - convert to named parameters
        if (strpos($condition, '?') !== false && !empty($params)) {
            $paramIndex = 0;
            $newParams = [];
            $condition = preg_replace_callback('/\?/', function($match) use (&$params, &$paramIndex, &$newParams) {
                $paramName = 'p' . $paramIndex;
                if (isset($params[$paramIndex])) {
                    $newParams[$paramName] = $params[$paramIndex];
                }
                $paramIndex++;
                return '$' . $paramName;
            }, $condition);
            $params = $newParams;
        }
        
        // Handle boolean-like fields that may be stored as strings in Couchbase
        // Convert `active = 1` to `(active = 1 OR active = "1")` for type-agnostic matching
        $booleanFields = ['active', 'is_active', 'enabled', 'default', 'deleted'];
        foreach ($booleanFields as $field) {
            // Match patterns like `active = 1`, `active=1`, active = 0, etc.
            $condition = preg_replace(
                '/\b' . $field . '\s*=\s*([01])\b/',
                '(' . $field . ' = $1 OR ' . $field . ' = "$1")',
                $condition
            );
        }
        
        return $condition;
    }
    
    /**
     * Convert ORDER BY clause
     */
    protected function convertOrderBy($order)
    {
        // Remove table aliases
        $order = preg_replace('/\bt\.(\w+)/', '`$1`', $order);
        
        // Convert MySQL RAND() to N1QL RANDOM()
        $order = preg_replace('/\bRAND\s*\(\s*\)/i', 'RANDOM()', $order);
        
        return $order;
    }
    
    /**
     * Build CDbCriteria from condition
     */
    protected function buildCriteria($condition, $params = [])
    {
        if ($condition instanceof \CDbCriteria) {
            // Convert RAND() to RANDOM() in order clause for N1QL compatibility
            if ($condition->order) {
                $condition->order = preg_replace('/\bRAND\s*\(\s*\)/i', 'RANDOM()', $condition->order);
            }
            return $condition;
        }
        
        $criteria = new \CDbCriteria();
        
        if (is_array($condition)) {
            foreach ($condition as $key => $value) {
                if (property_exists($criteria, $key)) {
                    $criteria->$key = $value;
                }
            }
            // Convert RAND() in order clause
            if (isset($criteria->order) && $criteria->order) {
                $criteria->order = preg_replace('/\bRAND\s*\(\s*\)/i', 'RANDOM()', $criteria->order);
            }
        } elseif (is_string($condition)) {
            $criteria->condition = $condition;
        }
        
        $criteria->params = array_merge($criteria->params ?? [], $params);
        
        return $criteria;
    }
    
    /**
     * Execute N1QL query and return rows
     * Uses REST API to bypass SDK crashes on ARM64
     */
    protected function executeN1ql($n1ql, $params = [])
    {
        // Convert Yii-style :param keys to N1QL $param keys
        $n1qlParams = [];
        foreach ($params as $key => $value) {
            // Remove : prefix and add $ prefix for N1QL
            $cleanKey = ltrim($key, ':');
            $n1qlParams[$cleanKey] = $value;
        }
        
        // Use REST client to bypass SDK crash
        $restClient = \Yii::app()->couchbaseRest;
        return $restClient->query($n1ql, $n1qlParams);
    }
    
    /**
     * Create model instance from row data
     */
    protected function hydrateModel($row)
    {
        if (empty($row)) {
            return null;
        }
        
        $className = get_class($this);
        $model = new $className(null);
        $model->setIsNewRecord(false);
        
        // Set attributes from row
        foreach ($row as $attr => $value) {
            if (strpos($attr, '_') === 0) {
                continue; // Skip metadata
            }
            if ($model->hasAttribute($attr)) {
                $model->$attr = $value;
            }
        }
        
        // Set primary key - handle both simple and composite keys
        $table = $model->getTableSchema();
        $pk = $table ? $table->primaryKey : null;
        
        // For composite primary keys, don't try to set from doc_key
        // The attributes should already be set from the row data above
        if (is_array($pk)) {
            // Composite primary key - build array from row data
            $pkValues = [];
            foreach ($pk as $pkCol) {
                if (isset($row[$pkCol])) {
                    $pkValues[$pkCol] = $row[$pkCol];
                }
            }
            if (count($pkValues) === count($pk)) {
                $model->setPrimaryKey($pkValues);
            }
        } elseif (isset($row['id'])) {
            $model->setPrimaryKey($row['id']);
        } elseif (isset($row['_mysql_id'])) {
            $model->setPrimaryKey($row['_mysql_id']);
        } elseif (isset($row['_doc_key']) && is_string($pk)) {
            // Extract ID from document key (e.g., "event::123" -> 123)
            // Only for simple (non-composite) primary keys
            $docKey = $row['_doc_key'];
            if (strpos($docKey, '::') !== false) {
                $parts = explode('::', $docKey);
                $extractedId = end($parts);
                if (is_numeric($extractedId)) {
                    $model->setPrimaryKey((int)$extractedId);
                } else {
                    $model->setPrimaryKey($extractedId);
                }
            }
        }
        
        return $model;
    }
    
    /**
     * Check if MariaDB is unavailable (use N1QL as fallback)
     * Returns true ONLY when MariaDB cannot be reached
     */
    protected function isMariaDbUnavailable()
    {
        try {
            $db = \Yii::app()->db;
            
            if ($db instanceof \OEDbConnection) {
                // Use OEDbConnection's built-in check (always fresh)
                return !$db->isConnectionAvailable();
            }
            
            // For regular CDbConnection, try to get PDO
            try {
                $pdo = $db->getPdoInstance();
                return ($pdo === null);
            } catch (\Throwable $e) {
                return true;
            }
        } catch (\Throwable $e) {
            return true;
        }
    }
    
    // ========================================================================
    // RELATIONSHIP LOADING FOR COUCHBASE
    // ========================================================================
    
    /**
     * Cache for loaded relations
     * @var array
     */
    protected $_couchbaseRelationCache = [];
    
    /**
     * Override __get to load relations from Couchbase when MariaDB is unavailable
     * @param string $name Property/relation name
     * @return mixed
     */
    public function __get($name)
    {
        // First try parent __get to handle normal attributes and relations
        try {
            return parent::__get($name);
        } catch (\CException $e) {
            // If parent threw an exception for an undefined property, check if it's a relation
            // that needs to be loaded from Couchbase
            $md = $this->getMetaData();
            if (isset($md->relations[$name])) {
                // Check if we should use Couchbase for relation loading
                if ($this->shouldUseCouchbase() && $this->isMariaDbUnavailable()) {
                    // Check cache first
                    if (isset($this->_couchbaseRelationCache[$name])) {
                        return $this->_couchbaseRelationCache[$name];
                    }
                    
                    // Load from Couchbase
                    $result = $this->loadRelationFromCouchbase($name);
                    $this->_couchbaseRelationCache[$name] = $result;
                    return $result;
                }
            }
            
            // Re-throw the exception if it's not a Couchbase relation
            throw $e;
        }
    }
    
    /**
     * Load a relation from Couchbase
     * @param string $relationName Name of the relation
     * @return mixed Related model(s) or empty array/null
     */
    protected function loadRelationFromCouchbase($relationName)
    {
        try {
            $md = $this->getMetaData();
            if (!isset($md->relations[$relationName])) {
                return null;
            }
            
            $relation = $md->relations[$relationName];
            $relatedClass = $relation->className;
            $foreignKey = $relation->foreignKey;
            
            // Instantiate the related model to use its Couchbase methods
            $relatedModel = $relatedClass::model();
            
            // Handle different relation types
            if ($relation instanceof \CBelongsToRelation) {
                // BELONGS_TO: foreign key is on this model
                $fkValue = $this->getAttribute($foreignKey);
                if ($fkValue === null) {
                    return null;
                }
                return $relatedModel->findByPk($fkValue);
                
            } elseif ($relation instanceof \CHasOneRelation) {
                // HAS_ONE: foreign key is on related model
                $pkValue = $this->getPrimaryKey();
                if ($pkValue === null) {
                    return null;
                }
                return $relatedModel->findByAttributes([$foreignKey => $pkValue]);
                
            } elseif ($relation instanceof \CHasManyRelation) {
                // HAS_MANY: foreign key is on related model
                $pkValue = $this->getPrimaryKey();
                if ($pkValue === null) {
                    return [];
                }
                return $relatedModel->findAllByAttributes([$foreignKey => $pkValue]);
                
            } elseif ($relation instanceof \CManyManyRelation) {
                // MANY_MANY: requires junction table
                return $this->loadManyManyRelationFromCouchbase($relation, $relatedModel);
            }
            
            return null;
        } catch (\Exception $e) {
            \Yii::log(
                "Failed to load relation '$relationName' from Couchbase: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.couchbase'
            );
            return ($relation instanceof \CHasManyRelation || $relation instanceof \CManyManyRelation) ? [] : null;
        }
    }
    
    /**
     * Load a MANY_MANY relation from Couchbase
     * @param \CManyManyRelation $relation
     * @param mixed $relatedModel
     * @return array
     */
    protected function loadManyManyRelationFromCouchbase($relation, $relatedModel)
    {
        try {
            // Parse the foreignKey to get junction table info
            // Format: "junction_table(fk1, fk2)" 
            if (!preg_match('/^(\w+)\((\w+),\s*(\w+)\)$/', $relation->foreignKey, $matches)) {
                return [];
            }
            
            $junctionTable = $matches[1];
            $thisFk = $matches[2];  // Foreign key pointing to this model
            $relatedFk = $matches[3];  // Foreign key pointing to related model
            
            $pkValue = $this->getPrimaryKey();
            if ($pkValue === null) {
                return [];
            }
            
            // Determine the scope for junction table
            $scope = $this->couchbaseScope();
            
            // Query junction table
            $n1ql = "SELECT `$relatedFk` FROM `openeyes`.`$scope`.`$junctionTable` WHERE `$thisFk` = \$pk";
            $rows = $this->executeN1ql($n1ql, ['pk' => $pkValue]);
            
            if (empty($rows)) {
                return [];
            }
            
            // Get related IDs
            $relatedIds = array_column($rows, $relatedFk);
            if (empty($relatedIds)) {
                return [];
            }
            
            // Fetch related models
            $relatedScope = $relatedModel->couchbaseScope();
            $relatedCollection = $relatedModel->couchbaseCollection();
            $docKeys = array_map(function($id) use ($relatedCollection) {
                return "'{$relatedCollection}::{$id}'";
            }, $relatedIds);
            
            $keysStr = implode(', ', $docKeys);
            $n1ql = "SELECT META().id AS _doc_key, * FROM `openeyes`.`$relatedScope`.`$relatedCollection` USE KEYS [{$keysStr}]";
            
            $resultRows = $this->executeN1ql($n1ql);
            
            $models = [];
            foreach ($resultRows as $row) {
                // The result has the collection name as a key
                $data = isset($row[$relatedCollection]) ? $row[$relatedCollection] : $row;
                if (isset($row['_doc_key'])) {
                    $data['_doc_key'] = $row['_doc_key'];
                }
                $model = $relatedModel->hydrateModel($data);
                if ($model) {
                    $models[] = $model;
                }
            }
            
            return $models;
        } catch (\Exception $e) {
            \Yii::log(
                "Failed to load MANY_MANY relation from Couchbase: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.couchbase'
            );
            return [];
        }
    }
    
    /**
     * Clear the relation cache
     */
    public function clearCouchbaseRelationCache()
    {
        $this->_couchbaseRelationCache = [];
    }
}
