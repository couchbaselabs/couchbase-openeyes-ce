<?php
/**
 * Command to migrate administrative tables to Couchbase
 * Phase 12: Audit logs, settings, authentication, authorization
 * 
 * Usage:
 *   php protected/yiic admin migrate
 *   php protected/yiic admin migrate --table=audit
 *   php protected/yiic admin status
 *   php protected/yiic admin verify
 */

class AdminMigrationCommand extends CConsoleCommand
{
    /**
     * Tables to migrate in dependency order
     * Tier structure ensures dependencies are migrated first
     */
    protected $tables = [
        // Tier 1: Small independent tables
        'audit_action' => ['model' => 'AuditAction', 'scope' => 'admin', 'batch' => 100, 'pk' => 'id'],
        'audit_type' => ['model' => 'AuditType', 'scope' => 'admin', 'batch' => 100, 'pk' => 'id'],
        'setting_group' => ['model' => 'SettingGroup', 'scope' => 'admin', 'batch' => 100, 'pk' => 'id'],
        'setting_field_type' => ['model' => 'SettingFieldType', 'scope' => 'admin', 'batch' => 100, 'pk' => 'id'],
        'auth_item' => ['model' => 'AuthItem', 'scope' => 'admin', 'batch' => 500, 'pk' => 'name'],
        
        // Tier 2: Depends on Tier 1
        'setting_metadata' => ['model' => 'SettingMetadata', 'scope' => 'admin', 'batch' => 500, 'pk' => 'id'],
        'user_authentication_method' => ['model' => 'UserAuthenticationMethod', 'scope' => 'admin', 'batch' => 100, 'pk' => 'code'],
        
        // Tier 3: Settings values
        'setting_installation' => ['model' => 'SettingInstallation', 'scope' => 'admin', 'batch' => 1000, 'pk' => 'id'],
        'setting_institution' => ['model' => 'SettingInstitution', 'scope' => 'admin', 'batch' => 1000, 'pk' => 'id'],
        'setting_site' => ['model' => 'SettingSite', 'scope' => 'admin', 'batch' => 1000, 'pk' => 'id'],
        'setting_firm' => ['model' => 'SettingFirm', 'scope' => 'admin', 'batch' => 1000, 'pk' => 'id'],
        'setting_user' => ['model' => 'SettingUser', 'scope' => 'admin', 'batch' => 1000, 'pk' => 'id'],
        
        // Tier 4: Authentication and authorization
        'user_authentication' => ['model' => 'UserAuthentication', 'scope' => 'admin', 'batch' => 500, 'pk' => 'id'],
        'institution_authentication' => ['model' => 'InstitutionAuthentication', 'scope' => 'admin', 'batch' => 500, 'pk' => 'id'],
        'auth_assignment' => ['model' => 'AuthAssignment', 'scope' => 'admin', 'batch' => 1000, 'pk' => ['userid', 'itemname']],
        
        // Tier 5: Large audit table (process last with smaller batches)
        'audit' => ['model' => 'Audit', 'scope' => 'admin', 'batch' => 500, 'pk' => 'id', 'large' => true],
    ];

    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic admin <action> [options]

DESCRIPTION
  Phase 12: Migrate administrative tables to Couchbase
  
  This includes:
  - Audit logs (audit, audit_action, audit_type)
  - Settings (setting_metadata, setting_installation, setting_institution, etc.)
  - Authentication (user_authentication, institution_authentication)
  - Authorization (auth_item, auth_assignment)

ACTIONS
  migrate    Migrate all or specific table
  status     Show migration status for all tables
  verify     Verify migrated data integrity
  help       Show this help message

OPTIONS
  --table=<name>   Specific table to migrate (optional)
  --batch=<n>      Override batch size (default: varies by table)
  --verbose        Show detailed output for debugging
  --dryRun         Preview migration without making changes

EXAMPLES
  # Migrate all administrative tables
  yiic admin migrate
  
  # Migrate only audit table with verbose output
  yiic admin migrate --table=audit --verbose
  
  # Check migration status
  yiic admin status
  
  # Verify data integrity with 20 sample records
  yiic admin verify --sample=20
  
