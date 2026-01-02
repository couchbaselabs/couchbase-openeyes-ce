<?php
/**
 * Helper class for migrating SQL queries to N1QL
 */

namespace OE\Database;

class QueryMigrationHelper
{
    private $bucket = 'openeyes';
    private $collectionMap;
    
    public function __construct()
    {
        $mapFile = \Yii::getPathOfAlias('application.config') . '/couchbase-collection-map.php';
        if (file_exists($mapFile)) {
            $this->collectionMap = require($mapFile);
        } else {
            $this->collectionMap = $this->getDefaultMapping();
        }
    }
    
    /**
     * Get default table to scope/collection mapping
     * @return array
     */
    private function getDefaultMapping()
    {
        return [
            'core' => [
                'patient', 'contact', 'address', 'user', 'episode', 'event',
                'firm', 'site', 'institution', 'service_subspecialty_assignment',
            ],
            'clinical' => [
                'examination', 'diagnosis', 'allergy', 'medication', 'procedure',
            ],
            'booking' => [
                'operation', 'session', 'whiteboard', 'booking',
            ],
            'correspondence' => [
                'letter', 'message', 'document',
            ],
            'admin' => [
                'audit', 'setting', 'user_session',
            ],
            'reference' => [
                'specialty', 'subspecialty', 'disorder', 'drug', 'procedure_type',
            ],
        ];
    }
    
    /**
     * Get the scope for a given table
     * @param string $table Table name
     * @return string Scope name
     */
    public function getScopeForTable($table)
    {
        $table = strtolower(preg_replace('/^(et_|ophtr|ophco|ophci)/', '', $table));
        
        foreach ($this->collectionMap as $scope => $tables) {
            if (in_array($table, $tables)) {
                return $scope;
            }
        }
        
        // Pattern-based mapping
        if (preg_match('/^et_ophciexamination_/', $table)) {
            return 'clinical';
        }
        if (preg_match('/^ophtr/', $table)) {
            return 'booking';
        }
        if (preg_match('/^ophco/', $table)) {
            return 'correspondence';
        }
        
        return 'core';
    }
    
    /**
     * Get the full keyspace for a table
     * @param string $table Table name
     * @return string Keyspace string
     */
    public function getKeyspace($table)
    {
        $scope = $this->getScopeForTable($table);
        $collection = $this->getCollectionName($table);
        return "`{$this->bucket}`.`{$scope}`.`{$collection}`";
    }
    
    /**
     * Get collection name from table name
     * @param string $table MySQL table name
     * @return string Collection name
     */
    public function getCollectionName($table)
    {
        // Remove common prefixes
        $collection = preg_replace('/^(et_ophciexamination_|et_|ophtr|ophco|ophci)/', '', $table);
        return strtolower($collection);
    }
    
    /**
     * Convert a simple SELECT query to N1QL
     * @param string $sql SQL query
     * @return string N1QL query
     */
    public function convertSimpleSelect($sql)
    {
        $n1ql = $sql;
        
        // Extract table name and replace with keyspace
        if (preg_match('/FROM\s+[`]?(\w+)[`]?(\s+AS\s+(\w+))?/i', $sql, $matches)) {
            $table = $matches[1];
            $alias = $matches[3] ?? null;
            
            $keyspace = $this->getKeyspace($table);
            if ($alias) {
                $keyspace .= " AS {$alias}";
            }
            
            $n1ql = preg_replace(
                '/FROM\s+[`]?\w+[`]?(\s+AS\s+\w+)?/i',
                "FROM {$keyspace}",
                $n1ql
            );
        }
        
        // Add META().id to SELECT if selecting *
        if (preg_match('/SELECT\s+\*/i', $n1ql)) {
            $n1ql = preg_replace(
                '/SELECT\s+\*/i',
                'SELECT META().id AS _id, *',
                $n1ql
            );
        }
        
        // Convert MySQL-specific functions
        $n1ql = $this->convertFunctions($n1ql);
        
        // Convert parameter placeholders
        $n1ql = $this->convertParameters($n1ql);
        
        return $n1ql;
    }
    
    /**
     * Convert MySQL functions to N1QL equivalents
     * @param string $query Query string
     * @return string Converted query
     */
    public function convertFunctions($query)
    {
        $conversions = [
            '/IFNULL\s*\(/i' => 'IFNULL(',
            '/COALESCE\s*\(/i' => 'IFNULL(',
            '/NOW\s*\(\)/i' => 'NOW_STR()',
            '/CURDATE\s*\(\)/i' => 'SUBSTR(NOW_STR(), 0, 10)',
            '/DATE_FORMAT\s*\(\s*([^,]+),\s*[\'"]%Y-%m-%d[\'"]\s*\)/i' => 'SUBSTR($1, 0, 10)',
            '/CONCAT\s*\(/i' => 'CONCAT(',
            '/GROUP_CONCAT\s*\(/i' => 'ARRAY_AGG(',
            '/DATEDIFF\s*\(\s*([^,]+),\s*([^)]+)\)/i' => 'DATE_DIFF_STR($1, $2, "day")',
        ];
        
        foreach ($conversions as $pattern => $replacement) {
            $query = preg_replace($pattern, $replacement, $query);
        }
        
        return $query;
    }
    
    /**
     * Convert MySQL parameter placeholders to N1QL named parameters
     * @param string $query Query string
     * @return string Converted query
     */
    public function convertParameters($query)
    {
        // Convert :paramName to $paramName
        $query = preg_replace('/:(\w+)/', '\$$1', $query);
        
        // Convert ? placeholders to named params (p0, p1, etc.)
        $index = 0;
        $query = preg_replace_callback('/\?/', function() use (&$index) {
            return '$p' . ($index++);
        }, $query);
        
        return $query;
    }
    
    /**
     * Convert CDbCriteria to N1qlQueryBuilder
     * @param CDbCriteria $criteria Yii criteria object
     * @param string $table Table name
     * @return N1qlQueryBuilder
     */
    public function criteriaToBuilder(\CDbCriteria $criteria, $table)
    {
        $builder = new N1qlQueryBuilder($this->bucket);
        
        $scope = $this->getScopeForTable($table);
        $collection = $this->getCollectionName($table);
        
        $builder->from($scope, $collection);
        
        // SELECT
        if ($criteria->select !== '*') {
            $builder->select($criteria->select);
        }
        
        // WHERE
        if (!empty($criteria->condition)) {
            $condition = $this->convertParameters($criteria->condition);
            $condition = $this->convertFunctions($condition);
            $builder->where($condition, $criteria->params ?: []);
        }
        
        // ORDER
        if (!empty($criteria->order)) {
            $orders = explode(',', $criteria->order);
            foreach ($orders as $order) {
                $parts = preg_split('/\s+/', trim($order));
                $column = $parts[0];
                $direction = $parts[1] ?? 'ASC';
                $builder->orderBy($column, $direction);
            }
        }
        
        // LIMIT
        if ($criteria->limit > 0) {
            $builder->limit($criteria->limit);
        }
        
        // OFFSET
        if ($criteria->offset > 0) {
            $builder->offset($criteria->offset);
        }
        
        return $builder;
    }
    
    /**
     * Create a new N1qlQueryBuilder for the given table
     * @param string $table Table name
     * @return N1qlQueryBuilder
     */
    public function createBuilder($table)
    {
        $builder = new N1qlQueryBuilder($this->bucket);
        $scope = $this->getScopeForTable($table);
        $collection = $this->getCollectionName($table);
        
        return $builder->from($scope, $collection);
    }
}
