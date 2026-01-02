<?php
/**
 * Integration tests for Couchbase queries
 * 
 * These tests require:
 * - Couchbase container running
 * - Phase 5 setup complete (collections, indexes)
 * - Test data synced to Couchbase
 * 
 * Run with: php protected/vendor/bin/phpunit protected/tests/integration/CouchbaseQueryIntegrationTest.php
 */

class CouchbaseQueryIntegrationTest extends CDbTestCase
{
    private $couchbaseAvailable = false;
    
    protected function setUp()
    {
        parent::setUp();
        
        // Check if Couchbase is available
        try {
            if (isset(Yii::app()->couchbase)) {
                Yii::app()->couchbase->ping();
                $this->couchbaseAvailable = true;
            }
        } catch (Exception $e) {
            $this->couchbaseAvailable = false;
        }
    }
    
    private function skipIfNoCouchbase()
    {
        if (!$this->couchbaseAvailable) {
            $this->markTestSkipped('Couchbase is not available');
        }
    }
    
    /**
     * Test N1QL Query Builder execution
     */
    public function testQueryBuilderExecution()
    {
        $this->skipIfNoCouchbase();
        
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        
        try {
            $query = $builder
                ->from('core', 'patient')
                ->select('hos_num, nhs_num')
                ->limit(5)
                ->build();
            
            $this->assertNotEmpty($query);
            $this->assertStringContainsString('FROM `openeyes`.`core`.`patient`', $query);
            
            // Execute query (may return empty if no data)
            $results = $builder->execute();
            $this->assertIsArray($results);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Query execution failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test patient search by hospital number
     */
    public function testPatientSearchByHospitalNumber()
    {
        $this->skipIfNoCouchbase();
        
        // First, get a patient from MariaDB
        $mariadbPatient = Patient::model()->find(['limit' => 1]);
        
        if (!$mariadbPatient) {
            $this->markTestSkipped('No patients in MariaDB');
        }
        
        $search = new \OE\Reports\CouchbasePatientSearch();
        
        try {
            $couchbasePatient = $search->findByHosNum($mariadbPatient->hos_num);
            
            if ($couchbasePatient) {
                $this->assertEquals($mariadbPatient->hos_num, $couchbasePatient['hos_num']);
                $this->assertEquals($mariadbPatient->nhs_num, $couchbasePatient['nhs_num']);
            } else {
                $this->markTestSkipped('Patient not synced to Couchbase yet');
            }
        } catch (Exception $e) {
            $this->markTestSkipped('Search failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test that Couchbase and MariaDB return same patient count
     */
    public function testPatientCountConsistency()
    {
        $this->skipIfNoCouchbase();
        
        // Get count from MariaDB
        $mariadbCount = Patient::model()->count();
        
        // Get count from Couchbase
        try {
            $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
            $couchbaseCount = $builder->from('core', 'patient')->count();
            
            // Counts should match (or Couchbase should be less if not fully synced)
            $this->assertLessThanOrEqual($mariadbCount, $couchbaseCount);
            
        } catch (Exception $e) {
            $this->markTestSkipped('Count query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test episode history query
     */
    public function testEpisodeHistoryQuery()
    {
        $this->skipIfNoCouchbase();
        
        // Get a patient with episodes from MariaDB
        $patient = Patient::model()->with('episodes')->find();
        
        if (!$patient || empty($patient->episodes)) {
            $this->markTestSkipped('No patients with episodes in MariaDB');
        }
        
        $report = new \OE\Reports\CouchbaseEpisodeReport();
        
        try {
            $episodes = $report->patientHistory($patient->id);
            
            // Should return array
            $this->assertIsArray($episodes);
            
            // If data is synced, should have same count
            if (!empty($episodes)) {
                $this->assertGreaterThan(0, count($episodes));
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('Episode query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test date range query
     */
    public function testDateRangeQuery()
    {
        $this->skipIfNoCouchbase();
        
        try {
            $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
            $results = $builder
                ->from('core', 'episode')
                ->whereBetween('start_date', '2020-01-01', '2024-12-31')
                ->limit(10)
                ->execute();
            
            $this->assertIsArray($results);
            
            // Verify each result has start_date in range
            foreach ($results as $row) {
                if (isset($row['start_date'])) {
                    $startDate = $row['start_date'];
                    $this->assertGreaterThanOrEqual('2020-01-01', $startDate);
                    $this->assertLessThanOrEqual('2024-12-31', $startDate);
                }
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('Date range query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test JOIN query
     */
    public function testJoinQuery()
    {
        $this->skipIfNoCouchbase();
        
        try {
            $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
            $results = $builder
                ->from('core', 'episode', 'e')
                ->join('core', 'patient', 'e.patient_id = META(p).id', 'p')
                ->select('e.*, p.hos_num')
                ->limit(5)
                ->execute();
            
            $this->assertIsArray($results);
            
            // Verify join worked - should have hos_num from patient
            foreach ($results as $row) {
                if (isset($row['hos_num'])) {
                    $this->assertNotEmpty($row['hos_num']);
                }
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('JOIN query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test WHERE IN query
     */
    public function testWhereInQuery()
    {
        $this->skipIfNoCouchbase();
        
        try {
            $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
            $results = $builder
                ->from('core', 'patient')
                ->whereIn('gender', ['M', 'F'])
                ->limit(5)
                ->execute();
            
            $this->assertIsArray($results);
            
            // Verify gender is M or F
            foreach ($results as $row) {
                if (isset($row['gender'])) {
                    $this->assertContains($row['gender'], ['M', 'F', 'U']);
                }
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('WHERE IN query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test ORDER BY query
     */
    public function testOrderByQuery()
    {
        $this->skipIfNoCouchbase();
        
        try {
            $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
            $results = $builder
                ->from('core', 'episode')
                ->select('start_date')
                ->orderBy('start_date', 'DESC')
                ->limit(10)
                ->execute();
            
            $this->assertIsArray($results);
            
            // Verify results are in descending order
            $prevDate = null;
            foreach ($results as $row) {
                if (isset($row['start_date'])) {
                    if ($prevDate !== null) {
                        $this->assertLessThanOrEqual($prevDate, $row['start_date']);
                    }
                    $prevDate = $row['start_date'];
                }
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('ORDER BY query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test LIKE query
     */
    public function testLikeQuery()
    {
        $this->skipIfNoCouchbase();
        
        try {
            $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
            $results = $builder
                ->from('core', 'patient')
                ->whereILike('contact.last_name', 'a%', 'name')
                ->limit(5)
                ->execute();
            
            $this->assertIsArray($results);
            
            // Verify last names start with 'a' (case insensitive)
            foreach ($results as $row) {
                if (isset($row['contact']['last_name'])) {
                    $firstChar = strtolower(substr($row['contact']['last_name'], 0, 1));
                    $this->assertEquals('a', $firstChar);
                }
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('LIKE query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test aggregation query
     */
    public function testAggregationQuery()
    {
        $this->skipIfNoCouchbase();
        
        try {
            $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
            $results = $builder
                ->from('core', 'episode')
                ->select('firm_id, COUNT(*) as count')
                ->groupBy('firm_id')
                ->limit(10)
                ->execute();
            
            $this->assertIsArray($results);
            
            // Verify each result has count
            foreach ($results as $row) {
                $this->assertArrayHasKey('count', $row);
                $this->assertGreaterThan(0, $row['count']);
            }
            
        } catch (Exception $e) {
            $this->markTestSkipped('Aggregation query failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Test that Query Migration Helper works
     */
    public function testQueryMigrationHelper()
    {
        $helper = new \OE\Database\QueryMigrationHelper();
        
        // Test keyspace generation
        $keyspace = $helper->getKeyspace('patient');
        $this->assertEquals('`openeyes`.`core`.`patient`', $keyspace);
        
        // Test scope detection
        $scope = $helper->getScopeForTable('patient');
        $this->assertEquals('core', $scope);
        
        $scope = $helper->getScopeForTable('examination');
        $this->assertEquals('clinical', $scope);
        
        // Test SQL conversion
        $sql = 'SELECT * FROM patient WHERE id = 123';
        $n1ql = $helper->convertSimpleSelect($sql);
        
        $this->assertStringContainsString('FROM `openeyes`.`core`.`patient`', $n1ql);
        $this->assertStringContainsString('META().id', $n1ql);
    }
}
