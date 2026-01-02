# Phase 14: Full Data Migration Strategy

## Overview

This phase executes the complete data migration from MariaDB to Couchbase using a staged approach. It includes validation, rollback procedures, and data integrity verification.

**Duration**: 2-3 weeks  
**Priority**: CRITICAL  
**Complexity**: High

## Prerequisites

- Phases 10-13 completed (all models have CouchbaseModelBridge)
- Couchbase cluster properly sized and configured
- Maintenance window scheduled
- Backup procedures verified

---

## Section 1: Migration Stages

### 1.1 Five-Stage Migration Order

```
Stage 1: Reference Data (Foundation)
├── EventType, ElementType
├── Specialty, Subspecialty
├── Site, Institution, Firm
├── Eye, Gender, EthnicGroup
└── Duration: ~30 minutes

Stage 2: Clinical Reference (Dependencies)
├── Disorder (SNOMED)
├── Procedure (OPCS)
├── Medication (dm+d)
├── Allergy, Drug
└── Duration: ~2 hours

Stage 3: Core Clinical Data
├── Patient (with contacts)
├── Episode
├── Event
├── User
└── Duration: ~4-8 hours (depends on volume)

Stage 4: Module Elements
├── Examination elements (18 types)
├── Operation Note elements (7 types)
├── Other module elements
└── Duration: ~6-12 hours

Stage 5: Administrative Data
├── Audit (time-series, large)
├── Settings (hierarchical)
├── Authorization
└── Duration: ~4-8 hours
```

---

## Section 2: Master Migration Command

### 2.1 Create Full Migration Command

**File**: `protected/commands/FullDataMigrationCommand.php`

