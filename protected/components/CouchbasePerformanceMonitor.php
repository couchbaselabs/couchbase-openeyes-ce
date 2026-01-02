<?php
/**
 * Performance monitoring for Couchbase operations
 * 
 * Tracks operation timing, collects metrics, and logs slow queries.
 * Provides performance reports and statistics.
 * 
 * Usage:
 *   $monitor = CouchbasePerformanceMonitor::getInstance();
 *   $timer = $monitor->startTimer('patient_search');
 *   // ... perform operation ...
 *   $monitor->endTimer($timer, ['criteria' => $searchCriteria]);
 */
class CouchbasePerformanceMonitor
{
    /**
     * @var CouchbasePerformanceMonitor Singleton instance
     */
    private static $_instance;
    
    /**
     * @var array Collected metrics
     */
    protected $metrics = [];
    
    /**
     * @var int Slow query threshold in milliseconds
     */
    protected $slowQueryThreshold = 100;
    
    /**
     * @var bool Enable/disable monitoring
     */
    protected $enabled = true;
    
    /**
     * @var array Slow query log
     */
    protected $slowQueries = [];
    
    /**
     * @var int Maximum slow queries to keep
     */
    protected $maxSlowQueries = 100;

    /**
     * Private constructor for singleton
     */
    private function __construct()
    {
        // Load threshold from config if available
        if (isset(Yii::app()->params['couchbase_slow_query_threshold'])) {
            $this->slowQueryThreshold = Yii::app()->params['couchbase_slow_query_threshold'];
        }
    }

    /**
     * Get singleton instance
     * 
     * @return CouchbasePerformanceMonitor
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Start timing an operation
     * 
     * @param string $operation Operation name
     * @return array Timer data
     */
    public function startTimer($operation)
    {
        if (!$this->enabled) {
            return null;
        }
        
        return [
            'operation' => $operation,
            'start' => microtime(true),
        ];
    }

    /**
     * End timing and record metrics
     * 
     * @param array|null $timer Timer data from startTimer
     * @param array $metadata Additional metadata
     * @return float Duration in milliseconds
     */
    public function endTimer($timer, $metadata = [])
    {
        if (!$this->enabled || !$timer) {
            return 0;
        }
        
        $duration = (microtime(true) - $timer['start']) * 1000; // Convert to ms
        
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
     * 
     * @param array $metric Metric data
     * @return void
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
                'durations' => [],
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
        $this->metrics[$operation]['durations'][] = $metric['duration_ms'];
        $this->metrics[$operation]['last'] = $metric;
        
        // Keep only last 1000 durations for percentile calculations
        if (count($this->metrics[$operation]['durations']) > 1000) {
            array_shift($this->metrics[$operation]['durations']);
        }
    }

    /**
     * Log slow query
     * 
     * @param string $operation Operation name
     * @param float $duration Duration in milliseconds
     * @param array $metadata Additional metadata
     * @return void
     */
    protected function logSlowQuery($operation, $duration, $metadata)
    {
        $slowQuery = [
            'operation' => $operation,
            'duration_ms' => $duration,
            'timestamp' => time(),
            'metadata' => $metadata,
        ];
        
        // Add to slow query log
        $this->slowQueries[] = $slowQuery;
        
        // Trim to max size
        if (count($this->slowQueries) > $this->maxSlowQueries) {
            array_shift($this->slowQueries);
        }
        
        $message = sprintf(
            "Slow Couchbase query: %s (%.2fms) - %s",
            $operation,
            $duration,
            json_encode($metadata)
        );
        
        Yii::log($message, CLogger::LEVEL_WARNING, 'couchbase.performance');
    }

    /**
     * Get summary statistics
     * 
     * @return array Summary data
     */
    public function getSummary()
    {
        $summary = [];
        
        foreach ($this->metrics as $operation => $data) {
            $avg = $data['count'] > 0 ? $data['total_ms'] / $data['count'] : 0;
            $p50 = $this->calculatePercentile($data['durations'], 50);
            $p95 = $this->calculatePercentile($data['durations'], 95);
            $p99 = $this->calculatePercentile($data['durations'], 99);
            
            $summary[$operation] = [
                'count' => $data['count'],
                'avg_ms' => round($avg, 2),
                'min_ms' => $data['min_ms'] === PHP_INT_MAX ? 0 : round($data['min_ms'], 2),
                'max_ms' => round($data['max_ms'], 2),
                'total_ms' => round($data['total_ms'], 2),
                'p50_ms' => round($p50, 2),
                'p95_ms' => round($p95, 2),
                'p99_ms' => round($p99, 2),
            ];
        }
        
        return $summary;
    }

    /**
     * Calculate percentile from array of values
     * 
     * @param array $values Array of numeric values
     * @param int $percentile Percentile (0-100)
     * @return float Percentile value
     */
    protected function calculatePercentile($values, $percentile)
    {
        if (empty($values)) {
            return 0;
        }
        
        $sorted = $values;
        sort($sorted);
        
        $index = (count($sorted) - 1) * ($percentile / 100);
        $lower = floor($index);
        $upper = ceil($index);
        
        if ($lower === $upper) {
            return $sorted[$lower];
        }
        
        $fraction = $index - $lower;
        return $sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * $fraction;
    }

