<?php
/**
 * Performance benchmarking command for Couchbase operations
 * 
 * Runs benchmarks against common operations and compares against targets.
 * 
 * Commands:
 *   - run: Run full benchmark suite
 *   - quick: Run quick benchmark (fewer iterations)
 *   - operation: Benchmark specific operation
 */
class PerformanceBenchmarkCommand extends CConsoleCommand
{
    /**
     * @var array Performance targets (p95 in milliseconds)
     */
    protected $targets = [
        'patient_lookup' => 10,
        'patient_search' => 50,
        'episode_list' => 30,
        'event_timeline' => 40,
        'examination_load' => 80,
        'reference_lookup' => 5,
    ];

    /**
     * Run full benchmark suite
     * 
     * @param int $iterations Number of iterations per benchmark (default: 100)
     */
    public function actionRun($iterations = 100)
    {
        echo "Couchbase Performance Benchmark\n";
        echo "================================\n";
        echo "Iterations: {$iterations}\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

        $benchmarks = $this->getBenchmarks();
        $results = [];

        foreach ($benchmarks as $name => $benchmark) {
            echo "Running: {$name}... ";
            
            try {
                $result = $this->runBenchmark($name, $benchmark, $iterations);
                $results[$name] = $result;
                
                $status = $this->getStatus($name, $result['p95']);
                echo "{$status}\n";
            } catch (Exception $e) {
                echo "✗ Error: {$e->getMessage()}\n";
                $results[$name] = ['error' => $e->getMessage()];
            }
        }

        echo "\n";
        $this->printResults($results);
        $this->printSummary($results);
    }

    /**
     * Run quick benchmark (10 iterations)
     */
    public function actionQuick()
    {
        $this->actionRun(10);
    }

    /**
     * Benchmark specific operation
     * 
     * @param string $operation Operation name
     * @param int $iterations Number of iterations
     */
    public function actionOperation($operation, $iterations = 100)
    {
        echo "Benchmarking: {$operation}\n";
        echo "=======================\n\n";

        $benchmarks = $this->getBenchmarks();
        
        if (!isset($benchmarks[$operation])) {
            echo "Error: Unknown operation '{$operation}'\n";
            echo "Available operations: " . implode(', ', array_keys($benchmarks)) . "\n";
            return 1;
        }

        try {
            $result = $this->runBenchmark($operation, $benchmarks[$operation], $iterations);
            $this->printDetailedResult($operation, $result);
        } catch (Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
    }

    /**
     * Get benchmark definitions
     * 
     * @return array Benchmark functions
     */
    protected function getBenchmarks()
    {
        return [
            'patient_lookup' => function() {
                // Get a random existing patient
                $patient = Patient::model()->find(['limit' => 1]);
                if (!$patient) {
                    throw new Exception('No patients found');
                }
                
                // Lookup via Couchbase
                return Yii::app()->couchbase->get('clinical', 'patient', "patient::{$patient->id}");
            },
            
            'patient_search' => function() {
                return OptimizedQueries::searchPatients(['last_name' => 'Smith'], 0, 10);
            },
            
            'episode_list' => function() {
                $patient = Patient::model()->find(['limit' => 1]);
                if (!$patient) {
                    throw new Exception('No patients found');
                }
                
                return OptimizedQueries::getPatientEpisodes($patient->id, null, 20);
            },
            
            'event_timeline' => function() {
                $episode = Episode::model()->find(['limit' => 1]);
                if (!$episode) {
                    throw new Exception('No episodes found');
                }
                
                return OptimizedQueries::getEpisodeTimeline($episode->id, 20);
            },
            
            'examination_load' => function() {
                // Find an examination event
                $event = Event::model()->find([
                    'condition' => 'event_type_id = :typeId',
                    'params' => [':typeId' => EventType::model()->find("name = 'Examination'")->id ?? 1],
                    'limit' => 1
                ]);
                
                if (!$event) {
                    throw new Exception('No examination events found');
                }
                
                return OptimizedQueries::getExaminationElements($event->id);
            },
            
            'reference_lookup' => function() {
                // Lookup event types (common reference data)
                $query = "SELECT * FROM `openeyes`.`reference`.`event_type` LIMIT 10";
                $result = Yii::app()->couchbase->query($query);
                return $result->rows();
            },
        ];
    }

    /**
     * Run a single benchmark
     * 
     * @param string $name Benchmark name
     * @param callable $benchmark Benchmark function
     * @param int $iterations Number of iterations
     * @return array Results
     */
    protected function runBenchmark($name, $benchmark, $iterations)
    {
        $times = [];
        $errors = 0;
        
        // Warm-up
        try {
            $benchmark();
        } catch (Exception $e) {
            // Ignore warm-up errors
        }
        
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $start = microtime(true);
                $benchmark();
                $duration = (microtime(true) - $start) * 1000; // ms
                $times[] = $duration;
            } catch (Exception $e) {
                $errors++;
                Yii::log("Benchmark error in {$name}: " . $e->getMessage(), CLogger::LEVEL_WARNING);
            }
        }
        
        if (empty($times)) {
            throw new Exception("All benchmark iterations failed");
        }
        
        sort($times);
        