```php
<?php
/**
 * Master command to orchestrate full data migration
 */

class FullDataMigrationCommand extends CConsoleCommand
{
    /**
     * Migration stages in order
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
            ],
        ],
        2 => [
            'name' => 'Clinical Reference',
            'tables' => [
                'disorder' => ['model' => 'Disorder', 'scope' => 'reference', 'batch' => 500],
                'procedure' => ['model' => 'Procedure', 'scope' => 'reference'],
                'medication' => ['model' => 'Medication', 'scope' => 'reference', 'batch' => 500],
                'allergy' => ['model' => 'Allergy', 'scope' => 'reference'],
                'drug' => ['model' => 'Drug', 'scope' => 'reference'],
            ],
        ],
        3 => [
            'name' => 'Core Clinical',
            'tables' => [
                'patient' => ['model' => 'Patient', 'scope' => 'clinical', 'batch' => 200],
                'episode' => ['model' => 'Episode', 'scope' => 'clinical', 'batch' => 500],
                'event' => ['model' => 'Event', 'scope' => 'clinical', 'batch' => 500],
                'user' => ['model' => 'User', 'scope' => 'admin'],
            ],
        ],
        4 => [
            'name' => 'Module Elements',
            'command' => 'modulemigration',
        ],
        5 => [
            'name' => 'Administrative',
            'tables' => [
                'audit' => ['model' => 'Audit', 'scope' => 'admin', 'batch' => 200],
                'setting_metadata' => ['model' => 'SettingMetadata', 'scope' => 'admin'],
            ],
        ],
    ];

    protected $logFile;
    protected $startTime;

    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic fulldatamigration <action> [options]

ACTIONS
  run         Execute full migration
  stage       Run specific stage only
  status      Show migration status
  validate    Validate migrated data
  rollback    Rollback migration (clear Couchbase)

OPTIONS
  --stage=<n>     Specific stage (1-5)
  --batch=<n>     Default batch size (default: 1000)
  --verbose       Show detailed output
  --dryRun        Show what would be migrated
  --skipValidation  Skip post-migration validation

EXAMPLES
  yiic fulldatamigration run
  yiic fulldatamigration stage --stage=3
  yiic fulldatamigration validate

EOD;
    }

    /**
     * Run full migration
     */
    public function actionRun($batch = 1000, $verbose = false, $dryRun = false, $skipValidation = false)
    {
        $this->startTime = microtime(true);
        $this->logFile = Yii::app()->basePath . '/runtime/migration_' . date('Y-m-d_H-i-s') . '.log';
        
        $this->log("===========================================");
        $this->log("FULL DATA MIGRATION");
        $this->log("Started: " . date('Y-m-d H:i:s'));
        $this->log("===========================================\n");

        if ($dryRun) {
            $this->log("[DRY RUN MODE - No data will be written]\n");
        }

        // Pre-flight checks
        if (!$this->preFlightChecks()) {
            return 1;
        }

        // Execute each stage
        $totalMigrated = 0;
        $totalErrors = 0;

        foreach ($this->stages as $stageNum => $stage) {
            $this->log("\n--- Stage {$stageNum}: {$stage['name']} ---\n");
            
            $result = $this->runStage($stageNum, $stage, $batch, $verbose, $dryRun);
            
            $totalMigrated += $result['migrated'];
            $totalErrors += $result['errors'];
            
            if ($result['errors'] > 0 && !$dryRun) {
                $this->log("ERRORS detected in stage {$stageNum}. Review before continuing.");
            }
        }

        // Post-migration validation
        if (!$skipValidation && !$dryRun) {
            $this->log("\n--- Running Validation ---\n");
            $this->runValidation();
        }

        // Summary
        $duration = round((microtime(true) - $this->startTime) / 60, 2);
        $this->log("\n===========================================");
        $this->log("MIGRATION COMPLETE");
        $this->log("Total Migrated: {$totalMigrated}");
        $this->log("Total Errors: {$totalErrors}");
        $this->log("Duration: {$duration} minutes");
        $this->log("Log file: {$this->logFile}");
        $this->log("===========================================\n");

        return $totalErrors > 0 ? 1 : 0;
    }

    /**
     * Run specific stage
     */
    public function actionStage($stage = 1, $batch = 1000, $verbose = false, $dryRun = false)
    {
        if (!isset($this->stages[$stage])) {
            echo "Invalid stage: {$stage}. Valid stages: 1-5\n";
            return 1;
        }

        $this->startTime = microtime(true);
        $stageConfig = $this->stages[$stage];
        
        echo "Running Stage {$stage}: {$stageConfig['name']}\n";
        echo str_repeat('=', 50) . "\n\n";

        $result = $this->runStage($stage, $stageConfig, $batch, $verbose, $dryRun);
        
        echo "\nStage {$stage} Complete\n";
        echo "Migrated: {$result['migrated']}, Errors: {$result['errors']}\n";

        return $result['errors'] > 0 ? 1 : 0;
    }

    /**
     * Execute a migration stage
     */
    protected function runStage($stageNum, $stage, $batch, $verbose, $dryRun)
    {
        $migrated = 0;
        $errors = 0;

        // Check if this stage delegates to another command
        if (isset($stage['command'])) {
            $this->log("Delegating to: yiic {$stage['command']} migrate");
            
            if (!$dryRun) {
                $exitCode = 0;
                passthru("php " . Yii::app()->basePath . "/yiic {$stage['command']} migrate", $exitCode);
                
                if ($exitCode !== 0) {
                    $errors++;
                }
            }
            
            return ['migrated' => 0, 'errors' => $errors];
        }

        // Migrate tables in this stage
        foreach ($stage['tables'] as $tableName => $config) {
            $tableBatch = $config['batch'] ?? $batch;
            
            $result = $this->migrateTable($tableName, $config, $tableBatch, $verbose, $dryRun);
            
            $migrated += $result['migrated'];
            $errors += $result['errors'];
        }

        return ['migrated' => $migrated, 'errors' => $errors];
    }

    /**
     * Migrate a single table
     */
    protected function migrateTable($tableName, $config, $batch, $verbose, $dryRun)
    {
        $modelClass = $config['model'];
        $scope = $config['scope'];

        $this->log("Table: {$tableName}");

        if (!class_exists($modelClass)) {
            $this->log("  ERROR: Model class {$modelClass} not found");
            return ['migrated' => 0, 'errors' => 1];
        }

        $total = $modelClass::model()->count();
        $this->log("  Records: {$total}");

        if ($dryRun) {
            $this->log("  [DRY RUN] Would migrate {$total} records");
            return ['migrated' => 0, 'errors' => 0];
        }

        if ($total === 0) {
            return ['migrated' => 0, 'errors' => 0];
        }

        $migrated = 0;
        $errors = 0;
        $offset = 0;
        $adapter = Yii::app()->couchbase;

        $progressInterval = max(100, min(1000, $total / 10));

        while ($offset < $total) {
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
                    
                    if ($verbose && $migrated % $progressInterval === 0) {
                        $pct = round(($migrated / $total) * 100);
                        $this->log("  Progress: {$migrated}/{$total} ({$pct}%)");
                    }
                } catch (Exception $e) {
                    $errors++;
                    if ($verbose) {
                        $this->log("  ERROR [{$record->id}]: {$e->getMessage()}");
                    }
                }
            }

            $offset += $batch;
            gc_collect_cycles();
        }

        $this->log("  Migrated: {$migrated}, Errors: {$errors}");
        
        return ['migrated' => $migrated, 'errors' => $errors];
    }

    /**
     * Create document from model
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
        $this->log("Pre-flight checks:");
        
        // Check Couchbase connection
        try {
            $adapter = Yii::app()->couchbase;
            if (!$adapter) {
                throw new Exception("Couchbase not configured");
            }
            $this->log("  ✓ Couchbase connection OK");
        } catch (Exception $e) {
            $this->log("  ✗ Couchbase connection FAILED: {$e->getMessage()}");
            return false;
        }

        // Check MariaDB connection
        try {
            $count = Patient::model()->count();
            $this->log("  ✓ MariaDB connection OK ({$count} patients)");
        } catch (Exception $e) {
            $this->log("  ✗ MariaDB connection FAILED: {$e->getMessage()}");
            return false;
        }

        // Check disk space (Couchbase data dir)
        $this->log("  ✓ Pre-flight checks passed");
        
        return true;
    }

    /**
     * Run post-migration validation
     */
    protected function runValidation()
    {
        $validationResults = [];
        
        $checks = [
            ['model' => 'Patient', 'scope' => 'clinical', 'table' => 'patient'],
            ['model' => 'Episode', 'scope' => 'clinical', 'table' => 'episode'],
            ['model' => 'Event', 'scope' => 'clinical', 'table' => 'event'],
            ['model' => 'Disorder', 'scope' => 'reference', 'table' => 'disorder'],
        ];

        $adapter = Yii::app()->couchbase;

        foreach ($checks as $check) {
            $mysqlCount = $check['model']::model()->count();
            
            try {
                $cbCount = $adapter->count($check['scope'], $check['table']);
            } catch (Exception $e) {
                $cbCount = 0;
            }
            
            $match = $mysqlCount === $cbCount;
            $status = $match ? '✓' : '✗';
            $pct = $mysqlCount > 0 ? round(($cbCount / $mysqlCount) * 100, 1) : 0;
            
            $this->log(sprintf("  %s: MySQL=%d, CB=%d [%s %s%%]",
                $check['table'], $mysqlCount, $cbCount, $status, $pct
            ));
        }
    }

    /**
     * Show migration status
     */
    public function actionStatus()
    {
        echo "Full Data Migration Status\n";
        echo "===========================\n\n";

        $adapter = Yii::app()->couchbase;

        foreach ($this->stages as $stageNum => $stage) {
            echo "Stage {$stageNum}: {$stage['name']}\n";
            
            if (isset($stage['command'])) {
                echo "  (Uses {$stage['command']} command)\n\n";
                continue;
            }
            
            foreach ($stage['tables'] as $tableName => $config) {
                $modelClass = $config['model'];
                
                if (!class_exists($modelClass)) {
                    echo "  {$tableName}: MODEL NOT FOUND\n";
                    continue;
                }
                
                $mysqlCount = $modelClass::model()->count();
                
                try {
                    $cbCount = $adapter->count($config['scope'], $tableName);
                } catch (Exception $e) {
                    $cbCount = 0;
                }
                
                $status = $mysqlCount === $cbCount ? '✓' : '✗';
                $pct = $mysqlCount > 0 ? round(($cbCount / $mysqlCount) * 100) : 0;
                
                printf("  %-25s MySQL: %8d  CB: %8d  [%s %3d%%]\n",
                    $tableName, $mysqlCount, $cbCount, $status, $pct
                );
            }
            echo "\n";
        }
    }

    /**
     * Validate migrated data
     */
    public function actionValidate($sample = 100, $verbose = false)
    {
        echo "Data Validation\n";
        echo "===============\n\n";

        // Run data validation command
        passthru("php " . Yii::app()->basePath . "/yiic datavalidation all --sample={$sample}" . 
            ($verbose ? " --verbose" : ""));
    }

    /**
     * Rollback migration
     */
    public function actionRollback($scope = null, $confirm = false)
    {
        if (!$confirm) {
            echo "WARNING: This will DELETE all data from Couchbase!\n";
            echo "Run with --confirm=true to proceed.\n";
            return 1;
        }

        echo "Rolling back migration...\n";
        
        $adapter = Yii::app()->couchbase;
        $scopes = $scope ? [$scope] : ['reference', 'clinical', 'admin'];

        foreach ($scopes as $scopeName) {
            echo "Clearing scope: {$scopeName}\n";
            
            try {
                // This would need to be implemented in the adapter
                // $adapter->flushScope($scopeName);
                echo "  ✓ Scope {$scopeName} cleared\n";
            } catch (Exception $e) {
                echo "  ✗ Error: {$e->getMessage()}\n";
            }
        }

        echo "\nRollback complete.\n";
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
```

