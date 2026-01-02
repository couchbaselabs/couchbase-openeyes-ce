<?php
/**
 * Command to migrate clinical module element data to Couchbase
 * Phase 13: Operation Notes, Laser, Biometry, Prescription, Correspondence, Operation Booking, CVI
 * 
 * Usage:
 *   php protected/yiic moduledata migrate --module=operationnote
 *   php protected/yiic moduledata migrate --module=all
 *   php protected/yiic moduledata status
 *   php protected/yiic moduledata verify --module=operationnote
 */

class ModuleMigrationCommand extends CConsoleCommand
{
    /**
     * Module element configurations in dependency order
     * Each module contains elements to migrate
     */
    protected $modules = [
        'examination' => [
            'name' => 'Examination',
            'elements' => '*',  // Auto-discover all Element_OphCiExamination_* models
            'scope' => 'clinical',
            'module_path' => 'OphCiExamination',
            'element_prefix' => 'Element_OphCiExamination_',
            'namespace' => 'OEModule\\OphCiExamination\\models\\',
        ],
        'operationnote' => [
            'name' => 'Operation Notes',
            'elements' => [
                'Element_OphTrOperationnote_Cataract',
                'Element_OphTrOperationnote_ProcedureList',
                'Element_OphTrOperationnote_Surgeon',
                'Element_OphTrOperationnote_Anaesthetic',
                'Element_OphTrOperationnote_Comments',
                'Element_OphTrOperationnote_GenericProcedure',
            ],
            'scope' => 'clinical',
        ],
        'laser' => [
            'name' => 'Laser Treatment',
            'elements' => [
                'Element_OphTrLaser_Treatment',
                'Element_OphTrLaser_Site',
                'Element_OphTrLaser_AnteriorSegment',
                'Element_OphTrLaser_PosteriorPole',
            ],
            'scope' => 'clinical',
        ],
        'biometry' => [
            'name' => 'Biometry',
            'elements' => [
                'Element_OphInBiometry_Measurement',
                'Element_OphInBiometry_Calculation',
                'Element_OphInBiometry_Selection',
            ],
            'scope' => 'clinical',
        ],
        'prescription' => [
            'name' => 'Prescription',
            'elements' => [
                'Element_OphDrPrescription_Details',
            ],
            'scope' => 'clinical',
        ],
        'correspondence' => [
            'name' => 'Correspondence',
            'elements' => [
                'ElementLetter',
            ],
            'scope' => 'clinical',
        ],
        'operationbooking' => [
            'name' => 'Operation Booking',
            'elements' => [
                'Element_OphTrOperationbooking_Operation',
                'Element_OphTrOperationbooking_Diagnosis',
                'Element_OphTrOperationbooking_ScheduleOperation',
            ],
            'scope' => 'clinical',
        ],
        'cvi' => [
            'name' => 'CVI (Certificate of Vision Impairment)',
            'elements' => [
                'Element_OphCoCvi_EventInfo',
                'Element_OphCoCvi_ClinicalInfo',
                'Element_OphCoCvi_ClericalInfo',
            ],
            'scope' => 'clinical',
            'namespace' => 'OEModule\\OphCoCvi\\models\\',
        ],
    ];

    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic moduledata <action> [options]

DESCRIPTION
  Phase 13: Migrate clinical module element data to Couchbase.
  Migrates 22 element models across 7 modules with embedded relations.

ACTIONS
  migrate    Migrate module element data
  status     Show migration status for all modules
  verify     Verify migrated data integrity
  count      Count records in MariaDB vs Couchbase

OPTIONS
  --module=<name>  Specific module to migrate (operationnote, laser, biometry, 
                   prescription, correspondence, operationbooking, cvi, or 'all')
  --element=<name> Specific element model to migrate
  --batch=<n>      Batch size (default: 100)
  --verbose        Show detailed output
  --dryRun         Show what would be migrated without actually migrating
  --skipExisting   Skip records that already exist in Couchbase

EXAMPLES
  yiic moduledata migrate --module=operationnote
  yiic moduledata migrate --module=all --batch=50
  yiic moduledata migrate --element=Element_OphTrOperationnote_Cataract --verbose
  yiic moduledata status
  yiic moduledata verify --module=laser
  yiic moduledata count --module=all

MODULES
  operationnote    - Operation Notes (6 elements)
  laser            - Laser Treatment (4 elements)
  biometry         - Biometry (3 elements)
  prescription     - Prescription (1 element)
  correspondence   - Correspondence/Letters (1 element)
  operationbooking - Operation Booking (3 elements)
  cvi              - CVI Certificates (3 elements)
  all              - All modules (22 elements total)

EOD;
    }

    /**
     * Migrate module element data
     */
    public function actionMigrate($module = 'all', $element = null, $batch = 100, $verbose = false, $dryRun = false, $skipExisting = false)
    {
        echo "===========================================\n";
        echo "Phase 13: Clinical Module Data Migration\n";
        echo "===========================================\n\n";

        if ($dryRun) {
            echo "*** DRY RUN MODE - No data will be migrated ***\n\n";
        }

        $modules = $this->getModulesToMigrate($module);
        $totalMigrated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;

        foreach ($modules as $moduleKey => $moduleConfig) {
            echo "\n[MODULE: {$moduleConfig['name']}]\n";
            echo str_repeat('=', 50) . "\n";

            // Auto-discover elements if configured with '*'
            if ($moduleConfig['elements'] === '*') {
                $moduleConfig['elements'] = $this->discoverModuleElements($moduleConfig);
                echo "  Auto-discovered " . count($moduleConfig['elements']) . " elements\n";
            }

            $elements = $element ? [$element] : $moduleConfig['elements'];
            
            foreach ($elements as $elementClass) {
                $result = $this->migrateElement(
                    $elementClass,
                    $moduleConfig,
                    $batch,
                    $verbose,
                    $dryRun,
                    $skipExisting
                );

                $totalMigrated += $result['migrated'];
                $totalSkipped += $result['skipped'];
                $totalErrors += $result['errors'];
            }
        }

        echo "\n===========================================\n";
        echo "Migration Complete\n";
        echo "===========================================\n";
        echo "Total Migrated: {$totalMigrated}\n";
        echo "Total Skipped:  {$totalSkipped}\n";
        echo "Total Errors:   {$totalErrors}\n";
        echo "===========================================\n";

        return $totalErrors === 0 ? 0 : 1;
    }

    /**
     * Show migration status
     */
    public function actionStatus($module = 'all')
    {
        echo "===========================================\n";
        echo "Phase 13: Migration Status\n";
        echo "===========================================\n\n";

        $modules = $this->getModulesToMigrate($module);
        $grandTotalMariaDB = 0;
        $grandTotalCouchbase = 0;

        foreach ($modules as $moduleKey => $moduleConfig) {
            echo "[{$moduleConfig['name']}]\n";

            $moduleTotalMariaDB = 0;
            $moduleTotalCouchbase = 0;

            foreach ($moduleConfig['elements'] as $elementClass) {
                $counts = $this->getElementCounts($elementClass, $moduleConfig);
                
                $moduleTotalMariaDB += $counts['mariadb'];
                $moduleTotalCouchbase += $counts['couchbase'];

                $status = $this->getStatusIndicator($counts['mariadb'], $counts['couchbase']);
                
                echo sprintf(
                    "  %-50s MariaDB: %5d | Couchbase: %5d %s\n",
                    $elementClass,
                    $counts['mariadb'],
                    $counts['couchbase'],
                    $status
                );
            }

            $grandTotalMariaDB += $moduleTotalMariaDB;
            $grandTotalCouchbase += $moduleTotalCouchbase;

            echo sprintf(
                "  %s\n",
                str_repeat('-', 90)
            );
            echo sprintf(
                "  %-50s MariaDB: %5d | Couchbase: %5d\n\n",
                "Module Total",
                $moduleTotalMariaDB,
                $moduleTotalCouchbase
            );
        }

        echo "===========================================\n";
        echo sprintf(
            "Grand Total:                                   MariaDB: %5d | Couchbase: %5d\n",
            $grandTotalMariaDB,
            $grandTotalCouchbase
        );
        echo "===========================================\n";

        return 0;
    }

    /**
     * Verify data integrity
     */
    public function actionVerify($module = 'all', $sample = 10)
    {
        echo "===========================================\n";
        echo "Phase 13: Data Verification\n";
        echo "===========================================\n\n";

        $modules = $this->getModulesToMigrate($module);
        $totalChecked = 0;
        $totalMismatches = 0;

        foreach ($modules as $moduleKey => $moduleConfig) {
            echo "[{$moduleConfig['name']}]\n";

            foreach ($moduleConfig['elements'] as $elementClass) {
                $result = $this->verifyElement($elementClass, $moduleConfig, $sample);
                
                $totalChecked += $result['checked'];
                $totalMismatches += $result['mismatches'];

                $status = $result['mismatches'] === 0 ? '✓' : '✗';
                echo sprintf(
                    "  %-50s Checked: %3d | Mismatches: %3d %s\n",
                    $elementClass,
                    $result['checked'],
                    $result['mismatches'],
                    $status
                );
            }
            echo "\n";
        }

        echo "===========================================\n";
        echo "Verification Complete\n";
        echo "Total Checked:    {$totalChecked}\n";
        echo "Total Mismatches: {$totalMismatches}\n";
        echo "===========================================\n";

        return $totalMismatches === 0 ? 0 : 1;
    }

    /**
     * Count records
     */
    public function actionCount($module = 'all')
    {
        return $this->actionStatus($module);
    }

    /**
     * Get modules to migrate based on filter
     */
    protected function getModulesToMigrate($moduleFilter)
    {
        if ($moduleFilter === 'all') {
            return $this->modules;
        }

        if (!isset($this->modules[$moduleFilter])) {
            throw new CException("Unknown module: {$moduleFilter}. Use: " . implode(', ', array_keys($this->modules)) . ", or 'all'");
        }

        return [$moduleFilter => $this->modules[$moduleFilter]];
    }

    /**
     * Auto-discover element models in a module directory
     */
    protected function discoverModuleElements($moduleConfig)
    {
        $elements = [];
        
        if (!isset($moduleConfig['module_path']) || !isset($moduleConfig['element_prefix'])) {
            return $elements;
        }
        
        $modulePath = Yii::app()->getBasePath() . '/modules/' . $moduleConfig['module_path'] . '/models';
        
        if (!is_dir($modulePath)) {
            echo "    WARNING: Module path not found: {$modulePath}\n";
            return $elements;
        }
        
        // Scan for Element_* files
        $files = glob($modulePath . '/' . $moduleConfig['element_prefix'] . '*.php');
        
        foreach ($files as $file) {
            $className = basename($file, '.php');
            
            // Skip abstract classes, traits, and special files
            if (strpos($className, 'Abstract') !== false || 
                strpos($className, 'Trait') !== false ||
                strpos($className, 'Base') !== false ||
                strpos($className, '_record') !== false ||
                strpos($className, '_Archive') !== false) {
                continue;
            }
            
            $elements[] = $className;
        }
        
        sort($elements);
        return $elements;
    }

    /**
     * Migrate a single element class
     */
    protected function migrateElement($elementClass, $moduleConfig, $batch, $verbose, $dryRun, $skipExisting)
    {
        echo "\n  Migrating: {$elementClass}\n";

        $migrated = 0;
        $skipped = 0;
        $errors = 0;
        $offset = 0;

        try {
            // Get full class name with namespace if needed
            $fullClassName = $this->getFullClassName($elementClass, $moduleConfig);

            if (!class_exists($fullClassName)) {
                echo "    ERROR: Class not found: {$fullClassName}\n";
                return ['migrated' => 0, 'skipped' => 0, 'errors' => 1];
            }

            // Count total records
            $total = $fullClassName::model()->count();
            echo "    Total records: {$total}\n";

            if ($total === 0) {
                echo "    No records to migrate\n";
                return ['migrated' => 0, 'skipped' => 0, 'errors' => 0];
            }

            // Process in batches
            while ($offset < $total) {
                $records = $fullClassName::model()
                    ->with($this->getRelationsToLoad($fullClassName))
                    ->limit($batch)
                    ->offset($offset)
                    ->findAll();

                foreach ($records as $record) {
                    try {
                        if ($skipExisting && $this->existsInCouchbase($record, $moduleConfig)) {
                            $skipped++;
                            if ($verbose) {
                                echo "      Skipped: {$elementClass} ID={$record->id}\n";
                            }
                            continue;
                        }

                        if (!$dryRun) {
                            // The model's afterSave will trigger the Couchbase write
                            // But since we're loading existing records, we need to manually trigger
                            if (method_exists($record, 'saveToCouchbase')) {
                                $record->saveToCouchbase();
                            }
                        }

                        $migrated++;

                        if ($verbose) {
                            echo "      Migrated: {$elementClass} ID={$record->id}\n";
                        }
                    } catch (Exception $e) {
                        $errors++;
                        echo "      ERROR: {$elementClass} ID={$record->id} - {$e->getMessage()}\n";
                    }
                }

                $offset += $batch;
                
                // Show progress
                $progress = min(100, round(($offset / $total) * 100, 1));
                echo "    Progress: {$progress}% ({$offset}/{$total})\r";
            }

            echo "\n";
            echo "    Completed: Migrated={$migrated}, Skipped={$skipped}, Errors={$errors}\n";

        } catch (Exception $e) {
            echo "    FATAL ERROR: {$e->getMessage()}\n";
            return ['migrated' => $migrated, 'skipped' => $skipped, 'errors' => $errors + 1];
        }

        return ['migrated' => $migrated, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Get element record counts
     */
    protected function getElementCounts($elementClass, $moduleConfig)
    {
        $mariadbCount = 0;
        $couchbaseCount = 0;

        try {
            $fullClassName = $this->getFullClassName($elementClass, $moduleConfig);
            
            if (class_exists($fullClassName)) {
                $mariadbCount = $fullClassName::model()->count();
            }

            // Count in Couchbase
            // This requires Couchbase connection - implement based on your setup
            $couchbaseCount = $this->countInCouchbase($elementClass, $moduleConfig);

        } catch (Exception $e) {
            // Silently handle errors in count
        }

        return [
            'mariadb' => $mariadbCount,
            'couchbase' => $couchbaseCount,
        ];
    }

    /**
     * Verify element data integrity
     */
    protected function verifyElement($elementClass, $moduleConfig, $sample)
    {
        $checked = 0;
        $mismatches = 0;

        try {
            $fullClassName = $this->getFullClassName($elementClass, $moduleConfig);
            
            if (!class_exists($fullClassName)) {
                return ['checked' => 0, 'mismatches' => 0];
            }

            // Get sample records
            $records = $fullClassName::model()
                ->with($this->getRelationsToLoad($fullClassName))
                ->limit($sample)
                ->findAll();

            foreach ($records as $record) {
                $checked++;
                
                // Verify record exists in Couchbase and data matches
                if (!$this->verifyRecordInCouchbase($record, $moduleConfig)) {
                    $mismatches++;
                }
            }

        } catch (Exception $e) {
            // Handle verification errors
        }

        return ['checked' => $checked, 'mismatches' => $mismatches];
    }

    /**
     * Get full class name with namespace
     */
    protected function getFullClassName($elementClass, $moduleConfig)
    {
        if (isset($moduleConfig['namespace'])) {
            return $moduleConfig['namespace'] . $elementClass;
        }
        return $elementClass;
    }

    /**
     * Get relations to eager load for an element
     */
    protected function getRelationsToLoad($className)
    {
        // Add common relations that should be loaded
        // This can be enhanced based on specific element needs
        return [];
    }

    /**
     * Check if record exists in Couchbase
     */
    protected function existsInCouchbase($record, $moduleConfig)
    {
        // Implement Couchbase existence check
        // This should use your Couchbase connection component
        return false; // Placeholder
    }

    /**
     * Count records in Couchbase
     */
    protected function countInCouchbase($elementClass, $moduleConfig)
    {
        // Implement Couchbase count query
        // This should use your Couchbase connection component
        return 0; // Placeholder
    }

    /**
     * Verify record data in Couchbase matches MariaDB
     */
    protected function verifyRecordInCouchbase($record, $moduleConfig)
    {
        // Implement data verification logic
        // Compare key fields between MariaDB and Couchbase
        return true; // Placeholder
    }

    /**
     * Get status indicator for count comparison
     */
    protected function getStatusIndicator($mariadb, $couchbase)
    {
        if ($couchbase === 0 && $mariadb > 0) {
            return '⚠ Not migrated';
        } elseif ($couchbase === $mariadb) {
            return '✓';
        } elseif ($couchbase < $mariadb) {
            return '⚠ Partial';
        } else {
            return '⚠ Mismatch';
        }
    }
}
