<?php
/**
 * ExportSchemaCommand - Exports all MariaDB table schemas to JSON
 * 
 * This command extracts table structure information from MariaDB and saves it
 * as a static JSON file that can be used by CouchbaseDbSchema to provide
 * schema information without requiring a MariaDB connection.
 * 
 * Usage:
 *   php yiic exportschema export          - Export all schemas to JSON
 *   php yiic exportschema export --table=firm  - Export single table
 *   php yiic exportschema verify          - Verify exported schemas
 */

class ExportSchemaCommand extends CConsoleCommand
{
    const SCHEMA_FILE = 'protected/config/schema-cache.json';
    const SCHEMA_PHP_FILE = 'protected/config/schema-cache.php';
    
    /**
     * Export all table schemas to JSON
     * @param string $table Optional specific table to export
     */
    public function actionExport($table = null)
    {
        echo "=== MariaDB Schema Export Tool ===\n\n";
        
        $db = Yii::app()->db;
        $dbName = $this->getDatabaseName();
        
        if ($table) {
            echo "Exporting schema for table: {$table}\n";
            $tables = [$table];
        } else {
            echo "Fetching all tables from database: {$dbName}\n";
            $tables = $this->getAllTables($db, $dbName);
            echo "Found " . count($tables) . " tables\n\n";
        }
        
        $schemas = [];
        $exported = 0;
        $errors = 0;
        
        foreach ($tables as $tableName) {
            try {
                $schema = $this->exportTableSchema($db, $tableName);
                if ($schema) {
                    $schemas[$tableName] = $schema;
                    $exported++;
                    if ($exported % 100 === 0) {
                        echo "  Exported {$exported} tables...\n";
                    }
                }
            } catch (Exception $e) {
                echo "  ERROR exporting {$tableName}: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
        
        // Save to JSON file
        $jsonPath = Yii::app()->basePath . '/../' . self::SCHEMA_FILE;
        $jsonContent = json_encode($schemas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($jsonPath, $jsonContent);
        
        // Also save as PHP for faster loading
        $phpPath = Yii::app()->basePath . '/../' . self::SCHEMA_PHP_FILE;
        $phpContent = "<?php\n// Auto-generated schema cache - DO NOT EDIT\n// Generated: " . date('Y-m-d H:i:s') . "\nreturn " . var_export($schemas, true) . ";\n";
        file_put_contents($phpPath, $phpContent);
        
        echo "\n=== Export Complete ===\n";
        echo "Tables exported: {$exported}\n";
        echo "Errors: {$errors}\n";
        echo "JSON file: {$jsonPath}\n";
        echo "PHP file: {$phpPath}\n";
        echo "File size: " . $this->formatBytes(filesize($jsonPath)) . "\n";
    }
    
    /**
     * Verify exported schemas match current database
     */
    public function actionVerify()
    {
        echo "=== Schema Verification ===\n\n";
        
        $phpPath = Yii::app()->basePath . '/../' . self::SCHEMA_PHP_FILE;
        
        if (!file_exists($phpPath)) {
            echo "ERROR: Schema cache not found. Run 'exportschema export' first.\n";
            return 1;
        }
        
        $cachedSchemas = require $phpPath;
        $db = Yii::app()->db;
        $dbName = $this->getDatabaseName();
        $currentTables = $this->getAllTables($db, $dbName);
        
        $missing = [];
        $extra = [];
        $different = [];
        
        // Check for missing tables in cache
        foreach ($currentTables as $table) {
            if (!isset($cachedSchemas[$table])) {
                $missing[] = $table;
            }
        }
        
        // Check for extra tables in cache
        foreach (array_keys($cachedSchemas) as $table) {
            if (!in_array($table, $currentTables)) {
                $extra[] = $table;
            }
        }
        
        // Check for column differences (sample 10 tables)
        $sampled = array_slice($currentTables, 0, 10);
        foreach ($sampled as $table) {
            if (isset($cachedSchemas[$table])) {
                $currentSchema = $this->exportTableSchema($db, $table);
                $cachedSchema = $cachedSchemas[$table];
                
                $currentCols = array_keys($currentSchema['columns'] ?? []);
                $cachedCols = array_keys($cachedSchema['columns'] ?? []);
                
                if ($currentCols !== $cachedCols) {
                    $different[] = $table;
                }
            }
        }
        
        echo "Cached tables: " . count($cachedSchemas) . "\n";
        echo "Current tables: " . count($currentTables) . "\n";
        echo "Missing from cache: " . count($missing) . "\n";
        echo "Extra in cache: " . count($extra) . "\n";
        echo "Column differences (sampled): " . count($different) . "\n";
        
        if (!empty($missing)) {
            echo "\nMissing tables:\n";
            foreach (array_slice($missing, 0, 10) as $t) {
                echo "  - {$t}\n";
            }
            if (count($missing) > 10) {
                echo "  ... and " . (count($missing) - 10) . " more\n";
            }
        }
        
        if (empty($missing) && empty($different)) {
            echo "\n✓ Schema cache is up to date!\n";
            return 0;
        } else {
            echo "\n✗ Schema cache needs update. Run 'exportschema export'\n";
            return 1;
        }
    }
    
    /**
     * Show statistics about the schema cache
     */
    public function actionStats()
    {
        echo "=== Schema Cache Statistics ===\n\n";
        
        $phpPath = Yii::app()->basePath . '/../' . self::SCHEMA_PHP_FILE;
        
        if (!file_exists($phpPath)) {
            echo "ERROR: Schema cache not found.\n";
            return 1;
        }
        
        $schemas = require $phpPath;
        
        $totalColumns = 0;
        $totalForeignKeys = 0;
        $totalIndexes = 0;
        $tablesByPrefix = [];
        
        foreach ($schemas as $table => $schema) {
            $totalColumns += count($schema['columns'] ?? []);
            $totalForeignKeys += count($schema['foreignKeys'] ?? []);
            $totalIndexes += count($schema['indexes'] ?? []);
            
            // Group by prefix
            $prefix = explode('_', $table)[0];
            if (!isset($tablesByPrefix[$prefix])) {
                $tablesByPrefix[$prefix] = 0;
            }
            $tablesByPrefix[$prefix]++;
        }
        
        arsort($tablesByPrefix);
        
        echo "Total tables: " . count($schemas) . "\n";
        echo "Total columns: {$totalColumns}\n";
        echo "Total foreign keys: {$totalForeignKeys}\n";
        echo "Total indexes: {$totalIndexes}\n";
        echo "Average columns per table: " . round($totalColumns / count($schemas), 1) . "\n";
        echo "\nTables by prefix (top 15):\n";
        
        $i = 0;
        foreach ($tablesByPrefix as $prefix => $count) {
            echo "  {$prefix}_*: {$count}\n";
            if (++$i >= 15) break;
        }
    }
    
    /**
     * Get all tables from database
     */
    private function getAllTables($db, $dbName)
    {
        $sql = "SELECT table_name FROM information_schema.tables 
                WHERE table_schema = :dbName 
                AND table_type = 'BASE TABLE'
                ORDER BY table_name";
        
        $command = $db->createCommand($sql);
        $command->bindValue(':dbName', $dbName);
        $rows = $command->queryAll();
        
        return array_column($rows, 'table_name');
    }
    
    /**
     * Export schema for a single table
     */
    private function exportTableSchema($db, $tableName)
    {
        $dbName = $this->getDatabaseName();
        
        // Get columns
        $columns = $this->getColumns($db, $dbName, $tableName);
        if (empty($columns)) {
            return null;
        }
        
        // Get primary key
        $primaryKey = $this->getPrimaryKey($db, $dbName, $tableName);
        
        // Get foreign keys
        $foreignKeys = $this->getForeignKeys($db, $dbName, $tableName);
        
        // Get indexes
        $indexes = $this->getIndexes($db, $dbName, $tableName);
        
        return [
            'name' => $tableName,
            'columns' => $columns,
            'primaryKey' => $primaryKey,
            'foreignKeys' => $foreignKeys,
            'indexes' => $indexes,
        ];
    }
    
    /**
     * Get column definitions
     */
    private function getColumns($db, $dbName, $tableName)
    {
        $sql = "SELECT 
                    column_name,
                    data_type,
                    column_type,
                    is_nullable,
                    column_default,
                    extra,
                    character_maximum_length,
                    numeric_precision,
                    numeric_scale
                FROM information_schema.columns 
                WHERE table_schema = :dbName 
                AND table_name = :tableName
                ORDER BY ordinal_position";
        
        $command = $db->createCommand($sql);
        $command->bindValue(':dbName', $dbName);
        $command->bindValue(':tableName', $tableName);
        $rows = $command->queryAll();
        
        $columns = [];
        foreach ($rows as $row) {
            $columns[$row['column_name']] = [
                'name' => $row['column_name'],
                'type' => $row['data_type'],
                'dbType' => $row['column_type'],
                'allowNull' => $row['is_nullable'] === 'YES',
                'defaultValue' => $row['column_default'],
                'autoIncrement' => strpos($row['extra'], 'auto_increment') !== false,
                'unsigned' => strpos($row['column_type'], 'unsigned') !== false,
                'size' => $row['character_maximum_length'] ?? $row['numeric_precision'],
                'scale' => $row['numeric_scale'],
                'isPrimaryKey' => strpos($row['extra'], 'auto_increment') !== false, // Will be updated
            ];
        }
        
        return $columns;
    }
    
    /**
     * Get primary key columns
     */
    private function getPrimaryKey($db, $dbName, $tableName)
    {
        $sql = "SELECT column_name 
                FROM information_schema.key_column_usage 
                WHERE table_schema = :dbName 
                AND table_name = :tableName 
                AND constraint_name = 'PRIMARY'
                ORDER BY ordinal_position";
        
        $command = $db->createCommand($sql);
        $command->bindValue(':dbName', $dbName);
        $command->bindValue(':tableName', $tableName);
        $rows = $command->queryAll();
        
        $keys = array_column($rows, 'column_name');
        
        if (count($keys) === 1) {
            return $keys[0];
        } elseif (count($keys) > 1) {
            return $keys; // Composite key
        }
        
        return null;
    }
    
    /**
     * Get foreign key definitions
     */
    private function getForeignKeys($db, $dbName, $tableName)
    {
        $sql = "SELECT 
                    column_name,
                    referenced_table_name,
                    referenced_column_name
                FROM information_schema.key_column_usage 
                WHERE table_schema = :dbName 
                AND table_name = :tableName 
                AND referenced_table_name IS NOT NULL";
        
        $command = $db->createCommand($sql);
        $command->bindValue(':dbName', $dbName);
        $command->bindValue(':tableName', $tableName);
        $rows = $command->queryAll();
        
        $foreignKeys = [];
        foreach ($rows as $row) {
            $foreignKeys[$row['column_name']] = [
                $row['referenced_table_name'],
                $row['referenced_column_name'],
            ];
        }
        
        return $foreignKeys;
    }
    
    /**
     * Get index definitions
     */
    private function getIndexes($db, $dbName, $tableName)
    {
        $sql = "SELECT 
                    index_name,
                    column_name,
                    non_unique,
                    seq_in_index
                FROM information_schema.statistics 
                WHERE table_schema = :dbName 
                AND table_name = :tableName
                ORDER BY index_name, seq_in_index";
        
        $command = $db->createCommand($sql);
        $command->bindValue(':dbName', $dbName);
        $command->bindValue(':tableName', $tableName);
        $rows = $command->queryAll();
        
        $indexes = [];
        foreach ($rows as $row) {
            $indexName = $row['index_name'];
            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'name' => $indexName,
                    'unique' => $row['non_unique'] == 0,
                    'columns' => [],
                ];
            }
            $indexes[$indexName]['columns'][] = $row['column_name'];
        }
        
        return $indexes;
    }
    
    /**
     * Get database name from connection string
     */
    private function getDatabaseName()
    {
        $dsn = Yii::app()->db->connectionString;
        if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            return $matches[1];
        }
        return 'openeyes';
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
