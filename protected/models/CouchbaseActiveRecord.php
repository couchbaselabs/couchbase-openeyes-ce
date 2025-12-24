<?php
/**
 * Base class for Couchbase document models
 * 
 * Provides ActiveRecord-like interface for Couchbase documents,
 * maintaining compatibility with existing OpenEyes patterns.
 */

use OE\Couchbase\Transformers\TypeTransformer;

abstract class CouchbaseActiveRecord extends CModel
{
    /** @var array Document attributes */
    protected $_attributes = [];
    
    /** @var array Original attributes for dirty checking */
    protected $_originalAttributes = [];
    
    /** @var bool Whether this is a new record */
    protected $_isNewRecord = true;
    
    /** @var mixed Primary key value */
    protected $_pk;
    
    /** @var CouchbaseConnection */
    protected $_connection;
    
    /** @var array Validation errors */
    private $_errors = [];
    
    /**
     * Get the document type identifier
     * @return string
     */
    abstract public function documentType();
    
    /**
     * Get the Couchbase scope name
     * @return string
     */
    abstract public function scope();
    
    /**
     * Get the Couchbase collection name
     * @return string
     */
    abstract public function collectionName();
    
    /**
     * Define validation rules
     * @return array
     */
    public function rules()
    {
        return [];
    }
    
    /**
     * Define attribute labels
     * @return array
     */
    public function attributeLabels()
    {
        return [];
    }
    
    /**
     * Get attribute names
     * @return array
     */
    public function attributeNames()
    {
        return array_keys($this->_attributes);
    }
    
    /**
     * Get the document key
     * @return string
     */
    public function getDocumentKey()
    {
        return $this->collectionName() . '::' . $this->getPrimaryKey();
    }
    
    /**
     * Get primary key
     * @return mixed
     */
    public function getPrimaryKey()
    {
        return $this->_pk;
    }
    
    /**
     * Set primary key
     * @param mixed $pk
     */
    public function setPrimaryKey($pk)
    {
        $this->_pk = $pk;
    }
    
    /**
     * Check if new record
     * @return bool
     */
    public function getIsNewRecord()
    {
        return $this->_isNewRecord;
    }
    
    /**
     * Set new record flag
     * @param bool $value
     */
    public function setIsNewRecord($value)
    {
        $this->_isNewRecord = $value;
    }
    
    /**
     * Get the Couchbase connection
     * @return CouchbaseConnection
     */
    public function getConnection()
    {
        if ($this->_connection === null) {
            $this->_connection = Yii::app()->couchbase;
        }
        return $this->_connection;
    }
    
    /**
     * Get the Couchbase collection object
     * @return \Couchbase\Collection
     */
    protected function getCollection()
    {
        return $this->getConnection()->getCollection(
            $this->scope(),
            $this->collectionName()
        );
    }
    
    /**
     * Get a single attribute
     * @param string $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        return isset($this->_attributes[$name]) ? $this->_attributes[$name] : null;
    }
    
    /**
     * Set a single attribute
     * @param string $name
     * @param mixed $value
     */
    public function setAttribute($name, $value)
    {
        $this->_attributes[$name] = $value;
    }
    
    /**
     * Get all attributes
     * @return array
     */
    public function getAttributes($names = null)
    {
        if ($names === null) {
            return $this->_attributes;
        }
        
        return array_intersect_key($this->_attributes, array_flip($names));
    }
    
    /**
     * Set multiple attributes
     * @param array $values
     * @param bool $safeOnly Only set safe attributes
     */
    public function setAttributes($values, $safeOnly = true)
    {
        foreach ($values as $name => $value) {
            $this->_attributes[$name] = $value;
        }
    }
    
    /**
     * Check if attribute is dirty
     * @param string $name
     * @return bool
     */
    public function isAttributeDirty($name)
    {
        if (!array_key_exists($name, $this->_originalAttributes)) {
            return true;
        }
        return $this->_attributes[$name] !== $this->_originalAttributes[$name];
    }
    
    /**
     * Get dirty attributes
     * @return array
     */
    public function getDirtyAttributes()
    {
        $dirty = [];
        foreach ($this->_attributes as $name => $value) {
            if ($this->isAttributeDirty($name)) {
                $dirty[$name] = $value;
            }
        }
        return $dirty;
    }
    
    /**
     * Static model factory (matches Yii pattern)
     * @param string|null $className
     * @return static
     */
    public static function model($className = null)
    {
        $className = $className ?: get_called_class();
        return new $className();
    }
    
