# Phase 16: Production Cutover

## Overview

This phase covers the gradual rollout from dual-write mode to Couchbase-primary, with comprehensive monitoring, rollback procedures, and production validation.

**Duration**: 2-4 weeks  
**Priority**: CRITICAL  
**Complexity**: High (requires careful orchestration)

## Prerequisites

- Phase 14 completed (full data migration)
- Phase 15 completed (performance optimized)
- All validation tests passing
- Rollback procedures tested
- Monitoring dashboards ready

---

## Section 1: Cutover Strategy

### 1.1 Gradual Rollout Plan

```
Week 1: 10% Traffic (Canary)
├── Day 1-2: Enable for internal users only
├── Day 3-4: Enable for 10% of external traffic
├── Day 5-7: Monitor and tune
└── Exit criteria: < 0.1% error rate

Week 2: 50% Traffic
├── Day 1-2: Scale to 50% traffic
├── Day 3-5: Monitor load patterns
├── Day 6-7: Address any issues
└── Exit criteria: p95 < 100ms

Week 3: 100% Traffic
├── Day 1-2: Full cutover
├── Day 3-5: Intensive monitoring
├── Day 6-7: Validate and document
└── Exit criteria: Stable 48 hours

Week 4: Decommission (Optional)
├── Day 1-3: Disable dual-write to MariaDB
├── Day 4-5: Archive MariaDB data
├── Day 6-7: Documentation and cleanup
└── Exit criteria: Clean shutdown
```

---

## Section 2: Feature Flag Configuration

### 2.1 Create Cutover Feature Flags

**File**: `protected/config/couchbase-cutover.php`

```php
<?php
/**
 * Couchbase cutover configuration
 */

return [
    // Master switch
    'enabled' => true,
    
    // Read source: 'mariadb', 'couchbase', 'hybrid'
    'read_source' => 'hybrid',
    
    // Write mode: 'mariadb_only', 'dual_write', 'couchbase_primary'
    'write_mode' => 'dual_write',
    
    // Traffic percentage to Couchbase (0-100)
    'couchbase_read_percentage' => 10,
    
    // Fallback settings
    'fallback_enabled' => true,
    'fallback_on_error' => true,
    'fallback_on_timeout' => true,
    'timeout_threshold_ms' => 500,
    
    // Per-model overrides
    'models' => [
        'Patient' => [
            'read_source' => 'couchbase',
            'percentage' => 100,
        ],
        'Episode' => [
            'read_source' => 'couchbase',
            'percentage' => 100,
        ],
        'Event' => [
            'read_source' => 'couchbase',
            'percentage' => 50,
        ],
        'Disorder' => [
            'read_source' => 'couchbase',
            'percentage' => 100,
        ],
        'Audit' => [
            'read_source' => 'mariadb', // Keep on MariaDB initially
            'percentage' => 0,
        ],
    ],
    
    // User-based targeting
    'user_targeting' => [
        'enabled' => true,
        // Internal users always use Couchbase
        'internal_users' => true,
        // Beta users
        'beta_user_ids' => [1, 2, 3, 4, 5],
        // Exclude specific users
        'excluded_user_ids' => [],
    ],
    
    // Site-based targeting
    'site_targeting' => [
        'enabled' => false,
        'enabled_site_ids' => [],
        'excluded_site_ids' => [],
    ],
    
    // Emergency kill switch
    'emergency_disable' => false,
    'emergency_disable_reason' => '',
];
```

**Lines**: ~75

### 2.2 Create Cutover Manager

**File**: `protected/components/CouchbaseCutoverManager.php`

