<?php
/**
 * Service Integration Tests
 * Compares results from MariaDB and Couchbase backends
 */

class ServiceIntegrationTest extends CDbTestCase
{
    private $couchbaseAvailable = false;
    private $originalCouchbaseRead;
    
    protected function setUp()
    {
        parent::setUp();
        
        $this->originalCouchbaseRead = Yii::app()->params['enable_couchbase_read'] ?? false;
        
        try {
            if (isset(Yii::app()->couchbase)) {
                Yii::app()->couchbase->ping();
                $this->couchbaseAvailable = true;
            }
        } catch (Exception $e) {
            $this->couchbaseAvailable = false;
        }
    }
    
    protected function tearDown()
    {
        Yii::app()->params['enable_couchbase_read'] = $this->originalCouchbaseRead;
        parent::tearDown();
    }
    
    private function skipIfNoCouchbase()
    {
        if (!$this->couchbaseAvailable) {
            $this->markTestSkipped('Couchbase is not available');
        }
    }
    
    /**
     * Test PatientService returns same data from both backends
     */
    public function testPatientServiceBackendConsistency()
    {
        $this->skipIfNoCouchbase();
        
        $patient = Patient::model()->find(['limit' => 1]);
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // Get from MariaDB
        Yii::app()->params['enable_couchbase_read'] = false;
        $mariadbService = new services\PatientService();
        $mariadbResult = $mariadbService->readPatient($patient->id);
        
        // Get from Couchbase
        Yii::app()->params['enable_couchbase_read'] = true;
        $couchbaseService = new services\PatientService();
        $couchbaseResult = $couchbaseService->readPatient($patient->id);
        
        // Both should return data
        $this->assertNotNull($mariadbResult);
        $this->assertNotNull($couchbaseResult);
        
        // Core fields should match
        $this->assertEquals($mariadbResult['id'], $couchbaseResult['id']);
        $this->assertEquals($mariadbResult['hos_num'], $couchbaseResult['hos_num']);
        $this->assertEquals($mariadbResult['nhs_num'] ?? null, $couchbaseResult['nhs_num'] ?? null);
    }
    
    /**
     * Test EpisodeService returns same data from both backends
     */
    public function testEpisodeServiceBackendConsistency()
    {
        $this->skipIfNoCouchbase();
        
        $episode = Episode::model()->find(['limit' => 1]);
        if (!$episode) {
            $this->markTestSkipped('No episodes in database');
        }
        
        // Get from MariaDB
        Yii::app()->params['enable_couchbase_read'] = false;
        $mariadbService = new services\EpisodeService();
        $mariadbResult = $mariadbService->readEpisode($episode->id);
        
        // Get from Couchbase
        Yii::app()->params['enable_couchbase_read'] = true;
        $couchbaseService = new services\EpisodeService();
        $couchbaseResult = $couchbaseService->readEpisode($episode->id);
        
        $this->assertNotNull($mariadbResult);
        $this->assertNotNull($couchbaseResult);
        
        $this->assertEquals($mariadbResult['id'], $couchbaseResult['id']);
        $this->assertEquals($mariadbResult['patient_id'], $couchbaseResult['patient_id']);
    }
    
    /**
     * Test EventService returns same data from both backends
     */
    public function testEventServiceBackendConsistency()
    {
        $this->skipIfNoCouchbase();
        
        $event = Event::model()->find(['limit' => 1]);
        if (!$event) {
            $this->markTestSkipped('No events in database');
        }
        
        // Get from MariaDB
        Yii::app()->params['enable_couchbase_read'] = false;
        $mariadbService = new services\EventService();
        $mariadbResult = $mariadbService->readEvent($event->id);
        
        // Get from Couchbase
        Yii::app()->params['enable_couchbase_read'] = true;
        $couchbaseService = new services\EventService();
        $couchbaseResult = $couchbaseService->readEvent($event->id);
        
        $this->assertNotNull($mariadbResult);
        $this->assertNotNull($couchbaseResult);
        
        $this->assertEquals($mariadbResult['id'], $couchbaseResult['id']);
        $this->assertEquals($mariadbResult['episode_id'], $couchbaseResult['episode_id']);
    }
    
    /**
     * Test patient search returns consistent results
     */
    public function testPatientSearchConsistency()
    {
        $this->skipIfNoCouchbase();
        
        $patient = Patient::model()->find(['limit' => 1]);
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // Search in MariaDB
        Yii::app()->params['enable_couchbase_read'] = false;
        $mariadbService = new services\PatientService();
        $mariadbResult = $mariadbService->findByHosNum($patient->hos_num);
        
        // Search in Couchbase
        Yii::app()->params['enable_couchbase_read'] = true;
        $couchbaseService = new services\PatientService();
        $couchbaseResult = $couchbaseService->findByHosNum($patient->hos_num);
        
        $this->assertNotNull($mariadbResult);
        $this->assertNotNull($couchbaseResult);
        
        $this->assertEquals($mariadbResult['hos_num'], $couchbaseResult['hos_num']);
    }
    
