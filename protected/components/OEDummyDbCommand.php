<?php
/**
 * Dummy database command that returns empty results when MariaDB is unavailable.
 * Used as a fallback when the application tries to execute SQL queries without a database connection.
 */
class OEDummyDbCommand extends CDbCommand
{
    private $_text;
    private $_connection;
    
    public function __construct($connection, $query = null)
    {
        $this->_connection = $connection;
        $this->_text = $query;
    }
    
    public function getText()
    {
        return $this->_text;
    }
    
    public function setText($value)
    {
        $this->_text = $value;
        return $this;
    }
    
    public function getConnection()
    {
        return $this->_connection;
    }
    
    public function prepare()
    {
        // Do nothing - no PDO to prepare
    }
    
    public function cancel()
    {
        // Do nothing
    }
    
    public function bindParam($name, &$value, $dataType = null, $length = null, $driverOptions = null)
    {
        return $this;
    }
    
    public function bindValue($name, $value, $dataType = null)
    {
        return $this;
    }
    
    public function bindValues($values)
    {
        return $this;
    }
    
    public function execute($params = [])
    {
        return 0; // No rows affected
    }
    
    public function query($params = [])
    {
        // Avoid creating a CDbDataReader which expects a PDO statement; return empty result instead
        return [];
    }
    
    public function queryAll($fetchAssociative = true, $params = [])
    {
        return []; // Empty array
    }
    
    public function queryRow($fetchAssociative = true, $params = [])
    {
        return false; // No row found
    }
    
    public function queryScalar($params = [])
    {
        return false; // No value
    }
    
    public function queryColumn($params = [])
    {
        return []; // Empty array
    }
    
    // Query builder methods - return $this for chaining
    public function select($columns = '*', $option = '')
    {
        return $this;
    }
    
    public function selectDistinct($columns = '*')
    {
        return $this;
    }
    
    public function from($tables)
    {
        return $this;
    }
    
    public function where($conditions, $params = [])
    {
        return $this;
    }
    
    public function andWhere($conditions, $params = [])
    {
        return $this;
    }
    
    public function orWhere($conditions, $params = [])
    {
        return $this;
    }
    
    public function join($table, $conditions, $params = [])
    {
        return $this;
    }
    
    public function leftJoin($table, $conditions, $params = [])
    {
        return $this;
    }
    
    public function rightJoin($table, $conditions, $params = [])
    {
        return $this;
    }
    
    public function crossJoin($table)
    {
        return $this;
    }
    
    public function naturalJoin($table)
    {
        return $this;
    }
    
    public function group($columns)
    {
        return $this;
    }
    
    public function having($conditions, $params = [])
    {
        return $this;
    }
    
    public function order($columns)
    {
        return $this;
    }
    
    public function limit($limit, $offset = null)
    {
        return $this;
    }
    
    public function offset($offset)
    {
        return $this;
    }
    
    public function union($sql)
    {
        return $this;
    }
    
    public function insert($table, $columns)
    {
        return 0;
    }
    
    public function update($table, $columns, $conditions = '', $params = [])
    {
        return 0;
    }
    
    public function delete($table, $conditions = '', $params = [])
    {
        return 0;
    }
}