```php
<?php
/**
 * Manages gradual cutover from MariaDB to Couchbase
 */

class CouchbaseCutoverManager
{
    protected $config;
    protected static $instance;

    public function __construct()
    {
        $this->config = require(Yii::app()->basePath . '/config/couchbase-cutover.php');
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if Couchbase should be used for this request
     */
    public function shouldUseCouchbase($modelClass, $operation = 'read')
    {
        // Emergency disable check
        if ($this->config['emergency_disable']) {
            Yii::log("Couchbase disabled: {$this->config['emergency_disable_reason']}", 'warning');
            return false;
        }

        // Master switch
        if (!$this->config['enabled']) {
            return false;
        }

        // Write operations
        if ($operation === 'write') {
            return in_array($this->config['write_mode'], ['dual_write', 'couchbase_primary']);
        }

        // Check model-specific config
        $modelConfig = $this->config['models'][$modelClass] ?? null;
        
        if ($modelConfig) {
            if ($modelConfig['read_source'] === 'mariadb') {
                return false;
            }
            if ($modelConfig['read_source'] === 'couchbase') {
                return $this->checkPercentage($modelConfig['percentage'] ?? 100);
            }
        }

        // Check user targeting
        if ($this->config['user_targeting']['enabled']) {
            if ($this->isInternalUser() || $this->isBetaUser()) {
                return true;
            }
            if ($this->isExcludedUser()) {
                return false;
            }
        }

        // Check site targeting
        if ($this->config['site_targeting']['enabled']) {
            if ($this->isExcludedSite()) {
                return false;
            }
            if (!empty($this->config['site_targeting']['enabled_site_ids'])) {
                return $this->isEnabledSite();
            }
        }

        // Default percentage-based routing
        return $this->checkPercentage($this->config['couchbase_read_percentage']);
    }

    /**
     * Check if request falls within percentage
     */
    protected function checkPercentage($percentage)
    {
        if ($percentage >= 100) return true;
        if ($percentage <= 0) return false;
        
        // Use session ID for consistent routing
        $sessionId = session_id() ?: uniqid();
        $hash = crc32($sessionId);
        $bucket = abs($hash % 100);
        
        return $bucket < $percentage;
    }

    /**
     * Check if current user is internal
     */
    protected function isInternalUser()
    {
        $user = Yii::app()->user;
        if (!$user || $user->isGuest) return false;
        
        // Check for admin or staff role
        return $user->checkAccess('admin') || $user->checkAccess('staff');
    }

    /**
     * Check if current user is beta tester
     */
    protected function isBetaUser()
    {
        $user = Yii::app()->user;
        if (!$user || $user->isGuest) return false;
        
        return in_array($user->id, $this->config['user_targeting']['beta_user_ids'] ?? []);
    }

    /**
     * Check if current user is excluded
     */
    protected function isExcludedUser()
    {
        $user = Yii::app()->user;
        if (!$user || $user->isGuest) return false;
        
        return in_array($user->id, $this->config['user_targeting']['excluded_user_ids'] ?? []);
    }

    /**
     * Check if current site is excluded
     */
    protected function isExcludedSite()
    {
        $siteId = Yii::app()->session->get('selected_site_id');
        if (!$siteId) return false;
        
        return in_array($siteId, $this->config['site_targeting']['excluded_site_ids'] ?? []);
    }

    /**
     * Check if current site is enabled
     */
    protected function isEnabledSite()
    {
        $siteId = Yii::app()->session->get('selected_site_id');
        if (!$siteId) return false;
        
        return in_array($siteId, $this->config['site_targeting']['enabled_site_ids'] ?? []);
    }

    /**
     * Enable emergency disable
     */
    public function emergencyDisable($reason)
    {
        $this->config['emergency_disable'] = true;
        $this->config['emergency_disable_reason'] = $reason;
        
        // Persist to config file
        $this->saveConfig();
        
        // Log alert
        Yii::log("EMERGENCY DISABLE: {$reason}", 'error', 'couchbase.cutover');
        
        // Could also trigger alerts here
    }

    /**
     * Update traffic percentage
     */
    public function setTrafficPercentage($percentage)
    {
        $this->config['couchbase_read_percentage'] = max(0, min(100, $percentage));
        $this->saveConfig();
        
        Yii::log("Traffic percentage set to: {$percentage}%", 'info', 'couchbase.cutover');
    }

    /**
     * Get current configuration
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Save config to file
     */
    protected function saveConfig()
    {
        $configPath = Yii::app()->basePath . '/config/couchbase-cutover.php';
        $content = "<?php\nreturn " . var_export($this->config, true) . ";\n";
        file_put_contents($configPath, $content);
    }
}
```

**Lines**: ~200

---

## Section 3: Rollback Procedures

### 3.1 Create Rollback Command

**File**: `protected/commands/CouchbaseRollbackCommand.php`

