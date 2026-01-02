# Phase 15: Performance Optimization

## Overview

This phase optimizes query performance, implements caching strategies, tunes connection pooling, and establishes monitoring for the Couchbase integration.

**Duration**: 2 weeks  
**Priority**: HIGH  
**Complexity**: Medium

## Prerequisites

- Phase 14 completed (data migration done)
- Couchbase cluster operational
- Application running in hybrid mode

---

## Section 1: Index Optimization

### 1.1 Index Analysis Command

**File**: `protected/commands/IndexAnalysisCommand.php`

```php
<?php
/**
 * Analyze and optimize Couchbase indexes
 */

class IndexAnalysisCommand extends CConsoleCommand
{
    /**
     * Analyze slow queries and suggest indexes
     */
    public function actionAnalyze($days = 7)
    {
        echo "Index Analysis Report\n";
        echo "=====================\n\n";

        // Get slow queries from Couchbase admin
        echo "1. Analyzing query patterns...\n";
        
        $patterns = $this->analyzeQueryPatterns();
        
        echo "2. Checking existing indexes...\n";
        
        $indexes = $this->getExistingIndexes();
        
        echo "3. Generating recommendations...\n\n";
        
        $recommendations = $this->generateRecommendations($patterns, $indexes);
        
        foreach ($recommendations as $rec) {
            echo "--- Recommendation ---\n";
            echo "Pattern: {$rec['pattern']}\n";
            echo "Suggested Index:\n";
            echo "  {$rec['index']}\n";
            echo "Expected Improvement: {$rec['improvement']}\n\n";
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
                    ON `openeyes`.`clinical`.`patient`(hos_num, nhs_num)
                    INCLUDE (first_name, last_name, dob, gender_id, contact)"
            ],
            // Episode list covering index
            [
                'name' => 'idx_episode_list_covering',
                'query' => "CREATE INDEX idx_episode_list_covering 
                    ON `openeyes`.`clinical`.`episode`(patient_id, start_date DESC)
                    INCLUDE (firm, subspecialty, status, support_services)"
            ],
            // Event timeline covering index
            [
                'name' => 'idx_event_timeline_covering',
                'query' => "CREATE INDEX idx_event_timeline_covering 
                    ON `openeyes`.`clinical`.`event`(episode_id, event_date DESC)
                    INCLUDE (event_type, created_user)"
            ],
        ];

        $adapter = Yii::app()->couchbase;

        foreach ($indexes as $index) {
            echo "Creating: {$index['name']}\n";
            
            try {
                $adapter->query($index['query']);
                echo "  ✓ Created\n";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "  - Already exists\n";
                } else {
                    echo "  ✗ Error: {$e->getMessage()}\n";
                }
            }
        }
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
            "CREATE INDEX idx_patient_name_dob 
             ON `openeyes`.`clinical`.`patient`(
                 LOWER(last_name), LOWER(first_name), dob
             ) WHERE _type = 'patient'",
            
            // Episodes by patient and status
            "CREATE INDEX idx_episode_patient_status 
             ON `openeyes`.`clinical`.`episode`(
                 patient_id, status.id, start_date DESC
             )",
            
            // Events by type and date
            "CREATE INDEX idx_event_type_date 
             ON `openeyes`.`clinical`.`event`(
                 event_type.id, event_date DESC
             )",
            
            // Audit by user and date
            "CREATE INDEX idx_audit_user_date 
             ON `openeyes`.`admin`.`audit`(
                 user_id, created_date_only DESC
             )",
            
            // Disorder by specialty and term
            "CREATE INDEX idx_disorder_specialty_term 
             ON `openeyes`.`reference`.`disorder`(
                 specialty_id, term_lower
             ) WHERE active = true",
        ];

        $adapter = Yii::app()->couchbase;

        foreach ($indexes as $query) {
            // Extract index name from query
            preg_match('/CREATE INDEX (\w+)/', $query, $matches);
            $name = $matches[1] ?? 'unknown';
            
            echo "Creating: {$name}\n";
            
            try {
                $adapter->query($query);
                echo "  ✓ Created\n";
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "  - Already exists\n";
                } else {
                    echo "  ✗ Error: {$e->getMessage()}\n";
                }
            }
        }
    }

    protected function analyzeQueryPatterns()
    {
        // Would analyze actual query logs
        return [
            'patient_search_by_hos_num',
            'episode_list_by_patient',
            'event_timeline_by_episode',
        ];
    }

    protected function getExistingIndexes()
    {
        // Would query Couchbase for existing indexes
        return [];
    }

    protected function generateRecommendations($patterns, $indexes)
    {
        return [];
    }
}
```

