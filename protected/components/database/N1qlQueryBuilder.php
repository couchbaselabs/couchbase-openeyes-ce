<?php
/**
 * Fluent N1QL Query Builder for Couchbase
 * 
 * Provides a chainable interface for building N1QL queries with
 * proper parameter binding and Couchbase-specific features.
 * 
 * Usage:
 *   $builder = new N1qlQueryBuilder('openeyes');
 *   $results = $builder
 *       ->from('core', 'patient')
 *       ->select('hos_num, nhs_num, dob')
 *       ->where('hos_num = $hosNum', ['hosNum' => '12345'])
 *       ->orderBy('last_name')
 *       ->limit(10)
 *       ->execute();
 */

namespace OE\Database;

class N1qlQueryBuilder
{
    private $bucket;
    private $scope;
    private $collection;
    private $alias;
    private $select = ['*'];
    private $joins = [];
    private $where = [];
    private $params = [];
    private $orderBy = [];
    private $groupBy = [];
    private $having = [];
    private $limit;
    private $offset;
    private $useKeys = null;
    private $includeMetaId = true;
    
    /**
     * @param string $bucket Couchbase bucket name
     */
    public function __construct(string $bucket = 'openeyes')
    {
        $this->bucket = $bucket;
    }
    
    /**
     * Set the collection to query from
     * @param string $scope Scope name
     * @param string $collection Collection name
     * @param string|null $alias Optional alias
     * @return self
     */
    public function from(string $scope, string $collection, $alias = null)
    {
        $this->scope = $scope;
        $this->collection = $collection;
        $this->alias = $alias;
        return $this;
    }
    
