<?php
/**
 * Handles MySQL to Couchbase type transformations
 * 
 * Converts MySQL column values to appropriate JSON types
 * and back for bidirectional data flow.
 */

namespace OE\Couchbase\Transformers;

class TypeTransformer
{
    /**
     * MySQL to JSON type mapping
     */
    private static $typeMap = [
        'int' => 'integer',
        'tinyint' => 'integer',
        'smallint' => 'integer',
        'mediumint' => 'integer',
        'bigint' => 'integer',
        'decimal' => 'number',
        'float' => 'number',
        'double' => 'number',
        'char' => 'string',
        'varchar' => 'string',
        'text' => 'string',
        'mediumtext' => 'string',
        'longtext' => 'string',
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'datetime',
        'time' => 'time',
        'tinyint(1)' => 'boolean',
        'enum' => 'string',
        'set' => 'array',
        'blob' => 'binary',
        'mediumblob' => 'binary',
        'longblob' => 'binary',
        'json' => 'object',
    ];
    
    /**
     * Boolean field patterns (tinyint(1) columns that are booleans)
     */
    private static $booleanFields = [
        'active', 'deleted', 'is_', 'has_', 'can_', 'allow_',
        'enabled', 'disabled', 'visible', 'hidden', 'default',
        'global_firm_rights', 'support_services', 'delete_pending',
    ];
    
    /**
     * Transform MySQL value to Couchbase JSON value
     * 
     * @param mixed $value The MySQL value
     * @param string $mysqlType MySQL column type (e.g., 'int(10) unsigned')
     * @param string $columnName Column name for context
     * @return mixed Transformed value
     */
    public static function toJson($value, $mysqlType, $columnName = '')
    {
        if ($value === null) {
            return null;
        }
        
        // Normalize type string
        $baseType = strtolower(preg_replace('/\([^)]+\)/', '', $mysqlType));
        $baseType = trim(str_replace(['unsigned', 'signed'], '', $baseType));
        
        // Check for boolean by column name pattern
        if (self::isBooleanField($columnName, $mysqlType)) {
            return (bool)$value;
        }
        
        switch ($baseType) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return (int)$value;
                
            case 'decimal':
            case 'float':
            case 'double':
                return (float)$value;
                
            case 'date':
                // Convert to ISO 8601 date format
                if ($value === '0000-00-00') {
                    return null;
                }
                return date('Y-m-d', strtotime($value));
                
            case 'datetime':
            case 'timestamp':
                // Convert to ISO 8601 datetime format
                if ($value === '0000-00-00 00:00:00') {
                    return null;
                }
                return date('c', strtotime($value));
                
            case 'time':
                return $value; // Keep as HH:MM:SS string
                
            case 'blob':
            case 'mediumblob':
            case 'longblob':
                // Base64 encode binary data
                return base64_encode($value);
                
            case 'json':
                // Decode JSON string to object/array
                $decoded = json_decode($value, true);
                return $decoded ?? $value;
                
            case 'set':
                // Convert comma-separated to array
                return $value ? explode(',', $value) : [];
                
            case 'enum':
            case 'char':
            case 'varchar':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            default:
                return (string)$value;
        }
    }
    
    /**
     * Transform Couchbase JSON value back to MySQL format
     * 
     * @param mixed $value The JSON value
     * @param string $mysqlType Target MySQL column type
     * @param string $columnName Column name for context
     * @return mixed MySQL-compatible value
     */
    public static function toMysql($value, $mysqlType, $columnName = '')
    {
        if ($value === null) {
            return null;
        }
        
        $baseType = strtolower(preg_replace('/\([^)]+\)/', '', $mysqlType));
        $baseType = trim(str_replace(['unsigned', 'signed'], '', $baseType));
        
        // Boolean to tinyint
        if (self::isBooleanField($columnName, $mysqlType)) {
            return $value ? 1 : 0;
        }
        
        switch ($baseType) {
            case 'datetime':
            case 'timestamp':
                // Convert ISO 8601 back to MySQL format
                if (is_string($value) && $value) {
                    return date('Y-m-d H:i:s', strtotime($value));
                }
                return $value;
                
            case 'date':
                if (is_string($value) && $value) {
                    return date('Y-m-d', strtotime($value));
                }
                return $value;
                
            case 'blob':
            case 'mediumblob':
            case 'longblob':
                // Decode base64
                return base64_decode($value);
                
            case 'json':
                // Encode to JSON string
                return json_encode($value);
                
            case 'set':
                // Convert array to comma-separated
                return is_array($value) ? implode(',', $value) : $value;
                
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return (int)$value;
                
            case 'decimal':
            case 'float':
            case 'double':
                return (float)$value;
                
            default:
                return (string)$value;
        }
    }
    
    /**
     * Check if column should be treated as boolean
     * 
     * @param string $columnName Column name
     * @param string $mysqlType MySQL type
     * @return bool
     */
    private static function isBooleanField($columnName, $mysqlType)
    {
        // tinyint(1) is typically boolean
        if (strpos($mysqlType, 'tinyint(1)') !== false) {
            // Check common boolean patterns
            foreach (self::$booleanFields as $pattern) {
                if (strpos($columnName, $pattern) === 0 || $columnName === $pattern) {
                    return true;
                }
            }
            // If tinyint(1) and has boolean-like name, treat as bool
            if (preg_match('/^(is_|has_|can_|allow_|enable|disable|show_|hide_)/', $columnName)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Transform an entire row from MySQL to Couchbase format
     * 
     * @param array $row MySQL row data
     * @param array $columnTypes Column name => MySQL type mapping
     * @return array Transformed row
     */
    public static function transformRow($row, $columnTypes)
    {
        $result = [];
        
        foreach ($row as $column => $value) {
            $type = isset($columnTypes[$column]) ? $columnTypes[$column] : 'varchar(255)';
            $result[$column] = self::toJson($value, $type, $column);
        }
        
        return $result;
    }
    
    /**
     * Get JSON Schema type for a MySQL type
     * 
     * @param string $mysqlType MySQL column type
     * @param bool $nullable Whether column allows NULL
     * @return array JSON Schema type definition
     */
    public static function getJsonSchemaType($mysqlType, $nullable = false)
    {
        $baseType = strtolower(preg_replace('/\([^)]+\)/', '', $mysqlType));
        $baseType = trim(str_replace(['unsigned', 'signed'], '', $baseType));
        
        $jsonType = null;
        switch ($baseType) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                $jsonType = 'integer';
                break;
            case 'decimal':
            case 'float':
            case 'double':
                $jsonType = 'number';
                break;
            case 'date':
                $jsonType = ['type' => 'string', 'format' => 'date'];
                break;
            case 'datetime':
            case 'timestamp':
                $jsonType = ['type' => 'string', 'format' => 'date-time'];
                break;
            case 'json':
                $jsonType = 'object';
                break;
            case 'set':
                $jsonType = 'array';
                break;
            default:
                $jsonType = 'string';
                break;
        }
        
        // Handle nullable
        if ($nullable && is_string($jsonType)) {
            return ['type' => [$jsonType, 'null']];
        } elseif ($nullable && is_array($jsonType)) {
            $jsonType['type'] = [$jsonType['type'], 'null'];
            return $jsonType;
        }
        
        return is_array($jsonType) ? $jsonType : ['type' => $jsonType];
    }
}
