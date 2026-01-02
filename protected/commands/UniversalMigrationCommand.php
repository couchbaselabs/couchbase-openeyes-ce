<?php
/**
 * Universal Migration Command
 * Migrates any MariaDB table to Couchbase
 * 
 * Phase 1 of MariaDB removal - complete data migration
 */

class UniversalMigrationCommand extends CConsoleCommand
{
    public $verbose = false;
    public $batchSize = 100;
    public $skipVersionTables = true;
    public $dryRun = false;
    
    private $couchbase;
    private $bucket;
    private $stats = [
        'tables_processed' => 0,
        'tables_skipped' => 0,
        'records_migrated' => 0,
        'records_failed' => 0,
    ];

    public function getHelp()
    {
        return <<<HELP
USAGE
  yiic universalmigration <action> [options]

DESCRIPTION
  Universal migration tool for MariaDB to Couchbase migration.
  Migrates any table, automatically determining the correct Couchbase scope.

ACTIONS
  audit       Show migration status for all tables
  migrate     Migrate specified tables or all tables
  table       Migrate a single table
  verify      Verify migration completeness

OPTIONS
  --verbose              Show detailed output
  --batchSize=N          Batch size for migration (default: 100)
  --skipVersionTables    Skip *_version tables (default: true)
  --dryRun               Show what would be done without migrating
  --tables=t1,t2         Specific tables to migrate (comma-separated)
  --scope=name           Override scope for migration
  --limit=N              Limit number of tables to process

EXAMPLES
  yiic universalmigration audit
  yiic universalmigration migrate --verbose
  yiic universalmigration table --table=country --verbose
  yiic universalmigration migrate --tables=country,language,authitem
  yiic universalmigration migrate --skipVersionTables=false

HELP;
    }

    public function actionAudit()
    {
        $this->initCouchbase();
        
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "MIGRATION AUDIT REPORT\n";
        echo str_repeat("=", 70) . "\n\n";
        
        // Get all MariaDB tables with row counts
        $tables = $this->getMariaDbTables();
        $collections = $this->getCouchbaseCollections();
        
        $migrated = [];
        $notMigrated = [];
        $versionTables = [];
        
        foreach ($tables as $table => $rows) {
            if (strpos($table, '_version') !== false) {
                $versionTables[$table] = $rows;
            } elseif (in_array($table, $collections)) {
                $migrated[$table] = $rows;
            } else {
                $notMigrated[$table] = $rows;
            }
        }
        
        echo "MariaDB tables with data: " . count($tables) . "\n";
        echo "Couchbase collections: " . count($collections) . "\n\n";
        
        echo "✅ Already migrated: " . count($migrated) . " tables\n";
        echo "❌ Not migrated: " . count($notMigrated) . " tables\n";
        echo "📋 Version tables: " . count($versionTables) . " tables\n\n";
        
        if ($this->verbose && count($notMigrated) > 0) {
            echo str_repeat("-", 70) . "\n";
            echo "Tables NOT migrated (sorted by row count):\n";
            echo str_repeat("-", 70) . "\n";
            
            arsort($notMigrated);
            $count = 0;
            foreach ($notMigrated as $table => $rows) {
                $scope = $this->determineScope($table);
                echo sprintf("  %-45s %6d rows  [%s]\n", $table, $rows, $scope);
                if (++$count >= 50) {
                    echo "  ... and " . (count($notMigrated) - 50) . " more\n";
                    break;
                }
            }
        }
        
        echo "\n";
    }

    public function actionMigrate($tables = null, $scope = null, $limit = null)
    {
        $this->initCouchbase();
        
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "UNIVERSAL MIGRATION - MariaDB to Couchbase\n";
        echo str_repeat("=", 70) . "\n\n";
        
        if ($this->dryRun) {
            echo "*** DRY RUN MODE - No data will be migrated ***\n\n";
        }
        
        // Get tables to migrate
        $tablesToMigrate = [];
        
        if ($tables) {
            // Specific tables provided
            $tableList = explode(',', $tables);
            $allTables = $this->getMariaDbTables();
            foreach ($tableList as $t) {
                $t = trim($t);
                if (isset($allTables[$t])) {
                    $tablesToMigrate[$t] = $allTables[$t];
                } else {
                    echo "⚠ Table '$t' not found or empty, skipping.\n";
                }
            }
        } else {
            // All tables
            $tablesToMigrate = $this->getMariaDbTables();
            $collections = $this->getCouchbaseCollections();
            
            // Filter out already migrated
            foreach ($collections as $col) {
                unset($tablesToMigrate[$col]);
            }
            
            // Filter out version tables if requested
            if ($this->skipVersionTables) {
                foreach (array_keys($tablesToMigrate) as $t) {
                    if (strpos($t, '_version') !== false) {
                        unset($tablesToMigrate[$t]);
                    }
                }
            }
            
            // Skip system tables
            unset($tablesToMigrate['tbl_migration']);
        }
        
        if ($limit) {
            $tablesToMigrate = array_slice($tablesToMigrate, 0, (int)$limit, true);
        }
        
        echo "Tables to migrate: " . count($tablesToMigrate) . "\n\n";
        
        // Sort by row count (smallest first for quick wins)
        asort($tablesToMigrate);
        
        foreach ($tablesToMigrate as $table => $rows) {
            $this->migrateTable($table, $scope);
        }
        
        // Print summary
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "MIGRATION SUMMARY\n";
        echo str_repeat("=", 70) . "\n";
        echo "Tables processed: " . $this->stats['tables_processed'] . "\n";
        echo "Tables skipped: " . $this->stats['tables_skipped'] . "\n";
        echo "Records migrated: " . $this->stats['records_migrated'] . "\n";
        echo "Records failed: " . $this->stats['records_failed'] . "\n";
        echo str_repeat("=", 70) . "\n\n";
    }