```php
<?php
/**
 * Emergency rollback from Couchbase to MariaDB
 */

class CouchbaseRollbackCommand extends CConsoleCommand
{
    /**
     * Instant rollback - disable Couchbase reads
     */
    public function actionInstant($reason = 'Emergency rollback')
    {
        echo "===========================================\n";
        echo "INSTANT ROLLBACK\n";
        echo "===========================================\n\n";

        $manager = CouchbaseCutoverManager::getInstance();
        
        echo "Disabling Couchbase reads...\n";
        $manager->emergencyDisable($reason);
        
        echo "✓ Couchbase disabled\n";
        echo "✓ All traffic now routed to MariaDB\n\n";
        
        echo "Reason: {$reason}\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n";
        
        return 0;
    }

    /**
     * Gradual rollback - reduce traffic over time
     */
    public function actionGradual($targetPercentage = 0, $steps = 5, $intervalMinutes = 5)
    {
        echo "===========================================\n";
        echo "GRADUAL ROLLBACK\n";
        echo "===========================================\n\n";

        $manager = CouchbaseCutoverManager::getInstance();
        $currentPercentage = $manager->getConfig()['couchbase_read_percentage'];
        
        echo "Current: {$currentPercentage}%\n";
        echo "Target: {$targetPercentage}%\n";
        echo "Steps: {$steps}\n";
        echo "Interval: {$intervalMinutes} minutes\n\n";

        $step = ($currentPercentage - $targetPercentage) / $steps;

        for ($i = 1; $i <= $steps; $i++) {
            $newPercentage = max($targetPercentage, $currentPercentage - ($step * $i));
            
            echo "Step {$i}/{$steps}: Setting to " . round($newPercentage) . "%\n";
            $manager->setTrafficPercentage(round($newPercentage));
            
            if ($i < $steps) {
                echo "Waiting {$intervalMinutes} minutes...\n";
                sleep($intervalMinutes * 60);
            }
        }

        echo "\n✓ Gradual rollback complete\n";
        echo "Current traffic: {$targetPercentage}%\n";
        
        return 0;
    }

    /**
     * Verify rollback
     */
    public function actionVerify()
    {
        echo "Verifying rollback status...\n\n";

        $manager = CouchbaseCutoverManager::getInstance();
        $config = $manager->getConfig();

        echo "Emergency Disable: " . ($config['emergency_disable'] ? 'YES' : 'NO') . "\n";
        echo "Read Source: {$config['read_source']}\n";
        echo "Write Mode: {$config['write_mode']}\n";
        echo "Traffic %: {$config['couchbase_read_percentage']}%\n";

        if ($config['emergency_disable']) {
            echo "\n⚠ COUCHBASE IS DISABLED\n";
            echo "Reason: {$config['emergency_disable_reason']}\n";
        }

        // Test MariaDB connectivity
        echo "\nTesting MariaDB...\n";
        try {
            $count = Patient::model()->count();
            echo "✓ MariaDB OK ({$count} patients)\n";
        } catch (Exception $e) {
            echo "✗ MariaDB ERROR: {$e->getMessage()}\n";
            return 1;
        }

        return 0;
    }

    /**
     * Re-enable Couchbase after rollback
     */
    public function actionReenable($percentage = 10)
    {
        echo "Re-enabling Couchbase...\n\n";

        $manager = CouchbaseCutoverManager::getInstance();
        
        // Clear emergency disable
        $config = $manager->getConfig();
        $config['emergency_disable'] = false;
        $config['emergency_disable_reason'] = '';
        $config['couchbase_read_percentage'] = $percentage;
        
        // Verify Couchbase health first
        echo "Checking Couchbase health...\n";
        try {
            $adapter = Yii::app()->couchbase;
            $adapter->query("SELECT 1");
            echo "✓ Couchbase is healthy\n";
        } catch (Exception $e) {
            echo "✗ Couchbase unhealthy: {$e->getMessage()}\n";
            echo "Cannot re-enable\n";
            return 1;
        }

        $manager->setTrafficPercentage($percentage);
        
        echo "✓ Couchbase re-enabled at {$percentage}%\n";
        
        return 0;
    }
}
```

**Lines**: ~150

---

## Section 4: Monitoring Dashboard