    /**
     * Get performance report as string
     * 
     * @param bool $includeSlowQueries Include slow query log
     * @return string Report text
     */
    public function getReport($includeSlowQueries = false)
    {
        $summary = $this->getSummary();
        
        $report = "Couchbase Performance Report\n";
        $report .= "============================\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $report .= "Slow query threshold: {$this->slowQueryThreshold}ms\n\n";
        
        if (empty($summary)) {
            $report .= "No operations recorded.\n";
            return $report;
        }
        
        // Header
        $report .= sprintf(
            "%-40s %6s  %8s  %8s  %8s  %8s  %8s  %8s\n",
            'Operation', 'Count', 'Avg', 'Min', 'Max', 'p50', 'p95', 'p99'
        );
        $report .= str_repeat('-', 130) . "\n";
        
        // Sort by average time descending
        uasort($summary, function($a, $b) {
            return $b['avg_ms'] <=> $a['avg_ms'];
        });
        
        // Rows
        foreach ($summary as $operation => $stats) {
            $report .= sprintf(
                "%-40s %6d  %6.2fms  %6.2fms  %6.2fms  %6.2fms  %6.2fms  %6.2fms\n",
                substr($operation, 0, 40),
                $stats['count'],
                $stats['avg_ms'],
                $stats['min_ms'],
                $stats['max_ms'],
                $stats['p50_ms'],
                $stats['p95_ms'],
                $stats['p99_ms']
            );
        }
        
        // Totals
        $totalOps = array_sum(array_column($summary, 'count'));
        $totalTime = array_sum(array_column($summary, 'total_ms'));
        $avgTime = $totalOps > 0 ? $totalTime / $totalOps : 0;
        
        $report .= str_repeat('-', 130) . "\n";
        $report .= sprintf(
            "%-40s %6d  %6.2fms total\n\n",
            'TOTAL',
            $totalOps,
            $totalTime
        );
        
        // Slow queries
        if ($includeSlowQueries && !empty($this->slowQueries)) {
            $report .= "\nSlow Queries (" . count($this->slowQueries) . ")\n";
            $report .= str_repeat('=', 80) . "\n";
            
            // Show last 10 slow queries
            $recent = array_slice($this->slowQueries, -10);
            foreach ($recent as $sq) {
                $report .= sprintf(
                    "[%s] %s - %.2fms\n",
                    date('H:i:s', $sq['timestamp']),
                    $sq['operation'],
                    $sq['duration_ms']
                );
                if (!empty($sq['metadata'])) {
                    $report .= "  Metadata: " . json_encode($sq['metadata']) . "\n";
                }
            }
        }
        
        return $report;
    }

    /**
     * Get slow queries
     * 
     * @param int $limit Maximum queries to return
     * @return array Slow queries
     */
    public function getSlowQueries($limit = 100)
    {
        return array_slice($this->slowQueries, -$limit);
    }

    /**
     * Reset all metrics
     * 
     * @return void
     */
    public function reset()
    {
        $this->metrics = [];
        $this->slowQueries = [];
        
        Yii::log('Performance metrics reset', CLogger::LEVEL_INFO, 'couchbase.performance');
    }

    /**
     * Enable monitoring
     * 
     * @return void
     */
    public function enable()
    {
        $this->enabled = true;
    }

    /**
     * Disable monitoring
     * 
     * @return void
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Check if monitoring is enabled
     * 
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set slow query threshold
     * 
     * @param int $threshold Threshold in milliseconds
     * @return void
     */
    public function setSlowQueryThreshold($threshold)
    {
        $this->slowQueryThreshold = $threshold;
    }

    /**
     * Get operations exceeding threshold
     * 
     * @param float $threshold Threshold in milliseconds
     * @return array Operations with avg > threshold
     */
    public function getOperationsAboveThreshold($threshold)
    {
        $summary = $this->getSummary();
        
        return array_filter($summary, function($stats) use ($threshold) {
            return $stats['avg_ms'] > $threshold;
        });
    }

    /**
     * Get health status based on performance
     * 
     * @return array Health status with recommendations
     */
    public function getHealthStatus()
    {
        $summary = $this->getSummary();
        $issues = [];
        $warnings = [];
        
        foreach ($summary as $operation => $stats) {
            // Critical: p95 > 500ms
            if ($stats['p95_ms'] > 500) {
                $issues[] = "{$operation}: p95 is {$stats['p95_ms']}ms (target: <500ms)";
            }
            // Warning: p95 > 200ms
            elseif ($stats['p95_ms'] > 200) {
                $warnings[] = "{$operation}: p95 is {$stats['p95_ms']}ms (target: <200ms)";
            }
        }
        
        $status = empty($issues) ? ($empty($warnings) ? 'healthy' : 'warning') : 'critical';
        
        return [
            'status' => $status,
            'issues' => $issues,
            'warnings' => $warnings,
            'slow_queries_count' => count($this->slowQueries),
            'operations_monitored' => count($this->metrics),
        ];
    }
}
