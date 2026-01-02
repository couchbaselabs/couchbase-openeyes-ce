<?php
/**
 * Full data migration command from MariaDB to Couchbase
 * 
 * Usage:
 *   yiic datamigration run                      - Run full migration
 *   yiic datamigration run --batch=500          - Custom batch size
 *   yiic datamigration run --tables=patient,episode - Specific tables
 *   yiic datamigration run --resume             - Resume from last checkpoint
 *   yiic datamigration status                   - Show migration status
 *   yiic datamigration reset                    - Clear progress files
 */

class DataMigrationCommand extends CConsoleCommand
{
    private $batchSize = 1000;
    private $stats = [];
    private $logFile;
    private $startTime;
    
    /**
     * Migration order (respecting foreign key dependencies)
     */
    private $migrationOrder = [
        // Reference data first
        'institution',
        'site',
        'specialty',
        'subspecialty',
        'firm',
        'event_type',
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
        'event_draft',
    ];
    
    public function init()
    {
        parent::init();
        $this->logFile = Yii::getPathOfAlias('application.runtime') 
            . '/migration-' . date('Y-m-d-His') . '.log';
    }
    
    /**
     * @return string Command help text
     */
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic datamigration <action> [options]

ACTIONS
  run       - Run full data migration
  status    - Show migration progress
  reset     - Clear progress files

OPTIONS
  --batch=<size>    Batch size for processing (default: 1000)
  --tables=<list>   Comma-separated list of tables to migrate
  --resume          Resume from last checkpoint
  --dryRun          Show what would be migrated without migrating
  --confirm         Confirm destructive operations

EXAMPLES
  yiic datamigration run --batch=500
  yiic datamigration run --tables=patient,episode,event
  yiic datamigration run --resume
  yiic datamigration status
  yiic datamigration reset --confirm
EOD;
    }
    
    /**
     * Run full migration
     */
    public function actionRun($batch = 1000, $tables = null, $resume = false, $dryRun = false)
    {
        $this->batchSize = (int)$batch;
        $this->startTime = microtime(true);
        
        $this->log("=== Starting Full Data Migration ===");
        $this->log("Batch size: {$this->batchSize}");
        $this->log("Dry run: " . ($dryRun ? 'yes' : 'no'));
        $this->log("Resume: " . ($resume ? 'yes' : 'no'));
        
        $migrationOrder = $this->migrationOrder;
        
        if ($tables) {
            $requestedTables = array_map('trim', explode(',', $tables));
            $migrationOrder = array_intersect($migrationOrder, $requestedTables);
            
            // Add any requested tables not in the default order
            foreach ($requestedTables as $table) {
                if (!in_array($table, $migrationOrder)) {
                    $migrationOrder[] = $table;
                }
            }
        }
        
        $this->log("Tables to migrate: " . implode(', ', $migrationOrder));
        
        foreach ($migrationOrder as $table) {
            $this->migrateTable($table, $resume, $dryRun);
        }
        
        $this->printSummary();
        return 0;
    }
    
    /**
     * Show migration status
     */
    public function actionStatus()
    {
        echo "=== Migration Status ===\n\n";
        
        $runtimePath = Yii::getPathOfAlias('application.runtime');
        
        foreach ($this->migrationOrder as $table) {
            $progressFile = "{$runtimePath}/migration-progress-{$table}.txt";
            
            if (file_exists($progressFile)) {
                $lastId = file_get_contents($progressFile);
                $mysqlCount = $this->getTableCount($table);
                $progress = $mysqlCount > 0 ? round(($lastId / $mysqlCount) * 100, 1) : 0;
                echo "{$table}: Last ID {$lastId} (~{$progress}% complete)\n";
            } else {
                echo "{$table}: Not started\n";
            }
        }
    }
    
    /**
     * Reset migration progress
     */
    public function actionReset($confirm = false)
    {
        if (!$confirm) {
            echo "This will delete all migration progress files.\n";
            echo "Run with --confirm to proceed.\n";
            return 1;
        }
        
        $runtimePath = Yii::getPathOfAlias('application.runtime');
        $files = glob("{$runtimePath}/migration-progress-*.txt");
        
        foreach ($files as $file) {
            unlink($file);
            echo "Deleted: " . basename($file) . "\n";
        }
        
        echo "Migration progress reset.\n";
        return 0;
    }
    
    /**
     * Migrate a single table
     */
    private function migrateTable(string $table, bool $resume = false, bool $dryRun = false)
    {
        $this->log("\n--- Migrating table: {$table} ---");
        
        // Check if table exists
        if (!$this->tableExists($table)) {
            $this->log("  Skipping: Table does not exist");
            return;
        }
        
        $startId = 0;
        if ($resume) {
            $startId = $this->getLastMigratedId($table);
            if ($startId > 0) {
                $this->log("  Resuming from ID: {$startId}");
            }
        }
        
        $total = 0;
        $errors = 0;
        $migrator = $this->getMigrator($table);
        
        while (true) {
            $records = $this->fetchBatch($table, $startId);
            
            if (empty($records)) {
                break;
            }
            
            foreach ($records as $record) {
                try {
                    if (!$dryRun) {
                        $migrator->migrate($record);
                    }
                    $total++;
                    $startId = $record['id'];
                } catch (\Exception $e) {
                    $errors++;
                    $this->log("  Error migrating {$table}#{$record['id']}: " 
                        . $e->getMessage(), 'error');
                    
                    // Continue on error, don't stop migration
                    $startId = $record['id'];
                }
            }
            
            if (!$dryRun) {
                $this->saveProgress($table, $startId);
            }
            
            if ($total % 1000 === 0) {
                $this->log("  Processed {$total} records...");
            }
        }
        
        $this->stats[$table] = [
            'total' => $total,
            'errors' => $errors,
        ];
        
        $this->log("  Completed: {$total} migrated, {$errors} errors");
    }
    
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
    
    private function getMigrator(string $table): \OE\Migration\TableMigrator
    {
        $specialMigrators = [
            'patient' => \OE\Migration\PatientMigrator::class,
            'episode' => \OE\Migration\EpisodeMigrator::class,
            'event' => \OE\Migration\EventMigrator::class,
        ];
        
        if (isset($specialMigrators[$table])) {
            $class = $specialMigrators[$table];
            return new $class();
        }
        
        return new \OE\Migration\DefaultTableMigrator($table);
    }
    
    private function saveProgress(string $table, int $lastId)
    {
        $file = Yii::getPathOfAlias('application.runtime') 
            . "/migration-progress-{$table}.txt";
        file_put_contents($file, $lastId);
    }
    
    private function getLastMigratedId(string $table): int
    {
        $file = Yii::getPathOfAlias('application.runtime') 
            . "/migration-progress-{$table}.txt";
        if (file_exists($file)) {
            return (int) file_get_contents($file);
        }
        return 0;
    }
    
    private function tableExists(string $table): bool
    {
        try {
            $schema = Yii::app()->db->getSchema()->getTable($table);
            return $schema !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function getTableCount(string $table): int
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
    
    private function log(string $message, string $level = 'info')
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}\n";
        
        echo $line;
        file_put_contents($this->logFile, $line, FILE_APPEND);
    }
    
    private function printSummary()
    {
        $duration = round(microtime(true) - $this->startTime, 2);
        
        $this->log("\n=== Migration Summary ===");
        
        $totalRecords = 0;
        $totalErrors = 0;
        
        foreach ($this->stats as $table => $stats) {
            $this->log("{$table}: {$stats['total']} records, {$stats['errors']} errors");
            $totalRecords += $stats['total'];
            $totalErrors += $stats['errors'];
        }
        
        $this->log("\nTotal: {$totalRecords} records migrated, {$totalErrors} errors");
        $this->log("Duration: {$duration} seconds");
        $this->log("Log file: {$this->logFile}");
    }
}
