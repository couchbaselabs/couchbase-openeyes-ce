<?php
/**
 * Unified sync command for all module data to Couchbase
 */

// Autoload module document classes
spl_autoload_register(function($class) {
    $paths = [
        'ExaminationDocument' => '/var/www/openeyes/protected/modules/OphCiExamination/models/couchbase/ExaminationDocument.php',
        'OperationDocument' => '/var/www/openeyes/protected/modules/OphTrOperationbooking/models/couchbase/OperationDocument.php',
        'LetterDocument' => '/var/www/openeyes/protected/modules/OphCoCorrespondence/models/couchbase/LetterDocument.php',
    ];
    
    if (isset($paths[$class])) {
        require_once $paths[$class];
    }
});

class CouchbaseModuleSyncCommand extends CConsoleCommand
{
    /**
     * Module configurations
     */
    private $modules = [
        'OphCiExamination' => [
            'document_class' => '\\OEModule\\OphCiExamination\\models\\couchbase\\ExaminationDocument',
            'source_model' => 'Event',
            'event_type_class' => 'OphCiExamination',
            'create_method' => 'createFromEvent',
        ],
        'OphTrOperationbooking' => [
            'document_class' => '\\OEModule\\OphTrOperationbooking\\models\\couchbase\\OperationDocument',
            'source_model' => 'Element_OphTrOperationbooking_Operation',
            'create_method' => 'createFromElement',
        ],
        'OphCoCorrespondence' => [
            'document_class' => '\\OEModule\\OphCoCorrespondence\\models\\couchbase\\LetterDocument',
            'source_model' => 'ElementLetter',
            'create_method' => 'createFromElement',
        ],
    ];
    
    /**
     * Sync all modules or specific module
     * @param string $module Module name (optional)
     * @param int $batch Batch size
     * @param int $from Starting ID
     * @param int $to Ending ID
     * @param bool $verbose Verbose output
     */
    public function actionSync($module = null, $batch = 500, $from = null, $to = null, $verbose = false)
    {
        $modulesToSync = $module ? [$module] : array_keys($this->modules);
        
        foreach ($modulesToSync as $moduleName) {
            if (!isset($this->modules[$moduleName])) {
                echo "ERROR: Unknown module: {$moduleName}\n";
                continue;
            }
            
            echo "\n" . str_repeat('=', 70) . "\n";
            echo "Syncing module: {$moduleName}\n";
            echo str_repeat('=', 70) . "\n\n";
            
            $result = $this->syncModule($moduleName, $batch, $from, $to, $verbose);
            
            echo "\nModule {$moduleName}: ";
            echo "{$result['synced']} synced, {$result['errors']} errors\n";
        }
    }
    
    /**
     * Sync single module
     * @param string $moduleName
     * @param int $batchSize
     * @param int $fromId
     * @param int $toId
     * @param bool $verbose
     * @return array Stats
     */
    private function syncModule($moduleName, $batchSize, $fromId, $toId, $verbose)
    {
        $config = $this->modules[$moduleName];
        $docClass = $config['document_class'];
        $sourceModel = $config['source_model'];
        $createMethod = $config['create_method'];
        
        $criteria = new CDbCriteria();
        
        // Special handling for Event-based modules (OphCiExamination)
        if ($sourceModel === 'Event' && isset($config['event_type_class'])) {
            $eventType = EventType::model()->findByAttributes([
                'class_name' => $config['event_type_class']
            ]);
            
            if (!$eventType) {
                echo "ERROR: Event type {$config['event_type_class']} not found\n";
                return ['synced' => 0, 'errors' => 0];
            }
            
            $criteria->addCondition('event_type_id = :etid');
            $criteria->params[':etid'] = $eventType->id;
            $criteria->with = ['episode', 'episode.patient'];
        }
        
        $criteria->order = 't.id ASC';
        
        if ($fromId) {
            $criteria->addCondition('t.id >= :from');
            $criteria->params[':from'] = $fromId;
        }
        
        if ($toId) {
            $criteria->addCondition('t.id <= :to');
            $criteria->params[':to'] = $toId;
        }
        
        $total = $sourceModel::model()->count($criteria);
        echo "Found {$total} records to sync\n";
        
        $synced = 0;
        $errors = 0;
        $lastId = $fromId ?? 0;
        
        while (true) {
            $tempCriteria = clone $criteria;
            $tempCriteria->addCondition('t.id > :lastId');
            $tempCriteria->params[':lastId'] = $lastId;
            $tempCriteria->limit = $batchSize;
            
            $records = $sourceModel::model()->findAll($tempCriteria);
            
            if (empty($records)) {
                break;
            }
            
            foreach ($records as $record) {
                $lastId = $record->id;
                
                try {
                    $doc = call_user_func([$docClass, $createMethod], $record);
                    $doc->save(false);
                    $synced++;
                    
                    if ($verbose && $synced % 100 === 0) {
                        echo "  Progress: {$synced}/{$total} (" . 
                             round(($synced/$total)*100, 1) . "%)\n";
                    }
                } catch (Exception $e) {
                    echo "  ERROR syncing #{$record->id}: {$e->getMessage()}\n";
                    if ($verbose) {
                        echo "  Trace: " . $e->getTraceAsString() . "\n";
                    }
                    $errors++;
                }
            }
        }
        
        return ['synced' => $synced, 'errors' => $errors];
    }
    
    /**
     * Verify sync counts for all or specific module
     * @param string $module Module name (optional)
     */
    public function actionVerify($module = null)
    {
        $modulesToVerify = $module ? [$module] : array_keys($this->modules);
        
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "SYNC VERIFICATION\n";
        echo str_repeat('=', 70) . "\n\n";
        
        $allMatch = true;
        
        foreach ($modulesToVerify as $moduleName) {
            $match = $this->verifyModule($moduleName);
            if (!$match) {
                $allMatch = false;
            }
        }
        
        echo "\n" . str_repeat('=', 70) . "\n";
        echo $allMatch ? "✓ All modules verified successfully\n" : "✗ Some modules have mismatches\n";
        echo str_repeat('=', 70) . "\n";
        
        return $allMatch ? 0 : 1;
    }
    
    /**
     * Verify single module
     * @param string $moduleName
     * @return bool Match status
     */
    private function verifyModule($moduleName)
    {
        echo "Verifying {$moduleName}...\n";
        
        $config = $this->modules[$moduleName];
        $sourceModel = $config['source_model'];
        $docClass = $config['document_class'];
        
        // Get MySQL count
        $criteria = new CDbCriteria();
        if ($sourceModel === 'Event' && isset($config['event_type_class'])) {
            $eventType = EventType::model()->findByAttributes([
                'class_name' => $config['event_type_class']
            ]);
            if ($eventType) {
                $criteria->addCondition('event_type_id = :etid');
                $criteria->params[':etid'] = $eventType->id;
            }
        }
        $mysqlCount = $sourceModel::model()->count($criteria);
        
        // Get Couchbase count
        $doc = new $docClass();
        $conn = Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        $collection = $doc->collectionName();
        $scope = $doc->scope();
        
        $query = "SELECT COUNT(*) as count FROM `{$bucket}`.`{$scope}`.`{$collection}`";
        $result = $conn->query($query);
        $cbCount = 0;
        foreach ($result as $row) {
            $cbCount = $row['count'];
            break;
        }
        
        $match = $mysqlCount === $cbCount;
        $symbol = $match ? '✓' : '✗';
        echo "  MySQL: {$mysqlCount}, Couchbase: {$cbCount} {$symbol}\n";
        
        return $match;
    }
}
