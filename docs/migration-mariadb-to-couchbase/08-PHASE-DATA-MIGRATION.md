# Phase 8: Data Migration Scripts

## Overview
This phase creates and executes scripts to migrate all existing data from MariaDB to Couchbase.

## Prerequisites
- Phase 7 completed (Services layer ready)
- All document models validated
- Couchbase infrastructure scaled appropriately

## Dependencies
- Phase 7: Services Layer Migration

## Tasks

### 8.1 Migration Framework

#### 8.1.1 Data Migration Command
**File**: `/protected/commands/DataMigrationCommand.php`

```php
<?php
/**
 * Full data migration command from MariaDB to Couchbase
 */

class DataMigrationCommand extends CConsoleCommand
{
    private $batchSize = 1000;
    private $stats = [];
    private $logFile;
    
    public function init()
    {
        $this->logFile = Yii::getPathOfAlias('application.runtime') 
            . '/migration-' . date('Y-m-d-His') . '.log';
    }
    
    /**
     * Run full migration
     */
    public function actionRun($batch = 1000, $tables = null, $resume = false)
    {
        $this->batchSize = $batch;
        
        $this->log("Starting full data migration");
        $this->log("Batch size: {$batch}");
        
        $migrationOrder = $this->getMigrationOrder();
        
        if ($tables) {
            $migrationOrder = array_intersect(
                $migrationOrder,
                explode(',', $tables)
            );
        }
        
        foreach ($migrationOrder as $table) {
            $this->migrateTable($table, $resume);
        }
        
        $this->printSummary();
    }
    
    /**
     * Get migration order (respecting dependencies)
     */
    private function getMigrationOrder(): array
    {
        return [
            // Reference data first
            'institution',
            'site',
            'specialty',
            'subspecialty',
            'firm',
            'event_type',
            'element_type',
            'ethnic_group',
            'gender',
            'country',
            
            // Core entities
            'user',
            'contact',
            'address',
            'patient',
            
            // Clinical relationships
            'episode',
            'event',
            
            // Module data (handled separately)
        ];
    }
    
    /**
     * Migrate a single table
     */
    private function migrateTable(string $table, bool $resume = false)
    {
        $this->log("Migrating table: {$table}");
        
        $startId = 0;
        if ($resume) {
            $startId = $this->getLastMigratedId($table);
            $this->log("  Resuming from ID: {$startId}");
        }
        
        $total = 0;
        $errors = 0;
        
        // Get migrator for this table
        $migrator = $this->getMigrator($table);
        
        while (true) {
            $records = $this->fetchBatch($table, $startId);
            
            if (empty($records)) {
                break;
            }
            
            foreach ($records as $record) {
                try {
                    $migrator->migrate($record);
                    $total++;
                    $startId = $record['id'];
                } catch (\Exception $e) {
                    $errors++;
                    $this->log("  Error migrating {$table}#{$record['id']}: " 
                        . $e->getMessage(), 'error');
                }
            }
            
            $this->saveProgress($table, $startId);
            $this->log("  Processed {$total} records...");
        }
        
        $this->stats[$table] = [
            'total' => $total,
            'errors' => $errors,
        ];
        
        $this->log("Completed {$table}: {$total} migrated, {$errors} errors");
    }
    
    /**
     * Fetch a batch of records
     */
    private function fetchBatch(string $table, int $afterId): array
    {
        return Yii::app()->db->createCommand()
            ->select('*')
            ->from($table)
            ->where('id > :id', [':id' => $afterId])
            ->order('id ASC')
            ->limit($this->batchSize)
            ->queryAll();
    }
    
    /**
     * Get migrator for table
     */
    private function getMigrator(string $table): TableMigrator
    {
        $class = 'OE\\Migration\\' . ucfirst($table) . 'Migrator';
        if (class_exists($class)) {
            return new $class();
        }
        return new DefaultTableMigrator($table);
    }
    
    /**
     * Save migration progress
     */
    private function saveProgress(string $table, int $lastId)
    {
        $file = Yii::getPathOfAlias('application.runtime') 
            . "/migration-progress-{$table}.txt";
        file_put_contents($file, $lastId);
    }
    
    /**
     * Get last migrated ID for resume
     */
    private function getLastMigratedId(string $table): int
    {
        $file = Yii::getPathOfAlias('application.runtime') 
            . "/migration-progress-{$table}.txt";
        if (file_exists($file)) {
            return (int) file_get_contents($file);
        }
        return 0;
    }
    
    /**
     * Log message
     */
    private function log(string $message, string $level = 'info')
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}\n";
        
        echo $line;
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }
    
    /**
     * Print migration summary
     */
    private function printSummary()
    {
        $this->log("\n=== Migration Summary ===");
        
        $totalRecords = 0;
        $totalErrors = 0;
        
        foreach ($this->stats as $table => $stats) {
            $this->log("{$table}: {$stats['total']} records, {$stats['errors']} errors");
            $totalRecords += $stats['total'];
            $totalErrors += $stats['errors'];
        }
        
        $this->log("\nTotal: {$totalRecords} records migrated, {$totalErrors} errors");
        $this->log("Log file: {$this->logFile}");
    }
}
```

