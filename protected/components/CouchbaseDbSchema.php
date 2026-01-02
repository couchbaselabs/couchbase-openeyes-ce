<?php
/**
 * CouchbaseDbSchema - Static schema provider for Yii without MariaDB
 * 
 * This class provides table schema information from a static JSON/PHP cache
 * instead of querying MariaDB. This allows the application to run without
 * a MariaDB connection while still supporting Yii's ActiveRecord.
 * 
 * Usage:
 * 1. Export schemas: php yiic exportschema export
 * 2. Configure in common.php to use this schema class
 */

class CouchbaseDbSchema extends CMysqlSchema
{
    /**
     * @var array Cached schemas loaded from file
     */
    private $_staticSchemas;
    
    /**
     * @var bool Whether to use static schemas (no MariaDB)
     */
    public $useStaticSchemas = false;
    
    /**
     * @var string Path to schema cache file (relative to app base)
     */
    public $schemaCacheFile = 'protected/config/schema-cache.php';
    
    /**
     * @var array Table cache for quick lookups
     */
    private $_tables = [];
    
    /**
     * Get table schema - override to use static schemas
     * @param string $name Table name
     * @param bool $refresh Whether to refresh the schema
     * @return CDbTableSchema|null
     */
    public function getTable($name, $refresh = false)
    {
        // Use static schema if enabled
        if ($this->useStaticSchemas) {
            if ($refresh || !isset($this->_tables[$name])) {
                $this->_tables[$name] = $this->loadTableFromStatic($name);
            }
            return $this->_tables[$name];
        }
        
        return parent::getTable($name, $refresh);
    }
    
    /**
     * Load table schema
     * @param string $name Table name
     * @return CDbTableSchema|null
     */
    protected function loadTable($name)
    {
        // Try static schema first if enabled
        if ($this->useStaticSchemas || !$this->canConnectToDb()) {
            $schema = $this->loadTableFromStatic($name);
            if ($schema !== null) {
                return $schema;
            }
        }
        
        // Fall back to parent (MariaDB) if available
        try {
            return parent::loadTable($name);
        } catch (Exception $e) {
            // If MariaDB fails, try static again
            return $this->loadTableFromStatic($name);
        }
    }
    
