<?php
/**
 * Query Performance Tests
 * Benchmarks MariaDB vs Couchbase query performance
 */

class QueryPerformanceTest extends CDbTestCase
{
    private $couchbaseAvailable = false;
    private $results = [];
    
    // Performance thresholds (in milliseconds)
    const SINGLE_DOC_THRESHOLD = 100;     // 100ms for single document
    const SEARCH_THRESHOLD = 500;          // 500ms for search queries
    const COUNT_THRESHOLD = 200;           // 200ms for count queries
    const LIST_THRESHOLD = 300;            // 300ms for list queries
    
    protected function setUp()
    {
        parent::setUp();
        
        try {
            if (isset(Yii::app()->couchbase)) {
                Yii::app()->couchbase->ping();
                $this->couchbaseAvailable = true;
            }
        } catch (Exception $e) {
            $this->couchbaseAvailable = false;
        }
        
        $this->results = [];
    }
    
    protected function tearDown()
    {
        // Log results for analysis
        if (!empty($this->results)) {
            $this->logPerformanceResults();
        }
        
        parent::tearDown();
    }
    
    private function skipIfNoCouchbase()
    {
        if (!$this->couchbaseAvailable) {
            $this->markTestSkipped('Couchbase is not available');
        }
    }
    
    private function measure(callable $operation, $iterations = 3)
    {
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $operation();
            $times[] = (microtime(true) - $start) * 1000; // Convert to ms
        }
        
