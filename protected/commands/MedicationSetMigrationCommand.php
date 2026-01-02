<?php
/**
 * Command to migrate MedicationSet and MedicationSetItem data to Couchbase
 * 
 * Usage:
 *   php protected/yiic medicationSetMigration
 *   php protected/yiic medicationSetMigration --limit=100
 *   php protected/yiic medicationSetMigration --setId=5
 */
class MedicationSetMigrationCommand extends CConsoleCommand
{
    /**
     * @var int Limit number of items to migrate (0 = no limit)
     */
    public $limit = 0;
    
    /**
     * @var int Only migrate items for specific set ID
     */
    public $setId;
    
    /**
     * @var bool Dry run mode - don't actually save to Couchbase
     */
    public $dryRun = false;

    public function getHelp()
    {
        return <<<EOD
USAGE
  php protected/yiic medicationSetMigration [options]

DESCRIPTION
  Migrates MedicationSet and MedicationSetItem records from MariaDB to Couchbase.
  This command enables dual-write functionality for medication set data.

OPTIONS
  --limit=<number>     Limit number of items to migrate (default: 0 = all)
  --setId=<id>         Only migrate items for specific medication set ID
  --dryRun             Run without actually saving to Couchbase

EXAMPLES
  Migrate all medication sets and items:
    php protected/yiic medicationSetMigration

  Migrate first 100 items only:
    php protected/yiic medicationSetMigration --limit=100

  Migrate items for set ID 5 only:
    php protected/yiic medicationSetMigration --setId=5

  Dry run to see what would be migrated:
    php protected/yiic medicationSetMigration --dryRun

EOD;
    }

    /**
     * Migrate medication sets and items to Couchbase
     */
    public function actionIndex()
    {
        $this->stdout("================================================================================\n", Console::FG_CYAN);
        $this->stdout("Migrating Medication Sets to Couchbase\n", Console::FG_CYAN);
        $this->stdout("================================================================================\n\n", Console::FG_CYAN);
        
        if ($this->dryRun) {
            $this->stdout("DRY RUN MODE - No data will be saved\n\n", Console::FG_YELLOW);
        }
        
        // Check if dual-write is enabled
        if (!Yii::app()->params['enable_dual_write']) {
            $this->stdout("WARNING: Dual-write is not enabled in couchbase.php config\n", Console::FG_RED);
            $this->stdout("Data will be migrated but future updates won't sync automatically\n\n", Console::FG_YELLOW);
        }
        
        // Migrate medication sets first
        $this->migrateMedicationSets();
        
        $this->stdout("\n");
        
        // Migrate medication set items
        $this->migrateMedicationSetItems();
        
        $this->stdout("\n");
        $this->stdout("================================================================================\n", Console::FG_GREEN);
        $this->stdout("Migration Complete!\n", Console::FG_GREEN);
        $this->stdout("================================================================================\n", Console::FG_GREEN);
    }

    /**
     * Migrate MedicationSet records
     */
    protected function migrateMedicationSets()
    {
        $this->stdout("Step 1: Migrating Medication Sets\n", Console::FG_CYAN);
        $this->stdout("--------------------------------------------------\n");
        
        $criteria = new CDbCriteria();
        
        // If specific set ID requested, only migrate that one
        if ($this->setId) {
            $criteria->addCondition('id = :setId');
            $criteria->params[':setId'] = $this->setId;
        }
        
        $criteria->with = ['medicationSetRules', 'medicationSetRules.usageCode'];
        
        if ($this->limit > 0) {
            $criteria->limit = $this->limit;
        }
        
        $sets = MedicationSet::model()->findAll($criteria);
        $total = count($sets);
        
        if ($total === 0) {
            $this->stdout("No medication sets found to migrate\n", Console::FG_YELLOW);
            return;
        }
        
        $this->stdout("Found $total medication set(s) to migrate\n\n");
        
        $success = 0;
        $errors = 0;
        
        foreach ($sets as $index => $set) {
            $progress = $index + 1;
            $this->stdout("[$progress/$total] Migrating: {$set->name} (ID: {$set->id})...");
            
            if ($this->dryRun) {
                $this->stdout(" [DRY RUN - SKIPPED]\n", Console::FG_YELLOW);
                continue;
            }
            
            try {
                $set->enableCouchbaseSync();
                if ($set->save(false)) {
                    $this->stdout(" ✓\n", Console::FG_GREEN);
                    $success++;
                } else {
                    $this->stdout(" ✗ Failed to save\n", Console::FG_RED);
                    $errors++;
                }
            } catch (Exception $e) {
                $this->stdout(" ✗ Error: " . $e->getMessage() . "\n", Console::FG_RED);
                $errors++;
            }
        }
        
        $this->stdout("\n");
        $this->stdout("Medication Sets: $success successful, $errors errors\n", $errors > 0 ? Console::FG_YELLOW : Console::FG_GREEN);
    }

    /**
     * Migrate MedicationSetItem records
     */
    protected function migrateMedicationSetItems()
    {
        $this->stdout("Step 2: Migrating Medication Set Items\n", Console::FG_CYAN);
        $this->stdout("--------------------------------------------------\n");
        
        $criteria = new CDbCriteria();
        
        // If specific set ID requested, only migrate items for that set
        if ($this->setId) {
            $criteria->addCondition('medication_set_id = :setId');
            $criteria->params[':setId'] = $this->setId;
        }
        
        // Eager load all relations to embed in Couchbase
        $criteria->with = [
            'medication',
            'medicationSet',
            'defaultRoute',
            'defaultForm',
            'defaultFrequency',
            'defaultDuration'
        ];
        
        if ($this->limit > 0) {
            $criteria->limit = $this->limit;
        }
        
        $items = MedicationSetItem::model()->findAll($criteria);
        $total = count($items);
        
        if ($total === 0) {
            $this->stdout("No medication set items found to migrate\n", Console::FG_YELLOW);
            return;
        }
        
        $this->stdout("Found $total medication set item(s) to migrate\n\n");
        
        $success = 0;
        $errors = 0;
        
        foreach ($items as $index => $item) {
            $progress = $index + 1;
            $medName = $item->medication ? $item->medication->preferred_term : 'Unknown';
            $setName = $item->medicationSet ? $item->medicationSet->name : 'Unknown';
            
            $this->stdout("[$progress/$total] Migrating: $medName → $setName...");
            
            if ($this->dryRun) {
                $this->stdout(" [DRY RUN - SKIPPED]\n", Console::FG_YELLOW);
                continue;
            }
            
            try {
                $item->enableCouchbaseSync();
                if ($item->save(false)) {
                    $this->stdout(" ✓\n", Console::FG_GREEN);
                    $success++;
                } else {
                    $this->stdout(" ✗ Failed to save\n", Console::FG_RED);
                    $errors++;
                }
            } catch (Exception $e) {
                $this->stdout(" ✗ Error: " . $e->getMessage() . "\n", Console::FG_RED);
                $errors++;
            }
            
            // Progress indicator every 100 items
            if ($progress % 100 === 0) {
                $this->stdout("  Progress: $progress/$total items migrated\n", Console::FG_CYAN);
            }
        }
        
        $this->stdout("\n");
        $this->stdout("Medication Set Items: $success successful, $errors errors\n", $errors > 0 ? Console::FG_YELLOW : Console::FG_GREEN);
    }

    /**
     * Helper to output colored text
     */
    protected function stdout($string, $color = null)
    {
        if ($color !== null) {
            echo Console::ansiFormat($string, [$color]);
        } else {
            echo $string;
        }
    }
}