#### 8.1.2 Table Migrator Interface
**File**: `/protected/components/migration/TableMigrator.php`

```php
<?php

namespace OE\Migration;

interface TableMigrator
{
    /**
     * Migrate a single record
     */
    public function migrate(array $record): void;
}
```

#### 8.1.3 Default Table Migrator
**File**: `/protected/components/migration/DefaultTableMigrator.php`

```php
<?php

namespace OE\Migration;

use OE\Database\DatabaseAdapterFactory;
use OE\Database\Transformers\TypeTransformer;

class DefaultTableMigrator implements TableMigrator
{
    private $table;
    private $adapter;
    private $scope;
    private $collection;
    
    public function __construct(string $table)
    {
        $this->table = $table;
        $this->adapter = DatabaseAdapterFactory::getAdapter(
            DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
        
        // Determine scope and collection from config
        $collectionMap = require(\Yii::getPathOfAlias('application.config') 
            . '/couchbase-collection-map.php');
        
        foreach ($collectionMap as $scope => $collections) {
            if (isset($collections[$table])) {
                $this->scope = $scope;
                $this->collection = $table;
                break;
            }
        }
        
        if (!$this->scope) {
            $this->scope = '_default';
            $this->collection = $table;
        }
    }
    
    public function migrate(array $record): void
    {
        // Get column types from MySQL
        $schema = \Yii::app()->db->getSchema()->getTable($this->table);
        
        // Transform data
        $doc = [];
        foreach ($record as $column => $value) {
            if (isset($schema->columns[$column])) {
                $doc[$column] = TypeTransformer::transform(
                    $value,
                    $schema->columns[$column]->dbType
                );
            } else {
                $doc[$column] = $value;
            }
        }
        
        // Add metadata
        $doc['_type'] = $this->table;
        $doc['_migrated'] = date('c');
        $doc['_source'] = 'mariadb';
        
        // Insert or update in Couchbase
        $this->adapter->insert($this->collection, $doc);
    }
}
```

### 8.2 Specialized Migrators

#### 8.2.1 Patient Migrator
**File**: `/protected/components/migration/PatientMigrator.php`

```php
<?php

namespace OE\Migration;

class PatientMigrator implements TableMigrator
{
    private $adapter;
    
    public function __construct()
    {
        $this->adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
    }
    
    public function migrate(array $record): void
    {
        // Load full patient model for relationship data
        $patient = \Patient::model()->findByPk($record['id']);
        
        if (!$patient) {
            throw new \Exception("Patient not found: {$record['id']}");
        }
        
        // Use model's Couchbase conversion
        $doc = $patient->toCouchbaseDocument();
        $doc['_migrated'] = date('c');
        $doc['_source'] = 'mariadb';
        
        $this->adapter->insert('patient', $doc);
    }
}
```

#### 8.2.2 Event Migrator
**File**: `/protected/components/migration/EventMigrator.php`

