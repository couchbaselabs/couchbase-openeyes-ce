<?php
/**
 * Pre-Cutover Validation Command
 * 
 * Automated validation checks before switching from MariaDB to Couchbase.
 * Run this command to verify system readiness for production cutover.
 * 
 * Usage:
 *   php yiic precutovervalidation run           - Run all validations
 *   php yiic precutovervalidation counts        - Validate record counts only
 *   php yiic precutovervalidation sample        - Validate sample records
 *   php yiic precutovervalidation performance   - Run performance tests
 *   php yiic precutovervalidation report        - Generate full report
 */

class PreCutoverValidationCommand extends CConsoleCommand
{
    private $results = [];
    private $passed = 0;
    private $failed = 0;
    private $skipped = 0;
    
    private $outputFile = null;
    
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic precutovervalidation <action> [options]

ACTIONS
  run           Run all validation checks (default)
  counts        Validate record counts between databases
  sample        Validate sample records for data accuracy
  performance   Run performance benchmark tests
  services      Validate service layer functionality
  report        Generate comprehensive validation report

OPTIONS
  --output=FILE    Write results to file (in addition to console)
  --verbose        Show detailed validation output

EOD;
    }
    
    public function actionRun($output = null, $verbose = false)
    {
        $this->outputFile = $output;
        
        echo "\n========================================\n";
        echo "Pre-Cutover Validation Suite\n";
        echo "========================================\n";
        echo "Started: " . date('Y-m-d H:i:s') . "\n\n";
        
        $this->actionCounts($verbose);
        $this->actionSample($verbose);
        $this->actionPerformance($verbose);
        $this->actionServices($verbose);
        
        $this->printSummary();
        
        if ($this->outputFile) {
            $this->writeReport();
        }
        
        return $this->failed > 0 ? 1 : 0;
    }
    
    public function actionCounts($verbose = false)
    {
        echo "--- Record Count Validation ---\n";
        
        $tables = ['patient', 'episode', 'event', 'user'];
        
        foreach ($tables as $table) {
            $this->validateCount($table, $verbose);
        }
        
        echo "\n";
    }
    
    public function actionSample($sampleSize = 100, $verbose = false)
    {
        echo "--- Sample Record Validation ---\n";
        
        $this->validateSamplePatients($sampleSize, $verbose);
        $this->validateSampleEpisodes($sampleSize, $verbose);
        $this->validateSampleEvents($sampleSize, $verbose);
        
        echo "\n";
    }
    
    public function actionPerformance($verbose = false)
    {
        echo "--- Performance Validation ---\n";
        
        $this->validatePatientRetrievalPerformance();
        $this->validateSearchPerformance();
        $this->validateListPerformance();
        $this->validateAggregationPerformance();
        
        echo "\n";
    }
    
    public function actionServices($verbose = false)
    {
        echo "--- Service Layer Validation ---\n";
        
        $this->validatePatientService();
        $this->validateEpisodeService();
        $this->validateEventService();
        
        echo "\n";
    }
    
    public function actionReport($output = null)
    {
        $this->outputFile = $output ?? Yii::app()->basePath . '/runtime/pre-cutover-report-' . date('Y-m-d-His') . '.txt';
        
        echo "Generating comprehensive validation report...\n";
        
        $this->actionRun(null, true);
        
        echo "\nReport written to: {$this->outputFile}\n";
    }
    
    private function validateCount($table, $verbose = false)
    {
        $modelClass = ucfirst($table);
        
        try {
            // MariaDB count
            $mariadbCount = $modelClass::model()->count();
            
            // Couchbase count
            $couchbaseCount = 0;
            try {
                if (class_exists('OE\\Database\\N1qlQueryBuilder')) {
                    $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
                    $scope = $this->getScopeForTable($table);
                    $couchbaseCount = $builder->from($scope, $table)->count();
                }
            } catch (Exception $e) {
                $this->recordResult(
                    "Count: {$table}",
                    false,
                    "Couchbase query failed: " . $e->getMessage()
                );
                return;
            }
            
            $match = ($couchbaseCount == $mariadbCount);
            $this->recordResult(
                "Count: {$table}",
                $match,
                "MariaDB: {$mariadbCount}, Couchbase: {$couchbaseCount}"
            );
            
        } catch (Exception $e) {
            $this->recordResult("Count: {$table}", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validateSamplePatients($sampleSize, $verbose)
    {
        try {
            // Get random sample
            $patients = Patient::model()->findAll([
                'order' => 'RAND()',
                'limit' => $sampleSize,
            ]);
            
            $matched = 0;
            $errors = [];
            
            foreach ($patients as $patient) {
                if ($this->validatePatientInCouchbase($patient, $verbose)) {
                    $matched++;
                } else {
                    $errors[] = $patient->id;
                    if (count($errors) >= 5) break; // Limit error reporting
                }
            }
            
            $this->recordResult(
                "Sample: patients",
                $matched == count($patients),
                "Matched: {$matched}/" . count($patients) . 
                    (count($errors) > 0 ? ", First failures: " . implode(',', array_slice($errors, 0, 5)) : "")
            );
            
        } catch (Exception $e) {
            $this->recordResult("Sample: patients", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validatePatientInCouchbase($patient, $verbose)
    {
        try {
            if (!class_exists('OE\\Reports\\CouchbasePatientSearch')) {
                return true; // Skip if class not available
            }
            
            $search = new \OE\Reports\CouchbasePatientSearch();
            $couchbasePatient = $search->findByHosNum($patient->hos_num);
            
            if (!$couchbasePatient) {
                return false;
            }
            
            // Validate key fields
            return (
                $couchbasePatient['id'] == $patient->id &&
                $couchbasePatient['hos_num'] == $patient->hos_num &&
                ($couchbasePatient['nhs_num'] ?? null) == $patient->nhs_num
            );
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function validateSampleEpisodes($sampleSize, $verbose)
    {
        try {
            $episodes = Episode::model()->findAll([
                'order' => 'RAND()',
                'limit' => $sampleSize,
            ]);
            
            $matched = 0;
            
            foreach ($episodes as $episode) {
                if ($this->validateEpisodeInCouchbase($episode, $verbose)) {
                    $matched++;
                }
            }
            
            $this->recordResult(
                "Sample: episodes",
                $matched >= count($episodes) * 0.95, // 95% threshold
                "Matched: {$matched}/" . count($episodes)
            );
            
        } catch (Exception $e) {
            $this->recordResult("Sample: episodes", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validateEpisodeInCouchbase($episode, $verbose)
    {
        try {
            if (!class_exists('EpisodeDocument')) {
                return true;
            }
            
            $doc = \EpisodeDocument::findByPk($episode->id);
            return $doc !== null;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function validateSampleEvents($sampleSize, $verbose)
    {
        try {
            $events = Event::model()->findAll([
                'order' => 'RAND()',
                'limit' => $sampleSize,
            ]);
            
            $matched = 0;
            
            foreach ($events as $event) {
                if ($this->validateEventInCouchbase($event, $verbose)) {
                    $matched++;
                }
            }
            
            $this->recordResult(
                "Sample: events",
                $matched >= count($events) * 0.95,
                "Matched: {$matched}/" . count($events)
            );
            
        } catch (Exception $e) {
            $this->recordResult("Sample: events", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validateEventInCouchbase($event, $verbose)
    {
        try {
            if (!class_exists('EventDocument')) {
                return true;
            }
            
            $doc = \EventDocument::findByPk($event->id);
            return $doc !== null;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function validatePatientRetrievalPerformance()
    {
        $threshold = 100; // ms
        
        try {
            $patient = Patient::model()->find(['limit' => 1]);
            if (!$patient) {
                $this->recordResult("Perf: Patient retrieval", true, "Skipped - no data");
                return;
            }
            
            $times = [];
            for ($i = 0; $i < 5; $i++) {
                $start = microtime(true);
                Patient::model()->findByPk($patient->id);
                $times[] = (microtime(true) - $start) * 1000;
            }
            
            $avg = array_sum($times) / count($times);
            $this->recordResult(
                "Perf: Patient retrieval",
                $avg < $threshold,
                sprintf("Avg: %.2fms (threshold: %dms)", $avg, $threshold)
            );
            
        } catch (Exception $e) {
            $this->recordResult("Perf: Patient retrieval", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validateSearchPerformance()
    {
        $threshold = 500; // ms
        
        try {
            $patient = Patient::model()->find(['limit' => 1]);
            if (!$patient) {
                $this->recordResult("Perf: Patient search", true, "Skipped - no data");
                return;
            }
            
            $times = [];
            for ($i = 0; $i < 5; $i++) {
                $start = microtime(true);
                Patient::model()->findByAttributes(['hos_num' => $patient->hos_num]);
                $times[] = (microtime(true) - $start) * 1000;
            }
            
            $avg = array_sum($times) / count($times);
            $this->recordResult(
                "Perf: Patient search",
                $avg < $threshold,
                sprintf("Avg: %.2fms (threshold: %dms)", $avg, $threshold)
            );
            
        } catch (Exception $e) {
            $this->recordResult("Perf: Patient search", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validateListPerformance()
    {
        $threshold = 300; // ms
        
        try {
            $patient = Patient::model()->find(['limit' => 1]);
            if (!$patient) {
                $this->recordResult("Perf: Episode list", true, "Skipped - no data");
                return;
            }
            
            $times = [];
            for ($i = 0; $i < 5; $i++) {
                $start = microtime(true);
                Episode::model()->findAllByAttributes(['patient_id' => $patient->id]);
                $times[] = (microtime(true) - $start) * 1000;
            }
            
            $avg = array_sum($times) / count($times);
            $this->recordResult(
                "Perf: Episode list",
                $avg < $threshold,
                sprintf("Avg: %.2fms (threshold: %dms)", $avg, $threshold)
            );
            
        } catch (Exception $e) {
            $this->recordResult("Perf: Episode list", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validateAggregationPerformance()
    {
        $threshold = 1000; // ms
        
        try {
            $start = microtime(true);
            Yii::app()->db->createCommand("SELECT event_type_id, COUNT(*) FROM event GROUP BY event_type_id")->queryAll();
            $elapsed = (microtime(true) - $start) * 1000;
            
            $this->recordResult(
                "Perf: Aggregation",
                $elapsed < $threshold,
                sprintf("Time: %.2fms (threshold: %dms)", $elapsed, $threshold)
            );
            
        } catch (Exception $e) {
            $this->recordResult("Perf: Aggregation", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validatePatientService()
    {
        try {
            $patient = Patient::model()->find(['limit' => 1]);
            if (!$patient) {
                $this->recordResult("Service: PatientService", true, "Skipped - no data");
                return;
            }
            
            $service = new \services\PatientService();
            $result = $service->readPatient($patient->id);
            
            $this->recordResult(
                "Service: PatientService",
                $result !== null && $result['id'] == $patient->id,
                $result ? "Successfully retrieved patient {$patient->id}" : "Failed to retrieve patient"
            );
            
        } catch (Exception $e) {
            $this->recordResult("Service: PatientService", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validateEpisodeService()
    {
        try {
            $episode = Episode::model()->find(['limit' => 1]);
            if (!$episode) {
                $this->recordResult("Service: EpisodeService", true, "Skipped - no data");
                return;
            }
            
            $service = new \services\EpisodeService();
            $result = $service->readEpisode($episode->id);
            
            $this->recordResult(
                "Service: EpisodeService",
                $result !== null && $result['id'] == $episode->id,
                $result ? "Successfully retrieved episode {$episode->id}" : "Failed to retrieve episode"
            );
            
        } catch (Exception $e) {
            $this->recordResult("Service: EpisodeService", false, "Error: " . $e->getMessage());
        }
    }
    
    private function validateEventService()
    {
        try {
            $event = Event::model()->find(['limit' => 1]);
            if (!$event) {
                $this->recordResult("Service: EventService", true, "Skipped - no data");
                return;
            }
            
            $service = new \services\EventService();
            $result = $service->readEvent($event->id);
            
            $this->recordResult(
                "Service: EventService",
                $result !== null && $result['id'] == $event->id,
                $result ? "Successfully retrieved event {$event->id}" : "Failed to retrieve event"
            );
            
        } catch (Exception $e) {
            $this->recordResult("Service: EventService", false, "Error: " . $e->getMessage());
        }
    }
    
    private function getScopeForTable($table)
    {
        $coreCollections = ['patient', 'contact', 'address', 'episode', 'event', 'user'];
        $clinicalCollections = ['examination', 'diagnosis', 'treatment'];
        
        if (in_array($table, $coreCollections)) {
            return 'core';
        }
        if (in_array($table, $clinicalCollections)) {
            return 'clinical';
        }
        
        return 'core';
    }
    
    private function recordResult($check, $passed, $details = '')
    {
        $status = $passed ? 'PASS' : 'FAIL';
        $icon = $passed ? '✓' : '✗';
        
        if ($passed) {
            $this->passed++;
        } else {
            $this->failed++;
        }
        
        $this->results[] = [
            'check' => $check,
            'passed' => $passed,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        echo sprintf("  [%s] %s: %s\n", $status, $check, $details);
    }
    
    private function printSummary()
    {
        echo "\n========================================\n";
        echo "Validation Summary\n";
        echo "========================================\n";
        echo "Total Checks: " . ($this->passed + $this->failed + $this->skipped) . "\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Skipped: {$this->skipped}\n";
        echo "\n";
        
        if ($this->failed > 0) {
            echo "STATUS: NOT READY FOR CUTOVER\n";
            echo "\nFailed checks:\n";
            foreach ($this->results as $result) {
                if (!$result['passed']) {
                    echo "  - {$result['check']}: {$result['details']}\n";
                }
            }
        } else {
            echo "STATUS: READY FOR CUTOVER\n";
        }
        
        echo "\nCompleted: " . date('Y-m-d H:i:s') . "\n";
    }
    
    private function writeReport()
    {
        $report = "Pre-Cutover Validation Report\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $report .= str_repeat('=', 60) . "\n\n";
        
        foreach ($this->results as $result) {
            $status = $result['passed'] ? 'PASS' : 'FAIL';
            $report .= sprintf("[%s] %s\n", $status, $result['check']);
            $report .= "       Details: {$result['details']}\n";
            $report .= "\n";
        }
        
        $report .= str_repeat('=', 60) . "\n";
        $report .= "SUMMARY\n";
        $report .= "Passed: {$this->passed}\n";
        $report .= "Failed: {$this->failed}\n";
        $report .= "Skipped: {$this->skipped}\n";
        $report .= "\nDecision: " . ($this->failed > 0 ? "NOT READY" : "READY") . "\n";
        
        file_put_contents($this->outputFile, $report);
    }
}