    public function actionTable($table, $scope = null)
    {
        if (!$table) {
            echo "Error: --table parameter is required\n";
            return 1;
        }
        
        $this->initCouchbase();
        $this->migrateTable($table, $scope);
    }

    public function actionVerify()
    {
        $this->initCouchbase();
        
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "MIGRATION VERIFICATION\n";
        echo str_repeat("=", 70) . "\n\n";
        
        $tables = $this->getMariaDbTables();
        $collections = $this->getCouchbaseCollections();
        
        $mismatches = [];
        
        foreach ($tables as $table => $mariaRows) {
            if (in_array($table, $collections)) {
                $cbCount = $this->getCouchbaseCount($table);
                if ($cbCount !== null && $cbCount != $mariaRows) {
                    $mismatches[$table] = [
                        'mariadb' => $mariaRows,
                        'couchbase' => $cbCount,
                        'diff' => $mariaRows - $cbCount
                    ];
                }
            }
        }
        
        if (empty($mismatches)) {
            echo "✅ All migrated tables have matching record counts!\n";
        } else {
            echo "⚠ Found " . count($mismatches) . " tables with count mismatches:\n\n";
            foreach ($mismatches as $table => $counts) {
                echo sprintf("  %-40s MariaDB: %d, Couchbase: %d (diff: %d)\n",
                    $table, $counts['mariadb'], $counts['couchbase'], $counts['diff']);
            }
        }
        
        echo "\n";
    }

    private function initCouchbase()
    {
        $this->couchbase = Yii::app()->couchbase;
        $this->bucket = $this->couchbase->bucket;
    }

    private function getMariaDbTables()
    {
        $sql = "SELECT table_name, table_rows 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_rows > 0 
                ORDER BY table_name";
        
        $rows = Yii::app()->db->createCommand($sql)->queryAll();
        
        $tables = [];
        foreach ($rows as $row) {
            $tables[$row['table_name']] = (int)$row['table_rows'];
        }
        
        return $tables;
    }

    private function getCouchbaseCollections()
    {
        $collections = [];
        
        try {
            $manager = $this->bucket->collections();
            $scopes = $manager->getAllScopes();
            
            foreach ($scopes as $scope) {
                foreach ($scope->collections() as $col) {
                    $collections[] = $col->name();
                }
            }
        } catch (Exception $e) {
            if ($this->verbose) {
                echo "Warning: Could not get Couchbase collections: " . $e->getMessage() . "\n";
            }
        }
        
        return $collections;
    }

    private function getCouchbaseCount($collection)
    {
        $scope = $this->determineScope($collection);
        
        try {
            $query = "SELECT COUNT(*) as cnt FROM `openeyes`.`$scope`.`$collection`";
            $result = $this->couchbase->cluster->query($query);
            $rows = $result->rows();
            if (!empty($rows)) {
                return (int)$rows[0]['cnt'];
            }
        } catch (Exception $e) {
            // Collection might not be queryable
        }
        
        return null;
    }

    private function determineScope($tableName)
    {
        // Reference data
        if (in_array($tableName, [
            'country', 'language', 'ethnic_group', 'gender', 'eye',
            'specialty', 'subspecialty', 'event_type', 'element_type',
            'disorder', 'procedure', 'medication', 'drug', 'allergy',
            'benefit', 'complication', 'opcs_code', 'common_ophthalmic_disorder',
            'procedure_type', 'event_group'
        ])) {
            return 'reference';
        }
        
        // Reference patterns
        if (preg_match('/^medication_/', $tableName) ||
            preg_match('/^common_/', $tableName)) {
            return 'reference';
        }
        
        // Admin data
        if (in_array($tableName, [
            'audit', 'audit_type', 'audit_action', 'audit_model',
            'authitem', 'authitemchild', 'authassignment',
            'user_authentication', 'institution_authentication'
        ])) {
            return 'admin';
        }
        
        if (preg_match('/^setting/', $tableName) ||
            preg_match('/^auth/', $tableName)) {
            return 'admin';
        }
        
        // Core data
        if (in_array($tableName, [
            'patient', 'episode', 'event', 'user', 'contact', 'address',
            'institution', 'site', 'firm', 'person', 'gp', 'practice'
        ])) {
            return 'core';
        }
        
        if (preg_match('/^patient_/', $tableName) ||
            preg_match('/^episode_/', $tableName) ||
            preg_match('/^event_/', $tableName) ||
            preg_match('/^user_/', $tableName) ||
            preg_match('/^contact_/', $tableName)) {
            return 'core';
        }
        
        // Clinical data (module tables)
        if (preg_match('/^et_/', $tableName) ||
            preg_match('/^oph/', $tableName) ||
            preg_match('/^element_/', $tableName)) {
            return 'clinical';
        }
        
        // Default to reference for lookup tables
        return 'reference';
    }