**Lines**: ~160

---

## Section 2: Caching Layer

### 2.1 Create Cache Service

**File**: `protected/services/CouchbaseCacheService.php`

```php
<?php
/**
 * Caching service for frequently accessed data
 */

class CouchbaseCacheService
{
    protected $couchbase;
    protected $localCache = [];
    protected $localCacheTTL = 60; // seconds
    protected $localCacheTimestamps = [];

    public function __construct()
    {
        $this->couchbase = Yii::app()->couchbase;
    }

    /**
     * Get with multi-level caching
     * Level 1: Local PHP array (per-request)
     * Level 2: Couchbase document cache
     * Level 3: Primary data store
     */
    public function get($key, $scope = 'cache', $collection = 'cache')
    {
        // Level 1: Check local cache
        if ($this->isLocalCacheValid($key)) {
            return $this->localCache[$key];
        }

        // Level 2: Check Couchbase cache
        try {
            $result = $this->couchbase->get($scope, $collection, $key);
            
            if ($result) {
                // Populate local cache
                $this->setLocalCache($key, $result);
                return $result;
            }
        } catch (Exception $e) {
            // Cache miss
        }

        return null;
    }

    /**
     * Set with TTL
     */
    public function set($key, $value, $ttl = 3600, $scope = 'cache', $collection = 'cache')
    {
        // Set in Couchbase with TTL
        try {
            $this->couchbase->upsert($scope, $collection, $key, $value, ['expiry' => $ttl]);
            
            // Set in local cache
            $this->setLocalCache($key, $value);
            
            return true;
        } catch (Exception $e) {
            Yii::log("Cache set error: " . $e->getMessage(), 'error', 'cache');
            return false;
        }
    }

    /**
     * Delete from cache
     */
    public function delete($key, $scope = 'cache', $collection = 'cache')
    {
        // Remove from local cache
        unset($this->localCache[$key]);
        unset($this->localCacheTimestamps[$key]);

        // Remove from Couchbase
        try {
            $this->couchbase->remove($scope, $collection, $key);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get or set pattern
     */
    public function getOrSet($key, $callback, $ttl = 3600, $scope = 'cache', $collection = 'cache')
    {
        $value = $this->get($key, $scope, $collection);
        
        if ($value === null) {
            $value = call_user_func($callback);
            
            if ($value !== null) {
                $this->set($key, $value, $ttl, $scope, $collection);
            }
        }
        
        return $value;
    }

    /**
     * Cache patient summary
     */
    public function cachePatientSummary($patientId, $data, $ttl = 300)
    {
        $key = "patient_summary::{$patientId}";
        return $this->set($key, $data, $ttl, 'cache', 'patient_cache');
    }

    /**
     * Get cached patient summary
     */
    public function getPatientSummary($patientId)
    {
        $key = "patient_summary::{$patientId}";
        return $this->get($key, 'cache', 'patient_cache');
    }

    /**
     * Cache lookup table
     */
    public function cacheLookupTable($tableName, $data, $ttl = 86400)
    {
        $key = "lookup::{$tableName}";
        return $this->set($key, $data, $ttl, 'cache', 'lookup_cache');
    }

    /**
     * Get cached lookup table
     */
    public function getLookupTable($tableName)
    {
        $key = "lookup::{$tableName}";
        return $this->get($key, 'cache', 'lookup_cache');
    }

    /**
     * Invalidate patient cache
     */
    public function invalidatePatientCache($patientId)
    {
        $keys = [
            "patient_summary::{$patientId}",
            "patient_episodes::{$patientId}",
            "patient_events::{$patientId}",
        ];

        foreach ($keys as $key) {
            $this->delete($key, 'cache', 'patient_cache');
        }
    }

    protected function isLocalCacheValid($key)
    {
        if (!isset($this->localCache[$key])) {
            return false;
        }
        
        $timestamp = $this->localCacheTimestamps[$key] ?? 0;
        return (time() - $timestamp) < $this->localCacheTTL;
    }

    protected function setLocalCache($key, $value)
    {
        $this->localCache[$key] = $value;
        $this->localCacheTimestamps[$key] = time();
    }
}
```