        return [
            'count' => count($times),
            'errors' => $errors,
            'min' => min($times),
            'max' => max($times),
            'avg' => array_sum($times) / count($times),
            'p50' => $this->percentile($times, 50),
            'p95' => $this->percentile($times, 95),
            'p99' => $this->percentile($times, 99),
            'times' => $times,
        ];
    }

    /**
     * Calculate percentile
     * 
     * @param array $values Sorted values
     * @param int $percentile Percentile (0-100)
     * @return float Percentile value
     */
    protected function percentile($values, $percentile)
    {
        $index = (count($values) - 1) * ($percentile / 100);
        $lower = floor($index);
        $upper = ceil($index);
        
        if ($lower === $upper) {
            return $values[$lower];
        }
        
        $fraction = $index - $lower;
        return $values[$lower] + ($values[$upper] - $values[$lower]) * $fraction;
    }

    /**
     * Print results table
     * 
     * @param array $results Benchmark results
     */
    protected function printResults($results)
    {
        echo "Results\n";
        echo "=======\n\n";
        
        echo sprintf(
            "%-25s %8s %8s %8s %8s %8s %10s\n",
            'Operation', 'Min', 'Avg', 'Max', 'p50', 'p95', 'Status'
        );
        echo str_repeat('-', 85) . "\n";
        
        foreach ($results as $name => $result) {
            if (isset($result['error'])) {
                echo sprintf("%-25s %s\n", $name, "ERROR: " . $result['error']);
                continue;
            }
            
            $target = isset($this->targets[$name]) ? $this->targets[$name] : null;
            $status = $this->getStatusSymbol($name, $result['p95']);
            
            echo sprintf(
                "%-25s %6.2fms %6.2fms %6.2fms %6.2fms %6.2fms %10s\n",
                $name,
                $result['min'],
                $result['avg'],
                $result['max'],
                $result['p50'],
                $result['p95'],
                $status
            );
        }
        
        echo "\n";
    }

    /**
     * Print detailed result for single operation
     * 
     * @param string $name Operation name
     * @param array $result Result data
     */
    protected function printDetailedResult($name, $result)
    {
        echo "Operation: {$name}\n";
        echo str_repeat('-', 40) . "\n";
        echo "Iterations: {$result['count']}\n";
        echo "Errors: {$result['errors']}\n";
        echo "\n";
        
        echo "Timings:\n";
        echo "  Min:     " . sprintf("%6.2fms", $result['min']) . "\n";
        echo "  Average: " . sprintf("%6.2fms", $result['avg']) . "\n";
        echo "  Max:     " . sprintf("%6.2fms", $result['max']) . "\n";
        echo "  p50:     " . sprintf("%6.2fms", $result['p50']) . "\n";
        echo "  p95:     " . sprintf("%6.2fms", $result['p95']) . "\n";
        echo "  p99:     " . sprintf("%6.2fms", $result['p99']) . "\n";
        echo "\n";
        
        if (isset($this->targets[$name])) {
            $target = $this->targets[$name];
            $diff = $result['p95'] - $target;
            $status = $diff <= 0 ? "PASS" : "FAIL";
            
            echo "Target (p95): {$target}ms\n";
            echo "Status: {$status} ";
            
            if ($diff > 0) {
                echo "(+" . sprintf("%.2f", $diff) . "ms over target)\n";
            } else {
                echo "(" . sprintf("%.2f", abs($diff)) . "ms under target)\n";
            }
        }
    }

    /**
     * Print summary
     * 
     * @param array $results Benchmark results
     */
    protected function printSummary($results)
    {
        echo "Summary\n";
        echo "=======\n\n";
        
        $passed = 0;
        $failed = 0;
        $untested = 0;
        
        foreach ($results as $name => $result) {
            if (isset($result['error'])) {
                continue;
            }
            
            if (!isset($this->targets[$name])) {
                $untested++;
                continue;
            }
            
            $target = $this->targets[$name];
            if ($result['p95'] <= $target) {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        $total = $passed + $failed;
        
        echo "Benchmarks: " . count($results) . "\n";
        echo "With targets: {$total}\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n";
        
        if ($failed > 0) {
            echo "\n";
            echo "⚠ Some benchmarks did not meet performance targets.\n";
            echo "  Consider index optimization or query tuning.\n";
        } else if ($total > 0) {
            echo "\n";
            echo "✓ All benchmarks met performance targets!\n";
        }
    }

    /**
     * Get status for operation
     * 
     * @param string $name Operation name
     * @param float $p95 p95 latency
     * @return string Status text
     */
    protected function getStatus($name, $p95)
    {
        if (!isset($this->targets[$name])) {
            return sprintf("p95: %.2fms", $p95);
        }
        
        $target = $this->targets[$name];
        $diff = $p95 - $target;
        
        if ($diff <= 0) {
            return sprintf("✓ p95: %.2fms (target: %.0fms)", $p95, $target);
        } else {
            return sprintf("✗ p95: %.2fms (target: %.0fms, +%.2fms)", $p95, $target, $diff);
        }
    }

    /**
     * Get status symbol
     * 
     * @param string $name Operation name
     * @param float $p95 p95 latency
     * @return string Status symbol
     */
    protected function getStatusSymbol($name, $p95)
    {
        if (!isset($this->targets[$name])) {
            return "-";
        }
        
        $target = $this->targets[$name];
        return $p95 <= $target ? "✓ PASS" : "✗ FAIL";
    }
}