```php
<?php

namespace OE\Migration;

class EventMigrator implements TableMigrator
{
    private $adapter;
    
    public function __construct()
    {
        $this->adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
    }
    
    public function migrate(array $record): void
    {
        $event = \Event::model()->findByPk($record['id']);
        
        if (!$event) {
            throw new \Exception("Event not found: {$record['id']}");
        }
        
        $doc = $event->toCouchbaseDocument();
        $doc['_migrated'] = date('c');
        $doc['_source'] = 'mariadb';
        
        $this->adapter->insert('event', $doc);
    }
}
```

### 8.3 Validation Command

#### 8.3.1 Data Validation Command
**File**: `/protected/commands/DataValidationCommand.php`

```php
<?php
/**
 * Validate migrated data integrity
 */

class DataValidationCommand extends CConsoleCommand
{
    /**
     * Validate all tables
     */
    public function actionRun($tables = null)
    {
        $tablesToValidate = $tables 
            ? explode(',', $tables)
            : $this->getAllTables();
        
        $results = [];
        
        foreach ($tablesToValidate as $table) {
            $results[$table] = $this->validateTable($table);
        }
        
        $this->printResults($results);
    }
    
    /**
     * Validate a single table
     */
    private function validateTable(string $table): array
    {
        echo "Validating {$table}...\n";
        
        // Get counts
        $mysqlCount = Yii::app()->db->createCommand()
            ->select('COUNT(*)')
            ->from($table)
            ->queryScalar();
        
        $cbAdapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
        $cbCount = $cbAdapter->count($table);
        
        // Sample validation
        $sampleSize = min(100, $mysqlCount);
        $mismatches = 0;
        
        if ($sampleSize > 0) {
            $samples = Yii::app()->db->createCommand()
                ->select('id')
                ->from($table)
                ->order('RAND()')
                ->limit($sampleSize)
                ->queryColumn();
            
            foreach ($samples as $id) {
                $mysqlRecord = Yii::app()->db->createCommand()
                    ->select('*')
                    ->from($table)
                    ->where('id = :id', [':id' => $id])
                    ->queryRow();
                
                $cbRecord = $cbAdapter->findByPk($table, $id);
                
                if (!$cbRecord) {
                    $mismatches++;
                    continue;
                }
                
                // Compare key fields
                if (!$this->recordsMatch($mysqlRecord, $cbRecord)) {
                    $mismatches++;
                }
            }
        }
        
        return [
            'mysql_count' => $mysqlCount,
            'couchbase_count' => $cbCount,
            'count_match' => $mysqlCount === $cbCount,
            'sample_size' => $sampleSize,
            'sample_mismatches' => $mismatches,
            'integrity_score' => $sampleSize > 0 
                ? round((($sampleSize - $mismatches) / $sampleSize) * 100, 2)
                : 100,
        ];
    }
    
    /**
     * Compare records (simplified)
     */
    private function recordsMatch(array $mysql, array $cb): bool
    {
        // Compare essential fields (excluding timestamps and metadata)
        $excludeFields = [
            'last_modified_date', 'last_modified_user_id',
            '_type', '_migrated', '_source', '_created', '_modified'
        ];
        
        foreach ($mysql as $key => $value) {
            if (in_array($key, $excludeFields)) {
                continue;
            }
            
            $cbValue = $cb[$key] ?? null;
            
            // Type-aware comparison
            if (is_numeric($value) && is_numeric($cbValue)) {
                if ((float)$value !== (float)$cbValue) {
                    return false;
                }
            } elseif ($value !== $cbValue) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Print validation results
     */
    private function printResults(array $results)
    {
        echo "\n=== Validation Results ===\n\n";
        
        $format = "%-30s %10s %10s %8s %10s\n";
        printf($format, 'Table', 'MySQL', 'Couchbase', 'Match', 'Integrity');
        printf($format, str_repeat('-', 30), str_repeat('-', 10), 
            str_repeat('-', 10), str_repeat('-', 8), str_repeat('-', 10));
        
        foreach ($results as $table => $r) {
            $match = $r['count_match'] ? '✓' : '✗';
            printf($format, 
                $table,
                $r['mysql_count'],
                $r['couchbase_count'],
                $match,
                $r['integrity_score'] . '%'
            );
        }
    }
    
    private function getAllTables(): array
    {
        return Yii::app()->db->createCommand("SHOW TABLES")->queryColumn();
    }
}
```