**Lines**: ~180

---

## Section 3: Connection Pooling

### 3.1 Update Couchbase Connection

**File**: `protected/components/CouchbaseConnection.php` (Update)

```php
<?php
/**
 * Enhanced Couchbase connection with pooling
 */

class CouchbaseConnection extends CApplicationComponent
{
    // Connection pool settings
    public $poolSize = 10;
    public $connectionTimeout = 5000; // ms
    public $operationTimeout = 10000; // ms
    public $queryTimeout = 75000; // ms
    
    // Retry settings
    public $maxRetries = 3;
    public $retryDelay = 100; // ms
    
    // Connection reuse
    protected $cluster;
    protected $bucket;
    protected $collections = [];
    protected $lastUsed;
    protected $connectionCount = 0;

    /**
     * Initialize with connection pooling
     */
    public function init()
    {
        parent::init();
        
        // Configure connection options
        $options = new \Couchbase\ClusterOptions();
        $options->credentials($this->username, $this->password);
        
        // Timeout settings
        $options->connectTimeout($this->connectionTimeout);
        $options->kvTimeout($this->operationTimeout);
        $options->queryTimeout($this->queryTimeout);
        
        // Connection pooling
        $options->maxConnections($this->poolSize);
        $options->numIoThreads(4);
        
        // Enable metrics
        $options->enableMetrics(true);
        
        $this->cluster = new \Couchbase\Cluster($this->connectionString, $options);
        $this->bucket = $this->cluster->bucket($this->bucketName);
        $this->lastUsed = time();
    }

    /**
     * Get collection with lazy initialization
     */
    public function getCollection($scopeName, $collectionName)
    {
        $key = "{$scopeName}.{$collectionName}";
        
        if (!isset($this->collections[$key])) {
            $scope = $this->bucket->scope($scopeName);
            $this->collections[$key] = $scope->collection($collectionName);
        }
        
        $this->lastUsed = time();
        return $this->collections[$key];
    }

    /**
     * Execute with retry
     */
    public function executeWithRetry($operation, $retries = null)
    {
        $retries = $retries ?? $this->maxRetries;
        $lastException = null;
        
        for ($i = 0; $i <= $retries; $i++) {
            try {
                return $operation();
            } catch (\Couchbase\Exception\TimeoutException $e) {
                $lastException = $e;
                if ($i < $retries) {
                    usleep($this->retryDelay * 1000 * pow(2, $i)); // Exponential backoff
                }
            } catch (\Couchbase\Exception\TemporaryFailureException $e) {
                $lastException = $e;
                if ($i < $retries) {
                    usleep($this->retryDelay * 1000);
                }
            }
        }
        
        throw $lastException;
    }

    /**
     * Get with retry
     */
    public function get($scope, $collection, $key)
    {
        return $this->executeWithRetry(function() use ($scope, $collection, $key) {
            $col = $this->getCollection($scope, $collection);
            $result = $col->get($key);
            return $result->content();
        });
    }

    /**
     * Upsert with retry
     */
    public function upsert($scope, $collection, $key, $doc, $options = [])
    {
        return $this->executeWithRetry(function() use ($scope, $collection, $key, $doc, $options) {
            $col = $this->getCollection($scope, $collection);
            $upsertOptions = new \Couchbase\UpsertOptions();
            
            if (isset($options['expiry'])) {
                $upsertOptions->expiry($options['expiry']);
            }
            
            return $col->upsert($key, $doc, $upsertOptions);
        });
    }

    /**
     * Query with retry
     */
    public function query($statement, $params = [])
    {
        return $this->executeWithRetry(function() use ($statement, $params) {
            $options = new \Couchbase\QueryOptions();
            
            if (!empty($params)) {
                $options->namedParameters($params);
            }
            
            $result = $this->cluster->query($statement, $options);
            return $result->rows();
        });
    }

    /**
     * Get connection stats
     */
    public function getStats()
    {
        return [
            'pool_size' => $this->poolSize,
            'collections_cached' => count($this->collections),
            'last_used' => $this->lastUsed,
            'uptime' => time() - $this->lastUsed,
        ];
    }
}
```

