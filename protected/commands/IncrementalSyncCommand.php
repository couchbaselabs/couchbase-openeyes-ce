<?php
/**
 * Incremental sync of recent changes from MariaDB to Couchbase
 * 
 * Usage:
 *   yiic incrementalsync run                  - Sync since last run
 *   yiic incrementalsync run --since="2024-01-01" - Sync from date
 *   yiic incrementalsync run --tables=patient  - Sync specific tables
 *   yiic incrementalsync daemon --interval=60  - Run continuously
 */

class IncrementalSyncCommand extends CConsoleCommand
{
    private $syncableTables = [
        'patient',
        'episode',
        'event',
        'event_draft',
        'user',
        'firm',
        'site',
    ];
    
    /**
     * @return string Command help text
     */
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic incrementalsync <action> [options]

ACTIONS
  run       - Sync changes since last run
  daemon    - Run continuously with interval
  status    - Show last sync time and pending changes

OPTIONS
  --since=<datetime>  Sync from specific date/time
  --tables=<list>     Comma-separated list of tables to sync
  --interval=<sec>    Daemon mode interval in seconds (default: 60)
  --verbose           Show detailed output

EXAMPLES
  yiic incrementalsync run
  yiic incrementalsync run --since="2024-01-01 00:00:00"
  yiic incrementalsync run --tables=patient,episode
  yiic incrementalsync daemon --interval=30
  yiic incrementalsync status
EOD;
    }
    
    /**
     * Run incremental sync
     */
    public function actionRun($since = null, $tables = null, $verbose = false)
    {
        $since = $since ?: $this->getLastSyncTime();
        $tablesToSync = $tables 
            ? array_map('trim', explode(',', $tables))
            : $this->syncableTables;
        
        echo "=== Incremental Sync ===\n";
        echo "Syncing changes since: {$since}\n\n";
        
        $totalSynced = 0;
        $totalErrors = 0;
        
        foreach ($tablesToSync as $table) {
            list($synced, $errors) = $this->syncTable($table, $since, $verbose);
            $totalSynced += $synced;
            $totalErrors += $errors;
        }
        
        echo "\nTotal: {$totalSynced} synced, {$totalErrors} errors\n";
        
        $this->saveLastSyncTime();
        
        return $totalErrors === 0 ? 0 : 1;
    }
    
    /**
     * Run as daemon with interval
     */
    public function actionDaemon($interval = 60)
    {
        echo "Starting incremental sync daemon (interval: {$interval}s)\n";
        echo "Press Ctrl+C to stop\n\n";
        
        while (true) {
            $this->actionRun(null, null, false);
            echo "Sleeping for {$interval} seconds...\n\n";
            sleep($interval);
        }
    }
    
    /**
     * Show last sync time
     */
    public function actionStatus()
    {
        $lastSync = $this->getLastSyncTime();
        echo "Last sync: {$lastSync}\n\n";
        
        echo "Pending changes:\n";
        foreach ($this->syncableTables as $table) {
            $count = $this->getChangedCount($table, $lastSync);
            echo "  {$table}: {$count} pending\n";
        }
    }
    
    private function syncTable(string $table, string $since, bool $verbose): array
    {
        // Check if table has last_modified_date column
        try {
            $schema = Yii::app()->db->getSchema()->getTable($table);
        } catch (\Exception $e) {
            if ($verbose) {
                echo "{$table}: Skipping (table not found)\n";
            }
            return [0, 0];
        }
        
        if (!$schema || !isset($schema->columns['last_modified_date'])) {
            if ($verbose) {
                echo "{$table}: Skipping (no last_modified_date column)\n";
            }
            return [0, 0];
        }
        
        // Get changed records
        $records = Yii::app()->db->createCommand()
            ->select('*')
            ->from($table)
            ->where('last_modified_date > :since', [':since' => $since])
            ->order('id ASC')
            ->queryAll();
        
        if (empty($records)) {
            echo "{$table}: No changes\n";
            return [0, 0];
        }
        
        $migrator = $this->getMigrator($table);
        $synced = 0;
        $errors = 0;
        
        foreach ($records as $record) {
            try {
                $migrator->migrate($record);
                $synced++;
            } catch (\Exception $e) {
                $errors++;
                if ($verbose) {
                    echo "  Error syncing {$table}#{$record['id']}: {$e->getMessage()}\n";
                }
            }
        }
        
        echo "{$table}: {$synced} synced, {$errors} errors\n";
        return [$synced, $errors];
    }
    
    private function getMigrator(string $table): \OE\Migration\TableMigrator
    {
        $specialMigrators = [
            'patient' => \OE\Migration\PatientMigrator::class,
            'episode' => \OE\Migration\EpisodeMigrator::class,
            'event' => \OE\Migration\EventMigrator::class,
            'event_draft' => \OE\Migration\EventDraftMigrator::class,
        ];
        
        if (isset($specialMigrators[$table])) {
            $class = $specialMigrators[$table];
            return new $class();
        }
        
        return new \OE\Migration\DefaultTableMigrator($table);
    }
    
    private function getChangedCount(string $table, string $since): int
    {
        try {
            $schema = Yii::app()->db->getSchema()->getTable($table);
            if (!$schema || !isset($schema->columns['last_modified_date'])) {
                return 0;
            }
            
            return (int)Yii::app()->db->createCommand()
                ->select('COUNT(*)')
                ->from($table)
                ->where('last_modified_date > :since', [':since' => $since])
                ->queryScalar();
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getLastSyncTime(): string
    {
        $file = Yii::getPathOfAlias('application.runtime') . '/last-sync.txt';
        if (file_exists($file)) {
            return trim(file_get_contents($file));
        }
        return date('Y-m-d H:i:s', strtotime('-1 day'));
    }
    
    private function saveLastSyncTime()
    {
        $file = Yii::getPathOfAlias('application.runtime') . '/last-sync.txt';
        file_put_contents($file, date('Y-m-d H:i:s'));
    }
}
