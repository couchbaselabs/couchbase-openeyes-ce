<?php
/**
 * Base class for transforming MySQL records to Couchbase documents
 */

namespace OE\Couchbase\Transformers;

abstract class DocumentTransformer
{
    /** @var array Column type cache */
    protected $columnTypes = [];
    
    /** @var \CDbConnection */
    protected $db;
    
    public function __construct()
    {
        $this->db = \Yii::app()->cbdb;
        $this->loadColumnTypes();
    }
    
    /**
     * Get the MySQL table name
     * @return string
     */
    abstract public function getTableName();
    
    /**
     * Get the Couchbase document type
     * @return string
     */
    abstract public function getDocumentType();
    
    /**
     * Get the Couchbase scope
     * @return string
     */
    abstract public function getScope();
    
    /**
     * Get the Couchbase collection
     * @return string
     */
    abstract public function getCollection();
    
    /**
     * Transform a MySQL row to a Couchbase document
     * @param array $row MySQL row
     * @return array Couchbase document
     */
    public function transform($row)
    {
        // Start with base document structure
        $document = [
            '_type' => $this->getDocumentType(),
            '_id' => $this->generateDocumentKey($row),
            '_mysql_id' => (int)$row['id'],
            '_created' => date('c'),
            '_modified' => date('c'),
            '_version' => 1,
        ];
        
        // Transform base columns
        $transformed = TypeTransformer::transformRow($row, $this->columnTypes);
        
        // Apply custom transformations
        $transformed = $this->customTransform($transformed, $row);
        
        // Merge, with document metadata taking precedence
        return array_merge($transformed, $document);
    }
    
    /**
     * Apply custom transformations specific to this document type
     * Override in subclasses for entity-specific logic
     * 
     * @param array $transformed Already type-transformed data
     * @param array $originalRow Original MySQL row
     * @return array Further transformed data
     */
    protected function customTransform($transformed, $originalRow)
    {
        return $transformed;
    }
    
    /**
     * Generate the document key
     * @param array $row MySQL row
     * @return string Document key
     */
    protected function generateDocumentKey($row)
    {
        return $this->getCollection() . '::' . $row['id'];
    }
    
    /**
     * Load column types from database schema
     */
    protected function loadColumnTypes()
    {
        $tableName = $this->getTableName();
        $columns = $this->db->createCommand("DESCRIBE `{$tableName}`")->queryAll();
        
        foreach ($columns as $col) {
            $this->columnTypes[$col['Field']] = $col['Type'];
        }
    }
    
    /**
     * Embed related data into the document
     * 
     * @param array $document Current document
     * @param string $key Key to store embedded data
     * @param string $table Related table
     * @param string $foreignKey Foreign key column
     * @param mixed $foreignValue Foreign key value
     * @return array Document with embedded data
     */
    protected function embedRelated($document, $key, $table, $foreignKey, $foreignValue)
    {
        if ($foreignValue === null) {
            $document[$key] = null;
            return $document;
        }
        
        $sql = "SELECT * FROM `{$table}` WHERE `{$foreignKey}` = :fk";
        $related = $this->db->createCommand($sql)->queryRow(true, [':fk' => $foreignValue]);
        
        if ($related) {
            // Get column types for related table
            $relatedTypes = $this->getColumnTypesForTable($table);
            $document[$key] = TypeTransformer::transformRow($related, $relatedTypes);
        } else {
            $document[$key] = null;
        }
        
        return $document;
    }
    
    /**
     * Embed multiple related records
     * 
     * @param array $document Current document
     * @param string $key Key to store embedded array
     * @param string $table Related table
     * @param string $foreignKey Foreign key column
     * @param mixed $foreignValue Foreign key value
     * @return array Document with embedded array
     */
    protected function embedRelatedMany($document, $key, $table, $foreignKey, $foreignValue)
    {
        if ($foreignValue === null) {
            $document[$key] = [];
            return $document;
        }
        
        $sql = "SELECT * FROM `{$table}` WHERE `{$foreignKey}` = :fk";
        $rows = $this->db->createCommand($sql)->queryAll(true, [':fk' => $foreignValue]);
        
        $relatedTypes = $this->getColumnTypesForTable($table);
        $document[$key] = array_map(
            function($row) use ($relatedTypes) {
                return TypeTransformer::transformRow($row, $relatedTypes);
            },
            $rows
        );
        
        return $document;
    }
    
    /**
     * Get column types for any table
     * @param string $table Table name
     * @return array Column types
     */
    protected function getColumnTypesForTable($table)
    {
        static $cache = [];
        
        if (!isset($cache[$table])) {
            $columns = $this->db->createCommand("DESCRIBE `{$table}`")->queryAll();
            $cache[$table] = [];
            foreach ($columns as $col) {
                $cache[$table][$col['Field']] = $col['Type'];
            }
        }
        
        return $cache[$table];
    }
    
    /**
     * Transform document back to MySQL row format
     * @param array $document Couchbase document
     * @return array MySQL row
     */
    public function reverseTransform($document)
    {
        $row = [];
        
        // Remove document metadata
        $data = array_filter($document, function($k) {
            return strpos($k, '_') !== 0;
        }, ARRAY_FILTER_USE_KEY);
        
        foreach ($data as $column => $value) {
            if (isset($this->columnTypes[$column])) {
                $row[$column] = TypeTransformer::toMysql($value, $this->columnTypes[$column], $column);
            }
        }
        
        // Restore ID from metadata
        if (isset($document['_mysql_id'])) {
            $row['id'] = $document['_mysql_id'];
        }
        
        return $row;
    }
}