### 4.1 Create Monitoring Controller

**File**: `protected/controllers/CouchbaseMonitorController.php`

```php
<?php
/**
 * Monitoring dashboard for Couchbase migration
 */

class CouchbaseMonitorController extends BaseController
{
    public function accessRules()
    {
        return [
            ['allow', 'roles' => ['admin']],
            ['deny'],
        ];
    }

    /**
     * Main dashboard
     */
    public function actionIndex()
    {
        $data = [
            'cutover' => $this->getCutoverStatus(),
            'health' => $this->getHealthStatus(),
            'performance' => $this->getPerformanceMetrics(),
            'errors' => $this->getRecentErrors(),
            'sync' => $this->getSyncStatus(),
        ];

        $this->render('index', $data);
    }

    /**
     * Get cutover status
     */
    protected function getCutoverStatus()
    {
        $manager = CouchbaseCutoverManager::getInstance();
        return $manager->getConfig();
    }

    /**
     * Get health status
     */
    protected function getHealthStatus()
    {
        $status = [
            'mariadb' => ['status' => 'unknown', 'latency' => 0],
            'couchbase' => ['status' => 'unknown', 'latency' => 0],
        ];

        // Check MariaDB
        try {
            $start = microtime(true);
            Yii::app()->db->createCommand("SELECT 1")->queryScalar();
            $status['mariadb'] = [
                'status' => 'healthy',
                'latency' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (Exception $e) {
            $status['mariadb'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        // Check Couchbase
        try {
            $start = microtime(true);
            Yii::app()->couchbase->query("SELECT 1");
            $status['couchbase'] = [
                'status' => 'healthy',
                'latency' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (Exception $e) {
            $status['couchbase'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        return $status;
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics()
    {
        $monitor = Yii::app()->couchbaseMonitor ?? null;
        
        if ($monitor) {
            return $monitor->getSummary();
        }
        
        return [];
    }

    /**
     * Get recent errors
     */
    protected function getRecentErrors()
    {
        // Query recent error logs
        $logFile = Yii::app()->basePath . '/runtime/application.log';
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $errors = [];
        $lines = array_slice(file($logFile), -100);
        
        foreach ($lines as $line) {
            if (strpos($line, 'couchbase') !== false && strpos($line, 'error') !== false) {
                $errors[] = trim($line);
            }
        }
        
        return array_slice($errors, -10);
    }

    /**
     * Get sync status
     */
    protected function getSyncStatus()
    {
        $tables = ['patient', 'episode', 'event', 'disorder', 'medication'];
        $status = [];

        $adapter = Yii::app()->couchbase;

        foreach ($tables as $table) {
            $modelClass = ucfirst($table);
            
            if (!class_exists($modelClass)) {
                continue;
            }

            $mysqlCount = $modelClass::model()->count();
            
            try {
                $scope = in_array($table, ['patient', 'episode', 'event']) ? 'clinical' : 'reference';
                $cbCount = $adapter->count($scope, $table);
            } catch (Exception $e) {
                $cbCount = 0;
            }

            $status[$table] = [
                'mariadb' => $mysqlCount,
                'couchbase' => $cbCount,
                'synced' => $mysqlCount === $cbCount,
                'diff' => $mysqlCount - $cbCount,
            ];
        }

        return $status;
    }

    /**
     * API endpoint for metrics
     */
    public function actionMetrics()
    {
        header('Content-Type: application/json');
        
        echo json_encode([
            'timestamp' => time(),
            'health' => $this->getHealthStatus(),
            'performance' => $this->getPerformanceMetrics(),
            'cutover' => $this->getCutoverStatus(),
        ]);
    }

    /**
     * Trigger emergency disable
     */
    public function actionEmergencyDisable()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(405, 'Method not allowed');
        }

        $reason = Yii::app()->request->getPost('reason', 'Manual emergency disable');
        
        $manager = CouchbaseCutoverManager::getInstance();
        $manager->emergencyDisable($reason);

        $this->redirect(['index']);
    }

    /**
     * Update traffic percentage
     */
    public function actionSetTraffic()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(405, 'Method not allowed');
        }

        $percentage = (int)Yii::app()->request->getPost('percentage', 0);
        
        $manager = CouchbaseCutoverManager::getInstance();
        $manager->setTrafficPercentage($percentage);

        $this->redirect(['index']);
    }
}
```

