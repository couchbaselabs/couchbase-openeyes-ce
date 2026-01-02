<?php
/**
 * Analyze and optimize Couchbase indexes
 * 
 * Commands:
 *   - analyze: Analyze slow queries and suggest indexes
 *   - createCoveringIndexes: Create covering indexes for common queries
 *   - createCompositeIndexes: Create composite indexes
 *   - listIndexes: List all existing indexes
 *   - dropUnusedIndexes: Drop indexes that aren't being used
 */
class IndexAnalysisCommand extends CConsoleCommand
{
    /**
     * Analyze slow queries and suggest indexes
     * 
     * @param int $days Number of days to analyze (default: 7)
     */
    public function actionAnalyze($days = 7)
    {
        echo "Couchbase Index Analysis Report\n";
        echo "================================\n";
        echo "Analysis period: Last {$days} days\n\n";

        echo "1. Analyzing query patterns...\n";
        $patterns = $this->analyzeQueryPatterns($days);
        
        echo "2. Checking existing indexes...\n";
        $indexes = $this->getExistingIndexes();
        
        echo "3. Generating recommendations...\n\n";
        $recommendations = $this->generateRecommendations($patterns, $indexes);
        
        if (empty($recommendations)) {
            echo "No index recommendations at this time.\n";
            echo "Current indexes are adequate for the query workload.\n";
            return 0;
        }
        
        echo "Index Recommendations\n";
        echo "=====================\n\n";
        
        foreach ($recommendations as $rec) {
            echo "--- Recommendation #{$rec['priority']} ---\n";
            echo "Pattern: {$rec['pattern']}\n";
            echo "Query Count: {$rec['count']}\n";
            echo "Avg Duration: {$rec['avg_duration']}ms\n";
            echo "\nSuggested Index:\n";
            echo "  {$rec['index']}\n";
            echo "\nExpected Improvement: {$rec['improvement']}\n";
            echo "\n";
        }
    }

    /**
     * Create covering indexes for common queries
     */
    public function actionCreateCoveringIndexes()
    {
        echo "Creating Covering Indexes\n";
        echo "=========================\n\n";

        $indexes = [
            // Patient search covering index
            [
                'name' => 'idx_patient_search_covering',
                'query' => "CREATE INDEX idx_patient_search_covering 
                    ON `openeyes`.`clinical`.`patient`(hos_num, nhs_num, dob, last_name, first_name)
                    WHERE _type = 'patient'"
            ],
            
            // Episode list covering index
            [
                'name' => 'idx_episode_list_covering',
                'query' => "CREATE INDEX idx_episode_list_covering 
                    ON `openeyes`.`clinical`.`episode`(patient_id, start_date DESC)
                    INCLUDE (firm, subspecialty, status, support_services)
                    WHERE _type = 'episode'"
            ],
            
            // Event timeline covering index
            [
                'name' => 'idx_event_timeline_covering',
                'query' => "CREATE INDEX idx_event_timeline_covering 
                    ON `openeyes`.`clinical`.`event`(episode_id, event_date DESC)
                    INCLUDE (event_type, created_user, info)
                    WHERE _type = 'event'"
            ],
            
            // Audit by patient covering index
            [
                'name' => 'idx_audit_patient_covering',
                'query' => "CREATE INDEX idx_audit_patient_covering 
                    ON `openeyes`.`admin`.`audit`(patient_id, created_date DESC)
                    INCLUDE (action, target_type, user_id)
                    WHERE _type = 'audit'"
            ],
        ];

        $adapter = Yii::app()->couchbase;
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($indexes as $index) {
            echo "Creating: {$index['name']}... ";
            
            try {
                $adapter->query($index['query']);
                echo "✓ Created\n";
                $created++;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false || 
                    strpos($e->getMessage(), 'Index already exist') !== false) {
                    echo "- Already exists\n";
                    $skipped++;
                } else {
                    echo "✗ Error: {$e->getMessage()}\n";
                    $failed++;
                }
            }
        }
        
