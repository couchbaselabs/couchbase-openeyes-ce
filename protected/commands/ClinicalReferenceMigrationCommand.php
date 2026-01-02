<?php
/**
 * Command to migrate clinical reference tables to Couchbase
 * 
 * Usage:
 *   php protected/yiic clinicalreference migrate
 *   php protected/yiic clinicalreference status
 *   php protected/yiic clinicalreference verify
 */

class ClinicalReferenceMigrationCommand extends CConsoleCommand
{
    /**
     * Tables to migrate in dependency order
     */
    protected $tables = [
        // Tier 1: No dependencies
        'allergy' => ['model' => 'Allergy', 'scope' => 'reference'],
        'medication_route' => ['model' => 'MedicationRoute', 'scope' => 'reference'],
        'medication_form' => ['model' => 'MedicationForm', 'scope' => 'reference'],
        'medication_frequency' => ['model' => 'MedicationFrequency', 'scope' => 'reference'],
        'medication_duration' => ['model' => 'MedicationDuration', 'scope' => 'reference'],
        'medication_laterality' => ['model' => 'MedicationLaterality', 'scope' => 'reference'],
        'benefit' => ['model' => 'Benefit', 'scope' => 'reference'],
        'complication' => ['model' => 'Complication', 'scope' => 'reference'],
        
        // Tier 2: Depends on Tier 1
        'disorder' => ['model' => 'Disorder', 'scope' => 'reference', 'large' => true],
        'drug' => ['model' => 'Drug', 'scope' => 'reference'],
        'procedure' => ['model' => 'Procedure', 'scope' => 'reference', 'collection' => 'procedure'],
        
        // Tier 3: Depends on Tier 2
        'medication' => ['model' => 'Medication', 'scope' => 'reference', 'large' => true],
        'common_ophthalmic_disorder' => ['model' => 'CommonOphthalmicDisorder', 'scope' => 'reference'],
        'opcs_code' => ['model' => 'OPCSCode', 'scope' => 'reference'],
    ];

    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic clinicalreference <action> [options]

ACTIONS
  migrate    Migrate all clinical reference tables
  status     Show migration status
  verify     Verify migrated data
  search     Test search functionality

OPTIONS
  --table=<name>  Specific table to migrate
  --batch=<n>     Batch size (default: 500)
  --verbose       Show detailed output
  --dryRun        Show what would be migrated

EXAMPLES
  yiic clinicalreference migrate
  yiic clinicalreference migrate --table=disorder --verbose
  yiic clinicalreference status
  yiic clinicalreference search --term="diabetes" --type=disorder

EOD;
    }

    /**
     * Migrate all tables
     */
    public function actionMigrate($table = null, $batch = 500, $verbose = false, $dryRun = false)
    {
        echo "===========================================\n";
        echo "Phase 11: Clinical Reference Data Migration\n";
        echo "===========================================\n\n";

        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        $totalMigrated = 0;
        $totalErrors = 0;

        foreach ($tables as $tableName => $config) {
            echo "Migrating table: {$tableName}\n";
            
            // Use smaller batch for large tables
            $tableBatch = isset($config['large']) && $config['large'] ? 500 : $batch;
            
            $result = $this->migrateTable($tableName, $config, $tableBatch, $verbose, $dryRun);
            
            $totalMigrated += $result['migrated'];
            $totalErrors += $result['errors'];
            
            echo "  Migrated: {$result['migrated']}, Errors: {$result['errors']}\n\n";
        }

        echo "===========================================\n";
        echo "Migration Complete\n";
        echo "Total Migrated: {$totalMigrated}\n";
        echo "Total Errors: {$totalErrors}\n";
        echo "===========================================\n";

        return $totalErrors === 0 ? 0 : 1;
    }

    /**
     * Migrate single table
     */
    protected function migrateTable($tableName, $config, $batch, $verbose, $dryRun)
    {
        $modelClass = $config['model'];
        $scope = $config['scope'];
        $collection = isset($config['collection']) ? $config['collection'] : $tableName;

        $migrated = 0;
        $errors = 0;

        $adapter = Yii::app()->couchbase;
        if (!$adapter) {
            echo "  ERROR: Couchbase not configured\n";
            return ['migrated' => 0, 'errors' => 1];
        }

        // Get total count
        try {
            $total = $modelClass::model()->count();
        } catch (Exception $e) {
            echo "  ERROR: Cannot count records - {$e->getMessage()}\n";
            return ['migrated' => 0, 'errors' => 1];
        }
        
        echo "  Total records: {$total}\n";

        if ($dryRun) {
            echo "  [DRY RUN] Would migrate {$total} records\n";
            return ['migrated' => 0, 'errors' => 0];
        }

        $offset = 0;
        while ($offset < $total) {
            try {
                $records = $modelClass::model()->findAll([
                    'limit' => $batch,
                    'offset' => $offset,
                ]);

                foreach ($records as $record) {
                    try {
                        $doc = $record->toCouchbaseDocument();
                        $key = $collection . '::' . $record->id;
                        
                        $adapter->upsert($scope, $collection, $key, $doc);
                        $migrated++;
                        
                        if ($verbose && $migrated % 100 === 0) {
                            echo "    Progress: {$migrated}/{$total}\n";
                        }
                    } catch (Exception $e) {
                        $errors++;
                        if ($verbose) {
                            echo "    ERROR: {$record->id} - {$e->getMessage()}\n";
                        }
                    }
                }

                $offset += $batch;
                
                // Memory management for large tables
                if (isset($config['large']) && $config['large']) {
                    gc_collect_cycles();
                }
            } catch (Exception $e) {
                echo "  ERROR: Batch processing failed - {$e->getMessage()}\n";
                $errors++;
                break;
            }
        }

        return ['migrated' => $migrated, 'errors' => $errors];
    }

    /**
     * Show migration status
     */
    public function actionStatus()
    {
        echo "Clinical Reference Data Migration Status\n";
        echo "========================================\n\n";

        $adapter = Yii::app()->couchbase;
        if (!$adapter) {
            echo "ERROR: Couchbase not configured\n";
            return 1;
        }

        foreach ($this->tables as $tableName => $config) {
            $modelClass = $config['model'];
            $scope = $config['scope'];
            $collection = isset($config['collection']) ? $config['collection'] : $tableName;
            
            try {
                $mysqlCount = $modelClass::model()->count();
            } catch (Exception $e) {
                $mysqlCount = 0;
            }
            
            try {
                // Query Couchbase to count documents
                $query = "SELECT COUNT(*) AS cnt FROM `openeyes`.`{$scope}`.`{$collection}`";
                $result = $adapter->query($query);
                $rows = $result->rows();
                $cbCount = !empty($rows) ? (int)$rows[0]['cnt'] : 0;
            } catch (Exception $e) {
                $cbCount = 0;
            }
            
            $status = $mysqlCount === $cbCount ? '✓' : '✗';
            $pct = $mysqlCount > 0 ? round(($cbCount / $mysqlCount) * 100) : 0;
            
            printf("%-35s MySQL: %6d  CB: %6d  [%s %3d%%]\n",
                $tableName,
                $mysqlCount,
                $cbCount,
                $status,
                $pct
            );
        }
        
        return 0;
    }

    /**
     * Verify migrated data
     */
    public function actionVerify($table = null, $samples = 5)
    {
        echo "Verifying Clinical Reference Data\n";
        echo "=================================\n\n";

        $adapter = Yii::app()->couchbase;
        if (!$adapter) {
            echo "ERROR: Couchbase not configured\n";
            return 1;
        }

        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        $totalChecked = 0;
        $totalErrors = 0;

        foreach ($tables as $tableName => $config) {
            echo "Verifying {$tableName}...\n";
            
            $result = $this->verifyTable($tableName, $config, $samples);
            $totalChecked += $result['checked'];
            $totalErrors += $result['errors'];
            
            if ($result['errors'] === 0) {
                echo "  ✓ All samples match\n\n";
            } else {
                echo "  ✗ {$result['errors']} mismatches found\n\n";
            }
        }

        echo "=================================\n";
        echo "Total Checked: {$totalChecked}\n";
        echo "Total Errors: {$totalErrors}\n";

        return $totalErrors === 0 ? 0 : 1;
    }

    /**
     * Verify single table
     */
    protected function verifyTable($tableName, $config, $samples)
    {
        $modelClass = $config['model'];
        $scope = $config['scope'];
        $collection = isset($config['collection']) ? $config['collection'] : $tableName;
        $adapter = Yii::app()->couchbase;

        $checked = 0;
        $errors = 0;

        try {
            // Get random sample
            $records = $modelClass::model()->findAll([
                'order' => 'RAND()',
                'limit' => $samples,
            ]);

            foreach ($records as $record) {
                try {
                    $key = $collection . '::' . $record->id;
                    $cbDoc = $adapter->get($scope, $collection, $key);
                    
                    if (!$cbDoc) {
                        echo "    ✗ Record {$record->id} not found in Couchbase\n";
                        $errors++;
                    } else {
                        // Basic validation
                        if ($cbDoc['id'] != $record->id) {
                            echo "    ✗ ID mismatch for {$record->id}\n";
                            $errors++;
                        }
                    }
                    $checked++;
                } catch (Exception $e) {
                    echo "    ✗ Error checking {$record->id}: {$e->getMessage()}\n";
                    $errors++;
                }
            }
        } catch (Exception $e) {
            echo "    ERROR: {$e->getMessage()}\n";
            $errors++;
        }

        return ['checked' => $checked, 'errors' => $errors];
    }

    /**
     * Test search functionality
     */
    public function actionSearch($term = '', $type = 'disorder')
    {
        if (empty($term)) {
            echo "Please provide a search term with --term=<term>\n";
            return 1;
        }

        echo "Searching {$type} for: {$term}\n\n";

        try {
            switch ($type) {
                case 'disorder':
                    $results = DisorderDocument::search($term, 10);
                    break;
                case 'medication':
                    $results = MedicationDocument::search($term, 10);
                    break;
                case 'procedure':
                    $results = ProcedureDocument::search($term, 10);
                    break;
                case 'drug':
                    $results = DrugDocument::search($term, 10);
                    break;
                default:
                    echo "Unknown type: {$type}\n";
                    echo "Valid types: disorder, medication, procedure, drug\n";
                    return 1;
            }

            if (empty($results)) {
                echo "No results found\n";
                return 0;
            }

            foreach ($results as $result) {
                $term = $result['term'] ?? $result['preferred_term'] ?? $result['name'] ?? 'N/A';
                $code = $result['snomed_code'] ?? $result['preferred_code'] ?? 'N/A';
                echo "  [{$result['id']}] {$term} (Code: {$code})\n";
            }

            echo "\nTotal: " . count($results) . " results\n";
            return 0;
        } catch (Exception $e) {
            echo "ERROR: {$e->getMessage()}\n";
            return 1;
        }
    }
}