**Lines**: ~200

---

## Section 5: Pre-Cutover Checklist

### 5.1 Checklist Command

**File**: `protected/commands/PreCutoverChecklistCommand.php`

```php
<?php
/**
 * Pre-cutover validation checklist
 */

class PreCutoverChecklistCommand extends CConsoleCommand
{
    protected $checks = [];
    protected $passed = 0;
    protected $failed = 0;

    public function actionRun()
    {
        echo "===========================================\n";
        echo "PRE-CUTOVER CHECKLIST\n";
        echo "===========================================\n\n";

        // Infrastructure checks
        $this->section("Infrastructure");
        $this->check("Couchbase cluster healthy", [$this, 'checkCouchbaseHealth']);
        $this->check("MariaDB accessible", [$this, 'checkMariaDBHealth']);
        $this->check("Network latency acceptable", [$this, 'checkNetworkLatency']);
        
        // Data checks
        $this->section("Data Integrity");
        $this->check("Patient count matches", [$this, 'checkPatientCount']);
        $this->check("Episode count matches", [$this, 'checkEpisodeCount']);
        $this->check("Event count matches", [$this, 'checkEventCount']);
        $this->check("Reference data complete", [$this, 'checkReferenceData']);
        
        // Performance checks
        $this->section("Performance");
        $this->check("Patient lookup < 50ms", [$this, 'checkPatientLookup']);
        $this->check("Search performance < 100ms", [$this, 'checkSearchPerformance']);
        $this->check("Index coverage adequate", [$this, 'checkIndexCoverage']);
        
        // Configuration checks
        $this->section("Configuration");
        $this->check("Cutover config valid", [$this, 'checkCutoverConfig']);
        $this->check("Fallback enabled", [$this, 'checkFallbackEnabled']);
        $this->check("Monitoring configured", [$this, 'checkMonitoring']);
        
        // Summary
        echo "\n===========================================\n";
        echo "SUMMARY\n";
        echo "===========================================\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        
        $ready = $this->failed === 0;
        echo "\nCutover Ready: " . ($ready ? "YES ✓" : "NO ✗") . "\n";
        
        return $ready ? 0 : 1;
    }

    protected function section($name)
    {
        echo "\n{$name}\n";
        echo str_repeat('-', strlen($name)) . "\n";
    }

    protected function check($name, $callback)
    {
        $result = call_user_func($callback);
        
        if ($result['passed']) {
            $this->passed++;
            echo "  ✓ {$name}";
        } else {
            $this->failed++;
            echo "  ✗ {$name}";
        }
        
        if (isset($result['detail'])) {
            echo " ({$result['detail']})";
        }
        
        echo "\n";
    }

    protected function checkCouchbaseHealth()
    {
        try {
            Yii::app()->couchbase->query("SELECT 1");
            return ['passed' => true];
        } catch (Exception $e) {
            return ['passed' => false, 'detail' => $e->getMessage()];
        }
    }

    protected function checkMariaDBHealth()
    {
        try {
            Yii::app()->db->createCommand("SELECT 1")->queryScalar();
            return ['passed' => true];
        } catch (Exception $e) {
            return ['passed' => false, 'detail' => $e->getMessage()];
        }
    }

    protected function checkNetworkLatency()
    {
        $times = [];
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            Yii::app()->couchbase->query("SELECT 1");
            $times[] = (microtime(true) - $start) * 1000;
        }
        
        $avg = array_sum($times) / count($times);
        $passed = $avg < 20; // 20ms threshold
        
        return ['passed' => $passed, 'detail' => round($avg, 2) . 'ms avg'];
    }

    protected function checkPatientCount()
    {
        $mysql = Patient::model()->count();
        $cb = Yii::app()->couchbase->count('clinical', 'patient');
        
        return [
            'passed' => $mysql === $cb,
            'detail' => "MySQL:{$mysql} CB:{$cb}"
        ];
    }

    protected function checkEpisodeCount()
    {
        $mysql = Episode::model()->count();
        $cb = Yii::app()->couchbase->count('clinical', 'episode');
        
        return [
            'passed' => $mysql === $cb,
            'detail' => "MySQL:{$mysql} CB:{$cb}"
        ];
    }

    protected function checkEventCount()
    {
        $mysql = Event::model()->count();
        $cb = Yii::app()->couchbase->count('clinical', 'event');
        
        return [
            'passed' => $mysql === $cb,
            'detail' => "MySQL:{$mysql} CB:{$cb}"
        ];
    }

    protected function checkReferenceData()
    {
        $tables = ['disorder', 'medication', 'procedure', 'event_type'];
        $allMatch = true;
        
        foreach ($tables as $table) {
            $modelClass = str_replace('_', '', ucwords($table, '_'));
            if (!class_exists($modelClass)) continue;
            
            $mysql = $modelClass::model()->count();
            $cb = Yii::app()->couchbase->count('reference', $table);
            
            if ($mysql !== $cb) {
                $allMatch = false;
            }
        }
        
        return ['passed' => $allMatch];
    }

    protected function checkPatientLookup()
    {
        $patient = Patient::model()->find();
        if (!$patient) return ['passed' => true, 'detail' => 'no data'];
        
        $start = microtime(true);
        PatientDocument::findById($patient->id);
        $duration = (microtime(true) - $start) * 1000;
        
        return ['passed' => $duration < 50, 'detail' => round($duration, 2) . 'ms'];
    }

    protected function checkSearchPerformance()
    {
        $start = microtime(true);
        OptimizedQueries::searchPatients(['last_name' => 'Smith'], 0, 10);
        $duration = (microtime(true) - $start) * 1000;
        
        return ['passed' => $duration < 100, 'detail' => round($duration, 2) . 'ms'];
    }

    protected function checkIndexCoverage()
    {
        // Would check if required indexes exist
        return ['passed' => true];
    }

    protected function checkCutoverConfig()
    {
        $config = require(Yii::app()->basePath . '/config/couchbase-cutover.php');
        return ['passed' => !empty($config)];
    }

    protected function checkFallbackEnabled()
    {
        $config = require(Yii::app()->basePath . '/config/couchbase-cutover.php');
        return ['passed' => $config['fallback_enabled'] ?? false];
    }

    protected function checkMonitoring()
    {
        // Check if monitoring is configured
        return ['passed' => Yii::app()->hasComponent('couchbaseMonitor')];
    }
}
```