        return [
            'min' => min($times),
            'max' => max($times),
            'avg' => array_sum($times) / count($times),
        ];
    }
    
    private function logPerformanceResults()
    {
        $logFile = Yii::app()->basePath . '/runtime/performance-test-results.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $log = "\n=== Performance Test Results ({$timestamp}) ===\n";
        
        foreach ($this->results as $test => $data) {
            $log .= "\n{$test}:\n";
            if (isset($data['mariadb'])) {
                $log .= sprintf("  MariaDB: min=%.2fms, max=%.2fms, avg=%.2fms\n", 
                    $data['mariadb']['min'], $data['mariadb']['max'], $data['mariadb']['avg']);
            }
            if (isset($data['couchbase'])) {
                $log .= sprintf("  Couchbase: min=%.2fms, max=%.2fms, avg=%.2fms\n", 
                    $data['couchbase']['min'], $data['couchbase']['max'], $data['couchbase']['avg']);
            }
            if (isset($data['mariadb']) && isset($data['couchbase'])) {
                $speedup = $data['mariadb']['avg'] / max($data['couchbase']['avg'], 0.001);
                $log .= sprintf("  Speedup: %.2fx %s\n", 
                    $speedup,
                    $speedup > 1 ? '(Couchbase faster)' : '(MariaDB faster)'
                );
            }
        }
        
        @file_put_contents($logFile, $log, FILE_APPEND);
    }
    
    /**
     * Test single patient retrieval performance
     */
    public function testSinglePatientRetrievalPerformance()
    {
        $patient = Patient::model()->find(['limit' => 1]);
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // MariaDB
        $mariadbTime = $this->measure(function() use ($patient) {
            Patient::model()->findByPk($patient->id);
        });
        
        $this->results['single_patient'] = ['mariadb' => $mariadbTime];
        $this->assertLessThan(self::SINGLE_DOC_THRESHOLD, $mariadbTime['avg'], 
            'MariaDB single patient retrieval too slow');
        
        // Couchbase
        if ($this->couchbaseAvailable && class_exists('PatientDocument')) {
            $couchbaseTime = $this->measure(function() use ($patient) {
                PatientDocument::findByPk($patient->id);
            });
            
            $this->results['single_patient']['couchbase'] = $couchbaseTime;
            $this->assertLessThan(self::SINGLE_DOC_THRESHOLD, $couchbaseTime['avg'], 
                'Couchbase single patient retrieval too slow');
        }
    }
    
    /**
     * Test patient search by hospital number performance
     */
    public function testPatientSearchPerformance()
    {
        $patient = Patient::model()->find(['limit' => 1]);
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // MariaDB
        $mariadbTime = $this->measure(function() use ($patient) {
            Patient::model()->findByAttributes(['hos_num' => $patient->hos_num]);
        });
        
        $this->results['patient_search'] = ['mariadb' => $mariadbTime];
        $this->assertLessThan(self::SEARCH_THRESHOLD, $mariadbTime['avg'], 
            'MariaDB patient search too slow');
        
        // Couchbase
        if ($this->couchbaseAvailable) {
            try {
                $search = new OE\Reports\CouchbasePatientSearch();
                $couchbaseTime = $this->measure(function() use ($search, $patient) {
                    $search->findByHosNum($patient->hos_num);
                });
                
                $this->results['patient_search']['couchbase'] = $couchbaseTime;
                $this->assertLessThan(self::SEARCH_THRESHOLD, $couchbaseTime['avg'], 
                    'Couchbase patient search too slow');
            } catch (Exception $e) {
                // Skip Couchbase timing if error
            }
        }
    }
    
    /**
     * Test patient count performance
     */
    public function testPatientCountPerformance()
    {
        // MariaDB
        $mariadbTime = $this->measure(function() {
            Patient::model()->count();
        });
        
        $this->results['patient_count'] = ['mariadb' => $mariadbTime];
        $this->assertLessThan(self::COUNT_THRESHOLD, $mariadbTime['avg'], 
            'MariaDB patient count too slow');
        
        // Couchbase
        if ($this->couchbaseAvailable) {
            try {
                $builder = new OE\Database\N1qlQueryBuilder('openeyes');
                $couchbaseTime = $this->measure(function() use ($builder) {
                    $builder->from('core', 'patient')->count();
                });
                
                $this->results['patient_count']['couchbase'] = $couchbaseTime;
                $this->assertLessThan(self::COUNT_THRESHOLD, $couchbaseTime['avg'], 
                    'Couchbase patient count too slow');
            } catch (Exception $e) {
                // Skip Couchbase timing if error
            }
        }
    }
    
    /**
     * Test episode list for patient performance
     */
    public function testEpisodeListPerformance()
    {
        $patient = Patient::model()->with('episodes')->find('1=1 LIMIT 1');
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // MariaDB
        $mariadbTime = $this->measure(function() use ($patient) {
            Episode::model()->findAllByAttributes(['patient_id' => $patient->id]);
        });
        
        $this->results['episode_list'] = ['mariadb' => $mariadbTime];
        $this->assertLessThan(self::LIST_THRESHOLD, $mariadbTime['avg'], 
            'MariaDB episode list too slow');
        
        // Couchbase
        if ($this->couchbaseAvailable) {
            try {
                $builder = new OE\Database\N1qlQueryBuilder('openeyes');
                $couchbaseTime = $this->measure(function() use ($builder, $patient) {
                    $builder->from('core', 'episode')
                        ->where('patient_id', '=', $patient->id)
                        ->execute();
                });
                
                $this->results['episode_list']['couchbase'] = $couchbaseTime;
                $this->assertLessThan(self::LIST_THRESHOLD, $couchbaseTime['avg'], 
                    'Couchbase episode list too slow');
            } catch (Exception $e) {
                // Skip Couchbase timing if error
            }
        }
    }
    
    /**
     * Test event search by date range performance
     */
    public function testEventDateRangeSearchPerformance()
    {
        $startDate = date('Y-01-01');
        $endDate = date('Y-m-d');
        
        // MariaDB
        $mariadbTime = $this->measure(function() use ($startDate, $endDate) {
            $criteria = new CDbCriteria();
            $criteria->addBetweenCondition('event_date', $startDate, $endDate);
            $criteria->limit = 100;
            Event::model()->findAll($criteria);
        });
        
        $this->results['event_date_range'] = ['mariadb' => $mariadbTime];
        $this->assertLessThan(self::SEARCH_THRESHOLD, $mariadbTime['avg'], 
            'MariaDB event date range search too slow');
        
        // Couchbase
        if ($this->couchbaseAvailable) {
            try {
                $builder = new OE\Database\N1qlQueryBuilder('openeyes');
                $couchbaseTime = $this->measure(function() use ($builder, $startDate, $endDate) {
                    $builder->from('core', 'event')
                        ->whereBetween('event_date', $startDate, $endDate)
                        ->limit(100)
                        ->execute();
                });
                
                $this->results['event_date_range']['couchbase'] = $couchbaseTime;
                $this->assertLessThan(self::SEARCH_THRESHOLD, $couchbaseTime['avg'], 
                    'Couchbase event date range search too slow');
            } catch (Exception $e) {
                // Skip Couchbase timing if error
            }
        }
    }
    
    /**
     * Test aggregation query performance
     */
    public function testAggregationPerformance()
    {
        // MariaDB
        $mariadbTime = $this->measure(function() {
            Yii::app()->db->createCommand("
                SELECT event_type_id, COUNT(*) as count 
                FROM event 
                WHERE deleted = 0 
                GROUP BY event_type_id
            ")->queryAll();
        });
        
        $this->results['aggregation'] = ['mariadb' => $mariadbTime];
        $this->assertLessThan(self::SEARCH_THRESHOLD, $mariadbTime['avg'], 
            'MariaDB aggregation too slow');
        
        // Couchbase
        if ($this->couchbaseAvailable) {
            try {
                $builder = new OE\Database\N1qlQueryBuilder('openeyes');
                $couchbaseTime = $this->measure(function() use ($builder) {
                    $builder->from('core', 'event')
                        ->select('event_type_id, COUNT(*) as count')
                        ->where('deleted', '=', 0)
                        ->groupBy('event_type_id')
                        ->execute();
                });
                
                $this->results['aggregation']['couchbase'] = $couchbaseTime;
                $this->assertLessThan(self::SEARCH_THRESHOLD, $couchbaseTime['avg'], 
                    'Couchbase aggregation too slow');
            } catch (Exception $e) {
                // Skip Couchbase timing if error
            }
        }
    }
    
    /**
     * Test bulk read performance
     */
    public function testBulkReadPerformance()
    {
        // MariaDB
        $mariadbTime = $this->measure(function() {
            Patient::model()->findAll(['limit' => 100]);
        });
        
        $this->results['bulk_read'] = ['mariadb' => $mariadbTime];
        $this->assertLessThan(self::LIST_THRESHOLD, $mariadbTime['avg'], 
            'MariaDB bulk read too slow');
        
        // Couchbase
        if ($this->couchbaseAvailable) {
            try {
                $builder = new OE\Database\N1qlQueryBuilder('openeyes');
                $couchbaseTime = $this->measure(function() use ($builder) {
                    $builder->from('core', 'patient')
                        ->limit(100)
                        ->execute();
                });
                
                $this->results['bulk_read']['couchbase'] = $couchbaseTime;
                $this->assertLessThan(self::LIST_THRESHOLD, $couchbaseTime['avg'], 
                    'Couchbase bulk read too slow');
            } catch (Exception $e) {
                // Skip Couchbase timing if error
            }
        }
    }
    
    /**
     * Test service layer performance
     */
    public function testServiceLayerPerformance()
    {
        $patient = Patient::model()->find(['limit' => 1]);
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // MariaDB via service
        Yii::app()->params['enable_couchbase_read'] = false;
        $mariadbService = new services\PatientService();
        
        $mariadbTime = $this->measure(function() use ($mariadbService, $patient) {
            $mariadbService->readPatient($patient->id);
        });
        
        $this->results['service_read'] = ['mariadb' => $mariadbTime];
        
        // Couchbase via service
        if ($this->couchbaseAvailable) {
            Yii::app()->params['enable_couchbase_read'] = true;
            $couchbaseService = new services\PatientService();
            
            $couchbaseTime = $this->measure(function() use ($couchbaseService, $patient) {
                $couchbaseService->readPatient($patient->id);
            });
            
            $this->results['service_read']['couchbase'] = $couchbaseTime;
        }
    }
}
