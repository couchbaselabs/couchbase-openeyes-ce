# Phase 10: Deployment & Cutover

## Overview
This phase covers the production deployment, cutover from MariaDB to Couchbase, and post-migration activities.

## Prerequisites
- Phase 9 completed (All testing passed)
- Pre-cutover checklist complete
- Stakeholder approval obtained
- Maintenance window scheduled

## Dependencies
- Phase 9: Testing & Validation

## Tasks

### 10.1 Pre-Deployment Preparation

#### 10.1.1 Production Couchbase Cluster Setup
**File**: `/docs/migration-mariadb-to-couchbase/production-setup.md`

```markdown
# Production Couchbase Setup

## Cluster Requirements

### Hardware (per node)
- CPU: 8+ cores
- RAM: 32GB minimum (64GB recommended)
- Storage: SSD, 500GB+
- Network: 10Gbps

### Recommended Topology
- 3+ nodes for high availability
- Data, Index, Query services distributed
- Cross-datacenter replication for DR

## Sizing Guide

Based on current OpenEyes data:
- Estimated documents: ~10 million
- Average document size: 2KB
- Total data size: ~20GB
- Recommended bucket RAM: 8GB
- Index RAM: 4GB
```

#### 10.1.2 Production Configuration
**File**: `/protected/config/local.sample/couchbase.production.php`

```php
<?php
/**
 * Production Couchbase configuration
 */

return [
    'connection' => [
        'hosts' => [
            getenv('COUCHBASE_HOST_1') ?: 'cb-node1.example.com',
            getenv('COUCHBASE_HOST_2') ?: 'cb-node2.example.com',
            getenv('COUCHBASE_HOST_3') ?: 'cb-node3.example.com',
        ],
        'username' => getenv('COUCHBASE_USER'),
        'password' => getenv('COUCHBASE_PASS'),
        'options' => [
            'timeout' => 10000,  // 10 seconds
            'connectTimeout' => 5000,
            'maxPoolSize' => 50,
        ],
    ],
    'bucket' => 'openeyes_prod',
    'scopes' => [
        'core' => 'core',
        'clinical' => 'clinical',
        'correspondence' => 'correspondence',
        'booking' => 'booking',
        'admin' => 'admin',
        'reference' => 'reference',
    ],
    'durability' => 'majority',  // Ensure writes replicated
];
```

### 10.2 Deployment Strategy

#### 10.2.1 Blue-Green Deployment Plan
```markdown
# Blue-Green Deployment

## Overview
Deploy Couchbase-enabled version alongside existing production.

## Steps

1. **Prepare Green Environment**
   - Deploy new application version with Couchbase support
   - Configure dual-write enabled
   - Couchbase reads disabled

2. **Data Sync**
   - Run full data migration
   - Enable incremental sync
   - Verify data integrity

3. **Smoke Test**
   - Route test traffic to green
   - Verify functionality
   - Check performance

4. **Traffic Switch**
   - Gradually shift traffic (10% -> 50% -> 100%)
   - Monitor for errors
   - Keep blue environment ready for rollback

5. **Enable Couchbase Reads**
   - Start with 10% of reads
   - Monitor performance
   - Increase gradually to 100%

6. **Decommission Blue**
   - After successful operation period (2-4 weeks)
   - Remove dual-write
   - Decommission blue environment
```

#### 10.2.2 Feature Flag Configuration
**File**: `/protected/config/couchbase-rollout.php`

```php
<?php
/**
 * Couchbase rollout configuration
 */

return [
    // Phase 1: Dual-write only
    'phase_1' => [
        'enable_dual_write' => true,
        'enable_couchbase_read' => false,
        'couchbase_read_percentage' => 0,
    ],
    
    // Phase 2: Partial reads
    'phase_2' => [
        'enable_dual_write' => true,
        'enable_couchbase_read' => true,
        'couchbase_read_percentage' => 10,
    ],
    
    // Phase 3: Majority reads
    'phase_3' => [
        'enable_dual_write' => true,
        'enable_couchbase_read' => true,
        'couchbase_read_percentage' => 50,
    ],
    
    // Phase 4: Full Couchbase
    'phase_4' => [
        'enable_dual_write' => true,
        'enable_couchbase_read' => true,
        'couchbase_read_percentage' => 100,
    ],
    
    // Phase 5: Couchbase primary (post-stabilization)
    'phase_5' => [
        'enable_dual_write' => false,
        'enable_couchbase_read' => true,
        'couchbase_read_percentage' => 100,
        'mariadb_fallback' => false,
    ],
];
```