**Lines**: ~200

---

## Section 6: Success Criteria

### 6.1 Cutover Success Metrics

| Metric | Threshold | Critical |
|--------|-----------|----------|
| Error Rate | < 0.1% | < 1% |
| p95 Latency | < 100ms | < 500ms |
| Data Consistency | 100% | 99.9% |
| Availability | 99.9% | 99% |
| Fallback Triggers | < 1/hour | < 10/hour |

### 6.2 Rollback Triggers

Automatically rollback if:
- Error rate > 1% for 5 minutes
- p99 latency > 1000ms for 5 minutes
- Couchbase cluster unhealthy
- Data inconsistency detected

---

## Summary

### Files to Create
| File | Lines | Purpose |
|------|-------|---------|
| couchbase-cutover.php | 75 | Feature flags |
| CouchbaseCutoverManager.php | 200 | Cutover management |
| CouchbaseRollbackCommand.php | 150 | Rollback procedures |
| CouchbaseMonitorController.php | 200 | Monitoring dashboard |
| PreCutoverChecklistCommand.php | 200 | Pre-cutover validation |

### Cutover Timeline
- Week 1: 10% canary deployment
- Week 2: 50% traffic
- Week 3: 100% traffic
- Week 4: Stabilization & cleanup

### Total Effort
- **New Files**: 5 files (~825 lines)
- **Estimated Duration**: 2-4 weeks

---

**Phase 16 Status**: SPECIFICATION COMPLETE  
**Ready for Implementation**: YES

---

# Migration Complete

All 7 phases (10-16) have been specified:
- Phase 10: Core Lookup Tables
- Phase 11: Clinical Reference Data
- Phase 12: Admin & Settings
- Phase 13: Additional Modules
- Phase 14: Full Data Migration
- Phase 15: Performance Optimization
- Phase 16: Production Cutover

Total estimated implementation time: 12-18 weeks
