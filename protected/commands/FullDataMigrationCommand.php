<?php
/**
 * Master command to orchestrate full data migration from MariaDB to Couchbase
 * Phase 14: Complete data migration in 5 stages
 * 
 * Usage:
 *   php protected/yiic fulldatamigration run
 *   php protected/yiic fulldatamigration stage --stage=3
 *   php protected/yiic fulldatamigration status
 *   php protected/yiic fulldatamigration validate
 *   php protected/yiic fulldatamigration rollback --confirm=true
 */

class FullDataMigrationCommand extends CConsoleCommand
{
    /**
     * Migration stages in dependency order
     * Stage 1: Reference Data (foundation)
     * Stage 2: Clinical Reference (SNOMED, OPCS, dm+d)
     * Stage 3: Core Clinical Data (patients, episodes, events)
     * Stage 4: Module Elements (delegates to ModuleMigrationCommand)
     * Stage 5: Administrative Data (audit, settings)
     */
    protected $stages = [
        1 => [
            'name' => 'Reference Data',
            'tables' => [
                'event_type' => ['model' => 'EventType', 'scope' => 'reference'],
                'element_type' => ['model' => 'ElementType', 'scope' => 'reference'],
                'specialty' => ['model' => 'Specialty', 'scope' => 'reference'],
                'subspecialty' => ['model' => 'Subspecialty', 'scope' => 'reference'],
                'site' => ['model' => 'Site', 'scope' => 'reference'],
                'institution' => ['model' => 'Institution', 'scope' => 'reference'],
                'firm' => ['model' => 'Firm', 'scope' => 'reference'],
                'eye' => ['model' => 'Eye', 'scope' => 'reference'],
                'gender' => ['model' => 'Gender', 'scope' => 'reference'],
                'ethnic_group' => ['model' => 'EthnicGroup', 'scope' => 'reference'],
                'event_group' => ['model' => 'EventGroup', 'scope' => 'reference'],
            ],
        ],
        2 => [
            'name' => 'Clinical Reference',
            'tables' => [
                'disorder' => ['model' => 'Disorder', 'scope' => 'reference', 'batch' => 500],
                'procedure' => ['model' => 'Procedure', 'scope' => 'reference', 'batch' => 500],
                'medication' => ['model' => 'Medication', 'scope' => 'reference', 'batch' => 500],
                'allergy' => ['model' => 'Allergy', 'scope' => 'reference'],
                'drug' => ['model' => 'Drug', 'scope' => 'reference'],
                'benefit' => ['model' => 'Benefit', 'scope' => 'reference'],
                'complication' => ['model' => 'Complication', 'scope' => 'reference'],
            ],
        ],
        3 => [
            'name' => 'Core Clinical',
            'tables' => [
                'patient' => ['model' => 'Patient', 'scope' => 'core', 'batch' => 200],
                'episode' => ['model' => 'Episode', 'scope' => 'core', 'batch' => 500],
                'event' => ['model' => 'Event', 'scope' => 'core', 'batch' => 500],
                'user' => ['model' => 'User', 'scope' => 'core', 'batch' => 500],
                'contact' => ['model' => 'Contact', 'scope' => 'core', 'batch' => 500],
            ],
        ],
        4 => [
            'name' => 'Module Elements',
            'command' => 'modulemigration',
            'args' => 'migrate --module=all --batch=100',
        ],
        5 => [
            'name' => 'Administrative',
            'tables' => [
                'audit' => ['model' => 'Audit', 'scope' => 'admin', 'batch' => 200],
                'audit_type' => ['model' => 'AuditType', 'scope' => 'reference'],
                'audit_action' => ['model' => 'AuditAction', 'scope' => 'reference'],
                'setting_metadata' => ['model' => 'SettingMetadata', 'scope' => 'admin'],
                'setting_installation' => ['model' => 'SettingInstallation', 'scope' => 'admin'],
                'setting_institution' => ['model' => 'SettingInstitution', 'scope' => 'admin'],
                'setting_site' => ['model' => 'SettingSite', 'scope' => 'admin'],
                'setting_user' => ['model' => 'SettingUser', 'scope' => 'admin'],
            ],
        ],
    ];

