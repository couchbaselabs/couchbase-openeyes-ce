<?php
/**
 * CouchbaseTableSchema - Table schema loaded from static cache
 * 
 * This extends CDbTableSchema to work with statically cached schema
 * information instead of querying MariaDB directly.
 */

class CouchbaseTableSchema extends CDbTableSchema
{
    /**
     * @var array Index definitions
     */
    public $indexes = [];
    
    /**
     * Get column by name
     * @param string $name Column name
     * @return CDbColumnSchema|null
     */
    public function getColumn($name)
    {
        return isset($this->columns[$name]) ? $this->columns[$name] : null;
    }
    
    /**
     * Check if column exists
     * @param string $name Column name
     * @return bool
     */
    public function hasColumn($name)
    {
        return isset($this->columns[$name]);
    }
    
    /**
     * Get all column names
     * @return array
     */
    public function getColumnNames()
    {
        return array_keys($this->columns);
    }
    
    /**
     * Get primary key column(s)
     * @return CDbColumnSchema|array|null
     */
    public function getPrimaryKeyColumn()
    {
        if (is_string($this->primaryKey)) {
            return $this->getColumn($this->primaryKey);
        } elseif (is_array($this->primaryKey)) {
            $columns = [];
            foreach ($this->primaryKey as $name) {
                $columns[$name] = $this->getColumn($name);
            }
            return $columns;
        }
        return null;
    }
    
    /**
     * Check if table has auto-increment column
     * @return bool
     */
    public function hasAutoIncrement()
    {
        foreach ($this->columns as $column) {
            if ($column->autoIncrement) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get auto-increment column name
     * @return string|null
     */
    public function getAutoIncrementColumn()
    {
        foreach ($this->columns as $name => $column) {
            if ($column->autoIncrement) {
                return $name;
            }
        }
        return null;
    }
    
    /**
     * Get foreign key columns
     * @return array Column names that are foreign keys
     */
    public function getForeignKeyColumns()
    {
        return array_keys($this->foreignKeys);
    }
    
    /**
     * Check if column is a foreign key
     * @param string $name Column name
     * @return bool
     */
    public function isForeignKey($name)
    {
        return isset($this->foreignKeys[$name]);
    }
    
    /**
     * Get referenced table for a foreign key column
     * @param string $name Column name
     * @return array|null [table, column] or null
     */
    public function getForeignKeyReference($name)
    {
        return isset($this->foreignKeys[$name]) ? $this->foreignKeys[$name] : null;
    }
    
    /**
     * Get index by name
     * @param string $name Index name
     * @return array|null
     */
    public function getIndex($name)
    {
        return isset($this->indexes[$name]) ? $this->indexes[$name] : null;
    }
    
    /**
     * Get unique indexes
     * @return array
     */
    public function getUniqueIndexes()
    {
        $unique = [];
        foreach ($this->indexes as $name => $index) {
            if (!empty($index['unique'])) {
                $unique[$name] = $index;
            }
        }
        return $unique;
    }
}