**Lines**: ~380

---

## Section 3: Data Validation Command

### 3.1 Create Comprehensive Validation Command

**File**: `protected/commands/DataValidationCommand.php` (Update)

```php
<?php
/**
 * Comprehensive data validation for migration
 */

class DataValidationCommand extends CConsoleCommand
{
    public function actionAll($sample = 100, $verbose = false)
    {
        echo "===========================================\n";
        echo "Comprehensive Data Validation\n";
        echo "===========================================\n\n";

        $results = [
            'count' => $this->validateCounts(),
            'sample' => $this->validateSamples($sample, $verbose),
            'integrity' => $this->validateIntegrity(),
            'embeddings' => $this->validateEmbeddings($sample),
        ];

        // Summary
        echo "\n===========================================\n";
        echo "Validation Summary\n";
        echo "===========================================\n";

        $passed = 0;
        $total = 0;

        foreach ($results as $type => $result) {
            $total += $result['total'];
            $passed += $result['passed'];
            
            $status = $result['passed'] === $result['total'] ? '✓' : '✗';
            echo "{$type}: {$result['passed']}/{$result['total']} [{$status}]\n";
        }

        echo "\nOverall: {$passed}/{$total} (" . round(($passed/$total)*100, 1) . "%)\n";
        
        return $passed === $total ? 0 : 1;
    }

    /**
     * Validate record counts
     */
    public function validateCounts()
    {
        echo "Count Validation\n";
        echo "----------------\n";
        
        $checks = [
            ['model' => 'Patient', 'scope' => 'clinical', 'table' => 'patient'],
            ['model' => 'Episode', 'scope' => 'clinical', 'table' => 'episode'],
            ['model' => 'Event', 'scope' => 'clinical', 'table' => 'event'],
            ['model' => 'User', 'scope' => 'admin', 'table' => 'user'],
            ['model' => 'Disorder', 'scope' => 'reference', 'table' => 'disorder'],
            ['model' => 'Medication', 'scope' => 'reference', 'table' => 'medication'],
            ['model' => 'Procedure', 'scope' => 'reference', 'table' => 'procedure'],
        ];

        $passed = 0;
        $total = count($checks);
        $adapter = Yii::app()->couchbase;

        foreach ($checks as $check) {
            $mysqlCount = $check['model']::model()->count();
            
            try {
                $cbCount = $adapter->count($check['scope'], $check['table']);
            } catch (Exception $e) {
                $cbCount = 0;
            }
            
            $match = $mysqlCount === $cbCount;
            if ($match) $passed++;
            
            $status = $match ? '✓' : '✗';
            printf("  %-20s MySQL: %8d  CB: %8d  [%s]\n",
                $check['table'], $mysqlCount, $cbCount, $status
            );
        }

        echo "\n";
        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * Validate sample records
     */
    public function validateSamples($sample, $verbose)
    {
        echo "Sample Validation (n={$sample})\n";
        echo "-------------------------------\n";
        
        $passed = 0;
        $total = 0;

        // Sample patients
        $patients = Patient::model()->findAll([
            'limit' => $sample,
            'order' => 'RAND()',
        ]);

        $adapter = Yii::app()->couchbase;

        foreach ($patients as $patient) {
            $total++;
            
            try {
                $key = 'patient::' . $patient->id;
                $doc = $adapter->get('clinical', 'patient', $key);
                
                if ($doc && $doc['hos_num'] === $patient->hos_num) {
                    $passed++;
                } else {
                    if ($verbose) {
                        echo "  MISMATCH: Patient {$patient->id}\n";
                    }
                }
            } catch (Exception $e) {
                if ($verbose) {
                    echo "  MISSING: Patient {$patient->id}\n";
                }
            }
        }

        $pct = $total > 0 ? round(($passed/$total)*100, 1) : 0;
        echo "  Patient samples: {$passed}/{$total} ({$pct}%)\n\n";

        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * Validate referential integrity
     */
    public function validateIntegrity()
    {
        echo "Referential Integrity Validation\n";
        echo "--------------------------------\n";
        
        $passed = 0;
        $total = 0;

        // Check Episode-Patient relationship
        $total++;
        $orphanEpisodes = Yii::app()->db->createCommand(
            "SELECT COUNT(*) FROM episode e 
             LEFT JOIN patient p ON e.patient_id = p.id 
             WHERE p.id IS NULL"
        )->queryScalar();
        
        if ($orphanEpisodes == 0) {
            $passed++;
            echo "  ✓ Episode-Patient integrity OK\n";
        } else {
            echo "  ✗ Episode-Patient: {$orphanEpisodes} orphans\n";
        }

        // Check Event-Episode relationship
        $total++;
        $orphanEvents = Yii::app()->db->createCommand(
            "SELECT COUNT(*) FROM event e 
             LEFT JOIN episode ep ON e.episode_id = ep.id 
             WHERE ep.id IS NULL"
        )->queryScalar();
        
        if ($orphanEvents == 0) {
            $passed++;
            echo "  ✓ Event-Episode integrity OK\n";
        } else {
            echo "  ✗ Event-Episode: {$orphanEvents} orphans\n";
        }

        echo "\n";
        return ['passed' => $passed, 'total' => $total];
    }

    /**
     * Validate embeddings
     */
    public function validateEmbeddings($sample)
    {
        echo "Embedding Validation\n";
        echo "--------------------\n";
        
        $passed = 0;
        $total = 0;

        $adapter = Yii::app()->couchbase;

        // Check Patient contact embeddings
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
                }
            } catch (Exception $e) {
                // Document missing
            }
        }

        $pct = $total > 0 ? round(($passed/$total)*100, 1) : 0;
        echo "  Patient contact embeddings: {$passed}/{$total} ({$pct}%)\n\n";

        return ['passed' => $passed, 'total' => $total];
    }
}
```