### 10.3 Cutover Procedure

#### 10.3.1 Cutover Runbook
**File**: `/docs/migration-mariadb-to-couchbase/cutover-runbook.md`

```markdown
# Cutover Runbook

## Pre-Cutover (T-24h)

1. **Final Data Sync**
   ```bash
   php protected/yiic incrementalsync run
   ```

2. **Validate Data**
   ```bash
   php protected/yiic datavalidation run
   ```

3. **Verify Indexes**
   ```bash
   curl -u admin:password http://cb-node1:8093/admin/ping
   ```

4. **Test Health Endpoints**
   ```bash
   curl http://app-server/api/health/couchbase
   ```

## Cutover (T-0)

1. **Enable Maintenance Mode**
   ```bash
   php protected/yiic maintenance enable
   ```

2. **Final Sync**
   ```bash
   php protected/yiic incrementalsync run --final
   ```

3. **Verify Final Counts**
   ```bash
   php protected/yiic datavalidation run --tables=patient,episode,event
   ```

4. **Enable Couchbase Reads**
   ```bash
   php protected/yiic couchbase setPhase 2
   ```

5. **Disable Maintenance Mode**
   ```bash
   php protected/yiic maintenance disable
   ```

6. **Monitor**
   - Check error rates
   - Check response times
   - Check Couchbase metrics

## Post-Cutover Validation

1. **Smoke Tests**
   - Patient search
   - Patient view
   - Create examination
   - Create letter

2. **Performance Check**
   - Response time p99 < 500ms
   - Error rate < 0.1%

3. **User Acceptance**
   - Clinical team verification
   - Admin team verification
```

### 10.4 Monitoring Setup

#### 10.4.1 Health Check Endpoint
**File**: `/protected/modules/Api/controllers/HealthController.php` (add)

```php
<?php
// Add to existing health controller

public function actionCouchbase()
{
    try {
        $connection = Yii::app()->couchbase;
        
        // Test connectivity
        $pingResult = $connection->ping();
        
        // Test query
        $queryResult = $connection->query(
            "SELECT 1 as test FROM `openeyes`.`core`.`patient` LIMIT 1"
        );
        
        $this->renderJson([
            'status' => 'healthy',
            'connection' => 'ok',
            'query' => 'ok',
            'latency_ms' => $pingResult['latency'] ?? null,
            'timestamp' => date('c'),
        ]);
    } catch (\Exception $e) {
        http_response_code(503);
        $this->renderJson([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
            'timestamp' => date('c'),
        ]);
    }
}
```

#### 10.4.2 Prometheus Metrics
**File**: `/protected/components/monitoring/CouchbaseMetrics.php`

```php
<?php

namespace OE\Monitoring;

class CouchbaseMetrics
{
    private static $queryDurations = [];
    private static $queryErrors = 0;
    private static $queryTotal = 0;
    
    public static function recordQueryDuration(float $duration, string $operation)
    {
        self::$queryDurations[] = [
            'operation' => $operation,
            'duration' => $duration,
            'timestamp' => time(),
        ];
        self::$queryTotal++;
    }
    
    public static function recordError()
    {
        self::$queryErrors++;
    }
    
    public static function getMetrics(): array
    {
        $recentDurations = array_filter(self::$queryDurations, function($d) {
            return $d['timestamp'] > time() - 60;
        });
        
        $durations = array_column($recentDurations, 'duration');
        
        return [
            'couchbase_query_total' => self::$queryTotal,
            'couchbase_query_errors_total' => self::$queryErrors,
            'couchbase_query_duration_avg_ms' => count($durations) > 0 
                ? array_sum($durations) / count($durations) * 1000 
                : 0,
            'couchbase_query_duration_p99_ms' => self::percentile($durations, 99) * 1000,
        ];
    }
    
    private static function percentile(array $data, int $percentile): float
    {
        if (empty($data)) return 0;
        sort($data);
        $index = ($percentile / 100) * (count($data) - 1);
        return $data[(int) $index];
    }
}
```

### 10.5 Rollback Procedures

#### 10.5.1 Rollback Script
**File**: `/protected/scripts/couchbase/rollback.sh`