### 8.4 Incremental Sync

#### 8.4.1 Incremental Sync Command
**File**: `/protected/commands/IncrementalSyncCommand.php`

```php
<?php
/**
 * Sync recent changes from MariaDB to Couchbase
 */

class IncrementalSyncCommand extends CConsoleCommand
{
    /**
     * Sync changes since last run
     */
    public function actionRun($since = null, $tables = null)
    {
        $since = $since ?: $this->getLastSyncTime();
        $tables = $tables ? explode(',', $tables) : $this->getSyncableTables();
        
        echo "Syncing changes since: {$since}\n\n";
        
        foreach ($tables as $table) {
            $this->syncTable($table, $since);
        }
        
        $this->saveLastSyncTime();
    }
    
    /**
     * Sync a table
     */
    private function syncTable(string $table, string $since)
    {
        // Get changed records
        $records = Yii::app()->db->createCommand()
            ->select('*')
            ->from($table)
            ->where('last_modified_date > :since', [':since' => $since])
            ->queryAll();
        
        if (empty($records)) {
            echo "{$table}: No changes\n";
            return;
        }
        
        $migrator = $this->getMigrator($table);
        $count = 0;
        $errors = 0;
        
        foreach ($records as $record) {
            try {
                $migrator->migrate($record);
                $count++;
            } catch (\Exception $e) {
                $errors++;
                echo "  Error: {$e->getMessage()}\n";
            }
        }
        
        echo "{$table}: {$count} synced, {$errors} errors\n";
    }
    
    private function getMigrator(string $table)
    {
        $class = 'OE\\Migration\\' . ucfirst($table) . 'Migrator';
        if (class_exists($class)) {
            return new $class();
        }
        return new \OE\Migration\DefaultTableMigrator($table);
    }
    
    private function getLastSyncTime(): string
    {
        $file = Yii::getPathOfAlias('application.runtime') . '/last-sync.txt';
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return date('Y-m-d H:i:s', strtotime('-1 day'));
    }
    
    private function saveLastSyncTime()
    {
        $file = Yii::getPathOfAlias('application.runtime') . '/last-sync.txt';
        file_put_contents($file, date('Y-m-d H:i:s'));
    }
    
    private function getSyncableTables(): array
    {
        return ['patient', 'episode', 'event', 'user', 'firm', 'site'];
    }
}
```

### 8.5 Migration Scripts

#### 8.5.1 Full Migration Script
**File**: `/protected/scripts/couchbase/run-full-migration.sh`

```bash
#!/bin/bash
# Full data migration from MariaDB to Couchbase

set -e

echo "=== OpenEyes Data Migration ==="
echo "Starting at: $(date)"

# Create indexes first
echo "Creating indexes..."
php protected/yiic couchbase createIndexes

# Run migration
echo "Running data migration..."
php protected/yiic datamigration run --batch=1000

# Validate
echo "Validating migration..."
php protected/yiic datavalidation run

echo "Migration completed at: $(date)"
```

## Testing Criteria

### Migration Tests
- [ ] All tables migrate successfully
- [ ] Resume functionality works
- [ ] Error handling appropriate

### Validation Tests
- [ ] Count validation passes
- [ ] Sample integrity passes
- [ ] Data types preserved

### Performance Tests
- [ ] Migration rate acceptable (>1000 records/minute)
- [ ] No memory issues with large batches

## Acceptance Criteria
- [ ] All data migrated successfully
- [ ] Validation passes with >99% integrity
- [ ] Incremental sync works
- [ ] Documentation complete

## Rollback Plan
1. Couchbase data can be deleted
2. MariaDB remains untouched
3. Application continues using MariaDB

## Definition of Done
- [ ] Full migration completed
- [ ] Validation passes
- [ ] Incremental sync operational
- [ ] Runbook documented

---

*Phase 8 Completion Sign-off:*
- [ ] Technical Lead
- [ ] DBA
- [ ] QA

*Estimated Duration: 2-3 weeks*