    /**
     * Set SELECT columns
     * @param string|array $columns Columns to select
     * @return self
     */
    public function select($columns)
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $this->select = $columns;
        return $this;
    }
    
    /**
     * Add SELECT columns
     * @param string|array $columns Additional columns
     * @return self
     */
    public function addSelect($columns)
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $this->select = array_merge($this->select, $columns);
        return $this;
    }
    
    /**
     * Disable automatic META().id inclusion
     * @return self
     */
    public function withoutMetaId()
    {
        $this->includeMetaId = false;
        return $this;
    }
    
    /**
     * Add WHERE condition (AND)
     * @param string $condition Condition string
     * @param array $params Parameters for the condition
     * @return self
     */
    public function where(string $condition, array $params = [])
    {
        $this->where[] = ['AND', $condition];
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    /**
     * Alias for where() - adds AND condition
     * @param string $condition Condition string
     * @param array $params Parameters
     * @return self
     */
    public function andWhere(string $condition, array $params = [])
    {
        return $this->where($condition, $params);
    }
    
    /**
     * Add OR WHERE condition
     * @param string $condition Condition string
     * @param array $params Parameters
     * @return self
     */
    public function orWhere(string $condition, array $params = [])
    {
        $this->where[] = ['OR', $condition];
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    /**
     * Add IN condition
     * @param string $column Column name
     * @param array $values Array of values
     * @param string $paramPrefix Parameter name prefix
     * @return self
     */
    public function whereIn(string $column, array $values, string $paramPrefix = 'in')
    {
        if (empty($values)) {
            $this->where[] = ['AND', '1 = 0']; // Always false
            return $this;
        }
        
        $placeholders = [];
        foreach ($values as $i => $value) {
            $paramName = $paramPrefix . $i;
            $placeholders[] = '$' . $paramName;
            $this->params[$paramName] = $value;
        }
        
        $this->where[] = ['AND', "{$column} IN [" . implode(', ', $placeholders) . "]"];
        return $this;
    }
    
    /**
     * Add BETWEEN condition
     * @param string $column Column name
     * @param mixed $start Start value
     * @param mixed $end End value
     * @param string $paramPrefix Parameter prefix
     * @return self
     */
    public function whereBetween(string $column, $start, $end, string $paramPrefix = 'between')
    {
        $startParam = $paramPrefix . 'Start';
        $endParam = $paramPrefix . 'End';
        
        $this->where[] = ['AND', "{$column} BETWEEN \${$startParam} AND \${$endParam}"];
        $this->params[$startParam] = $start;
        $this->params[$endParam] = $end;
        
        return $this;
    }
    
    /**
     * Add LIKE condition
     * @param string $column Column name
     * @param string $pattern LIKE pattern
     * @param string $paramName Parameter name
     * @return self
     */
    public function whereLike(string $column, string $pattern, string $paramName = 'like')
    {
        $this->where[] = ['AND', "{$column} LIKE \${$paramName}"];
        $this->params[$paramName] = $pattern;
        return $this;
    }
    
    /**
     * Add case-insensitive LIKE condition
     * @param string $column Column name
     * @param string $pattern LIKE pattern
     * @param string $paramName Parameter name
     * @return self
     */
    public function whereILike(string $column, string $pattern, string $paramName = 'ilike')
    {
        $this->where[] = ['AND', "LOWER({$column}) LIKE LOWER(\${$paramName})"];
        $this->params[$paramName] = $pattern;
        return $this;
    }
    
    /**
     * Add IS NULL condition
     * @param string $column Column name
     * @return self
     */
    public function whereNull(string $column)
    {
        $this->where[] = ['AND', "{$column} IS NULL"];
        return $this;
    }
    
    /**
     * Add IS NOT NULL condition
     * @param string $column Column name
     * @return self
     */
    public function whereNotNull(string $column)
    {
        $this->where[] = ['AND', "{$column} IS NOT NULL"];
        return $this;
    }
    
    /**
     * Add ANY/SATISFIES condition for array queries
     * @param string $arrayPath Path to array field
     * @param string $itemVar Variable name for array item
     * @param string $condition Condition using itemVar
     * @param array $params Parameters for condition
     * @return self
     */
    public function whereAny(string $arrayPath, string $itemVar, string $condition, array $params = [])
    {
        $this->where[] = ['AND', "ANY {$itemVar} IN {$arrayPath} SATISFIES {$condition} END"];
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    /**
     * Add JOIN clause
     * @param string $scope Target scope
     * @param string $collection Target collection
     * @param string $on JOIN condition
     * @param string|null $alias Alias for joined collection
     * @return self
     */
    public function join(string $scope, string $collection, string $on, $alias = null)
    {
        $this->joins[] = [
            'type' => 'JOIN',
            'scope' => $scope,
            'collection' => $collection,
            'alias' => $alias,
            'on' => $on,
        ];
        return $this;
    }
    
    /**
     * Add LEFT JOIN clause
     * @param string $scope Target scope
     * @param string $collection Target collection
     * @param string $on JOIN condition
     * @param string|null $alias Alias
     * @return self
     */
    public function leftJoin(string $scope, string $collection, string $on, $alias = null)
    {
        $this->joins[] = [
            'type' => 'LEFT JOIN',
            'scope' => $scope,
            'collection' => $collection,
            'alias' => $alias,
            'on' => $on,
        ];
        return $this;
    }
    
    /**
     * Add NEST clause (Couchbase-specific - embeds joined docs as array)
     * @param string $scope Target scope
     * @param string $collection Target collection
     * @param string $as Alias for nested array
     * @param string $on NEST condition
     * @return self
     */
    public function nest(string $scope, string $collection, string $as, string $on)
    {
        $this->joins[] = [
            'type' => 'NEST',
            'scope' => $scope,
            'collection' => $collection,
            'alias' => $as,
            'on' => $on,
        ];
        return $this;
    }
    
    /**
     * Add UNNEST clause (expand array into rows)
     * @param string $arrayPath Path to array field
     * @param string $as Alias for unnested items
     * @return self
     */
    public function unnest(string $arrayPath, string $as)
    {
        $this->joins[] = [
            'type' => 'UNNEST',
            'path' => $arrayPath,
            'alias' => $as,
        ];
        return $this;
    }
    
    /**
     * Add ORDER BY clause
     * @param string $column Column to order by
     * @param string $direction ASC or DESC
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC')
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }
    
    /**
     * Add GROUP BY clause
     * @param string|array $columns Columns to group by
     * @return self
     */
    public function groupBy($columns)
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }
    
    /**
     * Add HAVING clause
     * @param string $condition HAVING condition
     * @param array $params Parameters
     * @return self
     */
    public function having(string $condition, array $params = [])
    {
        $this->having[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    /**
     * Set LIMIT
     * @param int $limit Maximum rows
     * @return self
     */
    public function limit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Set OFFSET
     * @param int $offset Rows to skip
     * @return self
     */
    public function offset(int $offset)
    {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * Set USE KEYS for direct key lookup
     * @param string|array $keys Document key(s)
     * @return self
     */
    public function useKeys($keys)
    {
        $this->useKeys = is_array($keys) ? $keys : [$keys];
        return $this;
    }
    
    /**
     * Build the N1QL query string
     * @return string
     */
    public function build()
    {
        $parts = [];
        
        // SELECT clause
        $selectStr = implode(', ', $this->select);
        if ($this->includeMetaId && strpos($selectStr, 'META()') === false && $selectStr !== '*') {
            $selectStr = "META().id AS _id, {$selectStr}";
        } elseif ($selectStr === '*' && $this->includeMetaId) {
            $selectStr = "META().id AS _id, *";
        }
        $parts[] = "SELECT {$selectStr}";
        
        // FROM clause
        $keyspace = "`{$this->bucket}`.`{$this->scope}`.`{$this->collection}`";
        if ($this->alias) {
            $keyspace .= " AS {$this->alias}";
        }
        $parts[] = "FROM {$keyspace}";
        
        // USE KEYS
        if ($this->useKeys !== null) {
            $keys = array_map(function($k) { return "'{$k}'"; }, $this->useKeys);
            $parts[] = "USE KEYS [" . implode(', ', $keys) . "]";
        }
        
        // JOINs
        foreach ($this->joins as $join) {
            if ($join['type'] === 'UNNEST') {
                $parts[] = "UNNEST {$join['path']} AS {$join['alias']}";
            } else {
                $target = "`{$this->bucket}`.`{$join['scope']}`.`{$join['collection']}`";
                if ($join['alias']) {
                    $target .= " AS {$join['alias']}";
                }
                $parts[] = "{$join['type']} {$target} ON {$join['on']}";
            }
        }
        
        // WHERE clause
        if (!empty($this->where)) {
            $conditions = [];
            foreach ($this->where as $i => $w) {
                if ($i === 0) {
                    $conditions[] = $w[1];
                } else {
                    $conditions[] = $w[0] . ' ' . $w[1];
                }
            }
            $parts[] = "WHERE " . implode(' ', $conditions);
        }
        
        // GROUP BY
        if (!empty($this->groupBy)) {
            $parts[] = "GROUP BY " . implode(', ', $this->groupBy);
        }
        
        // HAVING
        if (!empty($this->having)) {
            $parts[] = "HAVING " . implode(' AND ', $this->having);
        }
        
        // ORDER BY
        if (!empty($this->orderBy)) {
            $parts[] = "ORDER BY " . implode(', ', $this->orderBy);
        }
        
        // LIMIT
        if ($this->limit !== null) {
            $parts[] = "LIMIT {$this->limit}";
        }
        
        // OFFSET
        if ($this->offset !== null) {
            $parts[] = "OFFSET {$this->offset}";
        }
        
        return implode("\n", $parts);
    }
    
    /**
     * Get bound parameters
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }
    
    /**
     * Execute the query and return results
     * @return array
     */
    public function execute()
    {
        $connection = \Yii::app()->couchbase;
        $result = $connection->query($this->build(), $this->params);
        return $result->rows();
    }
    
    /**
     * Execute and return single row
     * @return array|null
     */
    public function one()
    {
        $this->limit(1);
        $results = $this->execute();
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Execute and return scalar value
     * @param string $column Column to return
     * @return mixed|null
     */
    public function scalar($column = null)
    {
        $row = $this->one();
        if ($row === null) {
            return null;
        }
        if ($column) {
            return $row[$column] ?? null;
        }
        return reset($row);
    }
    
    /**
     * Execute COUNT query
     * @return int
     */
    public function count()
    {
        $countBuilder = clone $this;
        $countBuilder->select = ['COUNT(*) AS cnt'];
        $countBuilder->orderBy = [];
        $countBuilder->limit = null;
        $countBuilder->offset = null;
        $countBuilder->includeMetaId = false;
        
        $result = $countBuilder->one();
        return (int)($result['cnt'] ?? 0);
    }
    
    /**
     * Reset builder for reuse
     * @return self
     */
    public function reset()
    {
        $this->scope = null;
        $this->collection = null;
        $this->alias = null;
        $this->select = ['*'];
        $this->joins = [];
        $this->where = [];
        $this->params = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->having = [];
        $this->limit = null;
        $this->offset = null;
        $this->useKeys = null;
        $this->includeMetaId = true;
        return $this;
    }
    
    /**
     * Get query string for debugging
     * @return string
     */
    public function __toString()
    {
        return $this->build();
    }
}
