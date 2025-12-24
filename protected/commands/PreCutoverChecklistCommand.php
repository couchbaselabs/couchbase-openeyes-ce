<?php
/**
 * Pre-Cutover Checklist Command
 * 
 * Validates system readiness for production cutover from MariaDB to Couchbase.
 * Runs comprehensive checks across infrastructure, data, performance, and configuration.
 * 
 * Phase 16: Production Cutover
 */

class PreCutoverChecklistCommand extends CConsoleCommand
{
    protected $checks = [];
    protected $passed = 0;
    protected $failed = 0;
    protected $warnings = 0;
    
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic precutoverchecklist run

DESCRIPTION
  Comprehensive pre-cutover validation checklist.
  Verifies system readiness for production cutover to Couchbase.

CHECKS
  Infrastructure:
    - Couchbase cluster healthy
    - MariaDB accessible
    - Network latency acceptable
    - Disk space sufficient

  Data Integrity:
    - Patient count matches
    - Episode count matches
    - Event count matches
    - Reference data complete

  Performance:
    - Patient lookup < 50ms
    - Search performance < 100ms
    - Index coverage adequate

  Configuration:
    - Cutover config valid
    - Fallback enabled
    - Monitoring configured

EXIT CODES
  0 - All checks passed (GO for cutover)
  1 - One or more checks failed (NO-GO)

EOD;
    }
    
    /**
     * Run pre-cutover checklist
     */
    public function actionRun()
    {
        echo "===========================================\n";
        echo "PRE-CUTOVER CHECKLIST\n";
        echo "===========================================\n";
        echo "Started: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Infrastructure checks
        $this->section("Infrastructure");
        $this->check("Couchbase cluster healthy", [$this, 'checkCouchbaseHealth']);
        $this->check("MariaDB accessible", [$this, 'checkMariaDBHealth']);
        $this->check("Network latency acceptable", [$this, 'checkNetworkLatency']);
        $this->check("Disk space sufficient", [$this, 'checkDiskSpace']);
        
        // Data integrity checks
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
        echo "Passed:   {$this->passed}\n";
        echo "Failed:   {$this->failed}\n";
        echo "Warnings: {$this->warnings}\n";
        echo "\n";
        
        $ready = $this->failed === 0;
        
        if ($ready && $this->warnings === 0) {
            echo "✓ CUTOVER READY: YES (All checks passed)\n";
        } else if ($ready && $this->warnings > 0) {
            echo "⚠ CUTOVER READY: YES WITH WARNINGS ({$this->warnings} warnings)\n";
            echo "  Review warnings before proceeding.\n";
        } else {
            echo "✗ CUTOVER READY: NO ({$this->failed} checks failed)\n";
            echo "  Address failures before attempting cutover.\n";
        }
        
        echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";
        
        return $ready ? 0 : 1;
    }
    
    /**
     * Print section header
     */
    protected function section($name)
    {
        echo "\n{$name}\n";
        echo str_repeat('-', strlen($name)) . "\n";
    }
    
    /**
     * Run a check and display result
     */
    protected function check($name, $callback)
    {
        $result = call_user_func($callback);
        
        $status = '';
        if ($result['passed']) {
            $this->passed++;
            $status = '✓';
        } else if (isset($result['warning']) && $result['warning']) {
            $this->warnings++;
            $status = '⚠';
        } else {
            $this->failed++;
            $status = '✗';
        }
        
        echo "  {$status} {$name}";
        
        if (isset($result['detail'])) {
            echo " ({$result['detail']})";
        }
        
        echo "\n";
        
        if (isset($result['message'])) {
            echo "     {$result['message']}\n";
        }
    }
    
    /**
     * Check Couchbase cluster health
     */
    protected function checkCouchbaseHealth()
    {
        try {
            if (!Yii::app()->hasComponent('couchbase')) {
                return [
                    'passed' => false,
                    'detail' => 'not configured',
                ];
            }
            
            $adapter = Yii::app()->couchbase;
            $adapter->query("SELECT 1");
            
            return ['passed' => true, 'detail' => 'healthy'];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check MariaDB accessibility
     */
    protected function checkMariaDBHealth()
    {
        try {
            $count = Patient::model()->count();
            return ['passed' => true, 'detail' => "{$count} patients"];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check network latency
     */
    protected function checkNetworkLatency()
    {
        if (!Yii::app()->hasComponent('couchbase')) {
            return ['passed' => false, 'detail' => 'not configured'];
        }
        
        try {
            $times = [];
            
            for ($i = 0; $i < 10; $i++) {
                $start = microtime(true);
                Yii::app()->couchbase->query("SELECT 1");
                $times[] = (microtime(true) - $start) * 1000;
            }
            
            $avg = array_sum($times) / count($times);
            $passed = $avg < 20; // 20ms threshold
            
            return [
                'passed' => $passed,
                'warning' => !$passed && $avg < 50,
                'detail' => round($avg, 2) . 'ms avg',
                'message' => $passed ? null : 'Consider improving network connection',
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check disk space
     */
    protected function checkDiskSpace()
    {
        try {
            $runtimePath = Yii::app()->getRuntimePath();
            $freeSpace = disk_free_space($runtimePath);
            $totalSpace = disk_total_space($runtimePath);
            
            $freeGB = round($freeSpace / (1024 * 1024 * 1024), 2);
            $totalGB = round($totalSpace / (1024 * 1024 * 1024), 2);
            $freePercent = round(($freeSpace / $totalSpace) * 100, 1);
            
            $passed = $freePercent > 20; // 20% free minimum
            
            return [
                'passed' => $passed,
                'warning' => !$passed && $freePercent > 10,
                'detail' => "{$freeGB}GB free ({$freePercent}%)",
                'message' => $passed ? null : 'Low disk space detected',
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check patient count match
     */
    protected function checkPatientCount()
    {
        if (!Yii::app()->hasComponent('couchbase')) {
            return ['passed' => false, 'detail' => 'not configured'];
        }
        
        try {
            $mysql = Patient::model()->count();
            $cb = Yii::app()->couchbase->count('clinical', 'patient');
            
            $match = $mysql === $cb;
            $diff = abs($mysql - $cb);
            $diffPercent = $mysql > 0 ? ($diff / $mysql) * 100 : 0;
            
            return [
                'passed' => $match,
                'warning' => !$match && $diffPercent < 1,
                'detail' => "MySQL:{$mysql} CB:{$cb}",
                'message' => $match ? null : "Difference: {$diff} ({$diffPercent}%)",
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check episode count match
     */
    protected function checkEpisodeCount()
    {
        if (!Yii::app()->hasComponent('couchbase')) {
            return ['passed' => false, 'detail' => 'not configured'];
        }
        
        try {
            $mysql = Episode::model()->count();
            $cb = Yii::app()->couchbase->count('clinical', 'episode');
            
            $match = $mysql === $cb;
            $diff = abs($mysql - $cb);
            $diffPercent = $mysql > 0 ? ($diff / $mysql) * 100 : 0;
            
            return [
                'passed' => $match,
                'warning' => !$match && $diffPercent < 1,
                'detail' => "MySQL:{$mysql} CB:{$cb}",
                'message' => $match ? null : "Difference: {$diff} ({$diffPercent}%)",
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check event count match
     */
    protected function checkEventCount()
    {
        if (!Yii::app()->hasComponent('couchbase')) {
            return ['passed' => false, 'detail' => 'not configured'];
        }
        
        try {
            $mysql = Event::model()->count();
            $cb = Yii::app()->couchbase->count('clinical', 'event');
            
            $match = $mysql === $cb;
            $diff = abs($mysql - $cb);
            $diffPercent = $mysql > 0 ? ($diff / $mysql) * 100 : 0;
            
            return [
                'passed' => $match,
                'warning' => !$match && $diffPercent < 1,
                'detail' => "MySQL:{$mysql} CB:{$cb}",
                'message' => $match ? null : "Difference: {$diff} ({$diffPercent}%)",
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check reference data completeness
     */
    protected function checkReferenceData()
    {
        if (!Yii::app()->hasComponent('couchbase')) {
            return ['passed' => false, 'detail' => 'not configured'];
        }
        
        $tables = ['disorder', 'medication', 'procedure', 'event_type'];
        $allMatch = true;
        $details = [];
        
        foreach ($tables as $table) {
            $modelClass = str_replace('_', '', ucwords($table, '_'));
            if (!class_exists($modelClass)) continue;
            
            try {
                $mysql = $modelClass::model()->count();
                $cb = Yii::app()->couchbase->count('reference', $table);
                
                if ($mysql !== $cb) {
                    $allMatch = false;
                    $details[] = "{$table}:{$mysql}/{$cb}";
                }
            } catch (Exception $e) {
                $allMatch = false;
                $details[] = "{$table}:error";
            }
        }
        
        return [
            'passed' => $allMatch,
            'detail' => $allMatch ? 'all match' : implode(', ', $details),
        ];
    }
    
    /**
     * Check patient lookup performance
     */
    protected function checkPatientLookup()
    {
        if (!Yii::app()->hasComponent('couchbase')) {
            return ['passed' => false, 'detail' => 'not configured'];
        }
        
        try {
            $patient = Patient::model()->find();
            if (!$patient) {
                return ['passed' => true, 'detail' => 'no data'];
            }
            
            // Time Couchbase lookup
            $start = microtime(true);
            $key = 'patient::' . $patient->id;
            Yii::app()->couchbase->get('clinical', 'patient', $key);
            $duration = (microtime(true) - $start) * 1000;
            
            $passed = $duration < 50;
            
            return [
                'passed' => $passed,
                'warning' => !$passed && $duration < 100,
                'detail' => round($duration, 2) . 'ms',
                'message' => $passed ? null : 'Performance below target',
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check search performance
     */
    protected function checkSearchPerformance()
    {
        if (!Yii::app()->hasComponent('couchbase')) {
            return ['passed' => false, 'detail' => 'not configured'];
        }
        
        try {
            // Check if OptimizedQueries exists
            if (!class_exists('OptimizedQueries')) {
                return [
                    'passed' => false,
                    'detail' => 'not implemented',
                    'message' => 'OptimizedQueries class not found',
                ];
            }
            
            $start = microtime(true);
            OptimizedQueries::searchPatients(['last_name' => 'Smith'], 0, 10);
            $duration = (microtime(true) - $start) * 1000;
            
            $passed = $duration < 100;
            
            return [
                'passed' => $passed,
                'warning' => !$passed && $duration < 200,
                'detail' => round($duration, 2) . 'ms',
                'message' => $passed ? null : 'Performance below target',
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check index coverage
     */
    protected function checkIndexCoverage()
    {
        if (!Yii::app()->hasComponent('couchbase')) {
            return ['passed' => false, 'detail' => 'not configured'];
        }
        
        try {
            // Query for indexes
            $query = "SELECT COUNT(*) as count FROM system:indexes 
                      WHERE keyspace_id = 'openeyes'";
            
            $result = Yii::app()->couchbase->query($query);
            $count = $result[0]['count'] ?? 0;
            
            // Expect at least 10 indexes for core functionality
            $passed = $count >= 10;
            
            return [
                'passed' => $passed,
                'warning' => !$passed && $count >= 5,
                'detail' => "{$count} indexes",
                'message' => $passed ? null : 'Create additional indexes for better performance',
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check cutover configuration validity
     */
    protected function checkCutoverConfig()
    {
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $config = $manager->getConfig();
            
            $valid = !empty($config) && 
                     isset($config['enabled']) && 
                     isset($config['couchbase_read_percentage']);
            
            return [
                'passed' => $valid,
                'detail' => $valid ? 'valid' : 'invalid',
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check if fallback is enabled
     */
    protected function checkFallbackEnabled()
    {
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $config = $manager->getConfig();
            
            $enabled = $config['fallback_enabled'] ?? false;
            
            return [
                'passed' => $enabled,
                'detail' => $enabled ? 'enabled' : 'disabled',
                'message' => $enabled ? null : 'Fallback should be enabled for initial cutover',
            ];
        } catch (Exception $e) {
            return [
                'passed' => false,
                'detail' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check if monitoring is configured
     */
    protected function checkMonitoring()
    {
        $hasPerformanceMonitor = Yii::app()->hasComponent('couchbaseMonitor') || 
                                 class_exists('CouchbasePerformanceMonitor');
        
        $hasController = class_exists('CouchbaseMonitorController');
        
        $configured = $hasPerformanceMonitor && $hasController;
        
        return [
            'passed' => $configured,
            'detail' => $configured ? 'configured' : 'not configured',
            'message' => $configured ? null : 'Set up monitoring dashboard and performance monitor',
        ];
    }
}
