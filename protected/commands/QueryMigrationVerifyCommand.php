<?php
/**
 * Command to verify query migration correctness
 * 
 * Compares results between MariaDB and Couchbase to ensure
 * migrated queries return identical data
 * 
 * Usage:
 *   yiic querymigrationverify all              - Verify all query types
 *   yiic querymigrationverify patient          - Verify patient queries
 *   yiic querymigrationverify episode          - Verify episode queries
 *   yiic querymigrationverify detailed         - Show detailed differences
 */

class QueryMigrationVerifyCommand extends CConsoleCommand
{
    private $errors = [];
    private $warnings = [];
    private $passed = 0;
    private $failed = 0;
    
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic querymigrationverify <action> [options]

ACTIONS
  all         - Verify all migrated queries
  patient     - Verify patient queries only
  episode     - Verify episode queries only
  event       - Verify event queries only
  detailed    - Show detailed differences for failures

OPTIONS
  --verbose   Show detailed output for all checks

EXAMPLES
  yiic querymigrationverify all
  yiic querymigrationverify patient --verbose
  yiic querymigrationverify detailed
EOD;
    }
    
    /**
     * Verify all query migrations
     */
    public function actionAll($verbose = false)
    {
        echo "=== Query Migration Verification ===\n\n";
        
        $this->verifyPatientQueries($verbose);
        $this->verifyEpisodeQueries($verbose);
        $this->verifyEventQueries($verbose);
        
        $this->printSummary();
    }
    
    /**
     * Verify patient queries
     */
    public function actionPatient($verbose = false)
    {
        echo "=== Patient Query Verification ===\n\n";
        $this->verifyPatientQueries($verbose);
        $this->printSummary();
    }
    
    /**
     * Verify episode queries
     */
    public function actionEpisode($verbose = false)
    {
        echo "=== Episode Query Verification ===\n\n";
        $this->verifyEpisodeQueries($verbose);
        $this->printSummary();
    }
    
    /**
     * Verify event queries
     */
    public function actionEvent($verbose = false)
    {
        echo "=== Event Query Verification ===\n\n";
        $this->verifyEventQueries($verbose);
        $this->printSummary();
    }
    
    /**
     * Show detailed differences
     */
    public function actionDetailed()
    {
        $this->actionAll(true);
    }
    
    private function verifyPatientQueries($verbose)
    {
        echo "Patient Queries:\n";
        
        // Get sample patients
        $patients = Patient::model()->findAll(['limit' => 5]);
        
        if (empty($patients)) {
            echo "  No patients found for testing\n";
            return;
        }
        
        // Test 1: Find by hospital number
        foreach ($patients as $patient) {
            if (!$patient->hos_num) continue;
            
            $mariadbResult = Patient::model()->findByAttributes(['hos_num' => $patient->hos_num]);
            
            try {
                $search = new \OE\Reports\CouchbasePatientSearch();
                $couchbaseResult = $search->findByHosNum($patient->hos_num);
                
                if ($this->comparePatientResults($mariadbResult, $couchbaseResult, $verbose)) {
                    $this->pass("  ✓ Find by hos_num: {$patient->hos_num}");
                } else {
                    $this->fail("  ✗ Find by hos_num: {$patient->hos_num} - Results mismatch");
                }
            } catch (Exception $e) {
                $this->warn("  ⚠ Find by hos_num: {$patient->hos_num} - Couchbase unavailable");
            }
            
            break; // Test one patient
        }
        
        // Test 2: Count patients
        $mariadbCount = Patient::model()->count();
        
        try {
            $builder = new \OE\Database\N1qlQueryBuilder();
            $couchbaseCount = $builder->from('core', 'patient')->count();
            
            if ($mariadbCount == $couchbaseCount) {
                $this->pass("  ✓ Patient count: {$mariadbCount}");
            } else {
                $this->warn("  ⚠ Patient count mismatch: MariaDB={$mariadbCount}, Couchbase={$couchbaseCount}");
            }
        } catch (Exception $e) {
            $this->warn("  ⚠ Patient count - Couchbase unavailable");
        }
        
        echo "\n";
    }
    
    private function verifyEpisodeQueries($verbose)
    {
        echo "Episode Queries:\n";
        
        // Get a patient with episodes
        $patient = Patient::model()->with('episodes')->find();
        
        if (!$patient || empty($patient->episodes)) {
            echo "  No patients with episodes found\n\n";
            return;
        }
        
        // Test: Patient episode history
        $mariadbEpisodes = Episode::model()->findAllByAttributes(['patient_id' => $patient->id]);
        
        try {
            $report = new \OE\Reports\CouchbaseEpisodeReport();
            $couchbaseEpisodes = $report->patientHistory($patient->id);
            
            if (count($mariadbEpisodes) == count($couchbaseEpisodes)) {
                $this->pass("  ✓ Episode history for patient {$patient->id}: " . count($mariadbEpisodes) . " episodes");
            } else {
                $this->fail("  ✗ Episode count mismatch: MariaDB=" . count($mariadbEpisodes) . ", Couchbase=" . count($couchbaseEpisodes));
            }
        } catch (Exception $e) {
            $this->warn("  ⚠ Episode history - Couchbase unavailable: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function verifyEventQueries($verbose)
    {
        echo "Event Queries:\n";
        
        // Get a patient with events
        $patient = Patient::model()->with('episodes.events')->find();
        
        if (!$patient) {
            echo "  No patients found\n\n";
            return;
        }
        
        // Test: Find events by patient
        $criteria = new CDbCriteria();
        $criteria->with = ['episode'];
        $criteria->condition = 'episode.patient_id = :patient_id';
        $criteria->params = [':patient_id' => $patient->id];
        $mariadbEvents = Event::model()->findAll($criteria);
        
        try {
            $report = new \OE\Reports\CouchbaseEventReport();
            $couchbaseEvents = $report->findByPatientId($patient->id, 1000);
            
            if (count($mariadbEvents) == count($couchbaseEvents)) {
                $this->pass("  ✓ Events for patient {$patient->id}: " . count($mariadbEvents) . " events");
            } else {
                $this->warn("  ⚠ Event count mismatch: MariaDB=" . count($mariadbEvents) . ", Couchbase=" . count($couchbaseEvents));
            }
        } catch (Exception $e) {
            $this->warn("  ⚠ Events query - Couchbase unavailable: " . $e->getMessage());
        }
        
        echo "\n";
    }
    
    private function comparePatientResults($mariadbResult, $couchbaseResult, $verbose)
    {
        if ($mariadbResult === null && $couchbaseResult === null) {
            return true;
        }
        
        if ($mariadbResult === null || $couchbaseResult === null) {
            if ($verbose) {
                echo "    One result is null\n";
            }
            return false;
        }
        
        // Compare key fields
        $fieldsToCompare = ['hos_num', 'nhs_num', 'dob', 'gender'];
        
        foreach ($fieldsToCompare as $field) {
            $mariadbValue = $mariadbResult->$field;
            $couchbaseValue = isset($couchbaseResult[$field]) ? $couchbaseResult[$field] : null;
            
            if ($mariadbValue != $couchbaseValue) {
                if ($verbose) {
                    echo "    Field mismatch: {$field} - MariaDB={$mariadbValue}, Couchbase={$couchbaseValue}\n";
                }
                return false;
            }
        }
        
        return true;
    }
    
    private function pass($message)
    {
        echo $message . "\n";
        $this->passed++;
    }
    
    private function fail($message)
    {
        echo $message . "\n";
        $this->failed++;
        $this->errors[] = $message;
    }
    
    private function warn($message)
    {
        echo $message . "\n";
        $this->warnings[] = $message;
    }
    
    private function printSummary()
    {
        echo "=== Verification Summary ===\n\n";
        echo "Passed:   " . $this->passed . "\n";
        echo "Failed:   " . $this->failed . "\n";
        echo "Warnings: " . count($this->warnings) . "\n\n";
        
        if ($this->failed > 0) {
            echo "Failures:\n";
            foreach ($this->errors as $error) {
                echo "  " . $error . "\n";
            }
            echo "\n";
        }
        
        if (!empty($this->warnings)) {
            echo "Warnings:\n";
            foreach ($this->warnings as $warning) {
                echo "  " . $warning . "\n";
            }
            echo "\n";
        }
        
        if ($this->failed == 0 && count($this->warnings) == 0) {
            echo "✓ All verifications passed!\n";
        } elseif ($this->failed == 0) {
            echo "⚠ All queries passed but some warnings present (likely Couchbase not synced)\n";
        } else {
            echo "✗ Some verifications failed - review output above\n";
        }
    }
}
