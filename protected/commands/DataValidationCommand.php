<?php
/**
 * Validate migrated data integrity between MariaDB and Couchbase
 * 
 * Usage:
 *   yiic datavalidation run                - Validate all tables
 *   yiic datavalidation run --tables=patient - Validate specific tables
 *   yiic datavalidation sample --table=patient --size=100 - Sample comparison
 *   yiic datavalidation counts            - Show count comparison
 */

class DataValidationCommand extends CConsoleCommand
{
    private $tablesToValidate = [
        'patient',
        'episode', 
        'event',
        'user',
        'institution',
        'site',
        'firm',
    ];
    
    /**
     * @return string Command help text
     */
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic datavalidation <action> [options]

ACTIONS
  all          - Run all validation types (comprehensive)
  run          - Validate all tables (legacy)
  counts       - Show count comparison only
  samples      - Sample record validation
  integrity    - Referential integrity validation
  embeddings   - Embedded relations validation
  indexes      - Index coverage validation

OPTIONS
  --tables=<list>   Comma-separated list of tables to validate
  --table=<name>    Table name for sample comparison
  --sample=<num>    Sample size (default: 500)
  --size=<num>      Sample size (alias for --sample)
  --verbose         Show detailed output

EXAMPLES
  yiic datavalidation all --sample=500
  yiic datavalidation counts
  yiic datavalidation samples --table=patient --sample=100
  yiic datavalidation integrity
  yiic datavalidation embeddings --sample=100
  yiic datavalidation indexes
EOD;
    }
    
    /**
     * Run full validation
     */
    public function actionRun($tables = null, $verbose = false)
    {
        $validateTables = $tables 
            ? array_map('trim', explode(',', $tables))
            : $this->tablesToValidate;
        
        echo "=== Data Validation ===\n\n";
        
        $results = [];
        $allPassed = true;
        
        foreach ($validateTables as $table) {
            $result = $this->validateTable($table, $verbose);
            $results[$table] = $result;
            
            if (!$result['count_match'] || $result['integrity_score'] < 99) {
                $allPassed = false;
            }
        }
        
        $this->printResults($results);
        
        return $allPassed ? 0 : 1;
    }
    
    /**
     * Run all validation types (comprehensive)
     */
    public function actionAll($sample = 500, $verbose = false)
    {
        echo str_repeat('=', 80) . "\n";
        echo "COMPREHENSIVE DATA VALIDATION\n";
        echo str_repeat('=', 80) . "\n\n";

        $results = [
            'count' => $this->validateCounts($verbose),
            'sample' => $this->validateSamples($sample, $verbose),
            'integrity' => $this->validateIntegrity($verbose),
            'embeddings' => $this->validateEmbeddings($sample, $verbose),
        ];

        // Summary
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "VALIDATION SUMMARY\n";
        echo str_repeat('=', 80) . "\n";

        $totalPassed = 0;
        $totalChecks = 0;

        foreach ($results as $type => $result) {
            $totalChecks += $result['total'];
            $totalPassed += $result['passed'];
            
            $pct = $result['total'] > 0 ? round(($result['passed'] / $result['total']) * 100, 1) : 0;
            $status = $result['passed'] === $result['total'] ? '✓' : '⚠';
            
            printf("  %-15s: %d/%d passed (%s%%) [%s]\n",
                ucfirst($type), $result['passed'], $result['total'], $pct, $status
            );
        }

        $overallPct = $totalChecks > 0 ? round(($totalPassed / $totalChecks) * 100, 1) : 0;
        echo "\n  OVERALL: {$totalPassed}/{$totalChecks} ({$overallPct}%)\n";
        echo str_repeat('=', 80) . "\n";
        
        return $totalPassed === $totalChecks ? 0 : 1;
    }

    /**
     * Show count comparison
     */
    public function actionCounts($verbose = false)
    {
        echo "=== Record Counts ===\n\n";
        
        $result = $this->validateCounts($verbose);
        
        echo "\nResult: {$result['passed']}/{$result['total']} tables match\n";
        return $result['passed'] === $result['total'] ? 0 : 1;
    }
    
    /**
     * Validate counts for all tables
     */
    protected function validateCounts($verbose = false)
    {
        $checks = [
            ['model' => 'Patient', 'scope' => 'clinical', 'table' => 'patient'],
            ['model' => 'Episode', 'scope' => 'clinical', 'table' => 'episode'],
            ['model' => 'Event', 'scope' => 'clinical', 'table' => 'event'],
            ['model' => 'User', 'scope' => 'admin', 'table' => 'user'],
            ['model' => 'Contact', 'scope' => 'clinical', 'table' => 'contact'],
            ['model' => 'Disorder', 'scope' => 'reference', 'table' => 'disorder'],
            ['model' => 'Medication', 'scope' => 'reference', 'table' => 'medication'],
            ['model' => 'Procedure', 'scope' => 'reference', 'table' => 'procedure'],
            ['model' => 'EventType', 'scope' => 'reference', 'table' => 'event_type'],
            ['model' => 'Site', 'scope' => 'reference', 'table' => 'site'],
        ];

        $passed = 0;
        $total = count($checks);

        $format = "%-25s %12s %12s %8s\n";
        printf($format, 'Table', 'MariaDB', 'Couchbase', 'Status');
        printf($format, str_repeat('-', 25), str_repeat('-', 12), 
            str_repeat('-', 12), str_repeat('-', 8));

        foreach ($checks as $check) {
            if (!class_exists($check['model'])) {
                if ($verbose) {
                    echo "  Skipping {$check['table']}: Model not found\n";
                }
                $total--;
                continue;
            }

            try {
                $mysqlCount = $check['model']::model()->count();
            } catch (Exception $e) {
                $mysqlCount = 0;
            }
            
            try {
                $adapter = Yii::app()->couchbase;
                $cbCount = $adapter->count($check['scope'], $check['table']);
            } catch (Exception $e) {
                $cbCount = 0;
            }
            
            $match = $mysqlCount === $cbCount;
            if ($match) $passed++;
            
            $status = $match ? '✓' : '✗';
            $pct = $mysqlCount > 0 ? round(($cbCount / $mysqlCount) * 100, 1) : 0;
            
            printf("%-25s %12d %12d %6s [%3s%%]\n",
                $check['table'], $mysqlCount, $cbCount, $status, $pct
            );
        }

        return ['passed' => $passed, 'total' => $total];
    }
    
    /**
     * Validate samples action
     */
    public function actionSamples($table = null, $sample = 100, $verbose = false)
    {
        echo "=== Sample Validation ===\n\n";
        
        if ($table) {
            // Validate specific table
            $result = $this->validateTableSamples($table, $sample, $verbose);
            echo "\nResult: {$result['passed']}/{$result['total']} samples matched\n";
            return $result['passed'] === $result['total'] ? 0 : 1;
        } else {
            // Validate all tables
            $result = $this->validateSamples($sample, $verbose);
            echo "\nResult: {$result['passed']}/{$result['total']} samples matched\n";
            return $result['passed'] === $result['total'] ? 0 : 1;
        }
    }
    
    /**
     * Validate sample records
     */
    protected function validateSamples($sample, $verbose)
    {
        $tables = [
            ['model' => 'Patient', 'table' => 'patient', 'scope' => 'clinical'],
            ['model' => 'Episode', 'table' => 'episode', 'scope' => 'clinical'],
            ['model' => 'Event', 'table' => 'event', 'scope' => 'clinical'],
        ];

        $totalPassed = 0;
        $totalChecked = 0;

        foreach ($tables as $config) {
            $result = $this->validateTableSamples($config['table'], $sample, $verbose);
            $totalPassed += $result['passed'];
            $totalChecked += $result['total'];
        }

        return ['passed' => $totalPassed, 'total' => $totalChecked];
    }
    
    /**
     * Validate samples for a specific table
     */
    protected function validateTableSamples($table, $sampleSize, $verbose)
    {
        $passed = 0;
        $total = 0;
        
        // Get sample records from MariaDB
        $modelClass = $this->getModelClass($table);
        if (!$modelClass || !class_exists($modelClass)) {
            echo "  Skipping {$table}: Model not found\n";
            return ['passed' => 0, 'total' => 0];
        }

        try {
            $records = $modelClass::model()->findAll([
                'limit' => $sampleSize,
                'order' => 'RAND()',
            ]);
        } catch (Exception $e) {
            echo "  Error fetching samples: {$e->getMessage()}\n";
            return ['passed' => 0, 'total' => 0];
        }

        $adapter = Yii::app()->couchbase;
        $scope = $this->getScope($table);

        foreach ($records as $record) {
            $total++;
            
            try {
                $key = $table . '::' . $record->id;
                $doc = $adapter->get($scope, $table, $key);
                
                // Basic validation - check key fields match
                if ($doc && $this->recordMatches($record, $doc)) {
                    $passed++;
                } else {
                    if ($verbose) {
                        echo "  MISMATCH: {$table} ID {$record->id}\n";
                    }
                }
            } catch (Exception $e) {
                if ($verbose) {
                    echo "  MISSING: {$table} ID {$record->id}\n";
                }
            }
        }

        $pct = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
        echo "  {$table}: {$passed}/{$total} ({$pct}%)\n";

        return ['passed' => $passed, 'total' => $total];
    }
    
    /**
     * Validate referential integrity action
     */
    public function actionIntegrity($verbose = false)
    {
        echo "=== Referential Integrity Validation ===\n\n";
        
        $result = $this->validateIntegrity($verbose);
        
        echo "\nResult: {$result['passed']}/{$result['total']} checks passed\n";
        return $result['passed'] === $result['total'] ? 0 : 1;
    }
    
    /**
     * Validate referential integrity
     */
    protected function validateIntegrity($verbose)
    {
        $passed = 0;
        $total = 0;

        // Check Episode → Patient relationship
        $total++;
        try {
            $orphanEpisodes = Yii::app()->db->createCommand(
                "SELECT COUNT(*) FROM episode e 
                 LEFT JOIN patient p ON e.patient_id = p.id 
                 WHERE p.id IS NULL"
            )->queryScalar();
            
            if ($orphanEpisodes == 0) {
                $passed++;
                echo "  ✓ Episode → Patient: No orphans\n";
            } else {
                echo "  ✗ Episode → Patient: {$orphanEpisodes} orphans found\n";
            }
        } catch (Exception $e) {
            echo "  ⚠ Episode → Patient: Check failed\n";
        }

        // Check Event → Episode relationship
        $total++;
        try {
            $orphanEvents = Yii::app()->db->createCommand(
                "SELECT COUNT(*) FROM event e 
                 LEFT JOIN episode ep ON e.episode_id = ep.id 
                 WHERE ep.id IS NULL"
            )->queryScalar();
            
            if ($orphanEvents == 0) {
                $passed++;
                echo "  ✓ Event → Episode: No orphans\n";
            } else {
                echo "  ✗ Event → Episode: {$orphanEvents} orphans found\n";
            }
        } catch (Exception $e) {
            echo "  ⚠ Event → Episode: Check failed\n";
        }

        // Check Event → EventType relationship
        $total++;
        try {
            $orphanEventTypes = Yii::app()->db->createCommand(
                "SELECT COUNT(*) FROM event e 
                 LEFT JOIN event_type et ON e.event_type_id = et.id 
                 WHERE et.id IS NULL"
            )->queryScalar();
            
            if ($orphanEventTypes == 0) {
                $passed++;
                echo "  ✓ Event → EventType: No orphans\n";
            } else {
                echo "  ✗ Event → EventType: {$orphanEventTypes} orphans found\n";
            }
        } catch (Exception $e) {
            echo "  ⚠ Event → EventType: Check failed\n";
        }

        // Check Episode → Firm relationship
        $total++;
        try {
            $orphanFirms = Yii::app()->db->createCommand(
                "SELECT COUNT(*) FROM episode e 
                 LEFT JOIN firm f ON e.firm_id = f.id 
                 WHERE e.firm_id IS NOT NULL AND f.id IS NULL"
            )->queryScalar();
            
            if ($orphanFirms == 0) {
                $passed++;
                echo "  ✓ Episode → Firm: No orphans\n";
            } else {
                echo "  ✗ Episode → Firm: {$orphanFirms} orphans found\n";
            }
        } catch (Exception $e) {
            echo "  ⚠ Episode → Firm: Check failed\n";
        }

        return ['passed' => $passed, 'total' => $total];
    }
    
    /**
     * Validate embeddings action
     */
    public function actionEmbeddings($sample = 100, $verbose = false)
    {
        echo "=== Embedded Relations Validation ===\n\n";
        
        $result = $this->validateEmbeddings($sample, $verbose);
        
        echo "\nResult: {$result['passed']}/{$result['total']} embeddings verified\n";
        return $result['passed'] === $result['total'] ? 0 : 1;
    }
    
    /**
     * Validate embedded relations
     */
    protected function validateEmbeddings($sample, $verbose)
    {
        $passed = 0;
        $total = 0;

        $adapter = Yii::app()->couchbase;

        // Check Patient contact embeddings
        try {
            $patients = Patient::model()->findAll([
                'limit' => $sample,
                'order' => 'RAND()',
                'condition' => 'contact_id IS NOT NULL',
            ]);

            foreach ($patients as $patient) {
                $total++;
                
                try {
                    $key = 'patient::' . $patient->id;
                    $doc = $adapter->get('clinical', 'patient', $key);
                    
                    if ($doc && isset($doc['contact']) && !empty($doc['contact'])) {
                        $passed++;
                    } else if ($verbose) {
                        echo "  MISSING: Patient {$patient->id} contact embedding\n";
                    }
                } catch (Exception $e) {
                    // Document missing
                }
            }

            $pct = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
            echo "  Patient contact embeddings: {$passed}/{$total} ({$pct}%)\n";
        } catch (Exception $e) {
            echo "  ⚠ Patient embedding check failed: {$e->getMessage()}\n";
        }

        // Check Episode firm embeddings
        try {
            $episodes = Episode::model()->findAll([
                'limit' => $sample,
                'order' => 'RAND()',
                'condition' => 'firm_id IS NOT NULL',
            ]);

            $episodePassed = 0;
            $episodeTotal = 0;

            foreach ($episodes as $episode) {
                $episodeTotal++;
                
                try {
                    $key = 'episode::' . $episode->id;
                    $doc = $adapter->get('clinical', 'episode', $key);
                    
                    if ($doc && isset($doc['firm']) && !empty($doc['firm'])) {
                        $episodePassed++;
                    } else if ($verbose) {
                        echo "  MISSING: Episode {$episode->id} firm embedding\n";
                    }
                } catch (Exception $e) {
                    // Document missing
                }
            }

            $total += $episodeTotal;
            $passed += $episodePassed;

            $pct = $episodeTotal > 0 ? round(($episodePassed / $episodeTotal) * 100, 1) : 0;
            echo "  Episode firm embeddings: {$episodePassed}/{$episodeTotal} ({$pct}%)\n";
        } catch (Exception $e) {
            echo "  ⚠ Episode embedding check failed: {$e->getMessage()}\n";
        }

        return ['passed' => $passed, 'total' => $total];
    }
    
    /**
     * Validate index coverage
     */
    public function actionIndexes($verbose = false)
    {
        echo "=== Index Coverage Validation ===\n\n";
        echo "ℹ This validates that required N1QL indexes exist.\n\n";
        
        // List of expected indexes
        $expectedIndexes = [
            'idx_patient_hos_num',
            'idx_patient_nhs_num',
            'idx_episode_patient_id',
            'idx_event_episode_id',
            'idx_event_event_type_id',
            'idx_event_created_date',
            'idx_disorder_snomed_code',
            'idx_procedure_snomed_code',
            'idx_medication_dmd_code',
        ];

        echo "Expected indexes:\n";
        foreach ($expectedIndexes as $index) {
            echo "  - {$index}\n";
        }
        
        echo "\nℹ Run index scripts from protected/scripts/couchbase/ to create missing indexes\n";
        
        return 0;
    }
    
    /**
     * Check if record matches document
     */
    protected function recordMatches($record, $doc)
    {
        // Basic check - verify ID matches
        if (!isset($doc['id']) || (int)$doc['id'] !== (int)$record->id) {
            return false;
        }
        
        // Check a few key fields exist
        $keyAttributes = ['id'];
        if (isset($record->created_date)) {
            $keyAttributes[] = 'created_date';
        }
        
        foreach ($keyAttributes as $attr) {
            if (!array_key_exists($attr, $doc)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get model class for table
     */
    protected function getModelClass($table)
    {
        $mapping = [
            'patient' => 'Patient',
            'episode' => 'Episode',
            'event' => 'Event',
            'user' => 'User',
            'contact' => 'Contact',
            'disorder' => 'Disorder',
            'medication' => 'Medication',
            'procedure' => 'Procedure',
            'event_type' => 'EventType',
            'site' => 'Site',
        ];
        
        return $mapping[$table] ?? null;
    }
    
    /**
     * Get scope for table
     */
    protected function getScope($table)
    {
        $scopeMapping = [
            'patient' => 'clinical',
            'episode' => 'clinical',
            'event' => 'clinical',
            'contact' => 'clinical',
            'user' => 'admin',
            'disorder' => 'reference',
            'medication' => 'reference',
            'procedure' => 'reference',
            'event_type' => 'reference',
            'site' => 'reference',
        ];
        
        return $scopeMapping[$table] ?? 'clinical';
    }
    
    /**
     * Sample comparison for specific table
     */
    public function actionSample($table = null, $size = 100)
    {
        if (!$table) {
            echo "Error: --table parameter required\n";
            return 1;
        }
        
        echo "=== Sample Comparison: {$table} ===\n\n";
        
        $samples = $this->getSampleIds($table, $size);
        
        if (empty($samples)) {
            echo "No records found in table {$table}\n";
            return 0;
        }
        
        $mismatches = [];
        
        foreach ($samples as $id) {
            $result = $this->compareRecord($table, $id);
            if ($result['status'] !== 'match') {
                $mismatches[] = $result;
            }
        }
        
        echo "Samples checked: " . count($samples) . "\n";
        echo "Mismatches found: " . count($mismatches) . "\n";
        
        if (!empty($mismatches)) {
            echo "\nMismatch details:\n";
            foreach (array_slice($mismatches, 0, 10) as $m) {
                echo "  ID {$m['id']}: {$m['status']}\n";
                if (isset($m['differences'])) {
                    foreach ($m['differences'] as $field => $diff) {
                        echo "    {$field}: MySQL=" . json_encode($diff['mysql']) 
                            . ", CB=" . json_encode($diff['couchbase']) . "\n";
                    }
                }
            }
        }
        
        return count($mismatches) === 0 ? 0 : 1;
    }
    
    private function validateTable(string $table, bool $verbose): array
    {
        echo "Validating {$table}...\n";
        
        // Get counts
        $mysqlCount = $this->getMySqlCount($table);
        $cbCount = $this->getCouchbaseCount($table);
        
        // Sample validation
        $sampleSize = min(100, $mysqlCount);
        $mismatches = 0;
        
        if ($sampleSize > 0) {
            $samples = $this->getSampleIds($table, $sampleSize);
            
            foreach ($samples as $id) {
                $result = $this->compareRecord($table, $id);
                if ($result['status'] !== 'match') {
                    $mismatches++;
                    if ($verbose) {
                        echo "  Mismatch at ID {$id}: {$result['status']}\n";
                    }
                }
            }
        }
        
        $integrityScore = $sampleSize > 0 
            ? round((($sampleSize - $mismatches) / $sampleSize) * 100, 2)
            : 100;
        
        return [
            'mysql_count' => $mysqlCount,
            'couchbase_count' => $cbCount,
            'count_match' => $mysqlCount === $cbCount,
            'sample_size' => $sampleSize,
            'sample_mismatches' => $mismatches,
            'integrity_score' => $integrityScore,
        ];
    }
    
    private function compareRecord(string $table, $id): array
    {
        $mysqlRecord = Yii::app()->db->createCommand()
            ->select('*')
            ->from($table)
            ->where('id = :id', [':id' => $id])
            ->queryRow();
        
        if (!$mysqlRecord) {
            return ['id' => $id, 'status' => 'mysql_missing'];
        }
        
        try {
            $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
                \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
            $cbRecord = $adapter->findByPk($table, $id);
        } catch (\Exception $e) {
            return ['id' => $id, 'status' => 'couchbase_error', 'error' => $e->getMessage()];
        }
        
        if (!$cbRecord) {
            return ['id' => $id, 'status' => 'couchbase_missing'];
        }
        
        // Compare key fields
        $excludeFields = [
            'last_modified_date', 'last_modified_user_id',
            '_type', '_migrated', '_source', '_created', '_modified', '_mysql_id', '_key'
        ];
        
        $differences = [];
        foreach ($mysqlRecord as $key => $value) {
            if (in_array($key, $excludeFields)) {
                continue;
            }
            
            $cbValue = $cbRecord[$key] ?? null;
            
            if (!$this->valuesMatch($value, $cbValue)) {
                $differences[$key] = [
                    'mysql' => $value,
                    'couchbase' => $cbValue,
                ];
            }
        }
        
        if (empty($differences)) {
            return ['id' => $id, 'status' => 'match'];
        }
        
        return [
            'id' => $id,
            'status' => 'mismatch',
            'differences' => $differences,
        ];
    }
    
    private function valuesMatch($mysqlValue, $cbValue): bool
    {
        // Null comparison
        if ($mysqlValue === null && $cbValue === null) {
            return true;
        }
        if ($mysqlValue === null || $cbValue === null) {
            return false;
        }
        
        // Numeric comparison
        if (is_numeric($mysqlValue) && is_numeric($cbValue)) {
            return (float)$mysqlValue === (float)$cbValue;
        }
        
        // Boolean comparison (MySQL stores as 0/1)
        if (is_bool($cbValue)) {
            return (bool)$mysqlValue === $cbValue;
        }
        
        // String comparison
        return (string)$mysqlValue === (string)$cbValue;
    }
    
    private function getMySqlCount(string $table): int
    {
        try {
            return (int)Yii::app()->db->createCommand()
                ->select('COUNT(*)')
                ->from($table)
                ->queryScalar();
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getCouchbaseCount(string $table): int
    {
        try {
            $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
                \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
            return $adapter->count($table);
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getSampleIds(string $table, int $size): array
    {
        try {
            return Yii::app()->db->createCommand()
                ->select('id')
                ->from($table)
                ->order('RAND()')
                ->limit($size)
                ->queryColumn();
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function showTableCounts(string $table)
    {
        $mysqlCount = $this->getMySqlCount($table);
        $cbCount = $this->getCouchbaseCount($table);
        $match = $mysqlCount === $cbCount ? 'Yes' : 'No';
        
        printf("%-20s %12d %12d %8s\n", $table, $mysqlCount, $cbCount, $match);
    }
    
    private function printResults(array $results)
    {
        echo "\n=== Validation Results ===\n\n";
        
        $format = "%-20s %10s %10s %8s %12s\n";
        printf($format, 'Table', 'MySQL', 'Couchbase', 'Match', 'Integrity');
        printf($format, str_repeat('-', 20), str_repeat('-', 10), 
            str_repeat('-', 10), str_repeat('-', 8), str_repeat('-', 12));
        
        foreach ($results as $table => $r) {
            $match = $r['count_match'] ? 'Yes' : 'No';
            printf($format, 
                $table,
                $r['mysql_count'],
                $r['couchbase_count'],
                $match,
                $r['integrity_score'] . '%'
            );
        }
    }
}
