<?php
/**
 * Analyzes MySQL table relationships for Couchbase document design
 * 
 * Usage: php protected/yiic.php analyzerelationships
 * Output: protected/scripts/couchbase/analyzers/relationship-map.json
 */

class RelationshipAnalyzer
{
    /** @var CDbConnection */
    private $db;
    
    /** @var array Collected relationships */
    private $relationships = [];
    
    /** @var array Table metadata */
    private $tableMetadata = [];
    
    public function __construct()
    {
        $this->db = Yii::app()->db;
    }
    
    /**
     * Run the full analysis
     * @return array Analysis results
     */
    public function analyze(): array
    {
        echo "Starting relationship analysis...\n";
        
        $tables = $this->getTables();
        echo "Found " . count($tables) . " tables\n";
        
        foreach ($tables as $table) {
            $this->analyzeTable($table);
        }
        
        $this->categorizeRelationships();
        
        return [
            'tables' => $this->tableMetadata,
            'relationships' => $this->relationships,
            'summary' => $this->generateSummary(),
        ];
    }
    
    /**
     * Get all tables in the database
     * @return array Table names
     */
    private function getTables(): array
    {
        return $this->db->createCommand("SHOW TABLES")->queryColumn();
    }
    
    /**
     * Analyze a single table
     * @param string $tableName Table to analyze
     */
    private function analyzeTable(string $tableName): void
    {
        // Get column information
        $columns = $this->db->createCommand("DESCRIBE `{$tableName}`")->queryAll();
        
        // Get foreign keys
        $sql = "
            SELECT 
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ";
        
        $foreignKeys = $this->db->createCommand($sql)
            ->queryAll(true, [':table' => $tableName]);
        
        // Get row count
        $rowCount = $this->db->createCommand("SELECT COUNT(*) FROM `{$tableName}`")->queryScalar();
        
        // Get indexes
        $indexes = $this->db->createCommand("SHOW INDEX FROM `{$tableName}`")->queryAll();
        
        // Store metadata
        $this->tableMetadata[$tableName] = [
            'columns' => array_map(function($col) {
                return [
                    'name' => $col['Field'],
                    'type' => $col['Type'],
                    'null' => $col['Null'] === 'YES',
                    'key' => $col['Key'],
                    'default' => $col['Default'],
                ];
            }, $columns),
            'row_count' => (int)$rowCount,
            'foreign_keys' => count($foreignKeys),
            'indexes' => $this->groupIndexes($indexes),
        ];
        
        // Store relationships
        foreach ($foreignKeys as $fk) {
            $this->relationships[$tableName][] = [
                'type' => 'belongs_to',
                'column' => $fk['COLUMN_NAME'],
                'references_table' => $fk['REFERENCED_TABLE_NAME'],
                'references_column' => $fk['REFERENCED_COLUMN_NAME'],
                'constraint' => $fk['CONSTRAINT_NAME'],
            ];
            
            // Add reverse relationship
            if (!isset($this->relationships[$fk['REFERENCED_TABLE_NAME']])) {
                $this->relationships[$fk['REFERENCED_TABLE_NAME']] = [];
            }
            $this->relationships[$fk['REFERENCED_TABLE_NAME']][] = [
                'type' => 'has_many',
                'table' => $tableName,
                'column' => $fk['COLUMN_NAME'],
            ];
        }
        
        echo "  {$tableName}: {$rowCount} rows, " . count($foreignKeys) . " FKs\n";
    }
    
    /**
     * Group indexes by name
     * @param array $indexes Raw index data
     * @return array Grouped indexes
     */
    private function groupIndexes(array $indexes): array
    {
        $grouped = [];
        foreach ($indexes as $idx) {
            $name = $idx['Key_name'];
            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'columns' => [],
                    'unique' => !$idx['Non_unique'],
                    'type' => $idx['Index_type'],
                ];
            }
            $grouped[$name]['columns'][] = $idx['Column_name'];
        }
        return $grouped;
    }
    
    /**
     * Categorize relationships for embedding decisions
     */
    private function categorizeRelationships(): void
    {
        foreach ($this->relationships as $table => &$rels) {
            foreach ($rels as &$rel) {
                if ($rel['type'] === 'belongs_to') {
                    $rel['embed_strategy'] = $this->determineEmbedStrategy($table, $rel);
                }
            }
        }
    }
    
    /**
     * Determine embedding strategy based on relationship characteristics
     * @param string $table Parent table
     * @param array $relationship Relationship info
     * @return string Strategy: 'embed', 'reference', or 'hybrid'
     */
    private function determineEmbedStrategy(string $table, array $relationship): string
    {
        $refTable = $relationship['references_table'];
        $refMeta = $this->tableMetadata[$refTable] ?? null;
        
        if (!$refMeta) {
            return 'reference';
        }
        
        // Lookup tables (small, rarely changing) - reference
        if ($refMeta['row_count'] < 100 && $this->isLookupTable($refTable)) {
            return 'reference';
        }
        
        // Large tables - always reference
        if ($refMeta['row_count'] > 10000) {
            return 'reference';
        }
        
        // One-to-one relationships - embed
        if ($this->isOneToOne($table, $relationship)) {
            return 'embed';
        }
        
        // Contact/Address relationships - embed
        if (in_array($refTable, ['contact', 'address'])) {
            return 'embed';
        }
        
        return 'reference';
    }
    
    /**
     * Check if table is a lookup/reference table
     * @param string $table Table name
     * @return bool
     */
    private function isLookupTable(string $table): bool
    {
        $lookupPatterns = [
            '_status', '_type', '_reason', '_method', '_unit',
            'gender', 'ethnic_group', 'country', 'specialty',
        ];
        
        foreach ($lookupPatterns as $pattern) {
            if (strpos($table, $pattern) !== false || $table === $pattern) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if relationship is one-to-one
     * @param string $table Table name
     * @param array $rel Relationship
     * @return bool
     */
    private function isOneToOne(string $table, array $rel): bool
    {
        $meta = $this->tableMetadata[$table] ?? null;
        if (!$meta) {
            return false;
        }
        
        // Check if FK column has unique constraint
        foreach ($meta['indexes'] as $idx) {
            if ($idx['unique'] && in_array($rel['column'], $idx['columns'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate summary statistics
     * @return array Summary data
     */
    private function generateSummary(): array
    {
        $totalTables = count($this->tableMetadata);
        $totalRows = array_sum(array_column($this->tableMetadata, 'row_count'));
        $totalRelationships = 0;
        $embedCount = 0;
        $referenceCount = 0;
        
        foreach ($this->relationships as $rels) {
            foreach ($rels as $rel) {
                $totalRelationships++;
                if (isset($rel['embed_strategy'])) {
                    if ($rel['embed_strategy'] === 'embed') {
                        $embedCount++;
                    } else {
                        $referenceCount++;
                    }
                }
            }
        }
        
        return [
            'total_tables' => $totalTables,
            'total_rows' => $totalRows,
            'total_relationships' => $totalRelationships,
            'embed_candidates' => $embedCount,
            'reference_candidates' => $referenceCount,
            'analysis_date' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Export analysis to JSON file
     * @param string $filename Output file path
     */
    public function exportToJson(string $filename): void
    {
        $data = $this->analyze();
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "\nExported to: {$filename}\n";
    }
}