**Lines**: ~180

---

## Section 4: Query Optimization

### 4.1 Optimized Query Patterns

**File**: `protected/components/database/OptimizedQueries.php`

```php
<?php
/**
 * Optimized query patterns for common operations
 */

class OptimizedQueries
{
    /**
     * Get patient with all relations in single query
     */
    public static function getPatientWithRelations($patientId)
    {
        $query = "SELECT p.*, 
            (SELECT e.* FROM `openeyes`.`clinical`.`episode` e 
             WHERE e.patient_id = p.id 
             ORDER BY e.start_date DESC LIMIT 10) AS recent_episodes,
            (SELECT ev.* FROM `openeyes`.`clinical`.`event` ev 
             WHERE ev.episode_id IN (
                 SELECT RAW ep.id FROM `openeyes`.`clinical`.`episode` ep 
                 WHERE ep.patient_id = p.id
             )
             ORDER BY ev.event_date DESC LIMIT 20) AS recent_events
          FROM `openeyes`.`clinical`.`patient` p 
          WHERE p.id = \$patientId";
        
        return Yii::app()->couchbase->query($query, ['patientId' => $patientId]);
    }

    /**
     * Search patients with pagination
     */
    public static function searchPatients($criteria, $offset = 0, $limit = 50)
    {
        $conditions = ["p._type = 'patient'"];
        $params = [];
        
        if (!empty($criteria['hos_num'])) {
            $conditions[] = "p.hos_num = \$hosNum";
            $params['hosNum'] = $criteria['hos_num'];
        }
        
        if (!empty($criteria['nhs_num'])) {
            $conditions[] = "p.nhs_num = \$nhsNum";
            $params['nhsNum'] = $criteria['nhs_num'];
        }
        
        if (!empty($criteria['last_name'])) {
            $conditions[] = "LOWER(p.last_name) LIKE \$lastName";
            $params['lastName'] = strtolower($criteria['last_name']) . '%';
        }
        
        if (!empty($criteria['dob'])) {
            $conditions[] = "p.dob = \$dob";
            $params['dob'] = $criteria['dob'];
        }
        
        $where = implode(' AND ', $conditions);
        
        $query = "SELECT p.id, p.hos_num, p.nhs_num, p.dob, 
                         p.contact.first_name, p.contact.last_name 
                  FROM `openeyes`.`clinical`.`patient` p USE INDEX (idx_patient_search_covering)
                  WHERE {$where}
                  ORDER BY p.last_name, p.first_name
                  OFFSET \$offset LIMIT \$limit";
        
        $params['offset'] = $offset;
        $params['limit'] = $limit;
        
        return Yii::app()->couchbase->query($query, $params);
    }

    /**
     * Get episode timeline
     */
    public static function getEpisodeTimeline($episodeId, $limit = 50)
    {
        $query = "SELECT ev.id, ev.event_date, ev.event_type, ev.created_user,
                         ev.info
                  FROM `openeyes`.`clinical`.`event` ev 
                  USE INDEX (idx_event_timeline_covering)
                  WHERE ev.episode_id = \$episodeId
                  ORDER BY ev.event_date DESC
                  LIMIT \$limit";
        
        return Yii::app()->couchbase->query($query, [
            'episodeId' => $episodeId,
            'limit' => $limit
        ]);
    }

    /**
     * Get examination elements efficiently
     */
    public static function getExaminationElements($eventId)
    {
        // Use UNION ALL for parallel element fetches
        $elementTypes = [
            'element_ophciexamination_history',
            'element_ophciexamination_visualacuity',
            'element_ophciexamination_refraction',
            'element_ophciexamination_anteriorsegment',
            'element_ophciexamination_fundus',
            'element_ophciexamination_diagnoses',
        ];
        
        $unions = [];
        foreach ($elementTypes as $type) {
            $unions[] = "SELECT e.* FROM `openeyes`.`clinical`.`{$type}` e WHERE e.event_id = \$eventId";
        }
        
        $query = implode(' UNION ALL ', $unions);
        
        return Yii::app()->couchbase->query($query, ['eventId' => $eventId]);
    }

    /**
     * Aggregate query for dashboard
     */
    public static function getDashboardStats($siteId, $dateFrom, $dateTo)
    {
        $query = "SELECT 
            COUNT(CASE WHEN ev.event_type.name = 'Examination' THEN 1 END) AS examinations,
            COUNT(CASE WHEN ev.event_type.name = 'Operation booking' THEN 1 END) AS bookings,
            COUNT(CASE WHEN ev.event_type.name = 'Operation note' THEN 1 END) AS operations,
            COUNT(DISTINCT ev.episode_id) AS unique_episodes
          FROM `openeyes`.`clinical`.`event` ev
          WHERE ev.site_id = \$siteId
          AND ev.event_date >= \$dateFrom
          AND ev.event_date <= \$dateTo";
        
        return Yii::app()->couchbase->query($query, [
            'siteId' => $siteId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
    }
}
```