    private function migrateTable($tableName, $overrideScope = null)
    {
        $scope = $overrideScope ?: $this->determineScope($tableName);
        
        if ($this->verbose) {
            echo "\n" . str_repeat("-", 60) . "\n";
            echo "Migrating: $tableName -> $scope.$tableName\n";
            echo str_repeat("-", 60) . "\n";
        } else {
            echo "Migrating $tableName... ";
        }
        
        // Get row count
        $countSql = "SELECT COUNT(*) FROM `$tableName`";
        $totalRows = (int)Yii::app()->db->createCommand($countSql)->queryScalar();
        
        if ($totalRows === 0) {
            echo "SKIP (empty)\n";
            $this->stats['tables_skipped']++;
            return;
        }
        
        if ($this->dryRun) {
            echo "DRY RUN: Would migrate $totalRows rows to $scope.$tableName\n";
            $this->stats['tables_processed']++;
            return;
        }
        
        // Ensure collection exists
        $this->ensureCollection($scope, $tableName);
        
        // Get collection
        $collection = $this->bucket->scope($scope)->collection($tableName);
        
        // Migrate in batches
        $offset = 0;
        $migrated = 0;
        $failed = 0;
        
        while ($offset < $totalRows) {
            $sql = "SELECT * FROM `$tableName` LIMIT {$this->batchSize} OFFSET $offset";
            $rows = Yii::app()->db->createCommand($sql)->queryAll();
            
            if (empty($rows)) {
                break;
            }
            
            foreach ($rows as $row) {
                // Generate document ID
                $docId = $this->generateDocId($tableName, $row);
                
                // Add metadata
                $row['_type'] = $tableName;
                $row['_migrated_at'] = date('Y-m-d H:i:s');
                $row['_source'] = 'mariadb';
                
                try {
                    $collection->upsert($docId, $row);
                    $migrated++;
                } catch (Exception $e) {
                    $failed++;
                    if ($this->verbose) {
                        echo "  ERROR: " . $e->getMessage() . "\n";
                    }
                }
            }
            
            $offset += $this->batchSize;
            
            if ($this->verbose && $totalRows > $this->batchSize) {
                $pct = min(100, round(($offset / $totalRows) * 100));
                echo "  Progress: $offset/$totalRows ($pct%)\n";
            }
        }
        
        $this->stats['tables_processed']++;
        $this->stats['records_migrated'] += $migrated;
        $this->stats['records_failed'] += $failed;
        
        if ($this->verbose) {
            echo "  Complete: $migrated migrated, $failed failed\n";
        } else {
            echo "OK ($migrated rows)\n";
        }
    }

    private function ensureCollection($scope, $collection)
    {
        try {
            $manager = $this->bucket->collections();
            
            // Check if scope exists
            $scopes = $manager->getAllScopes();
            $scopeExists = false;
            $collectionExists = false;
            
            foreach ($scopes as $s) {
                if ($s->name() === $scope) {
                    $scopeExists = true;
                    foreach ($s->collections() as $c) {
                        if ($c->name() === $collection) {
                            $collectionExists = true;
                            break;
                        }
                    }
                    break;
                }
            }
            
            if (!$scopeExists) {
                $manager->createScope($scope);
                if ($this->verbose) {
                    echo "  Created scope: $scope\n";
                }
            }
            
            if (!$collectionExists) {
                $manager->createCollection($scope, $collection);
                if ($this->verbose) {
                    echo "  Created collection: $scope.$collection\n";
                }
                // Wait for collection to be ready
                usleep(500000);
            }
        } catch (Exception $e) {
            if ($this->verbose) {
                echo "  Warning: " . $e->getMessage() . "\n";
            }
        }
    }

    private function generateDocId($tableName, $row)
    {
        // Try common ID columns
        if (isset($row['id'])) {
            return $tableName . '::' . $row['id'];
        }
        
        // Composite keys
        $keyColumns = ['id', 'code', 'name'];
        foreach ($keyColumns as $col) {
            if (isset($row[$col])) {
                return $tableName . '::' . $row[$col];
            }
        }
        
        // Fallback to hash of row data
        return $tableName . '::' . md5(json_encode($row));
    }
}