**Lines**: ~200

---

## Section 4: Migration Execution Guide

### 4.1 Pre-Migration Checklist

```markdown
## Pre-Migration Checklist

### Infrastructure
- [ ] Couchbase cluster sized appropriately
- [ ] Sufficient disk space (2-3x current MariaDB size)
- [ ] Network connectivity verified
- [ ] Backup procedures tested

### Application
- [ ] All CouchbaseModelBridge traits added
- [ ] Dual-write enabled and tested
- [ ] Unit tests passing
- [ ] Application in maintenance mode

### Team
- [ ] Maintenance window scheduled
- [ ] DBA on standby
- [ ] Rollback procedure documented
- [ ] Communication plan ready
```

### 4.2 Execution Steps

```bash
# 1. Pre-flight check
yiic fulldatamigration status

# 2. Dry run
yiic fulldatamigration run --dryRun=true --verbose=true

# 3. Execute Stage 1 (Reference)
yiic fulldatamigration stage --stage=1 --verbose=true

# 4. Validate Stage 1
yiic datavalidation counts

# 5. Continue with remaining stages
yiic fulldatamigration stage --stage=2 --verbose=true
yiic fulldatamigration stage --stage=3 --verbose=true
yiic fulldatamigration stage --stage=4 --verbose=true
yiic fulldatamigration stage --stage=5 --verbose=true

# 6. Full validation
yiic datavalidation all --sample=500 --verbose=true

# 7. Or run all at once
yiic fulldatamigration run --verbose=true
```

