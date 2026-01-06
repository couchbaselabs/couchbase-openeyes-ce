<?php
/**
 * CouchbaseDbConnection - Drop-in replacement for Yii's CDbConnection
 * 
 * This class provides a compatibility layer that allows existing code using
 * Yii::app()->db to work with Couchbase instead of MariaDB.
 * 
 * It converts SQL queries to N1QL where possible and provides the same
 * interface as CDbConnection for seamless migration.
 */

class CouchbaseDbConnection extends CApplicationComponent
{
    /**
     * @var \Couchbase\Cluster Couchbase cluster connection
     */
    private $_cluster;
    
    /**
     * @var \Couchbase\Bucket Couchbase bucket
     */
    private $_bucket;
    
    /**
     * @var string Bucket name
     */
    public $bucketName = 'openeyes';
    
    /**
     * @var string Default scope
     */
    public $defaultScope = '_default';
    
    /**
     * @var bool Whether the connection is active
     */
    private $_active = false;
    
    /**
     * @var CouchbaseDbCommand Current command being built
     */
    private $_currentCommand;

    /**
     * Schema caching compatibility properties (mirroring CDbConnection defaults)
     */
    public $schemaCachingDuration = 0;
    public $schemaCachingExclude = [];
    public $schemaCacheID = 'cache';

    /**
     * Table prefix compatibility (used by schema).
     */
    public $tablePrefix = '';

    /**
     * Driver name compatibility.
     */
    public function getDriverName()
    {
        return 'couchbase';
    }

    /** @var CouchbaseDbSchema */
    private $_schema;

    /**
     * Initialize the component
     */
    public function init()
    {
        parent::init();
        $this->open();
    }
    