    /**
     * Find document by primary key
     * @param mixed $pk
     * @return static|null
     */
    public static function findByPk($pk)
    {
        $model = static::model();
        
        try {
            $result = $model->getCollection()->get($model->collectionName() . '::' . $pk);
            
            $data = $result->content();
            if (is_object($data)) {
                $data = (array)$data;
            }
            
            $model->_attributes = $data;
            $model->_originalAttributes = $data;
            $model->_pk = $pk;
            $model->_isNewRecord = false;
            
            return $model;
        } catch (\Couchbase\Exception\DocumentNotFoundException $e) {
            return null;
        } catch (\Exception $e) {
            Yii::log(
                "Couchbase findByPk error: " . $e->getMessage(),
                CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return null;
        }
    }
    
    /**
     * Find documents by attributes using N1QL
     * @param array $attributes
     * @param array $options
     * @return array Array of models
     */
    public static function findAllByAttributes($attributes, $options = [])
    {
        $model = static::model();
        $conn = $model->getConnection();
        
        $bucket = $conn->config['bucket'];
        $scope = $model->scope();
        $collection = $model->collectionName();
        
        // Build N1QL query
        $query = "SELECT META().id as _key, * FROM `{$bucket}`.`{$scope}`.`{$collection}` WHERE _type = \$type";
        $params = ['type' => $model->documentType()];
        
        $paramIndex = 0;
        foreach ($attributes as $key => $value) {
            $paramName = "p{$paramIndex}";
            if ($value === null) {
                $query .= " AND `{$key}` IS NULL";
            } else {
                $query .= " AND `{$key}` = \${$paramName}";
                $params[$paramName] = $value;
            }
            $paramIndex++;
        }
        
        // Apply options
        if (!empty($options['order'])) {
            $query .= " ORDER BY " . $options['order'];
        }
        if (!empty($options['limit'])) {
            $query .= " LIMIT " . (int)$options['limit'];
        }
        if (!empty($options['offset'])) {
            $query .= " OFFSET " . (int)$options['offset'];
        }
        
        try {
            $results = $conn->query($query, $params);
            $models = [];
            
            foreach ($results as $row) {
                $newModel = static::model();
                
                // Extract data (structure depends on query)
                $data = isset($row[$collection]) ? (array)$row[$collection] : (array)$row;
                unset($data['_key']);
                
                $newModel->_attributes = $data;
                $newModel->_originalAttributes = $data;
                $newModel->_pk = isset($row['_key']) ? $model->extractPk($row['_key']) : (isset($data['_mysql_id']) ? $data['_mysql_id'] : null);
                $newModel->_isNewRecord = false;
                
                $models[] = $newModel;
            }
            
            return $models;
        } catch (\Exception $e) {
            Yii::log(
                "Couchbase findAllByAttributes error: " . $e->getMessage(),
                CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return [];
        }
    }
    
    /**
     * Extract primary key from document key
     * @param string $key
     * @return string
     */
    protected function extractPk($key)
    {
        $parts = explode('::', $key);
        return end($parts);
    }
    
    /**
     * Save the document
     * @param bool $runValidation
     * @return bool
     */
    public function save($runValidation = true)
    {
        if ($runValidation && !$this->validate()) {
            return false;
        }
        
        // Prepare document data
        $data = $this->_attributes;
        $data['_type'] = $this->documentType();
        $data['_modified'] = date('c');
        
        $userId = $this->getChangeUserId();
        $data['last_modified_user_id'] = $userId;
        $data['last_modified_date'] = date('Y-m-d H:i:s');
        
        try {
            if ($this->_isNewRecord) {
                // Generate ID if not set
                if (!$this->_pk) {
                    $this->_pk = $this->generatePrimaryKey();
                }
                
                $data['_created'] = date('c');
                $data['_mysql_id'] = is_numeric($this->_pk) ? (int)$this->_pk : null;
                $data['_version'] = 1;
                $data['created_user_id'] = $userId;
                $data['created_date'] = date('Y-m-d H:i:s');
                
                $this->beforeSave();
                $this->getCollection()->insert($this->getDocumentKey(), $data);
                $this->_isNewRecord = false;
            } else {
                // Increment version
                $data['_version'] = isset($data['_version']) ? $data['_version'] + 1 : 1;
                
                $this->beforeSave();
                $this->getCollection()->replace($this->getDocumentKey(), $data);
            }
            
            $this->_attributes = $data;
            $this->_originalAttributes = $data;
            $this->afterSave();
            
            return true;
        } catch (\Exception $e) {
            Yii::log(
                "Couchbase save error: " . $e->getMessage(),
                CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            $this->addError('_save', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete the document
     * @return bool
     */
    public function delete()
    {
        if ($this->_isNewRecord) {
            return false;
        }
        
        try {
            $this->beforeDelete();
            $this->getCollection()->remove($this->getDocumentKey());
            $this->afterDelete();
            return true;
        } catch (\Exception $e) {
            Yii::log(
                "Couchbase delete error: " . $e->getMessage(),
                CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return false;
        }
    }
    
    /**
     * Soft delete (set deleted flag)
     * @param string|null $reason
     * @return bool
     */
    public function softDelete($reason = null)
    {
        $this->_attributes['deleted'] = true;
        if ($reason) {
            $this->_attributes['delete_reason'] = $reason;
        }
        return $this->save(false);
    }
    
    /**
     * Validate the model
     * @param array|null $attributes
     * @param bool $clearErrors
     * @return bool
     */
    public function validate($attributes = null, $clearErrors = true)
    {
        $this->_errors = [];
        
        foreach ($this->rules() as $rule) {
            $ruleAttributes = is_array($rule[0]) ? $rule[0] : [$rule[0]];
            $validator = $rule[1];
            $params = array_slice($rule, 2);
            
            foreach ($ruleAttributes as $attr) {
                if ($attributes !== null && !in_array($attr, $attributes)) {
                    continue;
                }
                
                $value = isset($this->_attributes[$attr]) ? $this->_attributes[$attr] : null;
                
                if ($validator === 'required' && ($value === null || $value === '')) {
                    $this->addError($attr, "{$attr} is required");
                }
                // Add more validators as needed
            }
        }
        
        return empty($this->_errors);
    }
    
    /**
     * Add validation error
     * @param string $attribute
     * @param string $error
     */
    public function addError($attribute, $error)
    {
        if (!isset($this->_errors[$attribute])) {
            $this->_errors[$attribute] = [];
        }
        $this->_errors[$attribute][] = $error;
    }
    
    /**
     * Get validation errors
     * @param string|null $attribute
     * @return array
     */
    public function getErrors($attribute = null)
    {
        if ($attribute === null) {
            return $this->_errors;
        }
        return isset($this->_errors[$attribute]) ? $this->_errors[$attribute] : [];
    }
    
    /**
     * Check if has errors
     * @param string|null $attribute
     * @return bool
     */
    public function hasErrors($attribute = null)
    {
        if ($attribute === null) {
            return !empty($this->_errors);
        }
        return !empty($this->_errors[$attribute]);
    }
    
    /**
     * Generate a new primary key
     * @return string
     */
    protected function generatePrimaryKey()
    {
        return uniqid('', true);
    }
    
    /**
     * Get the current user ID for auditing
     * @return int
     */
    protected function getChangeUserId()
    {
        // In console applications, we don't have a user session
        if (Yii::app() instanceof CConsoleApplication) {
            return 1; // Default console user
        }
        $user = Yii::app()->user;
        return ($user && $user->id) ? $user->id : 1;
    }
    
    /**
     * Called before save - override in subclasses
     */
    protected function beforeSave()
    {
    }
    
    /**
     * Called after save - override in subclasses
     */
    protected function afterSave()
    {
    }
    
    /**
     * Called before delete - override in subclasses
     */
    protected function beforeDelete()
    {
    }
    
    /**
     * Called after delete - override in subclasses
     */
    protected function afterDelete()
    {
    }
    
    /**
     * Magic getter
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->_attributes)) {
            return $this->_attributes[$name];
        }
        return parent::__get($name);
    }
    
    /**
     * Magic setter
     */
    public function __set($name, $value)
    {
        $this->_attributes[$name] = $value;
    }
    
    /**
     * Magic isset
     */
    public function __isset($name)
    {
        return isset($this->_attributes[$name]);
    }
    
    /**
     * Magic unset
     */
    public function __unset($name)
    {
        unset($this->_attributes[$name]);
    }
    
    /**
     * Convert to array
     * @return array
     */
    public function toArray()
    {
        return $this->_attributes;
    }

    /**
     * Execute a N1QL query and return rows
     * @param string $query N1QL query string
     * @param array $params Named parameters (optional)
     * @return array Array of rows
     */
    protected static function executeQuery($query, $params = [])
    {
        $connection = Yii::app()->couchbase;
        $result = $connection->query($query, $params);
        return $result->rows();
    }
}