    protected $logFile;
    protected $startTime;
    protected $defaultBatchSize = 1000;

    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic fulldatamigration <action> [options]

DESCRIPTION
  Phase 14: Full data migration orchestration from MariaDB to Couchbase.
  Executes migration in 5 stages with validation and rollback support.

ACTIONS
  run         Execute full migration (all stages)
  stage       Run specific stage only
  status      Show migration status (record counts)
  validate    Validate migrated data
  rollback    Rollback migration (clear Couchbase)

OPTIONS
  --stage=<n>          Specific stage number (1-5)
  --batch=<n>          Default batch size (default: 1000)
  --verbose            Show detailed output
  --dryRun             Show what would be migrated without actually migrating
  --skipValidation     Skip post-migration validation
  --continueOnError    Continue migration even if errors occur
  --scope=<name>       Rollback specific scope only (reference/clinical/admin)
  --confirm            Confirmation for rollback action

STAGES
  1. Reference Data       - EventType, Site, Institution, etc. (~30 min)
  2. Clinical Reference   - Disorder, Procedure, Medication (~2 hours)
  3. Core Clinical        - Patient, Episode, Event (~4-8 hours)
  4. Module Elements      - All examination/operation elements (~6-12 hours)
  5. Administrative       - Audit, Settings (~4-8 hours)

EXAMPLES
  yiic fulldatamigration run
  yiic fulldatamigration run --verbose --batch=500
  yiic fulldatamigration stage --stage=3 --verbose
  yiic fulldatamigration status
  yiic fulldatamigration validate
  yiic fulldatamigration rollback --scope=clinical --confirm=true

EOD;
    }

    /**
     * Run full migration
     */
    public function actionRun($batch = null, $verbose = false, $dryRun = false, $skipValidation = false, $continueOnError = false)
    {
        $this->startTime = microtime(true);
        $this->defaultBatchSize = $batch ?? $this->defaultBatchSize;
        $this->logFile = Yii::app()->basePath . '/runtime/migration_' . date('Y-m-d_H-i-s') . '.log';
        
        $this->log("===========================================");
        $this->log("FULL DATA MIGRATION - PHASE 14");
        $this->log("Started: " . date('Y-m-d H:i:s'));
        $this->log("===========================================\n");

        if ($dryRun) {
            $this->log("[DRY RUN MODE - No data will be written]\n");
        }

        // Load configuration if available
        $configFile = Yii::app()->basePath . '/config/migration-config.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $this->log("Loaded configuration from migration-config.php");
        }

        // Pre-flight checks
        if (!$this->preFlightChecks()) {
            $this->log("\n❌ Pre-flight checks FAILED. Aborting migration.");
            return 1;
        }

        // Execute each stage
        $totalMigrated = 0;
        $totalErrors = 0;
        $stageResults = [];

        foreach ($this->stages as $stageNum => $stage) {
            $this->log("\n" . str_repeat('=', 60));
            $this->log("STAGE {$stageNum}: {$stage['name']}");
            $this->log(str_repeat('=', 60) . "\n");
            
            $stageStart = microtime(true);
            $result = $this->runStage($stageNum, $stage, $verbose, $dryRun, $continueOnError);
            $stageDuration = round((microtime(true) - $stageStart) / 60, 2);
            
            $totalMigrated += $result['migrated'];
            $totalErrors += $result['errors'];
            $stageResults[$stageNum] = array_merge($result, ['duration' => $stageDuration]);
            
            $this->log("\nStage {$stageNum} Complete:");
            $this->log("  Migrated: {$result['migrated']}");
            $this->log("  Errors: {$result['errors']}");
            $this->log("  Duration: {$stageDuration} minutes\n");
            
            if ($result['errors'] > 0 && !$continueOnError && !$dryRun) {
                $this->log("❌ ERRORS detected in stage {$stageNum}. Stopping migration.");
                $this->log("Use --continueOnError to override.");
                break;
            }
        }

        // Post-migration validation
        if (!$skipValidation && !$dryRun && $totalErrors === 0) {
            $this->log("\n" . str_repeat('=', 60));
            $this->log("POST-MIGRATION VALIDATION");
            $this->log(str_repeat('=', 60) . "\n");
            $this->runValidation();
        }

        // Summary
        $totalDuration = round((microtime(true) - $this->startTime) / 60, 2);
        $this->log("\n" . str_repeat('=', 60));
        $this->log("MIGRATION SUMMARY");
        $this->log(str_repeat('=', 60));
        $this->log("Total Records Migrated: {$totalMigrated}");
        $this->log("Total Errors: {$totalErrors}");
        $this->log("Total Duration: {$totalDuration} minutes");
        $this->log("Log File: {$this->logFile}");
        
        // Stage breakdown
        $this->log("\nStage Breakdown:");
        foreach ($stageResults as $num => $result) {
            $status = $result['errors'] === 0 ? '✓' : '✗';
            $this->log("  Stage {$num}: {$result['migrated']} records, {$result['errors']} errors, {$result['duration']} min [{$status}]");
        }
        
        $this->log(str_repeat('=', 60) . "\n");

        if ($totalErrors === 0 && !$dryRun) {
            $this->log("✓ Migration completed successfully!");
        } else if ($dryRun) {
            $this->log("ℹ Dry run completed. No data was migrated.");
        } else {
            $this->log("⚠ Migration completed with errors. Review log file.");
        }

        return $totalErrors > 0 ? 1 : 0;
    }

    /**
     * Run specific stage
     */
    public function actionStage($stage = 1, $batch = null, $verbose = false, $dryRun = false, $continueOnError = false)
    {
        if (!isset($this->stages[$stage])) {
            echo "❌ Invalid stage: {$stage}. Valid stages: 1-5\n";
            return 1;
        }

        $this->startTime = microtime(true);
        $this->defaultBatchSize = $batch ?? $this->defaultBatchSize;
        $this->logFile = Yii::app()->basePath . '/runtime/migration_stage{$stage}_' . date('Y-m-d_H-i-s') . '.log';
        
        $stageConfig = $this->stages[$stage];
        
        echo str_repeat('=', 60) . "\n";
        echo "STAGE {$stage}: {$stageConfig['name']}\n";
        echo str_repeat('=', 60) . "\n\n";

        if ($dryRun) {
            echo "[DRY RUN MODE]\n\n";
        }

        $result = $this->runStage($stage, $stageConfig, $verbose, $dryRun, $continueOnError);
        $duration = round((microtime(true) - $this->startTime) / 60, 2);
        
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "STAGE {$stage} COMPLETE\n";
        echo str_repeat('=', 60) . "\n";
        echo "Migrated: {$result['migrated']}\n";
        echo "Errors: {$result['errors']}\n";
        echo "Duration: {$duration} minutes\n";
        echo str_repeat('=', 60) . "\n";

        return $result['errors'] > 0 ? 1 : 0;
    }

    /**
     * Execute a migration stage
     */
    protected function runStage($stageNum, $stage, $verbose, $dryRun, $continueOnError)
    {
        $migrated = 0;
        $errors = 0;

        // Check if this stage delegates to another command
        if (isset($stage['command'])) {
            $this->log("Delegating to command: yiic {$stage['command']} {$stage['args']}");
            
            if (!$dryRun) {
                $cmd = "php " . Yii::app()->basePath . "/yiic {$stage['command']} {$stage['args']}";
                if ($verbose) {
                    $cmd .= " --verbose";
                }
                
                $this->log("Executing: {$cmd}");
                $exitCode = 0;
                passthru($cmd, $exitCode);
                
                if ($exitCode !== 0) {
                    $errors++;
                    $this->log("⚠ Command exited with code: {$exitCode}");
                }
            } else {
                $this->log("[DRY RUN] Would execute: yiic {$stage['command']} {$stage['args']}");
            }
            
            return ['migrated' => 0, 'errors' => $errors];
        }

        // Migrate tables in this stage
        foreach ($stage['tables'] as $tableName => $config) {
            $tableBatch = $config['batch'] ?? $this->defaultBatchSize;
            
            $result = $this->migrateTable($tableName, $config, $tableBatch, $verbose, $dryRun, $continueOnError);
            
            $migrated += $result['migrated'];
            $errors += $result['errors'];
            
            if ($result['errors'] > 0 && !$continueOnError) {
                break;
            }
        }

        return ['migrated' => $migrated, 'errors' => $errors];
    }

    /**
     * Migrate a single table
     */
    protected function migrateTable($tableName, $config, $batch, $verbose, $dryRun, $continueOnError)
    {
        $modelClass = $config['model'];
        $scope = $config['scope'];

        $this->log("Table: {$tableName} (Model: {$modelClass}, Scope: {$scope})");

        if (!class_exists($modelClass)) {
            $this->log("  ❌ ERROR: Model class {$modelClass} not found");
            return ['migrated' => 0, 'errors' => 1];
        }

        try {
            $total = $modelClass::model()->count();
        } catch (Exception $e) {
            $this->log("  ❌ ERROR counting records: {$e->getMessage()}");
            return ['migrated' => 0, 'errors' => 1];
        }

        $this->log("  Total Records: {$total}");

        if ($dryRun) {
            $this->log("  [DRY RUN] Would migrate {$total} records in batches of {$batch}");
            return ['migrated' => 0, 'errors' => 0];
        }

        if ($total === 0) {
            $this->log("  ℹ No records to migrate");
            return ['migrated' => 0, 'errors' => 0];
        }

        $migrated = 0;
        $errors = 0;
        $offset = 0;

        try {
            $adapter = Yii::app()->couchbase;
        } catch (Exception $e) {
            $this->log("  ❌ ERROR: Couchbase adapter not available: {$e->getMessage()}");
            return ['migrated' => 0, 'errors' => 1];
        }

        $progressInterval = max(100, min(1000, ceil($total / 10)));
        $lastProgress = 0;

        while ($offset < $total) {
            try {
                $records = $modelClass::model()->findAll([
                    'limit' => $batch,
                    'offset' => $offset,
                ]);

                foreach ($records as $record) {
                    try {
                        $doc = $this->createDocument($record);
                        $key = $tableName . '::' . $record->id;
                        
                        $adapter->upsert($scope, $tableName, $key, $doc);
                        $migrated++;
                        
                        if ($verbose && ($migrated - $lastProgress) >= $progressInterval) {
                            $pct = round(($migrated / $total) * 100, 1);
                            $this->log("  Progress: {$migrated}/{$total} ({$pct}%)");
                            $lastProgress = $migrated;
                        }
                    } catch (Exception $e) {
                        $errors++;
                        if ($verbose) {
                            $this->log("  ⚠ ERROR migrating record {$record->id}: {$e->getMessage()}");
                        }
                        
                        if (!$continueOnError) {
                            throw $e;
                        }
                    }
                }

                $offset += $batch;
                
                // Memory management
                gc_collect_cycles();
                
            } catch (Exception $e) {
                $this->log("  ❌ BATCH ERROR at offset {$offset}: {$e->getMessage()}");
                $errors++;
                
                if (!$continueOnError) {
                    break;
                }
                
                $offset += $batch;
            }
        }

        $pct = $total > 0 ? round(($migrated / $total) * 100, 1) : 0;
        $this->log("  ✓ Migrated: {$migrated}/{$total} ({$pct}%), Errors: {$errors}");
        
        return ['migrated' => $migrated, 'errors' => $errors];
    }

    /**
     * Create Couchbase document from model
     */
    protected function createDocument($record)
    {
        if (method_exists($record, 'toCouchbaseDocument')) {
            return $record->toCouchbaseDocument();
        }
        
        // Fallback for models without the trait
        $doc = $record->attributes;
        $doc['_type'] = get_class($record);
        return $doc;
    }

    /**
     * Pre-flight checks
     */
    protected function preFlightChecks()
    {
        $this->log("Pre-flight Checks:");
        $allPassed = true;
        
        // Check Couchbase connection
        try {
            $adapter = Yii::app()->couchbase;
            if (!$adapter) {
                throw new Exception("Couchbase component not configured");
            }
            $this->log("  ✓ Couchbase connection OK");
        } catch (Exception $e) {
            $this->log("  ❌ Couchbase connection FAILED: {$e->getMessage()}");
            $allPassed = false;
        }

        // Check MariaDB connection
        try {
            Yii::app()->db->createCommand("SELECT 1")->execute();
            $patientCount = Patient::model()->count();
            $this->log("  ✓ MariaDB connection OK ({$patientCount} patients)");
        } catch (Exception $e) {
            $this->log("  ❌ MariaDB connection FAILED: {$e->getMessage()}");
            $allPassed = false;
        }

        // Check required model classes
        $requiredModels = ['Patient', 'Episode', 'Event', 'User', 'EventType', 'Disorder'];
        foreach ($requiredModels as $model) {
            if (!class_exists($model)) {
                $this->log("  ❌ Required model class {$model} not found");
                $allPassed = false;
            }
        }
        if (count($requiredModels) === count(array_filter($requiredModels, 'class_exists'))) {
            $this->log("  ✓ Required model classes available");
        }

        // Check log file directory is writable
        $runtimeDir = Yii::app()->basePath . '/runtime';
        if (!is_writable($runtimeDir)) {
            $this->log("  ❌ Runtime directory not writable: {$runtimeDir}");
            $allPassed = false;
        } else {
            $this->log("  ✓ Log directory writable");
        }
        
        return $allPassed;
    }

    /**
     * Run post-migration validation
     */
    protected function runValidation()
    {
        $checks = [
            ['model' => 'Patient', 'scope' => 'clinical', 'table' => 'patient'],
            ['model' => 'Episode', 'scope' => 'clinical', 'table' => 'episode'],
            ['model' => 'Event', 'scope' => 'clinical', 'table' => 'event'],
            ['model' => 'User', 'scope' => 'admin', 'table' => 'user'],
            ['model' => 'Disorder', 'scope' => 'reference', 'table' => 'disorder'],
            ['model' => 'Medication', 'scope' => 'reference', 'table' => 'medication'],
        ];

        $adapter = Yii::app()->couchbase;
        $allPassed = true;

        foreach ($checks as $check) {
            try {
                $mysqlCount = $check['model']::model()->count();
                
                try {
                    $cbCount = $adapter->count($check['scope'], $check['table']);
                } catch (Exception $e) {
                    $cbCount = 0;
                }
                
                $match = $mysqlCount === $cbCount;
                $pct = $mysqlCount > 0 ? round(($cbCount / $mysqlCount) * 100, 1) : 0;
                $status = $match ? '✓' : '⚠';
                
                if (!$match) {
                    $allPassed = false;
                }
                
                $this->log(sprintf("  %s %-20s: MySQL=%d, Couchbase=%d [%s%%]",
                    $status, $check['table'], $mysqlCount, $cbCount, $pct
                ));
            } catch (Exception $e) {
                $this->log("  ❌ Validation error for {$check['table']}: {$e->getMessage()}");
                $allPassed = false;
            }
        }
        
        if ($allPassed) {
            $this->log("\n✓ All validation checks passed!");
        } else {
            $this->log("\n⚠ Some validation checks failed. Review counts above.");
        }
    }

    /**
     * Show migration status
     */
    public function actionStatus()
    {
        echo str_repeat('=', 80) . "\n";
        echo "FULL DATA MIGRATION STATUS\n";
        echo str_repeat('=', 80) . "\n\n";

        $adapter = Yii::app()->couchbase;

        foreach ($this->stages as $stageNum => $stage) {
            echo "Stage {$stageNum}: {$stage['name']}\n";
            echo str_repeat('-', 80) . "\n";
            
            if (isset($stage['command'])) {
                echo "  (Uses {$stage['command']} command - run separately for details)\n\n";
                continue;
            }
            
            foreach ($stage['tables'] as $tableName => $config) {
                $modelClass = $config['model'];
                
                if (!class_exists($modelClass)) {
                    echo sprintf("  %-25s MODEL NOT FOUND\n", $tableName);
                    continue;
                }
                
                try {
                    $mysqlCount = $modelClass::model()->count();
                } catch (Exception $e) {
                    $mysqlCount = 0;
                }
                
                try {
                    $cbCount = $adapter->count($config['scope'], $tableName);
                } catch (Exception $e) {
                    $cbCount = 0;
                }
                
                $match = $mysqlCount === $cbCount;
                $pct = $mysqlCount > 0 ? round(($cbCount / $mysqlCount) * 100) : 0;
                $status = $match ? '✓' : '⚠';
                
                printf("  %-25s MySQL: %8d  CB: %8d  [%s %3d%%]\n",
                    $tableName, $mysqlCount, $cbCount, $status, $pct
                );
            }
            echo "\n";
        }
        
        echo str_repeat('=', 80) . "\n";
    }

    /**
     * Validate migrated data
     */
    public function actionValidate($sample = 100, $verbose = false)
    {
        echo str_repeat('=', 60) . "\n";
        echo "DATA VALIDATION\n";
        echo str_repeat('=', 60) . "\n\n";

        echo "Running comprehensive validation...\n\n";

        // Run data validation command
        $cmd = "php " . Yii::app()->basePath . "/yiic datavalidation all --sample={$sample}";
        if ($verbose) {
            $cmd .= " --verbose";
        }
        
        passthru($cmd);
    }

    /**
     * Rollback migration (clear Couchbase data)
     */
    public function actionRollback($scope = null, $confirm = false)
    {
        if (!$confirm) {
            echo "\n";
            echo str_repeat('=', 60) . "\n";
            echo "WARNING: ROLLBACK OPERATION\n";
            echo str_repeat('=', 60) . "\n";
            echo "This will DELETE all migrated data from Couchbase!\n";
            echo "MariaDB data will NOT be affected.\n";
            echo "\n";
            if ($scope) {
                echo "Scope to clear: {$scope}\n";
            } else {
                echo "Scopes to clear: reference, clinical, admin\n";
            }
            echo "\n";
            echo "Run with --confirm=true to proceed.\n";
            echo str_repeat('=', 60) . "\n";
            return 1;
        }

        echo "\n";
        echo str_repeat('=', 60) . "\n";
        echo "ROLLING BACK MIGRATION\n";
        echo str_repeat('=', 60) . "\n\n";
        
        $scopes = $scope ? [$scope] : ['reference', 'clinical', 'admin'];

        foreach ($scopes as $scopeName) {
            echo "Clearing scope: {$scopeName}...\n";
            
            try {
                // Note: This would need to be implemented in the adapter
                // For now, log the action
                echo "  ⚠ Manual cleanup required: DELETE FROM openeyes.{$scopeName}._default\n";
                echo "  ℹ Run: cbq -e \"DELETE FROM openeyes.{$scopeName}._default\"\n";
            } catch (Exception $e) {
                echo "  ❌ Error: {$e->getMessage()}\n";
            }
        }

        echo "\n";
        echo str_repeat('=', 60) . "\n";
        echo "Rollback instructions displayed.\n";
        echo "Execute the N1QL commands manually to complete rollback.\n";
        echo str_repeat('=', 60) . "\n";
        
        return 0;
    }

    /**
     * Log message to console and file
     */
    protected function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}";
        
        echo $message . "\n";
        
        if ($this->logFile) {
            file_put_contents($this->logFile, $logMessage . "\n", FILE_APPEND);
        }
    }
}
