<?php
/**
 * Command to migrate core lookup tables to Couchbase
 * 
 * Usage:
 *   php protected/yiic corelookup migrate
 *   php protected/yiic corelookup status
 *   php protected/yiic corelookup verify
 */

class CoreLookupMigrationCommand extends CConsoleCommand
{
    /**
     * Tables to migrate in dependency order
     */
    protected $tables = [
        // Tier 1: No dependencies
        'eye' => ['model' => 'Eye', 'scope' => 'reference'],
        'gender' => ['model' => 'Gender', 'scope' => 'reference'],
        'ethnic_group' => ['model' => 'EthnicGroup', 'scope' => 'reference'],
        'specialty' => ['model' => 'Specialty', 'scope' => 'reference'],
        'event_group' => ['model' => 'EventGroup', 'scope' => 'reference'],
        
        // Tier 2: Depends on Tier 1
        'subspecialty' => ['model' => 'Subspecialty', 'scope' => 'reference'],
        'institution' => ['model' => 'Institution', 'scope' => 'core'],
        
        // Tier 3: Depends on Tier 2
        'site' => ['model' => 'Site', 'scope' => 'core'],
        'event_type' => ['model' => 'EventType', 'scope' => 'reference'],
        
        // Tier 4: Depends on Tier 3
        'element_type' => ['model' => 'ElementType', 'scope' => 'reference'],
        'firm' => ['model' => 'Firm', 'scope' => 'core'],
        'contact' => ['model' => 'Contact', 'scope' => 'core'],
        'address' => ['model' => 'Address', 'scope' => 'core'],
    ];

    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic corelookup <action> [options]

ACTIONS
  migrate    Migrate all core lookup tables
  status     Show migration status
  verify     Verify migrated data
  table      Migrate specific table

OPTIONS
  --table=<name>  Specific table to migrate
  --batch=<n>     Batch size (default: 500)
  --verbose       Show detailed output
  --dryRun        Show what would be migrated without executing

EXAMPLES
  yiic corelookup migrate
  yiic corelookup migrate --table=event_type
  yiic corelookup status
  yiic corelookup verify --table=site

EOD;
    }

    /**
     * Migrate all tables
     */
    public function actionMigrate($table = null, $batch = 500, $verbose = false, $dryRun = false)
    {
        echo "===========================================\n";
        echo "Phase 10: Core Lookup Tables Migration\n";
        echo "===========================================\n\n";

        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        $totalMigrated = 0;
        $totalErrors = 0;

        foreach ($tables as $tableName => $config) {
            echo "Migrating table: {$tableName}\n";
            
            $result = $this->migrateTable($tableName, $config, $batch, $verbose, $dryRun);
            
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
        $collection = $tableName;

        $migrated = 0;
        $errors = 0;

        // Get adapter
        $adapter = Yii::app()->couchbase;
        if (!$adapter) {
            echo "  ERROR: Couchbase not configured\n";
            return ['migrated' => 0, 'errors' => 1];
        }

        // Check if model class exists
        if (!class_exists($modelClass)) {
            echo "  ERROR: Model class {$modelClass} not found\n";
            return ['migrated' => 0, 'errors' => 1];
        }

        // Count total records
        try {
            $total = $modelClass::model()->count();
            echo "  Total records: {$total}\n";
        } catch (Exception $e) {
            echo "  ERROR counting records: {$e->getMessage()}\n";
            return ['migrated' => 0, 'errors' => 1];
        }

        if ($dryRun) {
            echo "  [DRY RUN] Would migrate {$total} records\n";
            return ['migrated' => 0, 'errors' => 0];
        }

        if ($total === 0) {
            echo "  No records to migrate\n";
            return ['migrated' => 0, 'errors' => 0];
        }

        // Process in batches
        $offset = 0;
        while ($offset < $total) {
            try {
                $records = $modelClass::model()->findAll([
                    'limit' => $batch,
                    'offset' => $offset,
                ]);

                foreach ($records as $record) {
                    try {
                        // Check if model has toCouchbaseDocument method
                        if (method_exists($record, 'toCouchbaseDocument')) {
                            $doc = $record->toCouchbaseDocument();
                        } else {
                            // Fallback: use attributes directly
                            $doc = $record->attributes;
                            $doc['_type'] = $tableName;
                        }
                        
                        $key = $tableName . '::' . $record->id;
                        
                        $adapter->upsert($scope, $collection, $key, $doc);
                        $migrated++;
                        
                        if ($verbose) {
                            echo "    Migrated: {$key}\n";
                        }
                    } catch (Exception $e) {
                        $errors++;
                        echo "    ERROR: {$record->id} - {$e->getMessage()}\n";
                    }
                }

                $offset += $batch;
                
                if (!$verbose && $migrated % 100 === 0) {
                    echo "  Progress: {$migrated}/{$total}\n";
                }
            } catch (Exception $e) {
                echo "  ERROR in batch: {$e->getMessage()}\n";
                $errors++;
                $offset += $batch;
            }
        }

        return ['migrated' => $migrated, 'errors' => $errors];
    }

    /**
     * Show migration status
     */
    public function actionStatus()
    {
        echo "Core Lookup Tables Migration Status\n";
        echo "====================================\n\n";

        $adapter = Yii::app()->couchbase;
        if (!$adapter) {
            echo "ERROR: Couchbase not configured\n";
            return 1;
        }

        foreach ($this->tables as $tableName => $config) {
            $modelClass = $config['model'];
            $scope = $config['scope'];
            
            if (!class_exists($modelClass)) {
                echo sprintf("%-35s ERROR: Model not found\n", $tableName);
                continue;
            }

            try {
                $mysqlCount = $modelClass::model()->count();
            } catch (Exception $e) {
                echo sprintf("%-35s ERROR: Cannot count MySQL records\n", $tableName);
                continue;
            }
            
            try {
                // Try to count documents in Couchbase
                $query = "SELECT COUNT(*) AS count FROM `openeyes`.`{$scope}`.`{$tableName}`";
                $result = $adapter->query($query);
                $rows = $result->rows();
                $cbCount = !empty($rows) && isset($rows[0]['count']) ? $rows[0]['count'] : 0;
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
    public function actionVerify($table = null, $sample = 10)
    {
        echo "Verifying Core Lookup Tables\n";
        echo "============================\n\n";

        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        $adapter = Yii::app()->couchbase;

        if (!$adapter) {
            echo "ERROR: Couchbase not configured\n";
            return 1;
        }

        $totalChecked = 0;
        $totalMatches = 0;

        foreach ($tables as $tableName => $config) {
            echo "Verifying: {$tableName}\n";
            
            $modelClass = $config['model'];
            $scope = $config['scope'];
            
            if (!class_exists($modelClass)) {
                echo "  ERROR: Model {$modelClass} not found\n";
                continue;
            }

            // Get random sample from MySQL
            try {
                $records = $modelClass::model()->findAll([
                    'order' => 'RAND()',
                    'limit' => $sample,
                ]);
            } catch (Exception $e) {
                echo "  ERROR: Cannot query MySQL: {$e->getMessage()}\n";
                continue;
            }
            
            $matches = 0;
            $mismatches = 0;
            
            foreach ($records as $record) {
                $key = $tableName . '::' . $record->id;
                $totalChecked++;
                
                try {
                    $cbDoc = $adapter->get($scope, $tableName, $key);
                    
                    if ($cbDoc && isset($cbDoc['id']) && $cbDoc['id'] == $record->id) {
                        $matches++;
                        $totalMatches++;
                    } else {
                        $mismatches++;
                        echo "  MISMATCH: {$key}\n";
                    }
                } catch (Exception $e) {
                    $mismatches++;
                    echo "  MISSING: {$key}\n";
                }
            }
            
            echo "  Verified: {$matches}/{$sample}\n\n";
        }

        $successRate = $totalChecked > 0 ? round(($totalMatches / $totalChecked) * 100, 1) : 0;
        echo "Overall: {$totalMatches}/{$totalChecked} ({$successRate}%)\n";

        return 0;
    }
}
