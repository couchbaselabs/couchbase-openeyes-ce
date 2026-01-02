<?php
/**
 * Transforms MySQL data types to appropriate PHP/Couchbase types
 */

namespace OE\Migration;

class TypeTransformer
{
    /**
     * Transform a value based on MySQL column type
     * @param mixed $value The value to transform
     * @param string $dbType MySQL column type
     * @return mixed Transformed value
     */
    public static function transform($value, string $dbType)
    {
        if ($value === null) {
            return null;
        }
        
        $baseType = self::extractBaseType($dbType);
        
        switch ($baseType) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return self::transformInteger($value);
                
            case 'decimal':
            case 'float':
            case 'double':
                return self::transformFloat($value);
                
            case 'date':
                return self::transformDate($value);
                
            case 'datetime':
            case 'timestamp':
                return self::transformDatetime($value);
                
            case 'time':
                return self::transformTime($value);
                
            case 'json':
                return self::transformJson($value);
                
            case 'tinyint(1)':
            case 'bool':
            case 'boolean':
                return self::transformBoolean($value);
                
            case 'varchar':
            case 'char':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            case 'enum':
            default:
                return self::transformString($value);
        }
    }
    
    /**
     * Extract base type from full type definition
     */
    private static function extractBaseType(string $dbType): string
    {
        // Handle boolean specially
        if (preg_match('/^tinyint\(1\)$/i', $dbType)) {
            return 'tinyint(1)';
        }
        
        // Extract base type (e.g., "int" from "int(11) unsigned")
        if (preg_match('/^(\w+)/', strtolower($dbType), $matches)) {
            return $matches[1];
        }
        
        return strtolower($dbType);
    }
    
    private static function transformInteger($value): ?int
    {
        return $value !== null ? (int)$value : null;
    }
    
    private static function transformFloat($value): ?float
    {
        return $value !== null ? (float)$value : null;
    }
    
    private static function transformBoolean($value): ?bool
    {
        if ($value === null) {
            return null;
        }
        return (bool)$value;
    }
    
    private static function transformDate($value): ?string
    {
        if (empty($value) || $value === '0000-00-00') {
            return null;
        }
        return $value;
    }
    
    private static function transformDatetime($value): ?string
    {
        if (empty($value) || $value === '0000-00-00 00:00:00') {
            return null;
        }
        // Convert to ISO 8601 format
        try {
            $dt = new \DateTime($value);
            return $dt->format('c');
        } catch (\Exception $e) {
            return $value;
        }
    }
    
    private static function transformTime($value): ?string
    {
        return $value !== null ? (string)$value : null;
    }
    
    private static function transformJson($value): ?array
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
    
    private static function transformString($value): ?string
    {
        return $value !== null ? (string)$value : null;
    }
}