    /**
     * Load table schema from static cache
     * @param string $name Table name
     * @return CouchbaseTableSchema|null
     */
    protected function loadTableFromStatic($name)
    {
        $schemas = $this->getStaticSchemas();
        
        if (!isset($schemas[$name])) {
            // Fallback minimal schema for Couchbase-only workflows
            if ($name === 'ophciexamination_workflow') {
                $schemas[$name] = [
                    'name' => 'ophciexamination_workflow',
                    'primaryKey' => 'id',
                    'columns' => [
                        'id' => [
                            'name' => 'id',
                            'allowNull' => false,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => true,
                            'isPrimaryKey' => true,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'name' => [
                            'name' => 'name',
                            'allowNull' => false,
                            'dbType' => 'varchar(255)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 255,
                            'scale' => null,
                            'type' => 'string',
                        ],
                        'institution_id' => [
                            'name' => 'institution_id',
                            'allowNull' => true,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'created_user_id' => [
                            'name' => 'created_user_id',
                            'allowNull' => true,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'created_date' => [
                            'name' => 'created_date',
                            'allowNull' => true,
                            'dbType' => 'datetime',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => null,
                            'scale' => null,
                            'type' => 'datetime',
                        ],
                        'last_modified_user_id' => [
                            'name' => 'last_modified_user_id',
                            'allowNull' => true,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'last_modified_date' => [
                            'name' => 'last_modified_date',
                            'allowNull' => true,
                            'dbType' => 'datetime',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => null,
                            'scale' => null,
                            'type' => 'datetime',
                        ],
                    ],
                    'foreignKeys' => [],
                ];
            } elseif ($name === 'ophciexamination_workflow_rule') {
                $schemas[$name] = [
                    'name' => 'ophciexamination_workflow_rule',
                    'primaryKey' => 'id',
                    'columns' => [
                        'id' => [
                            'name' => 'id',
                            'allowNull' => false,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => true,
                            'isPrimaryKey' => true,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'workflow_id' => [
                            'name' => 'workflow_id',
                            'allowNull' => true,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'subspecialty_id' => [
                            'name' => 'subspecialty_id',
                            'allowNull' => true,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'firm_id' => [
                            'name' => 'firm_id',
                            'allowNull' => true,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'episode_status_id' => [
                            'name' => 'episode_status_id',
                            'allowNull' => true,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'created_user_id' => [
                            'name' => 'created_user_id',
                            'allowNull' => true,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'created_date' => [
                            'name' => 'created_date',
                            'allowNull' => true,
                            'dbType' => 'datetime',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => null,
                            'scale' => null,
                            'type' => 'datetime',
                        ],
                        'last_modified_user_id' => [
                            'name' => 'last_modified_user_id',
                            'allowNull' => true,
                            'dbType' => 'int(11)',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => 11,
                            'scale' => null,
                            'type' => 'integer',
                        ],
                        'last_modified_date' => [
                            'name' => 'last_modified_date',
                            'allowNull' => true,
                            'dbType' => 'datetime',
                            'defaultValue' => null,
                            'autoIncrement' => false,
                            'isPrimaryKey' => false,
                            'size' => null,
                            'scale' => null,
                            'type' => 'datetime',
                        ],
                    ],
                    'foreignKeys' => [],
                ];
            } else {
                return null;
            }
        }
        
        $schemaData = $schemas[$name];
        return $this->createTableSchema($schemaData);
    }
    
    /**
     * Get all static schemas
     * @return array
     */
    protected function getStaticSchemas()
    {
        if ($this->_staticSchemas === null) {
            $this->_staticSchemas = $this->loadStaticSchemas();
        }
        return $this->_staticSchemas;
    }
    
    /**
     * Load schemas from cache file
     * @return array
     */
    protected function loadStaticSchemas()
    {
        $basePath = Yii::app()->basePath . '/../';
        $phpFile = $basePath . $this->schemaCacheFile;
        
        if (file_exists($phpFile)) {
            return require $phpFile;
        }
        
        // Try JSON file as fallback
        $jsonFile = str_replace('.php', '.json', $phpFile);
        if (file_exists($jsonFile)) {
            $content = file_get_contents($jsonFile);
            return json_decode($content, true) ?: [];
        }
        
        Yii::log("Schema cache not found: {$phpFile}", CLogger::LEVEL_WARNING);
        return [];
    }
    
    /**
     * Create a CouchbaseTableSchema from cached data
     * @param array $data Schema data
     * @return CouchbaseTableSchema
     */
    protected function createTableSchema($data)
    {
        $table = new CouchbaseTableSchema();
        $table->name = $data['name'];
        $table->rawName = $this->quoteTableName($data['name']);
        $table->primaryKey = $data['primaryKey'];
        $table->sequenceName = ''; // Auto-increment handled differently
        
        // Create column schemas
        foreach ($data['columns'] as $colName => $colData) {
            $column = $this->createColumnSchema($colData);
            $table->columns[$colName] = $column;
            
            // Mark primary key columns
            if ($data['primaryKey'] === $colName || 
                (is_array($data['primaryKey']) && in_array($colName, $data['primaryKey']))) {
                $column->isPrimaryKey = true;
            }
        }
        
        // Set foreign keys
        $table->foreignKeys = $data['foreignKeys'] ?? [];
        
        return $table;
    }
    
    /**
     * Create a column schema from cached data
     * @param array $data Column data
     * @return CDbColumnSchema
     */
    protected function createColumnSchema($data)
    {
        $column = new CDbColumnSchema();
        $column->name = $data['name'];
        $column->rawName = $this->quoteColumnName($data['name']);
        $column->allowNull = $data['allowNull'];
        $column->dbType = $data['dbType'];
        $column->defaultValue = $data['defaultValue'];
        $column->autoIncrement = $data['autoIncrement'] ?? false;
        $column->isPrimaryKey = $data['isPrimaryKey'] ?? false;
        $column->isForeignKey = false; // Will be set later
        $column->size = $data['size'];
        $column->scale = $data['scale'];
        
        // Determine PHP type from DB type
        $column->type = $this->extractPhpType($data['type'], $data['dbType']);
        
        // Extract default value
        if ($column->defaultValue !== null) {
            $column->defaultValue = $this->extractDefaultValue($column);
        }
        
        return $column;
    }
    
    /**
     * Extract PHP type from database type
     * @param string $type Data type
     * @param string $dbType Full column type
     * @return string
     */
    protected function extractPhpType($type, $dbType)
    {
        static $typeMap = [
            'tinyint' => 'integer',
            'smallint' => 'integer',
            'mediumint' => 'integer',
            'int' => 'integer',
            'integer' => 'integer',
            'bigint' => 'integer',
            'float' => 'double',
            'double' => 'double',
            'decimal' => 'double',
            'numeric' => 'double',
            'bit' => 'integer',
            'bool' => 'boolean',
            'boolean' => 'boolean',
        ];
        
        $type = strtolower($type);
        
        if (isset($typeMap[$type])) {
            // Special case: tinyint(1) is typically boolean
            if ($type === 'tinyint' && strpos($dbType, 'tinyint(1)') !== false) {
                return 'boolean';
            }
            return $typeMap[$type];
        }
        
        return 'string';
    }
    
    /**
     * Extract typed default value
     * @param CDbColumnSchema $column
     * @return mixed
     */
    protected function extractDefaultValue($column)
    {
        $value = $column->defaultValue;
        
        if ($value === null || $value === 'NULL') {
            return null;
        }
        
        if ($value === 'CURRENT_TIMESTAMP') {
            return null; // Will be set at insert time
        }
        
        switch ($column->type) {
            case 'integer':
                return (int)$value;
            case 'double':
                return (float)$value;
            case 'boolean':
                return (bool)$value;
            default:
                return $value;
        }
    }
    
    /**
     * Check if we can connect to MariaDB
     * @return bool
     */
    protected function canConnectToDb()
    {
        try {
            $pdo = $this->getDbConnection()->getPdoInstance();
            return $pdo !== null;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get all table names (from static cache if using static schemas)
     * @param string $schema Schema name (not used)
     * @return array
     */
    protected function findTableNames($schema = '')
    {
        if ($this->useStaticSchemas || !$this->canConnectToDb()) {
            return array_keys($this->getStaticSchemas());
        }
        
        return parent::findTableNames($schema);
    }
    
    /**
     * Refresh the schema cache
     */
    public function refresh()
    {
        $this->_staticSchemas = null;
        parent::refresh();
    }
}