  # Dry run to see what would be migrated
  yiic admin migrate --dryRun

EOD;
    }

    /**
     * Migrate all or specific table
     */
    public function actionMigrate($table = null, $batch = null, $verbose = false, $dryRun = false)
    {
        echo "===========================================\n";
        echo "Phase 12: Administrative Tables Migration\n";
        echo "===========================================\n\n";

        if ($dryRun) {
            echo "[DRY RUN MODE - No changes will be made]\n\n";
        }

        // Determine tables to migrate
        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        
        if ($table && !isset($this->tables[$table])) {
            echo "ERROR: Unknown table '{$table}'\n";
            echo "Available tables: " . implode(', ', array_keys($this->tables)) . "\n";
            return 1;
        }

        $totalMigrated = 0;
        $totalErrors = 0;
        $startTime = microtime(true);

        foreach ($tables as $tableName => $config) {
            echo "Migrating table: {$tableName}\n";
            echo str_repeat('-', 50) . "\n";
            
            // Use custom batch size if provided, otherwise use table config
            $tableBatch = $batch ?? $config['batch'];
            
            $result = $this->migrateTable($tableName, $config, $tableBatch, $verbose, $dryRun);
            
            $totalMigrated += $result['migrated'];
            $totalErrors += $result['errors'];
            
            echo "  ✓ Migrated: {$result['migrated']}, Errors: {$result['errors']}\n";
            echo "  Time: {$result['duration']}s\n\n";
            
            // Memory management for large tables
            if (!empty($config['large']) && $result['migrated'] > 0) {
                gc_collect_cycles();
            }
        }

        $totalTime = round(microtime(true) - $startTime, 2);

        echo "===========================================\n";
        echo "Migration Complete\n";
        echo "===========================================\n";
        echo "Total Migrated: {$totalMigrated}\n";
        echo "Total Errors: {$totalErrors}\n";
        echo "Total Time: {$totalTime}s\n";
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
        $startTime = microtime(true);

        // Validate model class exists
        if (!class_exists($modelClass)) {
            echo "  ERROR: Model class {$modelClass} not found\n";
            return ['migrated' => 0, 'errors' => 1, 'duration' => 0];
        }

        // Get Couchbase adapter
        $adapter = Yii::app()->couchbase;
        if (!$adapter) {
            echo "  ERROR: Couchbase not configured\n";
            return ['migrated' => 0, 'errors' => 1, 'duration' => 0];
        }

        // Count total records
        try {
            $total = $modelClass::model()->count();
            echo "  Total records: {$total}\n";
        } catch (Exception $e) {
            echo "  ERROR counting records: {$e->getMessage()}\n";
            return ['migrated' => 0, 'errors' => 1, 'duration' => 0];
        }

        if ($total === 0) {
            echo "  No records to migrate\n";
            return ['migrated' => 0, 'errors' => 0, 'duration' => 0];
        }

        if ($dryRun) {
            echo "  [DRY RUN] Would migrate {$total} records\n";
            return ['migrated' => 0, 'errors' => 0, 'duration' => 0];
        }

        // Get primary key field(s)
        $pk = $config['pk'] ?? 'id';
        $orderBy = is_array($pk) ? $pk[0] . ' ASC' : $pk . ' ASC';

        // Process in batches
        $offset = 0;
        while ($offset < $total) {
            try {
                // Load batch with relations for embedding
                $records = $modelClass::model()->findAll([
                    'limit' => $batch,
                    'offset' => $offset,
                    'order' => $orderBy, // Consistent ordering using PK
                ]);

                foreach ($records as $record) {
                    try {
                        // Generate document key (use custom method if available)
                        if (method_exists($record, 'getCouchbaseDocumentKey')) {
                            $key = $record->getCouchbaseDocumentKey();
                        } elseif (is_array($pk)) {
                            // Composite key
                            $keyParts = array_map(function($field) use ($record) {
                                return $record->$field;
                            }, $pk);
                            $key = $tableName . '::' . implode('::', $keyParts);
                        } else {
                            // Single key
                            $key = $tableName . '::' . $record->$pk;
                        }
                        
                        // Convert to Couchbase document (don't embed relations to avoid errors)
                        $doc = $record->attributes;
                        $doc['_type'] = $tableName;
                        
                        // Upsert to Couchbase
                        $adapter->upsert($scope, $collection, $key, $doc);
                        $migrated++;
                        
                        if ($verbose && $migrated % 100 === 0) {
                            echo "    Progress: {$migrated}/{$total}\n";
                        }
                    } catch (Exception $e) {
                        $errors++;
                        echo "    ERROR: Record {$record->id} - {$e->getMessage()}\n";
                    }
                }

                $offset += $batch;
                
                // Show progress for large tables
                if (!$verbose && $total > 1000 && $migrated % 1000 === 0) {
                    echo "  Progress: {$migrated}/{$total}\n";
                }
                
            } catch (Exception $e) {
                echo "  ERROR in batch at offset {$offset}: {$e->getMessage()}\n";
                $errors++;
                $offset += $batch; // Continue to next batch
            }
        }

        $duration = round(microtime(true) - $startTime, 2);

        return [
            'migrated' => $migrated,
            'errors' => $errors,
            'duration' => $duration
        ];
    }

    /**
     * Show migration status for all tables
     */
    public function actionStatus()
    {
        echo "Administrative Tables Migration Status\n";
        echo "======================================\n\n";

        $adapter = Yii::app()->couchbase;
        if (!$adapter) {
            echo "ERROR: Couchbase not configured\n";
            return 1;
        }

        $allMatch = true;

        foreach ($this->tables as $tableName => $config) {
            $modelClass = $config['model'];
            $scope = $config['scope'];
            
            if (!class_exists($modelClass)) {
                printf("%-35s ERROR: Model not found\n", $tableName);
                continue;
            }

            try {
                $mysqlCount = $modelClass::model()->count();
            } catch (Exception $e) {
                printf("%-35s ERROR: Cannot count MySQL records\n", $tableName);
                continue;
            }
            
            try {
                // Count documents in Couchbase
                $query = "SELECT COUNT(*) AS count FROM `openeyes`.`{$scope}`.`{$tableName}`";
                $result = $adapter->query($query);
                $rows = $result->rows();
                $cbCount = !empty($rows) && isset($rows[0]['count']) ? $rows[0]['count'] : 0;
            } catch (Exception $e) {
                $cbCount = 0;
            }
            
            $match = $mysqlCount === $cbCount;
            $status = $match ? '✓' : '✗';
            $pct = $mysqlCount > 0 ? round(($cbCount / $mysqlCount) * 100) : 0;
            
            printf("%-35s MySQL: %6d  CB: %6d  [%s %3d%%]\n",
                $tableName,
                $mysqlCount,
                $cbCount,
                $status,
                $pct
            );
            
            if (!$match) {
                $allMatch = false;
            }
        }

        echo "\n";
        if ($allMatch) {
            echo "✓ All tables migrated successfully!\n";
            return 0;
        } else {
            echo "✗ Some tables need migration or verification\n";
            return 1;
        }
    }

    /**
     * Verify migrated data integrity
     */
    public function actionVerify($table = null, $sample = 10)
    {
        echo "Verifying Administrative Tables\n";
        echo "================================\n\n";

        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        $adapter = Yii::app()->couchbase;

        if (!$adapter) {
            echo "ERROR: Couchbase not configured\n";
            return 1;
        }

        $totalChecked = 0;
        $totalMatches = 0;
        $totalMismatches = 0;

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
            
            if (empty($records)) {
                echo "  No records found\n\n";
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
                        $totalMismatches++;
                        echo "  MISMATCH: {$key} - ID doesn't match\n";
                    }
                } catch (Exception $e) {
                    $mismatches++;
                    $totalMismatches++;
                    echo "  MISSING: {$key}\n";
                }
            }
            
            $pct = count($records) > 0 ? round(($matches / count($records)) * 100) : 0;
            echo "  Verified: {$matches}/{$sample} ({$pct}%)\n\n";
        }

        echo "================================\n";
        echo "Total Checked: {$totalChecked}\n";
        echo "Matches: {$totalMatches}\n";
        echo "Mismatches: {$totalMismatches}\n";
        echo "================================\n";

        return $totalMismatches === 0 ? 0 : 1;
    }
}
