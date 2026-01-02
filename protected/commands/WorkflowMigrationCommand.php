<?php
/**
 * WorkflowMigrationCommand - Migrate workflow data from MariaDB to Couchbase
 *
 * Migrates the following tables:
 * - ophciexamination_workflow
 * - ophciexamination_workflow_rule
 * - ophciexamination_element_set
 * - ophciexamination_element_set_item
 * - element_type
 */
class WorkflowMigrationCommand extends CConsoleCommand
{
    private $dryRun = false;
    private $verbose = false;
    private $restClient;
    private $pdo;
    
    private $tables = [
        'element_type' => [
            'scope' => 'reference',
            'collection' => 'element_type',
        ],
        'ophciexamination_workflow' => [
            'scope' => 'reference',
            'collection' => 'ophciexamination_workflow',
        ],
        'ophciexamination_workflow_rule' => [
            'scope' => 'reference',
            'collection' => 'ophciexamination_workflow_rule',
        ],
        'ophciexamination_element_set' => [
            'scope' => 'reference',
            'collection' => 'ophciexamination_element_set',
        ],
        'ophciexamination_element_set_item' => [
            'scope' => 'reference',
            'collection' => 'ophciexamination_element_set_item',
        ],
    ];

    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic workflowmigration [action] [options]

DESCRIPTION
  Manages workflow data in Couchbase.

ACTIONS
  index (default) - Run the full migration from MariaDB
  status          - Show current data status in Couchbase
  verify          - Verify data in Couchbase
  seed            - Create initial workflow data in Couchbase

OPTIONS
  --dry-run       Preview what will be done without making changes
  --verbose       Show detailed output
  --table=NAME    Process only a specific table

EXAMPLES
  php yiic workflowmigration
  php yiic workflowmigration seed
  php yiic workflowmigration status
  php yiic workflowmigration --dry-run

EOD;
    }

    public function actionIndex($dryRun = false, $verbose = false, $table = null)
    {
        $this->dryRun = $dryRun;
        $this->verbose = $verbose;
        
        echo "\n=== Workflow Data Migration (MariaDB → Couchbase) ===\n\n";
        
        if ($this->dryRun) {
            echo "[DRY RUN MODE - No changes will be made]\n\n";
        }
        
        // Initialize connections
        if (!$this->initConnections()) {
            echo "ERROR: Failed to initialize database connections\n";
            return 1;
        }
        
        // Determine which tables to migrate
        $tablesToMigrate = $table ? [$table => $this->tables[$table] ?? null] : $this->tables;
        
        if ($table && !isset($this->tables[$table])) {
            echo "ERROR: Unknown table '$table'\n";
            echo "Available tables: " . implode(', ', array_keys($this->tables)) . "\n";
            return 1;
        }
        
        $totalMigrated = 0;
        $totalErrors = 0;
        
        foreach ($tablesToMigrate as $tableName => $config) {
            if (!$config) continue;
            
            echo "Migrating: $tableName\n";
            echo str_repeat('-', 50) . "\n";
            
            $result = $this->migrateTable($tableName, $config);
            $totalMigrated += $result['migrated'];
            $totalErrors += $result['errors'];
            
            echo "  Migrated: {$result['migrated']}, Errors: {$result['errors']}\n\n";
        }
        
        echo "=== Migration Complete ===\n";
        echo "Total records migrated: $totalMigrated\n";
        echo "Total errors: $totalErrors\n";
        
        return $totalErrors > 0 ? 1 : 0;
    }

    public function actionStatus()
    {
        echo "\n=== Couchbase Data Status ===\n\n";
        
        if (!$this->initCouchbaseOnly()) {
            echo "ERROR: Failed to initialize Couchbase connection\n";
            return 1;
        }
        
        echo sprintf("%-40s %10s\n", "Collection", "Count");
        echo str_repeat('-', 52) . "\n";
        
        foreach ($this->tables as $tableName => $config) {
            $couchbaseCount = $this->getCouchbaseCount($config['scope'], $config['collection']);
            $status = $couchbaseCount > 0 ? '✓' : '✗';
            echo sprintf("%-40s %10d %s\n", $config['collection'], $couchbaseCount, $status);
        }
        
        echo "\n";
        return 0;
    }

    public function actionVerify($table = null)
    {
        echo "\n=== Verification ===\n\n";
        
        if (!$this->initCouchbaseOnly()) {
            echo "ERROR: Failed to initialize Couchbase connection\n";
            return 1;
        }
        
        $tablesToVerify = $table ? [$table => $this->tables[$table] ?? null] : $this->tables;
        
        foreach ($tablesToVerify as $tableName => $config) {
            if (!$config) continue;
            
            echo "Verifying: $tableName\n";
            $count = $this->getCouchbaseCount($config['scope'], $config['collection']);
            echo "  Found $count documents in Couchbase\n\n";
        }
        
        return 0;
    }

    /**
     * Seed initial workflow data into Couchbase (no MariaDB needed)
     */
    public function actionSeed($dryRun = false, $verbose = false)
    {
        $this->dryRun = $dryRun;
        $this->verbose = $verbose;
        
        echo "\n=== Workflow Data Seeding ===\n\n";
        
        if ($this->dryRun) {
            echo "[DRY RUN MODE - No changes will be made]\n\n";
        }
        
        if (!$this->initCouchbaseOnly()) {
            echo "ERROR: Failed to initialize Couchbase connection\n";
            return 1;
        }
        
        // Check if data already exists
        $existingWorkflows = $this->getCouchbaseCount('reference', 'ophciexamination_workflow');
        if ($existingWorkflows > 0) {
            echo "WARNING: Found $existingWorkflows existing workflows in Couchbase.\n";
            echo "Seeding will add new records with new IDs.\n\n";
        }
        
        // Create seed data
        $this->seedWorkflows();
        $this->seedWorkflowRules();
        $this->seedElementSets();
        $this->seedElementSetItems();
        
        echo "\n=== Seeding Complete ===\n";
        return 0;
    }

    private function initCouchbaseOnly()
    {
        try {
            $this->restClient = Yii::app()->couchbaseRest;
            if (!$this->restClient) {
                echo "ERROR: Could not get Couchbase REST client\n";
                return false;
            }
            echo "Couchbase REST client initialized\n\n";
            return true;
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function seedWorkflows()
    {
        echo "Seeding workflows...\n";
        
        $workflows = [
            ['id' => 1, 'name' => 'Standard Examination', 'active' => 1],
            ['id' => 2, 'name' => 'Glaucoma', 'active' => 1],
            ['id' => 3, 'name' => 'Medical Retina', 'active' => 1],
            ['id' => 4, 'name' => 'Cataract Pre-Op', 'active' => 1],
            ['id' => 30, 'name' => 'Default Workflow', 'active' => 1],
        ];
        
        foreach ($workflows as $workflow) {
            $docKey = "ophciexamination_workflow::{$workflow['id']}";
            $doc = array_merge($workflow, [
                '_type' => 'ophciexamination_workflow',
                '_mysql_id' => $workflow['id'],
                '_created' => date('c'),
                '_modified' => date('c'),
            ]);
            
            if ($this->dryRun) {
                echo "  [DRY] Would create: $docKey ({$workflow['name']})\n";
            } else {
                try {
                    $this->upsertDocument('reference', 'ophciexamination_workflow', $docKey, $doc);
                    echo "  Created: $docKey ({$workflow['name']})\n";
                } catch (Exception $e) {
                    echo "  ERROR: $docKey - " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    /**
     * Upsert a document using N1QL
     */
    private function upsertDocument($scope, $collection, $docKey, $doc)
    {
        $n1ql = "UPSERT INTO `openeyes`.`$scope`.`$collection` (KEY, VALUE) VALUES (\$key, \$doc)";
        $this->restClient->query($n1ql, ['key' => $docKey, 'doc' => $doc]);
    }

    private function seedWorkflowRules()
    {
        echo "Seeding workflow rules...\n";
        
        // Create generic rules that apply to all institutions/subspecialties
        $rules = [
            ['id' => 1, 'workflow_id' => 1, 'subspecialty_id' => null, 'firm_id' => null, 'episode_status_id' => null, 'parent_id' => null],
            ['id' => 2, 'workflow_id' => 30, 'subspecialty_id' => null, 'firm_id' => null, 'episode_status_id' => null, 'parent_id' => null],
        ];
        
        foreach ($rules as $rule) {
            $docKey = "ophciexamination_workflow_rule::{$rule['id']}";
            $doc = array_merge($rule, [
                '_type' => 'ophciexamination_workflow_rule',
                '_mysql_id' => $rule['id'],
                '_created' => date('c'),
                '_modified' => date('c'),
            ]);
            
            if ($this->dryRun) {
                echo "  [DRY] Would create: $docKey (workflow_id={$rule['workflow_id']})\n";
            } else {
                try {
                    $this->upsertDocument('reference', 'ophciexamination_workflow_rule', $docKey, $doc);
                    echo "  Created: $docKey (workflow_id={$rule['workflow_id']})\n";
                } catch (Exception $e) {
                    echo "  ERROR: $docKey - " . $e->getMessage() . "\n";
                }
            }
        }
    }

    private function seedElementSets()
    {
        echo "Seeding element sets (workflow steps)...\n";
        
        $sets = [
            // Standard Examination steps
            ['id' => 1, 'workflow_id' => 1, 'name' => 'History', 'position' => 1, 'is_active' => 1],
            ['id' => 2, 'workflow_id' => 1, 'name' => 'Examination', 'position' => 2, 'is_active' => 1],
            ['id' => 3, 'workflow_id' => 1, 'name' => 'Investigation', 'position' => 3, 'is_active' => 1],
            ['id' => 4, 'workflow_id' => 1, 'name' => 'Conclusion', 'position' => 4, 'is_active' => 1],
            // Default workflow step (workflow_id=30 that the admin page is looking for)
            ['id' => 30, 'workflow_id' => 30, 'name' => 'Default Step', 'position' => 1, 'is_active' => 1],
        ];
        
        foreach ($sets as $set) {
            $docKey = "ophciexamination_element_set::{$set['id']}";
            $doc = array_merge($set, [
                '_type' => 'ophciexamination_element_set',
                '_mysql_id' => $set['id'],
                '_created' => date('c'),
                '_modified' => date('c'),
            ]);
            
            if ($this->dryRun) {
                echo "  [DRY] Would create: $docKey ({$set['name']} for workflow {$set['workflow_id']})\n";
            } else {
                try {
                    $this->upsertDocument('reference', 'ophciexamination_element_set', $docKey, $doc);
                    echo "  Created: $docKey ({$set['name']} for workflow {$set['workflow_id']})\n";
                } catch (Exception $e) {
                    echo "  ERROR: $docKey - " . $e->getMessage() . "\n";
                }
            }
        }
    }

    private function seedElementSetItems()
    {
        echo "Seeding element set items...\n";
        
        // Get element types from Couchbase (try multiple possible locations)
        $elementTypes = [];
        
        // Try different collection paths where element_type might be stored
        $queries = [
            "SELECT et.id, et.class_name, et.name FROM `openeyes`.`reference`.`element_type` et LIMIT 20",
            "SELECT et.id, et.class_name, et.name FROM `openeyes`.`_default`.`element_type` et LIMIT 20",
        ];
        
        foreach ($queries as $n1ql) {
            try {
                $elementTypes = $this->restClient->query($n1ql, []);
                if (!empty($elementTypes)) {
                    echo "  Found " . count($elementTypes) . " element types\n";
                    break;
                }
            } catch (Exception $e) {
                // Try next query
            }
        }
        
        // Create basic items - will link to element types later
        echo "  Creating basic element set items...\n";
        $items = [
            // Items for Standard Examination workflow (set_id=1 History step)
            ['id' => 1, 'set_id' => 1, 'element_type_id' => 1, 'is_mandatory' => 0, 'is_hidden' => 0, 'display_order' => 1],
            ['id' => 2, 'set_id' => 1, 'element_type_id' => 2, 'is_mandatory' => 0, 'is_hidden' => 0, 'display_order' => 2],
            ['id' => 3, 'set_id' => 1, 'element_type_id' => 3, 'is_mandatory' => 0, 'is_hidden' => 0, 'display_order' => 3],
            // Items for Examination step (set_id=2)
            ['id' => 4, 'set_id' => 2, 'element_type_id' => 4, 'is_mandatory' => 0, 'is_hidden' => 0, 'display_order' => 1],
            ['id' => 5, 'set_id' => 2, 'element_type_id' => 5, 'is_mandatory' => 0, 'is_hidden' => 0, 'display_order' => 2],
            // Items for Default workflow step (set_id=30)
            ['id' => 30, 'set_id' => 30, 'element_type_id' => 1, 'is_mandatory' => 0, 'is_hidden' => 0, 'display_order' => 1],
            ['id' => 31, 'set_id' => 30, 'element_type_id' => 2, 'is_mandatory' => 0, 'is_hidden' => 0, 'display_order' => 2],
        ];
        
        // If we found element types, use real IDs
        if (!empty($elementTypes)) {
            $items = [];
            $itemId = 1;
            
            // Add items to History step (set_id=1)
            foreach (array_slice($elementTypes, 0, 5) as $index => $et) {
                $items[] = [
                    'id' => $itemId,
                    'set_id' => 1,
                    'element_type_id' => $et['id'],
                    'is_mandatory' => 0,
                    'is_hidden' => 0,
                    'display_order' => $index + 1,
                ];
                $itemId++;
            }
            
            // Add items to Default step (set_id=30)
            foreach (array_slice($elementTypes, 0, 3) as $index => $et) {
                $items[] = [
                    'id' => $itemId,
                    'set_id' => 30,
                    'element_type_id' => $et['id'],
                    'is_mandatory' => 0,
                    'is_hidden' => 0,
                    'display_order' => $index + 1,
                ];
                $itemId++;
            }
        }
        
        foreach ($items as $item) {
            $docKey = "ophciexamination_element_set_item::{$item['id']}";
            $doc = array_merge($item, [
                '_type' => 'ophciexamination_element_set_item',
                '_mysql_id' => $item['id'],
                '_created' => date('c'),
                '_modified' => date('c'),
            ]);
            
            if ($this->dryRun) {
                echo "  [DRY] Would create: $docKey (set={$item['set_id']}, element_type={$item['element_type_id']})\n";
            } else {
                try {
                    $this->upsertDocument('reference', 'ophciexamination_element_set_item', $docKey, $doc);
                    echo "  Created: $docKey (set={$item['set_id']}, element_type={$item['element_type_id']})\n";
                } catch (Exception $e) {
                    echo "  ERROR: $docKey - " . $e->getMessage() . "\n";
                }
            }
        }
    }

    private function initConnections()
    {
        try {
            // Create direct MariaDB PDO connection using environment variables
            $dbHost = getenv('DATABASE_HOST') ?: 'db';
            $dbPort = getenv('DATABASE_PORT') ?: '3306';
            $dbName = getenv('DATABASE_NAME') ?: 'openeyes';
            $dbUser = getenv('DATABASE_USER') ?: 'openeyes';
            $dbPass = getenv('DATABASE_PASS') ?: 'openeyes';
            
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8";
            
            echo "Connecting to MariaDB at $dbHost:$dbPort/$dbName...\n";
            
            $this->pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            echo "MariaDB connected successfully\n";
            
            // Get Couchbase REST client
            $this->restClient = Yii::app()->couchbaseRest;
            if (!$this->restClient) {
                echo "ERROR: Could not get Couchbase REST client\n";
                return false;
            }
            
            echo "Couchbase REST client initialized\n";
            echo "Connections initialized successfully\n\n";
            return true;
        } catch (PDOException $e) {
            echo "ERROR connecting to MariaDB: " . $e->getMessage() . "\n";
            return false;
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            return false;
        }
    }

    private function migrateTable($tableName, $config)
    {
        $result = ['migrated' => 0, 'errors' => 0];
        
        try {
            // Count records
            $count = $this->getMariaDbCount($tableName);
            echo "  Found $count records in MariaDB\n";
            
            if ($count === 0) {
                return $result;
            }
            
            // Fetch all records
            $stmt = $this->pdo->query("SELECT * FROM `$tableName`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Migrate each record
            $batchSize = 100;
            $processed = 0;
            
            foreach ($rows as $row) {
                $id = $row['id'];
                $docKey = "{$config['collection']}::$id";
                
                // Build Couchbase document
                $doc = $this->buildCouchbaseDoc($row, $config['collection']);
                
                if ($this->dryRun) {
                    if ($this->verbose) {
                        echo "  [DRY] Would upsert: $docKey\n";
                    }
                    $result['migrated']++;
                } else {
                    try {
                        $this->restClient->upsert($config['scope'], $config['collection'], $docKey, $doc);
                        $result['migrated']++;
                        
                        if ($this->verbose) {
                            echo "  Upserted: $docKey\n";
                        }
                    } catch (Exception $e) {
                        $result['errors']++;
                        echo "  ERROR upserting $docKey: " . $e->getMessage() . "\n";
                    }
                }
                
                $processed++;
                if ($processed % $batchSize === 0) {
                    echo "  Progress: $processed / $count\n";
                }
            }
            
        } catch (Exception $e) {
            echo "  ERROR: " . $e->getMessage() . "\n";
            $result['errors']++;
        }
        
        return $result;
    }

    private function buildCouchbaseDoc($row, $collection)
    {
        $doc = [];
        
        // Copy all columns
        foreach ($row as $key => $value) {
            // Convert types appropriately
            if ($value === null) {
                $doc[$key] = null;
            } elseif (is_numeric($value) && strpos($value, '.') === false) {
                $doc[$key] = (int)$value;
            } elseif (is_numeric($value)) {
                $doc[$key] = (float)$value;
            } else {
                $doc[$key] = $value;
            }
        }
        
        // Add metadata
        $doc['_type'] = $collection;
        $doc['_mysql_id'] = (int)$row['id'];
        $doc['_modified'] = date('c');
        $doc['_migrated_at'] = date('c');
        
        return $doc;
    }

    private function getMariaDbCount($tableName)
    {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$tableName`");
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    private function getMariaDbSample($tableName, $limit = 5)
    {
        try {
            $stmt = $this->pdo->query("SELECT * FROM `$tableName` LIMIT $limit");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    private function getCouchbaseCount($scope, $collection)
    {
        try {
            $n1ql = "SELECT RAW COUNT(*) FROM `openeyes`.`$scope`.`$collection`";
            $rows = $this->restClient->query($n1ql, []);
            return !empty($rows) ? (int)$rows[0] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    private function getCouchbaseDoc($scope, $collection, $docKey)
    {
        try {
            $n1ql = "SELECT d.* FROM `openeyes`.`$scope`.`$collection` d USE KEYS ['" . addslashes($docKey) . "']";
            $rows = $this->restClient->query($n1ql, []);
            return !empty($rows) ? $rows[0] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
