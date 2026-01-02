# Phase 15: Performance Optimization - Quick Start Guide

## Overview
Phase 15 adds performance optimization features including connection pooling, caching, optimized queries, and performance monitoring.

---

## Step 1: Create Indexes (Required First)

### Create All Indexes
```bash
# Create covering indexes (4 indexes)
php protected/yiic indexanalysis createCoveringIndexes

# Create composite indexes (8 indexes)
php protected/yiic indexanalysis createCompositeIndexes
```

**Expected Output**:
```
Creating Covering Indexes
=========================

Creating: idx_patient_search_covering... ✓ Created
Creating: idx_episode_list_covering... ✓ Created
Creating: idx_event_timeline_covering... ✓ Created
Creating: idx_audit_patient_covering... ✓ Created

Summary: 4 created, 0 skipped, 0 failed
```

### Verify Indexes
```bash
php protected/yiic indexanalysis listIndexes
```

---

## Step 2: Run Performance Benchmarks

### Quick Benchmark (10 iterations)
```bash
php protected/yiic performancebenchmark quick
```

### Full Benchmark (100 iterations)
```bash
php protected/yiic performancebenchmark run 100
```

### Benchmark Specific Operation
```bash
php protected/yiic performancebenchmark operation patient_lookup 100
```

**Sample Output**:
```
Couchbase Performance Benchmark
================================
Iterations: 100

Running: patient_lookup... ✓ p95: 8.45ms (target: 10ms)
Running: patient_search... ✓ p95: 42.30ms (target: 50ms)
Running: episode_list... ✓ p95: 28.15ms (target: 30ms)

Results
=======
Operation                      Min      Avg      Max      p50      p95     Status
---------------------------------------------------------------------------------
patient_lookup               5.20ms   7.50ms  12.30ms   7.40ms   8.45ms  ✓ PASS
patient_search              32.10ms  39.80ms  58.20ms  39.50ms  42.30ms  ✓ PASS
episode_list                21.40ms  26.30ms  35.80ms  26.10ms  28.15ms  ✓ PASS

Summary
=======
Benchmarks: 3
With targets: 3
Passed: 3
Failed: 0

✓ All benchmarks met performance targets!
```

---

## Step 3: Use Caching in Your Code

### Example 1: Patient Summary Cache
```php
// In PatientController.php
public function actionView($id)
{
    $cache = new CouchbaseCacheService();
    
    // Get or load patient summary
    $summary = $cache->getOrSet(
        "patient_summary::{$id}",
        function() use ($id) {
            return $this->loadPatientSummary($id);
        },
        300 // 5 minute cache
    );
    
    $this->render('view', ['summary' => $summary]);
}

// When patient is updated
public function actionUpdate($id)
{
    // ... save patient ...
    
    $cache = new CouchbaseCacheService();
    $cache->invalidatePatientCache($id);
}
```

### Example 2: Lookup Table Cache
```php
// In BaseController.php or bootstrap
public function getEventTypes()
{
    $cache = new CouchbaseCacheService();
    
    return $cache->getOrSet(
        'lookup::event_types',
        function() {
            return EventType::model()->findAll();
        },
        86400 // 24 hour cache
    );
}
```

---

## Step 4: Use Optimized Queries

### Example 1: Patient Search
```php
// Instead of:
$patients = Patient::model()
    ->findAll('last_name = :name', [':name' => $lastName]);

// Use optimized query:
$patients = OptimizedQueries::searchPatients(
    ['last_name' => $lastName],
    0,   // offset
    50   // limit
);
```

### Example 2: Episode Timeline
```php
// Instead of multiple queries:
$episode = Episode::model()->findByPk($episodeId);
$events = Event::model()->findAll('episode_id = :id', [':id' => $episodeId]);

// Use single optimized query:
$timeline = OptimizedQueries::getEpisodeTimeline($episodeId, 50);
```

### Example 3: Dashboard Stats
```php
// Efficient aggregation
$stats = OptimizedQueries::getDashboardStats(
    $siteId,
    '2024-01-01',
    '2024-12-31'
);

echo "Examinations: {$stats[0]['examinations']}\n";
echo "Operations: {$stats[0]['operations']}\n";
```

---

## Step 5: Monitor Performance

### Get Performance Report
```php
// In a debug action or scheduled task
$monitor = CouchbasePerformanceMonitor::getInstance();
echo $monitor->getReport(true); // Include slow queries
```

### Track Operation Performance
```php
public function actionComplexOperation()
{
    $monitor = CouchbasePerformanceMonitor::getInstance();
    
    $timer = $monitor->startTimer('complex_operation');
    
    // ... perform operation ...
    
    $duration = $monitor->endTimer($timer, [
        'user_id' => Yii::app()->user->id,
        'params' => $this->getActionParams()
    ]);
    
    Yii::log("Operation took {$duration}ms", CLogger::LEVEL_INFO);
}
```