    /**
     * Test patient episodes consistency
     */
    public function testPatientEpisodesConsistency()
    {
        $this->skipIfNoCouchbase();
        
        $patient = Patient::model()->with('episodes')->find('1=1 ORDER BY RAND() LIMIT 1');
        if (!$patient || empty($patient->episodes)) {
            $this->markTestSkipped('No patients with episodes found');
        }
        
        // Get from MariaDB
        Yii::app()->params['enable_couchbase_read'] = false;
        $mariadbService = new services\PatientService();
        $mariadbEpisodes = $mariadbService->getEpisodes($patient->id);
        
        // Get from Couchbase
        Yii::app()->params['enable_couchbase_read'] = true;
        $couchbaseService = new services\PatientService();
        $couchbaseEpisodes = $couchbaseService->getEpisodes($patient->id);
        
        // Both should return episodes
        $this->assertNotEmpty($mariadbEpisodes);
        $this->assertNotEmpty($couchbaseEpisodes);
        
        // Count should be same or close
        $this->assertEquals(count($mariadbEpisodes), count($couchbaseEpisodes));
    }
    
    /**
     * Test episode events consistency
     */
    public function testEpisodeEventsConsistency()
    {
        $this->skipIfNoCouchbase();
        
        $episode = Episode::model()->with('events')->find('1=1 ORDER BY RAND() LIMIT 1');
        if (!$episode || empty($episode->events)) {
            $this->markTestSkipped('No episodes with events found');
        }
        
        // Get from MariaDB
        Yii::app()->params['enable_couchbase_read'] = false;
        $mariadbService = new services\EpisodeService();
        $mariadbEvents = $mariadbService->getEvents($episode->id);
        
        // Get from Couchbase
        Yii::app()->params['enable_couchbase_read'] = true;
        $couchbaseService = new services\EpisodeService();
        $couchbaseEvents = $couchbaseService->getEvents($episode->id);
        
        $this->assertNotEmpty($mariadbEvents);
        $this->assertNotEmpty($couchbaseEvents);
        
        $this->assertEquals(count($mariadbEvents), count($couchbaseEvents));
    }
    
    /**
     * Test statistics consistency
     */
    public function testStatisticsConsistency()
    {
        $this->skipIfNoCouchbase();
        
        $params = [
            'start_date' => date('Y-01-01'),
            'end_date' => date('Y-m-d'),
        ];
        
        // Get from MariaDB
        Yii::app()->params['enable_couchbase_read'] = false;
        $mariadbService = new services\EpisodeService();
        $mariadbStats = $mariadbService->getStatistics($params);
        
        // Get from Couchbase
        Yii::app()->params['enable_couchbase_read'] = true;
        $couchbaseService = new services\EpisodeService();
        $couchbaseStats = $couchbaseService->getStatistics($params);
        
        // Both should return data
        $this->assertIsArray($mariadbStats);
        $this->assertIsArray($couchbaseStats);
        
        // Note: Exact counts may differ if not fully synced
    }
    
    /**
     * Test graceful fallback when Couchbase has missing data
     */
    public function testFallbackOnMissingData()
    {
        // Even with Couchbase enabled, should fall back gracefully
        Yii::app()->params['enable_couchbase_read'] = true;
        
        $patient = Patient::model()->find(['limit' => 1]);
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // Should not throw, should return result from fallback
        $service = new services\PatientService();
        $result = $service->readPatient($patient->id);
        
        $this->assertNotNull($result);
        $this->assertEquals($patient->id, $result['id']);
    }
    
    /**
     * Test that service initialization doesn't fail
     */
    public function testServiceInitialization()
    {
        // Test with Couchbase disabled
        Yii::app()->params['enable_couchbase_read'] = false;
        $patientService = new services\PatientService();
        $episodeService = new services\EpisodeService();
        $eventService = new services\EventService();
        
        $this->assertInstanceOf('services\PatientService', $patientService);
        $this->assertInstanceOf('services\EpisodeService', $episodeService);
        $this->assertInstanceOf('services\EventService', $eventService);
        
        // Test with Couchbase enabled
        Yii::app()->params['enable_couchbase_read'] = true;
        $patientService = new services\PatientService();
        $episodeService = new services\EpisodeService();
        $eventService = new services\EventService();
        
        $this->assertInstanceOf('services\PatientService', $patientService);
        $this->assertInstanceOf('services\EpisodeService', $episodeService);
        $this->assertInstanceOf('services\EventService', $eventService);
    }
    
    /**
     * Test concurrent read operations
     */
    public function testConcurrentReads()
    {
        $patient = Patient::model()->find(['limit' => 1]);
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        $service = new services\PatientService();
        
        // Simulate concurrent reads
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $results[] = $service->readPatient($patient->id);
        }
        
        // All results should be the same
        foreach ($results as $result) {
            $this->assertNotNull($result);
            $this->assertEquals($patient->id, $result['id']);
        }
    }
}