```bash
#!/bin/bash
# Emergency rollback to MariaDB

set -e

echo "=== ROLLBACK TO MARIADB ==="
echo "Starting at: $(date)"

# Disable Couchbase reads immediately
php protected/yiic couchbase disable

# Verify MariaDB connectivity
echo "Verifying MariaDB..."
php protected/yiic db ping

# Clear any cached Couchbase data
php protected/yiic cache clear

echo "Rollback complete at: $(date)"
echo "Application now using MariaDB only"
```

#### 10.5.2 Rollback Decision Tree
```markdown
# Rollback Decision Tree

## Trigger Conditions

### Immediate Rollback (No approval needed)
- Error rate > 5%
- Response time p99 > 5s
- Data integrity issues detected
- Couchbase cluster unavailable

### Evaluated Rollback (Manager approval)
- Error rate > 1% for 10+ minutes
- Performance degradation > 50%
- Critical functionality broken

## Rollback Steps

1. Run rollback script
2. Verify MariaDB connectivity
3. Monitor error rates
4. Notify stakeholders
5. Conduct post-mortem
```

### 10.6 Post-Migration Activities

#### 10.6.1 Post-Migration Checklist
```markdown
# Post-Migration Checklist

## Day 1
- [ ] Monitor error rates hourly
- [ ] Review performance metrics
- [ ] Address any user-reported issues

## Week 1
- [ ] Daily performance review
- [ ] User feedback collection
- [ ] Performance tuning as needed

## Week 2-4
- [ ] Increase Couchbase read percentage
- [ ] Continue monitoring
- [ ] Document learnings

## Month 2
- [ ] Evaluate dual-write removal
- [ ] Plan MariaDB decommissioning (if applicable)
- [ ] Optimize indexes based on usage

## Month 3+
- [ ] Complete MariaDB decommissioning plan
- [ ] Archive MariaDB backups
- [ ] Update disaster recovery procedures
```

#### 10.6.2 Performance Tuning Guide
```markdown
# Performance Tuning Guide

## Index Optimization

Review slow queries:
```sql
SELECT * FROM system:completed_requests 
WHERE elapsedTime > '100ms'
ORDER BY elapsedTime DESC
```

Create covering indexes for frequent queries.

## Memory Tuning

- Data bucket quota: 60% of total RAM
- Index quota: 30% of total RAM
- Query quota: 10% of total RAM

## Connection Pooling

Optimize pool size based on concurrent users:
- Max connections: 2 * (concurrent users / app servers)
```

### 10.7 MariaDB Decommissioning (Optional)

#### 10.7.1 Decommissioning Plan
```markdown
# MariaDB Decommissioning Plan

## Prerequisites
- 4+ weeks stable operation on Couchbase
- All reads from Couchbase
- Dual-write disabled
- Full Couchbase backup verified

## Steps

1. **Final Verification**
   - All data in Couchbase validated
   - No dependencies on MariaDB

2. **Remove Dual-Write Code** (optional)
   - Update models to remove MariaDB writes
   - Deploy updated application

3. **Archive MariaDB**
   - Full backup to cold storage
   - Document archive location

4. **Decommission**
   - Stop MariaDB services
   - Archive server configuration
   - Release infrastructure

5. **Update Documentation**
   - Architecture diagrams
   - Runbooks
   - DR procedures
```

## Testing Criteria

### Deployment Tests
- [ ] Blue-green deployment successful
- [ ] Traffic switching works
- [ ] Rollback tested

### Monitoring Tests
- [ ] Health checks work
- [ ] Metrics collected
- [ ] Alerts fire correctly

## Acceptance Criteria
- [ ] Production deployment successful
- [ ] Cutover completed
- [ ] Monitoring operational
- [ ] Rollback tested
- [ ] Documentation complete

## Rollback Plan
1. Execute rollback script
2. Verify MariaDB operation
3. Investigate issues
4. Plan re-attempt

## Definition of Done
- [ ] Production running on Couchbase
- [ ] Stable for 2+ weeks
- [ ] Performance acceptable
- [ ] No critical issues
- [ ] Stakeholder sign-off

---

*Phase 10 Completion Sign-off:*
- [ ] Technical Lead
- [ ] Operations Lead
- [ ] Clinical Lead
- [ ] Project Manager

*Estimated Duration: 1-2 weeks (cutover) + 4 weeks (stabilization)*

---

# Migration Complete

Upon successful completion of Phase 10, the OpenEyes system will be:
- Running entirely on Couchbase
- Fully tested and validated
- Monitored and supported
- Ready for future scale

Total Estimated Migration Duration: 23-35 weeks