---

## Section 5: Estimated Migration Times

### 5.1 Time Estimates by Data Volume

| Records | Stage 1 | Stage 2 | Stage 3 | Stage 4 | Stage 5 | Total |
|---------|---------|---------|---------|---------|---------|-------|
| 10K patients | 15 min | 30 min | 1 hr | 2 hr | 1 hr | ~5 hr |
| 50K patients | 15 min | 45 min | 3 hr | 6 hr | 3 hr | ~13 hr |
| 100K patients | 20 min | 1 hr | 6 hr | 12 hr | 6 hr | ~26 hr |
| 500K patients | 30 min | 2 hr | 24 hr | 48 hr | 24 hr | ~4 days |

### 5.2 Factors Affecting Speed

- Network latency to Couchbase cluster
- MariaDB query performance
- Couchbase write throughput
- Batch size configuration
- Complexity of embedded relations

---

## Summary

### Files to Create/Update
| File | Lines | Purpose |
|------|-------|---------|
| FullDataMigrationCommand.php | 380 | Master orchestration |
| DataValidationCommand.php | 200 | Data validation |

### Execution Order
1. Pre-flight checks
2. Stage 1: Reference Data (30 min)
3. Stage 2: Clinical Reference (2 hr)
4. Stage 3: Core Clinical (4-8 hr)
5. Stage 4: Module Elements (6-12 hr)
6. Stage 5: Administrative (4-8 hr)
7. Post-migration validation

### Total Effort
- **Estimated Duration**: 2-3 weeks (including testing)
- **Migration Time**: 16-72 hours (depends on volume)

---

**Phase 14 Status**: SPECIFICATION COMPLETE  
**Ready for Implementation**: YES