### Check Health Status
```php
$monitor = CouchbasePerformanceMonitor::getInstance();
$health = $monitor->getHealthStatus();

if ($health['status'] === 'critical') {
    // Alert admin
    foreach ($health['issues'] as $issue) {
        Yii::log($issue, CLogger::LEVEL_ERROR);
    }
}
```

---

## Step 6: Analyze and Optimize

### Analyze Query Patterns
```bash
# Analyze last 7 days
php protected/yiic indexanalysis analyze 7

# Get recommendations for new indexes
```

### Check for Unused Indexes
```bash
# Dry run (no changes)
php protected/yiic indexanalysis dropUnusedIndexes true

# Actually drop (use with caution)
php protected/yiic indexanalysis dropUnusedIndexes false
```

---

## Configuration Options

### Set Slow Query Threshold
In `protected/config/common.php`:
```php
'params' => [
    'couchbase_slow_query_threshold' => 100, // milliseconds
],
```

### Configure Connection Pool
In your Couchbase config:
```php
'couchbase' => [
    'class' => 'CouchbaseConnection',
    'poolSize' => 10,
    'connectionTimeout' => 5000,
    'operationTimeout' => 10000,
    'queryTimeout' => 75000,
    'maxRetries' => 3,
    'retryDelay' => 100,
    // ... other config
],
```

### Adjust Cache TTLs
```php
$cache = new CouchbaseCacheService();

// Short-lived (5 minutes)
$cache->set('key', $data, 300);

// Medium (1 hour)
$cache->set('key', $data, 3600);

// Long-lived (24 hours)
$cache->set('key', $data, 86400);
```

---

## Troubleshooting

### Indexes Not Created
```bash
# Check Couchbase server logs
# Verify you have CREATE INDEX permission
# Try creating indexes manually in Couchbase UI
```

### Benchmarks Failing
```bash
# Ensure there's test data in the database
# Check if indexes exist
php protected/yiic indexanalysis listIndexes

# Run with fewer iterations
php protected/yiic performancebenchmark run 10
```

### Cache Not Working
```php
// Check if Couchbase connection is active
$connected = Yii::app()->couchbase->isConnected();

// Test cache manually
$cache = new CouchbaseCacheService();
$cache->set('test', ['value' => 123], 60);
$result = $cache->get('test');
var_dump($result); // Should show ['value' => 123]
```

### Slow Queries
```php
// Get slow query log
$monitor = CouchbasePerformanceMonitor::getInstance();
$slowQueries = $monitor->getSlowQueries(20);

foreach ($slowQueries as $sq) {
    echo "{$sq['operation']}: {$sq['duration_ms']}ms\n";
    print_r($sq['metadata']);
}
```

---

## Performance Tips

### 1. Use Covering Indexes
Always use `USE INDEX` hints in queries:
```sql
SELECT * FROM `openeyes`.`clinical`.`patient` 
USE INDEX (idx_patient_search_covering USING GSI)
WHERE hos_num = $hosNum
```

### 2. Cache Aggressively
- Cache lookup tables for 24h
- Cache patient summaries for 5 minutes
- Cache reference data indefinitely (invalidate on update)

### 3. Batch Operations
```php
// Instead of multiple gets:
foreach ($ids as $id) {
    $doc = $couchbase->get('scope', 'collection', $id);
}

// Use batch get:
$docs = OptimizedQueries::batchGet('scope', 'collection', $ids);
```

### 4. Use Retry Logic
Always use `executeWithRetry()` for critical operations:
```php
$result = Yii::app()->couchbase->executeWithRetry(function() {
    // Critical operation
    return $this->performOperation();
});
```

### 5. Monitor Continuously
Set up a cron job to track performance:
```bash
# Every hour
0 * * * * php /path/to/yiic performancebenchmark quick >> /var/log/perf.log
```

---

## Success Metrics

After implementing Phase 15, you should see:

- ✓ Query latencies reduced by 30-50%
- ✓ Cache hit rate > 70%
- ✓ Fewer timeout errors
- ✓ Better connection pool utilization
- ✓ Improved user experience

---

## Support

For issues or questions:
1. Check logs: `application.couchbase`, `cache`, `couchbase.performance`
2. Review slow query logs
3. Run health check: `$monitor->getHealthStatus()`
4. Review benchmark results

---

## Next Steps

1. ✓ Create all indexes
2. ✓ Run initial benchmarks
3. ✓ Integrate caching in high-traffic areas
4. ✓ Monitor for 1 week
5. ✓ Analyze slow queries
6. ✓ Adjust indexes as needed
7. ✓ Fine-tune cache TTLs
8. ✓ Set up alerting for performance degradation

---

**Phase 15 Status**: READY FOR USE ✓