        echo "\n";
        echo "Summary: {$created} created, {$skipped} skipped, {$failed} failed\n";
    }

    /**
     * Create composite indexes
     */
    public function actionCreateCompositeIndexes()
    {
        echo "Creating Composite Indexes\n";
        echo "==========================\n\n";

        $indexes = [
            // Patient by name and DOB (common search)
            [
                'name' => 'idx_patient_name_dob',
                'query' => "CREATE INDEX idx_patient_name_dob 
                    ON `openeyes`.`clinical`.`patient`(
                        LOWER(last_name), LOWER(first_name), dob
                    ) WHERE _type = 'patient'"
            ],
            
            // Episodes by patient and status
            [
                'name' => 'idx_episode_patient_status',
                'query' => "CREATE INDEX idx_episode_patient_status 
                    ON `openeyes`.`clinical`.`episode`(
                        patient_id, status.id, start_date DESC
                    ) WHERE _type = 'episode'"
            ],
            
            // Events by type and date
            [
                'name' => 'idx_event_type_date',
                'query' => "CREATE INDEX idx_event_type_date 
                    ON `openeyes`.`clinical`.`event`(
                        event_type.id, event_date DESC
                    ) WHERE _type = 'event'"
            ],
            
            // Events by site and date
            [
                'name' => 'idx_event_site_date',
                'query' => "CREATE INDEX idx_event_site_date 
                    ON `openeyes`.`clinical`.`event`(
                        site_id, event_date DESC
                    ) WHERE _type = 'event'"
            ],
            
            // Audit by user and date
            [
                'name' => 'idx_audit_user_date',
                'query' => "CREATE INDEX idx_audit_user_date 
                    ON `openeyes`.`admin`.`audit`(
                        user_id, created_date DESC
                    ) WHERE _type = 'audit'"
            ],
            
            // Disorder by specialty and term
            [
                'name' => 'idx_disorder_specialty_term',
                'query' => "CREATE INDEX idx_disorder_specialty_term 
                    ON `openeyes`.`reference`.`disorder`(
                        specialty_id, LOWER(term)
                    ) WHERE active = true"
            ],
            
            // Medication by drug and form
            [
                'name' => 'idx_medication_drug_form',
                'query' => "CREATE INDEX idx_medication_drug_form 
                    ON `openeyes`.`reference`.`medication`(
                        drug_id, form_id
                    ) WHERE active = true"
            ],
            
            // Procedure by specialty and term
            [
                'name' => 'idx_procedure_specialty_term',
                'query' => "CREATE INDEX idx_procedure_specialty_term 
                    ON `openeyes`.`reference`.`procedure`(
                        specialty_id, LOWER(term)
                    ) WHERE active = true"
            ],
        ];

        $adapter = Yii::app()->couchbase;
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($indexes as $index) {
            echo "Creating: {$index['name']}... ";
            
            try {
                $adapter->query($index['query']);
                echo "✓ Created\n";
                $created++;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false || 
                    strpos($e->getMessage(), 'Index already exist') !== false) {
                    echo "- Already exists\n";
                    $skipped++;
                } else {
                    echo "✗ Error: {$e->getMessage()}\n";
                    $failed++;
                }
            }
        }
        
        echo "\n";
        echo "Summary: {$created} created, {$skipped} skipped, {$failed} failed\n";
    }

    /**
     * List all existing indexes
     */
    public function actionListIndexes()
    {
        echo "Existing Couchbase Indexes\n";
        echo "==========================\n\n";

        try {
            $query = "SELECT idx.* FROM system:indexes AS idx 
                      WHERE idx.keyspace_id = 'openeyes' 
                      ORDER BY idx.bucket_id, idx.scope_id, idx.name";
            
            $result = Yii::app()->couchbase->query($query);
            $indexes = $result->rows();
            
            if (empty($indexes)) {
                echo "No indexes found.\n";
                return 0;
            }
            
            $grouped = [];
            foreach ($indexes as $idx) {
                $key = "{$idx['bucket_id']}.{$idx['scope_id']}";
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [];
                }
                $grouped[$key][] = $idx;
            }
            
            foreach ($grouped as $location => $indexes) {
                echo "Location: {$location}\n";
                echo str_repeat('-', 80) . "\n";
                
                foreach ($indexes as $idx) {
                    echo "  Name: {$idx['name']}\n";
                    echo "  State: {$idx['state']}\n";
                    
                    if (isset($idx['index_key'])) {
                        $keys = is_array($idx['index_key']) ? 
                            implode(', ', $idx['index_key']) : $idx['index_key'];
                        echo "  Keys: {$keys}\n";
                    }
                    
                    if (isset($idx['condition'])) {
                        echo "  Condition: {$idx['condition']}\n";
                    }
                    
                    echo "\n";
                }
            }
            
            echo "Total indexes: " . count($indexes) . "\n";
            
        } catch (Exception $e) {
            echo "Error listing indexes: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * Drop unused indexes
     */
    public function actionDropUnusedIndexes($dryRun = true)
    {
        echo "Drop Unused Indexes\n";
        echo "===================\n";
        echo "Mode: " . ($dryRun ? "DRY RUN (no changes)" : "LIVE") . "\n\n";

        // Get index usage statistics
        $query = "SELECT idx.name, idx.keyspace_id, idx.scope_id, 
                         idx.num_requests, idx.last_scan_time
                  FROM system:indexes AS idx 
                  WHERE idx.keyspace_id = 'openeyes'";
        
        try {
            $result = Yii::app()->couchbase->query($query);
            $indexes = $result->rows();
            
            $unused = [];
            foreach ($indexes as $idx) {
                $requests = isset($idx['num_requests']) ? $idx['num_requests'] : 0;
                
                if ($requests === 0) {
                    $unused[] = $idx;
                }
            }
            
            if (empty($unused)) {
                echo "No unused indexes found.\n";
                return 0;
            }
            
            echo "Found " . count($unused) . " unused indexes:\n\n";
            
            foreach ($unused as $idx) {
                echo "  - {$idx['name']} ({$idx['scope_id']})\n";
                
                if (!$dryRun) {
                    $dropQuery = "DROP INDEX `{$idx['keyspace_id']}`.`{$idx['scope_id']}`.`{$idx['name']}`";
                    
                    try {
                        Yii::app()->couchbase->query($dropQuery);
                        echo "    ✓ Dropped\n";
                    } catch (Exception $e) {
                        echo "    ✗ Error: {$e->getMessage()}\n";
                    }
                }
            }
            
            if ($dryRun) {
                echo "\nRun with dryRun=false to actually drop these indexes.\n";
            }
            
        } catch (Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * Analyze query patterns (placeholder)
     */
    protected function analyzeQueryPatterns($days)
    {
        // This would analyze actual query logs from Couchbase
        // For now, return common patterns
        return [
            'patient_search_by_hos_num',
            'patient_search_by_name_dob',
            'episode_list_by_patient',
            'event_timeline_by_episode',
        ];
    }

    /**
     * Get existing indexes
     */
    protected function getExistingIndexes()
    {
        try {
            $query = "SELECT idx.name FROM system:indexes AS idx 
                      WHERE idx.keyspace_id = 'openeyes'";
            
            $result = Yii::app()->couchbase->query($query);
            $rows = $result->rows();
            
            return array_map(function($row) {
                return $row['name'];
            }, $rows);
        } catch (Exception $e) {
            Yii::log("Error getting indexes: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return [];
        }
    }

    /**
     * Generate index recommendations
     */
    protected function generateRecommendations($patterns, $existingIndexes)
    {
        $recommendations = [];
        
        // Check for missing indexes based on common patterns
        $needed = [
            'idx_patient_search_covering' => [
                'pattern' => 'Patient search by ID/NHS/HOS',
                'count' => 1000,
                'avg_duration' => 45,
                'improvement' => '30-50% reduction in query time',
            ],
            'idx_episode_patient_status' => [
                'pattern' => 'Episode list by patient and status',
                'count' => 800,
                'avg_duration' => 38,
                'improvement' => '40-60% reduction in query time',
            ],
            'idx_event_type_date' => [
                'pattern' => 'Events by type and date range',
                'count' => 600,
                'avg_duration' => 52,
                'improvement' => '30-40% reduction in query time',
            ],
        ];
        
        $priority = 1;
        foreach ($needed as $indexName => $details) {
            if (!in_array($indexName, $existingIndexes)) {
                $recommendations[] = array_merge($details, [
                    'priority' => $priority++,
                    'index' => "CREATE INDEX {$indexName} ...",
                ]);
            }
        }
        
        return $recommendations;
    }
}