**Lines**: ~140

---

## Section 5: Performance Monitoring

### 5.1 Create Performance Monitor

**File**: `protected/components/CouchbasePerformanceMonitor.php`

```php
<?php
/**
 * Performance monitoring for Couchbase operations
 */

class CouchbasePerformanceMonitor
{
    protected $metrics = [];
    protected $slowQueryThreshold = 100; // ms
    protected $enabled = true;

    /**
     * Start timing an operation
     */
    public function startTimer($operation)
    {
        if (!$this->enabled) return null;
        
        return [
            'operation' => $operation,
            'start' => microtime(true),
        ];
    }

    /**
     * End timing and record
     */
    public function endTimer($timer, $metadata = [])
    {
        if (!$this->enabled || !$timer) return;
        
        $duration = (microtime(true) - $timer['start']) * 1000; // ms
        
        $this->recordMetric([
            'operation' => $timer['operation'],
            'duration_ms' => $duration,
            'timestamp' => time(),
            'metadata' => $metadata,
        ]);
        
        // Log slow queries
        if ($duration > $this->slowQueryThreshold) {
            $this->logSlowQuery($timer['operation'], $duration, $metadata);
        }
        
        return $duration;
    }

    /**
     * Record a metric
     */
    protected function recordMetric($metric)
    {
        $operation = $metric['operation'];
        
        if (!isset($this->metrics[$operation])) {
            $this->metrics[$operation] = [
                'count' => 0,
                'total_ms' => 0,
                'min_ms' => PHP_INT_MAX,
                'max_ms' => 0,
                'last' => null,
            ];
        }
        
        $this->metrics[$operation]['count']++;
        $this->metrics[$operation]['total_ms'] += $metric['duration_ms'];
        $this->metrics[$operation]['min_ms'] = min(
            $this->metrics[$operation]['min_ms'],
            $metric['duration_ms']
        );
        $this->metrics[$operation]['max_ms'] = max(
            $this->metrics[$operation]['max_ms'],
            $metric['duration_ms']
        );
        $this->metrics[$operation]['last'] = $metric;
    }

    /**
     * Log slow query
     */
    protected function logSlowQuery($operation, $duration, $metadata)
    {
        $message = sprintf(
            "Slow Couchbase query: %s (%.2fms) - %s",
            $operation,
            $duration,
            json_encode($metadata)
        );
        
        Yii::log($message, 'warning', 'couchbase.performance');
    }

    /**
     * Get summary statistics
     */
    public function getSummary()
    {
        $summary = [];
        
        foreach ($this->metrics as $operation => $data) {
            $summary[$operation] = [
                'count' => $data['count'],
                'avg_ms' => $data['count'] > 0 ? $data['total_ms'] / $data['count'] : 0,
                'min_ms' => $data['min_ms'] === PHP_INT_MAX ? 0 : $data['min_ms'],
                'max_ms' => $data['max_ms'],
                'total_ms' => $data['total_ms'],
            ];
        }
        
        return $summary;
    }

    /**
     * Get performance report
     */
    public function getReport()
    {
        $summary = $this->getSummary();
        
        $report = "Couchbase Performance Report\n";
        $report .= "============================\n\n";
        
        foreach ($summary as $operation => $stats) {
            $report .= sprintf(
                "%-40s Count: %6d  Avg: %8.2fms  Min: %8.2fms  Max: %8.2fms\n",
                $operation,
                $stats['count'],
                $stats['avg_ms'],
                $stats['min_ms'],
                $stats['max_ms']
            );
        }
        
        return $report;
    }

    /**
     * Reset metrics
     */
    public function reset()
    {
        $this->metrics = [];
    }
}
```

