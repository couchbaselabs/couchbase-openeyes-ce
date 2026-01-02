<?php
/**
 * Command to benchmark query performance: MariaDB vs Couchbase
 * 
 * Usage:
 *   yiic querybenchmark run                     - Run all benchmarks
 *   yiic querybenchmark patient                 - Benchmark patient queries
 *   yiic querybenchmark episode                 - Benchmark episode queries
 *   yiic querybenchmark compare --query=<name>  - Compare specific query
 */

class QueryBenchmarkCommand extends CConsoleCommand
{
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic querybenchmark <action> [options]

ACTIONS
  run         - Run all query benchmarks
  patient     - Benchmark patient queries
  episode     - Benchmark episode queries
  event       - Benchmark event queries
  compare     - Compare specific query performance

OPTIONS
  --query=<name>     Query name for compare action
  --iterations=<n>   Number of iterations (default: 10)
  --warmup=<n>       Warmup iterations (default: 2)

EXAMPLES
  yiic querybenchmark run
  yiic querybenchmark patient --iterations=20
  yiic querybenchmark compare --query=findByHosNum
EOD;
    }
    
    private $iterations = 10;
    private $warmupIterations = 2;
    
    /**
     * Run all benchmarks
     */
    public function actionRun($iterations = 10, $warmup = 2)
    {
        $this->iterations = (int)$iterations;
        $this->warmupIterations = (int)$warmup;
        
        echo "=== Query Performance Benchmark ===\n";
        echo "Iterations: {$this->iterations}, Warmup: {$this->warmupIterations}\n\n";
        
        $results = [];
        
        echo "Benchmarking Patient Queries...\n";
        $results['patient'] = $this->benchmarkPatientQueries();
        
        echo "\nBenchmarking Episode Queries...\n";
        $results['episode'] = $this->benchmarkEpisodeQueries();
        
        echo "\nBenchmarking Event Queries...\n";
        $results['event'] = $this->benchmarkEventQueries();
        
        $this->printSummary($results);
    }
    
    /**
     * Benchmark patient queries
     */
    public function actionPatient($iterations = 10, $warmup = 2)
    {
        $this->iterations = (int)$iterations;
        $this->warmupIterations = (int)$warmup;
        
        echo "=== Patient Query Benchmarks ===\n\n";
        $results = $this->benchmarkPatientQueries();
        $this->printResults('Patient', $results);
    }
    
    /**
     * Benchmark episode queries
     */
    public function actionEpisode($iterations = 10, $warmup = 2)
    {
        $this->iterations = (int)$iterations;
        $this->warmupIterations = (int)$warmup;
        
        echo "=== Episode Query Benchmarks ===\n\n";
        $results = $this->benchmarkEpisodeQueries();
        $this->printResults('Episode', $results);
    }
    
    /**
     * Benchmark event queries
     */
    public function actionEvent($iterations = 10, $warmup = 2)
    {
        $this->iterations = (int)$iterations;
        $this->warmupIterations = (int)$warmup;
        
        echo "=== Event Query Benchmarks ===\n\n";
        $results = $this->benchmarkEventQueries();
        $this->printResults('Event', $results);
    }
    
    private function benchmarkPatientQueries()
    {
        $results = [];
        
        // Get a sample patient
        $patient = Patient::model()->find(['limit' => 1]);
        if (!$patient) {
            echo "  No patients found, skipping\n";
            return $results;
        }
        
        // Benchmark: Find by Hospital Number
        $results['findByHosNum'] = $this->compareQuery(
            'findByHosNum',
            function() use ($patient) {
                // MariaDB
                return Patient::model()->findByAttributes(['hos_num' => $patient->hos_num]);
            },
            function() use ($patient) {
                // Couchbase
                $search = new \OE\Reports\CouchbasePatientSearch();
                return $search->findByHosNum($patient->hos_num);
            }
        );
        
        // Benchmark: Find by NHS Number
        if ($patient->nhs_num) {
            $results['findByNhsNum'] = $this->compareQuery(
                'findByNhsNum',
                function() use ($patient) {
                    return Patient::model()->findByAttributes(['nhs_num' => $patient->nhs_num]);
                },
                function() use ($patient) {
                    $search = new \OE\Reports\CouchbasePatientSearch();
                    return $search->findByNhsNum($patient->nhs_num);
                }
            );
        }
        
        // Benchmark: List patients (paginated)
        $results['listPatients'] = $this->compareQuery(
            'listPatients',
            function() {
                return Patient::model()->findAll(['limit' => 20]);
            },
            function() {
                $builder = new \OE\Database\N1qlQueryBuilder();
                return $builder->from('core', 'patient')->limit(20)->execute();
            }
        );
        
        return $results;
    }
    
    private function benchmarkEpisodeQueries()
    {
        $results = [];
        
        // Get a sample patient with episodes
        $patient = Patient::model()->with('episodes')->find();
        if (!$patient || empty($patient->episodes)) {
            echo "  No patients with episodes found, skipping\n";
            return $results;
        }
        
        // Benchmark: Patient episode history
        $results['patientHistory'] = $this->compareQuery(
            'patientHistory',
            function() use ($patient) {
                return Episode::model()->findAllByAttributes(['patient_id' => $patient->id]);
            },
            function() use ($patient) {
                $report = new \OE\Reports\CouchbaseEpisodeReport();
                return $report->patientHistory($patient->id);
            }
        );
        
        return $results;
    }
    
    private function benchmarkEventQueries()
    {
        $results = [];
        
        // Get a sample patient with events
        $patient = Patient::model()->with('episodes.events')->find();
        if (!$patient) {
            echo "  No patients found, skipping\n";
            return $results;
        }
        
        // Benchmark: Find events by patient
        $results['findByPatientId'] = $this->compareQuery(
            'findByPatientId',
            function() use ($patient) {
                // MariaDB - need to join through episodes
                $criteria = new CDbCriteria();
                $criteria->with = ['episode'];
                $criteria->condition = 'episode.patient_id = :patient_id';
                $criteria->params = [':patient_id' => $patient->id];
                $criteria->limit = 50;
                return Event::model()->findAll($criteria);
            },
            function() use ($patient) {
                $report = new \OE\Reports\CouchbaseEventReport();
                return $report->findByPatientId($patient->id, 50);
            }
        );
        
        return $results;
    }
    
    /**
     * Compare a single query between MariaDB and Couchbase
     */
    private function compareQuery($name, $mariadbQuery, $couchbaseQuery)
    {
        echo "  Testing: {$name}... ";
        
        // Warmup
        for ($i = 0; $i < $this->warmupIterations; $i++) {
            try {
                $mariadbQuery();
            } catch (Exception $e) {}
            try {
                $couchbaseQuery();
            } catch (Exception $e) {}
        }
        
        // Benchmark MariaDB
        $mariadbTimes = [];
        for ($i = 0; $i < $this->iterations; $i++) {
            $start = microtime(true);
            try {
                $mariadbQuery();
                $mariadbTimes[] = (microtime(true) - $start) * 1000; // Convert to ms
            } catch (Exception $e) {
                $mariadbTimes[] = null;
            }
        }
        
        // Benchmark Couchbase
        $couchbaseTimes = [];
        $couchbaseAvailable = true;
        for ($i = 0; $i < $this->iterations; $i++) {
            $start = microtime(true);
            try {
                $couchbaseQuery();
                $couchbaseTimes[] = (microtime(true) - $start) * 1000;
            } catch (Exception $e) {
                $couchbaseTimes[] = null;
                $couchbaseAvailable = false;
            }
        }
        
        // Calculate averages (excluding nulls)
        $mariadbAvg = $this->average($mariadbTimes);
        $couchbaseAvg = $couchbaseAvailable ? $this->average($couchbaseTimes) : null;
        
        $result = [
            'name' => $name,
            'mariadb_avg' => $mariadbAvg,
            'couchbase_avg' => $couchbaseAvg,
            'mariadb_min' => min(array_filter($mariadbTimes)),
            'mariadb_max' => max(array_filter($mariadbTimes)),
            'couchbase_min' => $couchbaseAvailable ? min(array_filter($couchbaseTimes)) : null,
            'couchbase_max' => $couchbaseAvailable ? max(array_filter($couchbaseTimes)) : null,
            'couchbase_available' => $couchbaseAvailable,
        ];
        
        if ($couchbaseAvailable && $mariadbAvg > 0) {
            $speedup = ($mariadbAvg / $couchbaseAvg);
            $result['speedup'] = $speedup;
            echo sprintf("%.2fms vs %.2fms (%.2fx)\n", $mariadbAvg, $couchbaseAvg, $speedup);
        } else {
            echo sprintf("%.2fms (Couchbase unavailable)\n", $mariadbAvg);
        }
        
        return $result;
    }
    
    private function average($values)
    {
        $values = array_filter($values, function($v) { return $v !== null; });
        return empty($values) ? 0 : array_sum($values) / count($values);
    }
    
    private function printResults($category, $results)
    {
        if (empty($results)) {
            echo "No results\n";
            return;
        }
        
        echo "\n" . str_pad("Query", 30) . str_pad("MariaDB", 15) . str_pad("Couchbase", 15) . "Speedup\n";
        echo str_repeat("-", 70) . "\n";
        
        foreach ($results as $result) {
            $mariadb = sprintf("%.2f ms", $result['mariadb_avg']);
            $couchbase = $result['couchbase_available'] 
                ? sprintf("%.2f ms", $result['couchbase_avg'])
                : 'N/A';
            $speedup = isset($result['speedup']) 
                ? sprintf("%.2fx", $result['speedup'])
                : '-';
            
            echo str_pad($result['name'], 30) . str_pad($mariadb, 15) . str_pad($couchbase, 15) . $speedup . "\n";
        }
    }
    
    private function printSummary($allResults)
    {
        echo "\n=== Summary ===\n\n";
        
        foreach ($allResults as $category => $results) {
            echo ucfirst($category) . " Queries:\n";
            $this->printResults($category, $results);
            echo "\n";
        }
    }
}