    /**
     * Open the Couchbase connection
     */
    public function open()
    {
        if ($this->_active) {
            return;
        }
        
        try {
            $couchbase = Yii::app()->couchbase;
            $this->_cluster = $couchbase->cluster;
            $this->_bucket = $couchbase->bucket;
            $this->_active = true;
        } catch (Exception $e) {
            throw new CDbException('Failed to connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    /**
     * Close the connection
     */
    public function close()
    {
        $this->_active = false;
        $this->_cluster = null;
        $this->_bucket = null;
    }
    
    /**
     * Check if connection is active
     * @return bool
     */
    public function getActive()
    {
        return $this->_active;
    }
    
    /**
     * Create a command for executing queries
     * @param string|null $sql SQL query (will be converted to N1QL)
     * @return CouchbaseDbCommand
     */
    public function createCommand($sql = null)
    {
        $this->open();
        return new CouchbaseDbCommand($this, $sql);
    }
    
    /**
     * Get the Couchbase cluster
     * @return \Couchbase\Cluster
     */
    public function getCluster()
    {
        $this->open();
        return $this->_cluster;
    }
    
    /**
     * Get the Couchbase bucket
     * @return \Couchbase\Bucket
     */
    public function getBucket()
    {
        $this->open();
        return $this->_bucket;
    }
    
    /**
     * Execute a N1QL query directly
     * @param string $n1ql N1QL query
     * @param array $params Query parameters
     * @return array Query results
     */
    public function query($n1ql, $params = [])
    {
        $this->open();
        
        // Convert parameters from MySQL format (:param_name) to Couchbase format ($param_name)
        list($n1ql, $params) = $this->convertParametersFormat($n1ql, $params);
        
        $options = new \Couchbase\QueryOptions();
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        try {
            $result = $this->_cluster->query($n1ql, $options);
            return $result->rows();
        } catch (Exception $e) {
            Yii::log("Couchbase query failed: {$n1ql} - " . $e->getMessage(), CLogger::LEVEL_ERROR);
            throw new CDbException('Couchbase query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Convert parameters from MySQL format (:param_name) to Couchbase format ($param_name)
     * @param string $n1ql N1QL query
     * @param array $params Parameters with MySQL format keys
     * @return array [converted_n1ql, converted_params]
     */
    private function convertParametersFormat($n1ql, $params)
    {
        if (empty($params)) {
            return [$n1ql, $params];
        }
        
        $converted_params = [];
        
        foreach ($params as $key => $value) {
            // Remove leading colon if present
            $param_name = ltrim($key, ':');
            $converted_params[$param_name] = $value;
            
            // Replace :param_name with $param_name in the query
            $n1ql = preg_replace('/:\b' . preg_quote($param_name) . '\b/', '$' . $param_name, $n1ql);
        }
        
        return [$n1ql, $converted_params];
    }
    
    /**
     * Get a collection
     * @param string $collection Collection name
     * @param string|null $scope Scope name (defaults to defaultScope)
     * @return \Couchbase\Collection
     */
    public function getCollection($collection, $scope = null)
    {
        $this->open();
        $scope = $scope ?? $this->defaultScope;
        return $this->_bucket->scope($scope)->collection($collection);
    }

    /**
     * Provide a schema stub for ActiveRecord metadata.
     * Note: this is minimal and currently supports only tables we explicitly map.
     */
    public function getSchema()
    {
        if ($this->_schema === null) {
            Yii::import('application.components.CouchbaseDbSchema');
            $this->_schema = new CouchbaseDbSchema($this);
            $this->_schema->useStaticSchemas = true;
        }
        return $this->_schema;
    }
    
    /**
     * Begin a transaction (stub - Couchbase handles this differently)
     * @return CouchbaseDbTransaction
     */
    public function beginTransaction()
    {
        return new CouchbaseDbTransaction($this);
    }

    /**
     * Begin a transaction if not already in one (compatibility stub).
     */
    public function beginInternalTransaction()
    {
        // Couchbase transactions are not required for these flows; return stub
        return $this->beginTransaction();
    }
    
    /**
     * Quote a value for use in queries
     * @param mixed $value Value to quote
     * @return string Quoted value
     */
    public function quoteValue($value)
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return "'" . addslashes($value) . "'";
    }
    
    /**
     * Quote a table/collection name
     * @param string $name Table name
     * @return string Quoted name
     */
    public function quoteTableName($name)
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
    
    /**
     * Quote a column name
     * @param string $name Column name
     * @return string Quoted name
     */
    public function quoteColumnName($name)
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
    
    /**
     * Get the last insert ID (stub for compatibility)
     * @return string|null
     */
    public function getLastInsertID()
    {
        // Couchbase doesn't have auto-increment IDs
        // This is handled by the application
        return null;
    }
    
    /**
     * Get PDO instance (returns null - no PDO in Couchbase)
     * @return null
     */
    public function getPdoInstance()
    {
        return null;
    }
    
    /**
     * Check if we're in a transaction
     * @return bool
     */
    public function getCurrentTransaction()
    {
        return null;
    }
}

/**
 * CouchbaseDbCommand - Mimics CDbCommand for Couchbase
 * Supports both raw SQL and fluent query builder interface
 */
class CouchbaseDbCommand
{
    /**
     * @var CouchbaseDbConnection
     */
    private $_connection;
    
    /**
     * @var string Original SQL
     */
    private $_sql;
    
    /**
     * @var string Converted N1QL
     */
    private $_n1ql;
    
    /**
     * @var array Query parameters
     */
    private $_params = [];
    
    /**
     * @var string Target collection
     */
    private $_collection;
    
    // Query builder state properties
    private $_select = '*';
    private $_distinct = false;
    private $_from = '';
    private $_joins = [];
    private $_where = [];
    private $_whereParams = [];
    private $_group = '';
    private $_having = '';
    private $_havingParams = [];
    private $_order = '';
    private $_limit = null;
    private $_offset = null;
    private $_useBuilder = false;
    private $_useMariaDbFallback = false;
    
    /**
     * Constructor
     * @param CouchbaseDbConnection $connection
     * @param string|null $sql
     */
    public function __construct(CouchbaseDbConnection $connection, $sql = null)
    {
        $this->_connection = $connection;
        if ($sql !== null) {
            $this->setText($sql);
        }
    }
    
    /**
     * Select columns
     * @param string|array $columns Columns to select
     * @param string $option Additional option (e.g., 'DISTINCT')
     * @return $this
     */
    public function select($columns = '*', $option = '')
    {
        $this->_useBuilder = true;
        if (is_array($columns)) {
            $this->_select = implode(', ', $columns);
        } else {
            $this->_select = $columns;
        }
        if (strtoupper($option) === 'DISTINCT') {
            $this->_distinct = true;
        }
        return $this;
    }
    
    /**
     * Select distinct columns
     * @param string|array $columns
     * @return $this
     */
    public function selectDistinct($columns = '*')
    {
        return $this->select($columns, 'DISTINCT');
    }
    
    /**
     * Set FROM table
     * @param string|array $tables Table name(s)
     * @return $this
     */
    public function from($tables)
    {
        $this->_useBuilder = true;
        if (is_array($tables)) {
            $this->_from = implode(', ', $tables);
        } else {
            $this->_from = $tables;
        }
        return $this;
    }
    
    /**
     * Add WHERE condition
     * @param mixed $conditions String condition or array ['and', cond1, cond2] or ['in', 'col', [...]]
     * @param array $params Bound parameters
     * @return $this
     */
    public function where($conditions, $params = [])
    {
        $this->_useBuilder = true;
        $this->_where = [$conditions];
        $this->_whereParams = array_merge($this->_whereParams, $params);
        return $this;
    }
    
    /**
     * Add AND WHERE condition
     * @param mixed $conditions
     * @param array $params
     * @return $this
     */
    public function andWhere($conditions, $params = [])
    {
        $this->_useBuilder = true;
        $this->_where[] = $conditions;
        $this->_whereParams = array_merge($this->_whereParams, $params);
        return $this;
    }
    
    /**
     * Add OR WHERE condition
     * @param mixed $conditions
     * @param array $params
     * @return $this
     */
    public function orWhere($conditions, $params = [])
    {
        $this->_useBuilder = true;
        if (!empty($this->_where)) {
            $existing = $this->_where;
            $this->_where = [['or', $existing, $conditions]];
        } else {
            $this->_where = [$conditions];
        }
        $this->_whereParams = array_merge($this->_whereParams, $params);
        return $this;
    }
    
    /**
     * Add INNER JOIN
     * @param string $table Table to join
     * @param string $conditions Join conditions
     * @param array $params Bound parameters
     * @return $this
     */
    public function join($table, $conditions, $params = [])
    {
        $this->_useBuilder = true;
        $this->_joins[] = ['INNER JOIN', $table, $conditions];
        $this->_whereParams = array_merge($this->_whereParams, $params);
        return $this;
    }
    
    /**
     * Add LEFT JOIN
     * @param string $table
     * @param string $conditions
     * @param array $params
     * @return $this
     */
    public function leftJoin($table, $conditions, $params = [])
    {
        $this->_useBuilder = true;
        $this->_joins[] = ['LEFT JOIN', $table, $conditions];
        $this->_whereParams = array_merge($this->_whereParams, $params);
        return $this;
    }
    
    /**
     * Add RIGHT JOIN
     * @param string $table
     * @param string $conditions
     * @param array $params
     * @return $this
     */
    public function rightJoin($table, $conditions, $params = [])
    {
        $this->_useBuilder = true;
        $this->_joins[] = ['RIGHT JOIN', $table, $conditions];
        $this->_whereParams = array_merge($this->_whereParams, $params);
        return $this;
    }
    
    /**
     * Add CROSS JOIN
     * @param string $table
     * @return $this
     */
    public function crossJoin($table)
    {
        $this->_useBuilder = true;
        $this->_joins[] = ['CROSS JOIN', $table, ''];
        return $this;
    }
    
    /**
     * Add NATURAL JOIN
     * @param string $table
     * @return $this
     */
    public function naturalJoin($table)
    {
        $this->_useBuilder = true;
        $this->_joins[] = ['NATURAL JOIN', $table, ''];
        return $this;
    }
    
    /**
     * Set GROUP BY
     * @param string|array $columns
     * @return $this
     */
    public function group($columns)
    {
        $this->_useBuilder = true;
        if (is_array($columns)) {
            $this->_group = implode(', ', $columns);
        } else {
            $this->_group = $columns;
        }
        return $this;
    }
    
    /**
     * Set HAVING condition
     * @param string $conditions
     * @param array $params
     * @return $this
     */
    public function having($conditions, $params = [])
    {
        $this->_useBuilder = true;
        $this->_having = $conditions;
        $this->_havingParams = array_merge($this->_havingParams, $params);
        return $this;
    }
    
    /**
     * Set ORDER BY
     * @param string|array $columns
     * @return $this
     */
    public function order($columns)
    {
        $this->_useBuilder = true;
        if (is_array($columns)) {
            $this->_order = implode(', ', $columns);
        } else {
            $this->_order = $columns;
        }
        return $this;
    }
    
    /**
     * Set LIMIT
     * @param int $limit
     * @param int|null $offset
     * @return $this
     */
    public function limit($limit, $offset = null)
    {
        $this->_useBuilder = true;
        $this->_limit = (int)$limit;
        if ($offset !== null) {
            $this->_offset = (int)$offset;
        }
        return $this;
    }
    
    /**
     * Set OFFSET
     * @param int $offset
     * @return $this
     */
    public function offset($offset)
    {
        $this->_useBuilder = true;
        $this->_offset = (int)$offset;
        return $this;
    }
    
    /**
     * Union with another query (stub - falls back to MariaDB)
     * @param string $sql
     * @return $this
     */
    public function union($sql)
    {
        $this->_useMariaDbFallback = true;
        return $this;
    }
    
    /**
     * Build SQL from query builder state
     * @return string
     */
    private function buildQuery()
    {
        if (!$this->_useBuilder || empty($this->_from)) {
            return $this->_sql ?? '';
        }
        
        $sql = 'SELECT ';
        if ($this->_distinct) {
            $sql .= 'DISTINCT ';
        }
        $sql .= $this->_select;
        $sql .= ' FROM ' . $this->_from;
        
        // Add JOINs
        foreach ($this->_joins as $join) {
            list($type, $table, $conditions) = $join;
            $sql .= " {$type} {$table}";
            if (!empty($conditions)) {
                // Handle array conditions by converting them to SQL
                if (is_array($conditions)) {
                    $conditions = $this->buildCondition($conditions);
                } elseif (!is_string($conditions)) {
                    $conditions = '';
                }
                if (!empty($conditions) && is_string($conditions)) {
                    $sql .= " ON {$conditions}";
                }
            }
        }
        
        // Add WHERE
        if (!empty($this->_where)) {
            $whereClause = $this->buildWhereClause($this->_where);
            if (!empty($whereClause)) {
                $sql .= ' WHERE ' . $whereClause;
            }
        }
        
        // Add GROUP BY
        if (!empty($this->_group)) {
            $sql .= ' GROUP BY ' . $this->_group;
        }
        
        // Add HAVING
        if (!empty($this->_having)) {
            $sql .= ' HAVING ' . $this->_having;
        }
        
        // Add ORDER BY
        if (!empty($this->_order)) {
            $sql .= ' ORDER BY ' . $this->_order;
        }
        
        // Add LIMIT
        if ($this->_limit !== null) {
            $sql .= ' LIMIT ' . $this->_limit;
        }
        
        // Add OFFSET
        if ($this->_offset !== null) {
            $sql .= ' OFFSET ' . $this->_offset;
        }
        
        return $sql;
    }
    
    /**
     * Build WHERE clause from conditions array
     * @param array $conditions
     * @return string
     */
    private function buildWhereClause($conditions)
    {
        if (empty($conditions)) {
            return '';
        }
        
        $parts = [];
        foreach ($conditions as $condition) {
            if (is_string($condition)) {
                $parts[] = $condition;
            } elseif (is_array($condition)) {
                $parts[] = $this->buildCondition($condition);
            }
        }
        
        return implode(' AND ', array_filter($parts));
    }
    
    /**
     * Build a single condition
     * @param array $condition
     * @return string
     */
    private function buildCondition($condition)
    {
        if (empty($condition)) {
            return '';
        }
        
        // Check for operator type
        if (isset($condition[0]) && is_string($condition[0])) {
            $operator = strtolower($condition[0]);
            
            if ($operator === 'and') {
                $parts = [];
                for ($i = 1; $i < count($condition); $i++) {
                    if (is_array($condition[$i])) {
                        $parts[] = $this->buildCondition($condition[$i]);
                    } elseif (is_string($condition[$i])) {
                        $parts[] = $condition[$i];
                    }
                }
                return '(' . implode(' AND ', array_filter($parts)) . ')';
            }
            
            if ($operator === 'or') {
                $parts = [];
                for ($i = 1; $i < count($condition); $i++) {
                    if (is_array($condition[$i])) {
                        $parts[] = $this->buildCondition($condition[$i]);
                    } elseif (is_string($condition[$i])) {
                        $parts[] = $condition[$i];
                    }
                }
                return '(' . implode(' OR ', array_filter($parts)) . ')';
            }
            
            if ($operator === 'in' && isset($condition[1]) && isset($condition[2])) {
                $column = $condition[1];
                $values = $condition[2];
                if (is_array($values)) {
                    $quotedValues = array_map(function($v) {
                        if (is_numeric($v)) {
                            return $v;
                        }
                        return "'" . addslashes($v) . "'";
                    }, $values);
                    return "{$column} IN (" . implode(', ', $quotedValues) . ")";
                }
            }
            
            if ($operator === 'not in' && isset($condition[1]) && isset($condition[2])) {
                $column = $condition[1];
                $values = $condition[2];
                if (is_array($values)) {
                    $quotedValues = array_map(function($v) {
                        if (is_numeric($v)) {
                            return $v;
                        }
                        return "'" . addslashes($v) . "'";
                    }, $values);
                    return "{$column} NOT IN (" . implode(', ', $quotedValues) . ")";
                }
            }
        }
        
        // Simple string condition
        if (is_string($condition)) {
            return $condition;
        }
        
        return '';
    }
    
    /**
     * Check if query should fall back to MariaDB
     * @return bool
     */
    private function shouldFallbackToMariaDb()
    {
        // Explicit fallback flag
        if ($this->_useMariaDbFallback) {
            return true;
        }
        
        // JOINs are complex in N1QL - fall back for safety
        if (!empty($this->_joins)) {
            return true;
        }
        
        // Check for unsupported functions in raw SQL
        if (!empty($this->_sql) && $this->hasUnsupportedFunctions($this->_sql)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Set the SQL text
     * @param string $sql
     * @return $this
     */
    public function setText($sql)
    {
        $this->_sql = $sql;
        $this->_n1ql = $this->convertToN1QL($sql);
        return $this;
    }
    
    /**
     * Get the SQL text
     * @return string
     */
    public function getText()
    {
        // If using query builder and SQL hasn't been built yet, build it now
        if ($this->_useBuilder && empty($this->_sql)) {
            $this->_sql = $this->buildQuery();
        }
        return $this->_sql;
    }
    
    /**
     * Magic getter for property access compatibility with Yii's CDbCommand
     * Allows accessing ->text as a property instead of calling getText()
     * @param string $name Property name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name === 'text') {
            return $this->getText();
        }
        throw new CException("Undefined property: " . get_class($this) . "::$name");
    }
    
    /**
     * Bind a parameter value
     * @param string $name Parameter name
     * @param mixed $value Parameter value
     * @return $this
     */
    public function bindValue($name, $value)
    {
        $this->_params[$name] = $value;
        return $this;
    }
    
    /**
     * Bind multiple parameter values
     * @param array $values
     * @return $this
     */
    public function bindValues($values)
    {
        foreach ($values as $name => $value) {
            $this->_params[$name] = $value;
        }
        return $this;
    }
    
    /**
     * Execute query and return all rows
     * @param array $params Additional parameters
     * @return array
     */
    public function queryAll($params = [])
    {
        $params = array_merge($this->_params, $this->_whereParams, $this->_havingParams, $params);
        
        // Build SQL from query builder if used
        if ($this->_useBuilder) {
            $this->_sql = $this->buildQuery();
        }
        
        // Check if we should fall back to MariaDB
        if ($this->shouldFallbackToMariaDb()) {
            Yii::log("CouchbaseDbCommand: Falling back to MariaDB for query: " . substr($this->_sql, 0, 200), CLogger::LEVEL_INFO);
            return $this->executeViaMariaDb($params);
        }
        
        // Fall back to MariaDB for unsupported MySQL functions in raw SQL
        if ($this->hasUnsupportedFunctions($this->_sql)) {
            return $this->executeViaMariaDb($params);
        }
        
        // Convert and execute via Couchbase
        $this->_n1ql = $this->convertToN1QL($this->_sql);
        return $this->_connection->query($this->_n1ql, $params);
    }
    
    /**
     * Execute query via MariaDB (fallback for unsupported N1QL conversions)
     * @param array $params
     * @return array
     */
    private function executeViaMariaDb($params = [])
    {
        $db = \Yii::app()->db;
        if (!$db || !$db->getActive()) {
            // MariaDB is not available - try to convert to N1QL anyway
            \Yii::log(
                "CouchbaseDbCommand: MariaDB not available, attempting N1QL conversion for: " . substr($this->_sql, 0, 200),
                \CLogger::LEVEL_WARNING,
                'application.couchbase'
            );
            
            try {
                // Try N1QL conversion even for complex queries
                $this->_n1ql = $this->convertToN1QL($this->_sql);
                return $this->_connection->query($this->_n1ql, $params);
            } catch (\Exception $e) {
                // N1QL conversion failed, return empty result to avoid breaking the page
                \Yii::log(
                    "CouchbaseDbCommand: N1QL conversion failed, returning empty result. Error: " . $e->getMessage(),
                    \CLogger::LEVEL_ERROR,
                    'application.couchbase'
                );
                return [];
            }
        }
        
        $command = $db->createCommand($this->_sql);
        
        foreach ($params as $name => $value) {
            $command->bindValue($name, $value);
        }
        
        return $command->queryAll();
    }
    
    /**
     * Check if SQL contains unsupported MySQL functions
     * @param string $sql
     * @return bool
     */
    private function hasUnsupportedFunctions($sql)
    {
        if (empty($sql)) {
            return false;
        }
        
        // These functions cannot be auto-converted and require MariaDB fallback
        // Note: GROUP_CONCAT, FIND_IN_SET, SUBSTRING_INDEX, TIMESTAMPDIFF are now converted
        $unsupportedFunctions = [
            'FIELD(',      // MySQL FIELD() for custom ordering
            'ELT(',        // MySQL ELT() for element selection
            'MATCH(',      // Full-text search
            'AGAINST(',    // Full-text search
            'REGEXP',      // MySQL regex (N1QL uses different syntax)
            'SOUNDS LIKE', // MySQL phonetic comparison
            'SHA1(',       // MySQL SHA1 hash function
            'SHA2(',       // MySQL SHA2 hash function
            'MD5(',        // MySQL MD5 hash function
            'AES_ENCRYPT', // MySQL encryption
            'AES_DECRYPT', // MySQL decryption
            'PASSWORD(',   // MySQL password function
            'EXISTS',      // EXISTS with subqueries (not supported in N1QL)
            'UNION',       // UNION queries (not properly supported in N1QL conversion)
            'SUBSTRING(',  // MySQL SUBSTRING - N1QL has different syntax
            'UNSIGNED',    // MySQL type casting - not supported in N1QL
        ];
        
        foreach ($unsupportedFunctions as $func) {
            if (stripos($sql, $func) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Execute query and return first row
     * @param array $params Additional parameters
     * @return array|null
     */
    public function queryRow($params = [])
    {
        $results = $this->queryAll($params);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Execute query and return single scalar value
     * @param array $params Additional parameters
     * @return mixed
     */
    public function queryScalar($params = [])
    {
        $row = $this->queryRow($params);
        if ($row) {
            return reset($row);
        }
        return null;
    }
    
    /**
     * Execute query and return single column
     * @param array $params Additional parameters
     * @return array
     */
    public function queryColumn($params = [])
    {
        $results = $this->queryAll($params);
        $column = [];
        foreach ($results as $row) {
            $column[] = reset($row);
        }
        return $column;
    }
    
    /**
     * Execute a non-query command (INSERT/UPDATE/DELETE)
     * @param array $params Additional parameters
     * @return int Affected rows (estimated)
     */
    public function execute($params = [])
    {
        $params = array_merge($this->_params, $this->_whereParams, $params);
        
        // Build SQL from query builder if used
        if ($this->_useBuilder) {
            $this->_sql = $this->buildQuery();
        }
        
        // Fall back to MariaDB for JOINs and unsupported features
        if ($this->shouldFallbackToMariaDb()) {
            $db = \Yii::app()->db;
            if ($db && $db->getActive()) {
                $command = $db->createCommand($this->_sql);
                foreach ($params as $name => $value) {
                    $command->bindValue($name, $value);
                }
                return $command->execute();
            }
        }
        
        // Convert and execute via Couchbase
        $this->_n1ql = $this->convertToN1QL($this->_sql);
        
        try {
            $results = $this->_connection->query($this->_n1ql, $params);
            // Return count of affected documents
            return is_array($results) ? count($results) : 1;
        } catch (Exception $e) {
            Yii::log("Execute failed: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            throw $e;
        }
    }
    
    /**
     * Convert SQL to N1QL
     * @param string $sql SQL query
     * @return string N1QL query
     */
    private function convertToN1QL($sql)
    {
        if (empty($sql)) {
            return $sql;
        }
        
        // Extract table name for routing to correct collection
        $this->_collection = $this->extractTableName($sql);
        
        // Determine the scope based on table name
        $scope = $this->determineScope($this->_collection);
        
        // Replace table references with fully qualified collection names
        $n1ql = $this->replaceTableReferences($sql, $scope);
        
        // Convert MySQL-specific syntax to N1QL
        $n1ql = $this->convertMySQLSyntax($n1ql);
        
        return $n1ql;
    }
    
    /**
     * Extract the main table name from SQL
     * @param string $sql
     * @return string|null
     */
    private function extractTableName($sql)
    {
        // Match FROM clause
        if (preg_match('/\bFROM\s+[`"\']?(\w+)[`"\']?/i', $sql, $matches)) {
            return $matches[1];
        }
        // Match INSERT INTO
        if (preg_match('/\bINSERT\s+INTO\s+[`"\']?(\w+)[`"\']?/i', $sql, $matches)) {
            return $matches[1];
        }
        // Match UPDATE
        if (preg_match('/\bUPDATE\s+[`"\']?(\w+)[`"\']?/i', $sql, $matches)) {
            return $matches[1];
        }
        // Match DELETE FROM
        if (preg_match('/\bDELETE\s+FROM\s+[`"\']?(\w+)[`"\']?/i', $sql, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Determine the Couchbase scope for a table
     * @param string $tableName
     * @return string
     */
    private function determineScope($tableName)
    {
        if (!$tableName) {
            return 'reference';
        }
        
        // Core tables - only main entity tables, not lookup/reference tables
        $coreTables = ['patient', 'episode', 'event', 'user', 'contact', 'address', 
                       'institution', 'site', 'firm', 'person', 'gp', 'practice'];
        if (in_array($tableName, $coreTables)) {
            return 'core';
        }
        
        // Core entity event-related tables (event_draft, event_log, etc., but NOT event_type/event_group which are reference)
        if (preg_match('/^(patient|episode|event_draft|event_log|event_issue|user|contact_|address_|institution_|site_|firm_|person_|gp_|practice_)/', $tableName)) {
            return 'core';
        }
        
        // Admin tables
        if (preg_match('/^audit|^auth|^setting/', $tableName)) {
            return 'admin';
        }
        
        // Clinical/module tables
        if (preg_match('/^et_|^oph|^element_/', $tableName)) {
            return 'clinical';
        }
        
        // Default to reference (includes event_type, event_group, etc.)
        return 'reference';
    }
    
    /**
     * Replace table references with fully qualified collection names
     * @param string $sql
     * @param string $scope
     * @return string
     */
    private function replaceTableReferences($sql, $scope)
    {
        // Pattern to match table names (handling various quoting styles)
        $pattern = '/\b(FROM|JOIN|INTO|UPDATE)\s+[`"\']?(\w+)[`"\']?/i';
        
        return preg_replace_callback($pattern, function($matches) use ($scope) {
            $keyword = $matches[1];
            $table = $matches[2];
            $tableScope = $this->determineScope($table);
            return "$keyword `openeyes`.`$tableScope`.`$table`";
        }, $sql);
    }
    
    /**
     * Convert MySQL-specific syntax to N1QL
     * @param string $sql
     * @return string
     */
    private function convertMySQLSyntax($sql)
    {
        // Convert GROUP_CONCAT to ARRAY_TO_STRING(ARRAY_AGG(...))
        // GROUP_CONCAT(DISTINCT col) -> ARRAY_TO_STRING(ARRAY_AGG(DISTINCT col), ',')
        // GROUP_CONCAT(col SEPARATOR ';') -> ARRAY_TO_STRING(ARRAY_AGG(col), ';')
        $sql = $this->convertGroupConcat($sql);
        
        // Convert FIND_IN_SET to ARRAY_CONTAINS
        // FIND_IN_SET(val, col) > 0 -> ARRAY_CONTAINS(SPLIT(col, ','), val)
        $sql = $this->convertFindInSet($sql);
        
        // Convert SUBSTRING_INDEX
        // SUBSTRING_INDEX(str, delim, count) for count=-1 -> ARRAY_REVERSE(SPLIT(str, delim))[0]
        $sql = $this->convertSubstringIndex($sql);
        
        // Replace IFNULL with COALESCE (N1QL compatible)
        $sql = preg_replace('/\bIFNULL\s*\(/i', 'COALESCE(', $sql);
        
        // Replace NOW() with NOW_STR()
        $sql = preg_replace('/\bNOW\s*\(\s*\)/i', 'NOW_STR()', $sql);
        
        // Replace CURDATE() with SUBSTR(NOW_STR(), 0, 10)
        $sql = preg_replace('/\bCURDATE\s*\(\s*\)/i', 'SUBSTR(NOW_STR(), 0, 10)', $sql);
        
        // Replace DATE_FORMAT
        $sql = preg_replace('/\bDATE_FORMAT\s*\(/i', 'DATE_FORMAT_STR(', $sql);
        
        // Replace MySQL concat with || (N1QL string concatenation)
        $sql = preg_replace('/\bCONCAT\s*\(([^,]+),\s*([^)]+)\)/i', '($1 || $2)', $sql);
        
        // Replace TIMESTAMPDIFF with DATE_DIFF_STR equivalent
        $sql = $this->convertTimestampDiff($sql);
        
        // Replace MySQL RAND() with N1QL RANDOM()
        $sql = preg_replace('/\bRAND\s*\(\s*\)/i', 'RANDOM()', $sql);
        
        // Handle backtick quoting (already N1QL compatible)
        
        return $sql;
    }
    
    /**
     * Convert GROUP_CONCAT to N1QL CONCAT2(separator, ARRAY_AGG(...))
     * N1QL uses CONCAT2 to join array elements with a separator
     * @param string $sql
     * @return string
     */
    private function convertGroupConcat($sql)
    {
        // Pattern 1: GROUP_CONCAT(DISTINCT col ORDER BY ... SEPARATOR 'sep')
        // Pattern 2: GROUP_CONCAT(DISTINCT col SEPARATOR 'sep')
        // Pattern 3: GROUP_CONCAT(DISTINCT col)
        // Pattern 4: GROUP_CONCAT(col)
        
        // Handle with SEPARATOR
        $sql = preg_replace_callback(
            '/GROUP_CONCAT\s*\(\s*(DISTINCT\s+)?(.+?)\s+SEPARATOR\s+[\'"]([^\'"]+)[\'"]\s*\)/i',
            function($matches) {
                $distinct = $matches[1] ? 'DISTINCT ' : '';
                $expr = $this->cleanGroupConcatExpr($matches[2]);
                $separator = $matches[3];
                // Use CONCAT2(separator, array) - the N1QL way to join array elements
                return "CONCAT2('{$separator}', ARRAY_AGG({$distinct}{$expr}))";
            },
            $sql
        );
        
        // Handle without SEPARATOR (default comma)
        $sql = preg_replace_callback(
            '/GROUP_CONCAT\s*\(\s*(DISTINCT\s+)?([^)]+)\)/i',
            function($matches) {
                // Skip if already converted
                if (stripos($matches[0], 'ARRAY_AGG') !== false) {
                    return $matches[0];
                }
                $distinct = $matches[1] ? 'DISTINCT ' : '';
                $expr = $this->cleanGroupConcatExpr($matches[2]);
                return "CONCAT2(',', ARRAY_AGG({$distinct}{$expr}))";
            },
            $sql
        );
        
        return $sql;
    }
    
    /**
     * Clean GROUP_CONCAT expression (remove ORDER BY clauses)
     * @param string $expr
     * @return string
     */
    private function cleanGroupConcatExpr($expr)
    {
        // Remove ORDER BY clause if present
        $expr = preg_replace('/\s+ORDER\s+BY\s+[^)]+$/i', '', $expr);
        return trim($expr);
    }
    
    /**
     * Convert FIND_IN_SET to N1QL ARRAY_CONTAINS
     * @param string $sql
     * @return string
     */
    private function convertFindInSet($sql)
    {
        // FIND_IN_SET(val, col) > 0 -> ARRAY_CONTAINS(SPLIT(col, ','), val)
        $sql = preg_replace_callback(
            '/FIND_IN_SET\s*\(\s*([^,]+),\s*([^)]+)\)\s*>\s*0/i',
            function($matches) {
                $val = trim($matches[1]);
                $col = trim($matches[2]);
                return "ARRAY_CONTAINS(SPLIT({$col}, ','), {$val})";
            },
            $sql
        );
        
        // Simple FIND_IN_SET without > 0 comparison
        $sql = preg_replace_callback(
            '/FIND_IN_SET\s*\(\s*([^,]+),\s*([^)]+)\)/i',
            function($matches) {
                // Skip if already converted
                if (stripos($matches[0], 'ARRAY_CONTAINS') !== false) {
                    return $matches[0];
                }
                $val = trim($matches[1]);
                $col = trim($matches[2]);
                return "(CASE WHEN ARRAY_CONTAINS(SPLIT({$col}, ','), {$val}) THEN ARRAY_POSITION(SPLIT({$col}, ','), {$val}) + 1 ELSE 0 END)";
            },
            $sql
        );
        
        return $sql;
    }
    
    /**
     * Convert SUBSTRING_INDEX to N1QL equivalent
     * @param string $sql
     * @return string
     */
    private function convertSubstringIndex($sql)
    {
        // SUBSTRING_INDEX(str, delim, -1) -> Get last element after split
        $sql = preg_replace_callback(
            '/SUBSTRING_INDEX\s*\(\s*([^,]+),\s*[\'"]([^\'"]+)[\'"],\s*(-?\d+)\s*\)/i',
            function($matches) {
                $str = trim($matches[1]);
                $delim = $matches[2];
                $count = (int)$matches[3];
                
                if ($count == -1) {
                    // Get last element
                    return "ARRAY_REVERSE(SPLIT({$str}, '{$delim}'))[0]";
                } elseif ($count == 1) {
                    // Get first element
                    return "SPLIT({$str}, '{$delim}')[0]";
                } else {
                    // More complex cases - fall back to MariaDB
                    return $matches[0];
                }
            },
            $sql
        );
        
        return $sql;
    }
    
    /**
     * Convert TIMESTAMPDIFF to N1QL equivalent
     * @param string $sql
     * @return string
     */
    private function convertTimestampDiff($sql)
    {
        // TIMESTAMPDIFF(YEAR, date1, date2) -> DATE_DIFF_STR(date2, date1, 'year')
        $sql = preg_replace_callback(
            '/TIMESTAMPDIFF\s*\(\s*(YEAR|MONTH|DAY|HOUR|MINUTE|SECOND)\s*,\s*([^,]+),\s*([^)]+)\)/i',
            function($matches) {
                $unit = strtolower($matches[1]);
                $date1 = trim($matches[2]);
                $date2 = trim($matches[3]);
                return "DATE_DIFF_STR({$date2}, {$date1}, '{$unit}')";
            },
            $sql
        );
        
        return $sql;
    }
}

/**
 * CouchbaseDbTransaction - Stub for transaction support
 */
class CouchbaseDbTransaction
{
    private $_connection;
    private $_active = true;
    
    public function __construct(CouchbaseDbConnection $connection)
    {
        $this->_connection = $connection;
    }
    
    public function commit()
    {
        // Couchbase transactions are handled differently
        // For compatibility, we just mark as inactive
        $this->_active = false;
    }
    
    public function rollback()
    {
        // Couchbase doesn't support traditional rollback
        // Log a warning
        Yii::log('Rollback called but Couchbase does not support traditional transactions', CLogger::LEVEL_WARNING);
        $this->_active = false;
    }
    
    public function getActive()
    {
        return $this->_active;
    }
}