**Lines**: ~140

---

## Section 6: Performance Targets

### 6.1 Target Metrics

| Operation | Target (p95) | Max Acceptable |
|-----------|-------------|----------------|
| Patient lookup by ID | < 10ms | < 50ms |
| Patient search | < 50ms | < 200ms |
| Episode list | < 30ms | < 100ms |
| Event timeline | < 40ms | < 150ms |
| Examination load | < 80ms | < 300ms |
| Audit query (day) | < 100ms | < 500ms |
| Reference lookup | < 5ms | < 20ms |

### 6.2 Benchmark Command

**File**: `protected/commands/PerformanceBenchmarkCommand.php`

```php
<?php
class PerformanceBenchmarkCommand extends CConsoleCommand
{
    public function actionRun($iterations = 100)
    {
        echo "Performance Benchmark\n";
        echo "=====================\n\n";

        $benchmarks = [
            'patient_lookup' => function() {
                $patient = Patient::model()->find();
                return PatientDocument::findById($patient->id);
            },
            'patient_search' => function() {
                return OptimizedQueries::searchPatients(['last_name' => 'Smith'], 0, 10);
            },
            'episode_list' => function() {
                $patient = Patient::model()->find();
                return EpisodeDocument::findByPatient($patient->id);
            },
            'reference_lookup' => function() {
                return EventTypeDocument::findAll();
            },
        ];

        foreach ($benchmarks as $name => $benchmark) {
            $times = [];
            
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                $benchmark();
                $times[] = (microtime(true) - $start) * 1000;
            }
            
            sort($times);
            $p50 = $times[(int)($iterations * 0.5)];
            $p95 = $times[(int)($iterations * 0.95)];
            $p99 = $times[(int)($iterations * 0.99)];
            $avg = array_sum($times) / count($times);
            
            printf("%-20s p50: %6.2fms  p95: %6.2fms  p99: %6.2fms  avg: %6.2fms\n",
                $name, $p50, $p95, $p99, $avg
            );
        }
    }
}
```

**Lines**: ~60

---

## Summary

### Files to Create/Update
| File | Lines | Purpose |
|------|-------|---------|
| IndexAnalysisCommand.php | 160 | Index optimization |
| CouchbaseCacheService.php | 180 | Caching layer |
| CouchbaseConnection.php | 180 | Connection pooling |
| OptimizedQueries.php | 140 | Query patterns |
| CouchbasePerformanceMonitor.php | 140 | Performance monitoring |
| PerformanceBenchmarkCommand.php | 60 | Benchmarking |

### Performance Targets
- p95 latency < 50ms for most operations
- p99 latency < 200ms
- Connection pool utilization > 80%
- Cache hit rate > 70%

### Total Effort
- **New Files**: 6 files (~860 lines)
- **Estimated Duration**: 2 weeks

---

**Phase 15 Status**: SPECIFICATION COMPLETE  
**Ready for Implementation**: YES
