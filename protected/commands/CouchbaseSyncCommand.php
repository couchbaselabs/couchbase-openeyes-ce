<?php
/**
 * Command to sync data from MariaDB to Couchbase
 * 
 * Usage:
 *   yiic couchbasesync all                    - Sync all supported models
 *   yiic couchbasesync model --model=Patient  - Sync specific model
 *   yiic couchbasesync verify                 - Verify sync integrity
 *   yiic couchbasesync count                  - Show record counts
 */

class CouchbaseSyncCommand extends CConsoleCommand
{
    /**
     * Models to sync in order (respects foreign key dependencies)
     */
    private $syncOrder = [
        'Institution',
        'Site',
        'Firm',
        'User',
        'Patient',
        'Episode',
        'Event',
    ];
    
    /**
     * @return string Command help text
     */
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic couchbasesync <action> [options]

ACTIONS
  all       - Sync all supported models
  model     - Sync a specific model
  verify    - Verify sync integrity (count comparison)
  count     - Show record counts in both databases
  compare   - Compare specific records between databases
  clean     - Remove orphaned documents from Couchbase

OPTIONS
  --model=<name>    Model class name for single model sync
  --batch=<size>    Batch size for processing (default: 1000)
  --from=<id>       Start from specific ID
  --to=<id>         End at specific ID
  --dry-run         Show what would be synced without syncing
  --verbose         Show detailed progress
  --force           Force sync even if counts match

EXAMPLES
  yiic couchbasesync all --batch=500
  yiic couchbasesync model --model=Patient --from=1000 --to=2000
  yiic couchbasesync verify --verbose
  yiic couchbasesync count
EOD;
    }
    
    /**
     * Sync all models
     * @param int $batch Batch size
     * @param bool $dryRun Dry run mode
     * @param bool $verbose Verbose output
     */
    public function actionAll($batch = 1000, $dryRun = false, $verbose = false)
    {
        echo "=== Syncing all models to Couchbase ===\n\n";
        
        $startTime = microtime(true);
        $totalSynced = 0;
        $totalErrors = 0;
        
        foreach ($this->syncOrder as $model) {
            list($synced, $errors) = $this->syncModel($model, $batch, null, null, $dryRun, $verbose);
            $totalSynced += $synced;
            $totalErrors += $errors;
        }
        
        $duration = round(microtime(true) - $startTime, 2);
        
        echo "\n=== Sync Complete ===\n";
        echo "Total synced: {$totalSynced}\n";
        echo "Total errors: {$totalErrors}\n";
        echo "Duration: {$duration} seconds\n";
    }
    
    /**
     * Sync a specific model
     * @param string $model Model class name
     * @param int $batch Batch size
     * @param int $from Start ID
     * @param int $to End ID
     * @param bool $dryRun Dry run mode
     * @param bool $verbose Verbose output
     */
    public function actionModel($model = null, $batch = 1000, $from = null, $to = null, $dryRun = false, $verbose = false)
    {
        if (!$model) {
            echo "Error: --model parameter required\n";
            echo "Available models: " . implode(', ', $this->syncOrder) . "\n";
            return 1;
        }
        
        if (!class_exists($model)) {
            echo "Error: Model class '{$model}' not found\n";
            return 1;
        }
        
        $this->syncModel($model, $batch, $from, $to, $dryRun, $verbose);
    }
    
    /**
     * Verify sync integrity
     * @param string $model Specific model to verify (optional)
     * @param bool $verbose Show detailed output
     */
    public function actionVerify($model = null, $verbose = false)
    {
        $models = $model ? [$model] : $this->syncOrder;
        
        echo "=== Verifying Sync Integrity ===\n\n";
        
        $allMatch = true;
        
        foreach ($models as $modelName) {
            $result = $this->verifyModel($modelName, $verbose);
            if (!$result) {
                $allMatch = false;
            }
        }
        
        echo "\n";
        if ($allMatch) {
            echo "✓ All models are in sync\n";
        } else {
            echo "✗ Some models have mismatches\n";
        }
    }
    
    /**
     * Show record counts
     */
    public function actionCount()
    {
        echo "=== Record Counts ===\n\n";
        echo str_pad("Model", 20) . str_pad("MariaDB", 12) . str_pad("Couchbase", 12) . "Status\n";
        echo str_repeat("-", 56) . "\n";
        
        foreach ($this->syncOrder as $model) {
            $this->showCounts($model);
        }
    }
    
    /**
     * Compare specific record
     * @param string $model Model name
     * @param int $id Record ID
     */
    public function actionCompare($model = null, $id = null)
    {
        if (!$model || !$id) {
            echo "Error: --model and --id parameters required\n";
            return 1;
        }
        
        $modelClass = $model;
        if (!class_exists($modelClass)) {
            echo "Error: Model class '{$modelClass}' not found\n";
            return 1;
        }
        
        $record = $modelClass::model()->findByPk($id);
        if (!$record) {
            echo "Error: Record not found in MariaDB\n";
            return 1;
        }
        
        if (!method_exists($record, 'compareWithCouchbase')) {
            echo "Error: Model does not support Couchbase comparison\n";
            return 1;
        }
        
        $result = $record->compareWithCouchbase();
        
        echo "=== Comparison: {$model} #{$id} ===\n\n";
        echo "Status: {$result['status']}\n";
        
        if ($result['status'] === 'mismatch' && isset($result['differences'])) {
            echo "\nDifferences:\n";
            foreach ($result['differences'] as $field => $values) {
                echo "  {$field}:\n";
                echo "    MariaDB:   " . json_encode($values['mysql']) . "\n";
                echo "    Couchbase: " . json_encode($values['couchbase']) . "\n";
            }
        } elseif (isset($result['message'])) {
            echo "Message: {$result['message']}\n";
        }
    }
    
    /**
     * Sync a single model
     * @return array [synced, errors]
     */
    private function syncModel($modelName, $batchSize, $fromId, $toId, $dryRun, $verbose)
    {
        echo "Syncing {$modelName}...\n";
        
        $modelClass = $modelName;
        if (!class_exists($modelClass)) {
            echo "  Error: Model class not found\n\n";
            return [0, 1];
        }
        
        $model = new $modelClass();
        
        if (!method_exists($model, 'toCouchbaseDocument')) {
            echo "  Skipping: Model does not support Couchbase bridge\n\n";
            return [0, 0];
        }
        
        $criteria = new CDbCriteria();
        $criteria->order = 'id ASC';
        $criteria->limit = $batchSize;
        
        if ($fromId) {
            $criteria->addCondition('id >= :fromId');
            $criteria->params[':fromId'] = $fromId;
        }
        if ($toId) {
            $criteria->addCondition('id <= :toId');
            $criteria->params[':toId'] = $toId;
        }
        
        $total = 0;
        $errors = 0;
        $lastId = $fromId ? $fromId - 1 : 0;
        
        try {
            $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
                \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
        } catch (\Exception $e) {
            echo "  Error: Could not get Couchbase adapter: {$e->getMessage()}\n\n";
            return [0, 1];
        }
        
        while (true) {
            // Update criteria for pagination
            $criteria->condition = ''; // Reset condition
            $criteria->params = [];
            $criteria->addCondition('id > :lastId');
            $criteria->params[':lastId'] = $lastId;
            
            if ($fromId) {
                $criteria->addCondition('id >= :fromId');
                $criteria->params[':fromId'] = $fromId;
            }
            if ($toId) {
                $criteria->addCondition('id <= :toId');
                $criteria->params[':toId'] = $toId;
            }
            
            $records = $modelClass::model()->findAll($criteria);
            
            if (empty($records)) {
                break;
            }
            
            foreach ($records as $record) {
                $lastId = $record->id;
                
                if ($dryRun) {
                    if ($verbose) {
                        echo "  Would sync {$modelName} #{$record->id}\n";
                    }
                    $total++;
                    continue;
                }
                
                try {
                    $doc = $record->toCouchbaseDocument();
                    $collection = $model->couchbaseCollection();
                    
                    // Use upsert to handle both insert and update
                    if ($adapter->exists($collection, $record->id)) {
                        $adapter->update($collection, $record->id, $doc);
                    } else {
                        $adapter->insert($collection, $doc);
                    }
                    
                    $total++;
                    
                    if ($verbose && $total % 100 === 0) {
                        echo "  Progress: {$total} records synced\n";
                    }
                } catch (\Exception $e) {
                    if ($verbose) {
                        echo "  Error syncing #{$record->id}: {$e->getMessage()}\n";
                    }
                    $errors++;
                }
            }
            
            // Clear entity cache to prevent memory issues
            $modelClass::model()->resetScope();
        }
        
        $status = $errors > 0 ? "({$errors} errors)" : "";
        echo "  Completed: {$total} records synced {$status}\n\n";
        
        return [$total, $errors];
    }
    
    /**
     * Verify a single model
     * @return bool True if counts match
     */
    private function verifyModel($modelName, $verbose)
    {
        $modelClass = $modelName;
        if (!class_exists($modelClass)) {
            return false;
        }
        
        try {
            $mysqlCount = $modelClass::model()->count();
        } catch (\Exception $e) {
            echo "{$modelName}: Error counting MariaDB records\n";
            return false;
        }
        
        try {
            $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
                \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
            
            $model = new $modelClass();
            if (!method_exists($model, 'couchbaseCollection')) {
                echo "{$modelName}: Not Couchbase-enabled\n";
                return true;
            }
            
            $cbCount = $adapter->count($model->couchbaseCollection());
        } catch (\Exception $e) {
            echo "{$modelName}: Error counting Couchbase records\n";
            return false;
        }
        
        $match = ($mysqlCount === $cbCount);
        $symbol = $match ? '✓' : '✗';
        $diff = $cbCount - $mysqlCount;
        $diffStr = $diff >= 0 ? "+{$diff}" : "{$diff}";
        
        echo "{$modelName}: MySQL={$mysqlCount}, Couchbase={$cbCount} ({$diffStr}) {$symbol}\n";
        
        return $match;
    }
    
    /**
     * Show counts for a model
     */
    private function showCounts($modelName)
    {
        $modelClass = $modelName;
        
        try {
            $mysqlCount = class_exists($modelClass) ? $modelClass::model()->count() : 0;
        } catch (\Exception $e) {
            $mysqlCount = 'Error';
        }
        
        try {
            $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
                \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
            $model = new $modelClass();
            $cbCount = method_exists($model, 'couchbaseCollection') 
                ? $adapter->count($model->couchbaseCollection()) 
                : 'N/A';
        } catch (\Exception $e) {
            $cbCount = 'Error';
        }
        
        $status = ($mysqlCount === $cbCount) ? '✓' : '✗';
        echo str_pad($modelName, 20) . str_pad($mysqlCount, 12) . str_pad($cbCount, 12) . $status . "\n";
    }
}
