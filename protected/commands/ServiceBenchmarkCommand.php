<?php
/**
 * (C) OpenEyes Foundation, 2025
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2025, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

/**
 * Benchmark service layer performance
 * 
 * Usage:
 *   yiic servicebenchmark run                    - Run all benchmarks
 *   yiic servicebenchmark patient                - Benchmark patient service
 *   yiic servicebenchmark compare                - Compare MariaDB vs Couchbase
 */
class ServiceBenchmarkCommand extends CConsoleCommand
{
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic servicebenchmark <action> [options]

ACTIONS
  run       - Run all service benchmarks
  patient   - Benchmark PatientService only
  episode   - Benchmark EpisodeService only
  compare   - Compare MariaDB vs Couchbase performance

OPTIONS
  --iterations=<n>  Number of iterations (default: 100)
  --verbose         Show detailed output

EXAMPLES
  yiic servicebenchmark run --iterations=50
  yiic servicebenchmark compare --verbose
EOD;
    }
    
    public function actionRun($iterations = 100, $verbose = false)
    {
        echo "=== Service Layer Benchmark ===\n\n";
        echo "Iterations: {$iterations}\n\n";
        
        $this->benchmarkPatientService($iterations, $verbose);
        echo "\n";
        $this->benchmarkEpisodeService($iterations, $verbose);
        echo "\n";
        $this->benchmarkEventService($iterations, $verbose);
    }
    
    public function actionPatient($iterations = 100, $verbose = false)
    {
        echo "=== PatientService Benchmark ===\n\n";
        $this->benchmarkPatientService($iterations, $verbose);
    }
    
    public function actionEpisode($iterations = 100, $verbose = false)
    {
        echo "=== EpisodeService Benchmark ===\n\n";
        $this->benchmarkEpisodeService($iterations, $verbose);
    }
    
    public function actionCompare($iterations = 50, $verbose = false)
    {
        echo "=== MariaDB vs Couchbase Comparison ===\n\n";
        
        // Get sample patient
        $patient = Patient::model()->find();
        if (!$patient) {
            echo "No patients found for testing\n";
            return;
        }
        
        $service = new \services\PatientService();
        
        // MariaDB only
        Yii::app()->params['enable_couchbase_read'] = false;
        $service = new \services\PatientService();
        
        $mariaDbTimes = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->readPatient($patient->id);
            $mariaDbTimes[] = (microtime(true) - $start) * 1000;
        }
        
        // Couchbase (if available)
        $couchbaseTimes = [];
        if ($this->isCouchbaseAvailable()) {
            Yii::app()->params['enable_couchbase_read'] = true;
            $service = new \services\PatientService();
            
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                try {
                    $service->readPatient($patient->id);
                    $couchbaseTimes[] = (microtime(true) - $start) * 1000;
                } catch (Exception $e) {
                    // Couchbase not fully setup, skip
                    break;
                }
            }
        }
        
        // Results
        echo "Patient Read Operation:\n";
        echo str_pad("Backend", 15) . str_pad("Avg (ms)", 12) . str_pad("Min", 12) . str_pad("Max", 12) . "\n";
        echo str_repeat("-", 51) . "\n";
        
        if (!empty($mariaDbTimes)) {
            $avg = array_sum($mariaDbTimes) / count($mariaDbTimes);
            printf("%-15s%-12.2f%-12.2f%-12.2f\n", "MariaDB", $avg, min($mariaDbTimes), max($mariaDbTimes));
        }
        
        if (!empty($couchbaseTimes)) {
            $avg = array_sum($couchbaseTimes) / count($couchbaseTimes);
            printf("%-15s%-12.2f%-12.2f%-12.2f\n", "Couchbase", $avg, min($couchbaseTimes), max($couchbaseTimes));
        } else {
            echo "Couchbase      Not available\n";
        }
        
        // Reset
        Yii::app()->params['enable_couchbase_read'] = false;
    }
    
    private function benchmarkPatientService($iterations, $verbose)
    {
        echo "PatientService:\n";
        
        $service = new \services\PatientService();
        
        // Get a sample patient
        $patient = Patient::model()->find();
        
        if ($patient) {
            // Read benchmark
            $times = [];
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                $service->readPatient($patient->id);
                $times[] = (microtime(true) - $start) * 1000;
            }
            $this->printStats("  readPatient()", $times, $verbose);
            
            // findByHosNum benchmark
            if ($patient->hos_num) {
                $times = [];
                for ($i = 0; $i < $iterations; $i++) {
                    $start = microtime(true);
                    $service->findByHosNum($patient->hos_num);
                    $times[] = (microtime(true) - $start) * 1000;
                }
                $this->printStats("  findByHosNum()", $times, $verbose);
            }
            
            // getEpisodes benchmark
            $times = [];
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                $service->getEpisodes($patient->id);
                $times[] = (microtime(true) - $start) * 1000;
            }
            $this->printStats("  getEpisodes()", $times, $verbose);
        } else {
            echo "  Skipped: No patients\n";
        }
    }
    
    private function benchmarkEpisodeService($iterations, $verbose)
    {
        echo "EpisodeService:\n";
        
        $service = new \services\EpisodeService();
        
        $patient = Patient::model()->find();
        if (!$patient) {
            echo "  Skipped: No patients\n";
            return;
        }
        
        // getForPatient
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->getForPatient($patient->id);
            $times[] = (microtime(true) - $start) * 1000;
        }
        $this->printStats("  getForPatient()", $times, $verbose);
        
        // getStatistics
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->getStatistics([
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-m-d'),
            ]);
            $times[] = (microtime(true) - $start) * 1000;
        }
        $this->printStats("  getStatistics()", $times, $verbose);
    }
    
    private function benchmarkEventService($iterations, $verbose)
    {
        echo "EventService:\n";
        
        $service = new \services\EventService();
        
        $patient = Patient::model()->find();
        if (!$patient) {
            echo "  Skipped: No patients\n";
            return;
        }
        
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->getForPatient($patient->id, 20);
            $times[] = (microtime(true) - $start) * 1000;
        }
        $this->printStats("  getForPatient()", $times, $verbose);
    }
    
    private function printStats($label, $times, $verbose)
    {
        if (empty($times)) {
            printf("%-25s No data\n", $label);
            return;
        }
        
        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);
        
        printf("%-25s avg: %6.2fms, min: %6.2fms, max: %6.2fms\n", $label, $avg, $min, $max);
        
        if ($verbose) {
            $p95 = $this->percentile($times, 95);
            $p99 = $this->percentile($times, 99);
            printf("%-25s p95: %6.2fms, p99: %6.2fms\n", "", $p95, $p99);
        }
    }
    
    private function percentile($array, $percentile)
    {
        if (empty($array)) {
            return 0;
        }
        
        sort($array);
        $index = ($percentile / 100) * count($array);
        
        if (floor($index) == $index) {
            return ($array[$index - 1] + $array[$index]) / 2;
        }
        
        return $array[floor($index)];
    }
    
    private function isCouchbaseAvailable()
    {
        try {
            $conn = Yii::app()->getComponent('couchbase');
            return $conn !== null;
        } catch (Exception $e) {
            return false;
        }
    }
}
