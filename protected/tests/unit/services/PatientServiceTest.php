<?php
/**
 * PatientService Unit Tests
 * Tests for MariaDB and Couchbase backend switching
 */

use services\PatientService;

class PatientServiceTest extends CDbTestCase
{
    private $service;
    private $originalCouchbaseRead;
    private $originalDualWrite;
    
    public $fixtures = array(
        'patients' => 'Patient',
        'contacts' => 'Contact',
        'episodes' => 'Episode',
        'events' => 'Event',
    );
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original settings
        $this->originalCouchbaseRead = Yii::app()->params['enable_couchbase_read'] ?? false;
        $this->originalDualWrite = Yii::app()->params['enable_dual_write'] ?? false;
        
        // Default to MariaDB mode
        Yii::app()->params['enable_couchbase_read'] = false;
        Yii::app()->params['enable_dual_write'] = false;
        
        $this->service = new PatientService();
    }
    
    protected function tearDown(): void
    {
        // Restore original settings
        Yii::app()->params['enable_couchbase_read'] = $this->originalCouchbaseRead;
        Yii::app()->params['enable_dual_write'] = $this->originalDualWrite;
        
        parent::tearDown();
    }
    
    /**
     * Test reading patient from MariaDB
     */
    public function testReadPatientFromMariaDB()
    {
        $patient = $this->patients('patient1');
        
        $result = $this->service->readPatient($patient->id);
        
        $this->assertNotNull($result);
        $this->assertEquals($patient->id, $result['id']);
        if ($patient->hasAttribute('hos_num')) {
            $this->assertEquals($patient->hos_num, $result['hos_num']);
        }
    }
    
    /**
     * Test reading non-existent patient returns null
     */
    public function testReadNonExistentPatientReturnsNull()
    {
        $result = $this->service->readPatient(999999);
        
        $this->assertNull($result);
    }
    
    /**
     * Test finding patient by hospital number
     */
    public function testFindByHosNum()
    {
        $patient = $this->patients('patient1');

        if (!$patient->hasAttribute('hos_num')) {
            $this->markTestSkipped('hos_num column not available in test schema');
        }
        
        $result = $this->service->findByHosNum($patient->hos_num);
        
        $this->assertNotNull($result);
        $this->assertEquals($patient->hos_num, $result['hos_num']);
    }
    
    /**
     * Test finding patient by NHS number
     */
    public function testFindByNhsNum()
    {
        $patient = $this->patients('patient1');
        
        if (!$patient->hasAttribute('nhs_num') || !$patient->nhs_num) {
            $this->markTestSkipped('Test patient has no NHS number');
        }
        
        $result = $this->service->findByNhsNum($patient->nhs_num);
        
        $this->assertNotNull($result);
        $this->assertEquals($patient->nhs_num, $result['nhs_num']);
    }
    
    /**
     * Test getting patient episodes
     */
    public function testGetEpisodes()
    {
        $patient = $this->patients('patient1');
        
        $episodes = $this->service->getEpisodes($patient->id);
        
        $this->assertIsArray($episodes);
        
        // Verify episodes belong to patient
        foreach ($episodes as $episode) {
            $this->assertEquals($patient->id, $episode['patient_id']);
        }
    }
    
    /**
     * Test getting patient events
     */
    public function testGetEvents()
    {
        $patient = $this->patients('patient1');
        
        $events = $this->service->getEvents($patient->id, 10);
        
        $this->assertIsArray($events);
        $this->assertLessThanOrEqual(10, count($events));
    }
    
    /**
     * Test service uses MariaDB when Couchbase is disabled
     */
    public function testUsesMariaDBWhenCouchbaseDisabled()
    {
        Yii::app()->params['enable_couchbase_read'] = false;
        $service = new PatientService();
        
        // Should be able to read patient without Couchbase
        $patient = $this->patients('patient1');
        $result = $service->readPatient($patient->id);
        
        $this->assertNotNull($result);
    }
    
    /**
     * Test service falls back to MariaDB when Couchbase fails
     */
    public function testFallbackToMariaDBOnCouchbaseError()
    {
        // Enable Couchbase but without actual connection
        Yii::app()->params['enable_couchbase_read'] = true;
        $service = new PatientService();
        
        // Should fall back to MariaDB
        $patient = $this->patients('patient1');
        $result = $service->readPatient($patient->id);
        
        $this->assertNotNull($result);
        $this->assertEquals($patient->id, $result['id']);
    }
    
    /**
     * Test patient search functionality
     */
    public function testSearch()
    {
        $patient = $this->patients('patient1');
        
        $results = $this->service->search(['id' => $patient->id]);
        
        $this->assertIsArray($results);
    }
    
    /**
     * Test search by identifier
     */
    public function testSearchByIdentifier()
    {
        $patient = $this->patients('patient1');

        if (!$patient->hasAttribute('hos_num')) {
            $this->markTestSkipped('hos_num column not available in test schema');
        }
        
        $results = $this->service->search(['identifier' => $patient->hos_num]);
        
        $this->assertIsArray($results);
    }
    
    /**
     * Test model to resource conversion
     */
    public function testModelToResource()
    {
        $patient = Patient::model()->with('contact')->findByPk($this->patients('patient1')->id);
        
        $resource = $this->service->modelToResource($patient);
        
        $this->assertNotNull($resource);
        if ($patient->hasAttribute('hos_num')) {
            $this->assertEquals($patient->hos_num, $resource->hos_num);
        }
        if ($patient->contact) {
            $this->assertEquals($patient->contact->last_name, $resource->family_name);
        }
    }
    
    /**
     * Test that episodes are ordered by date descending
     */
    public function testEpisodesOrderedByDateDesc()
    {
        $patient = $this->patients('patient1');
        
        $episodes = $this->service->getEpisodes($patient->id);
        
        if (count($episodes) > 1) {
            $prevDate = null;
            foreach ($episodes as $episode) {
                if ($prevDate !== null && isset($episode['start_date'])) {
                    $this->assertLessThanOrEqual($prevDate, $episode['start_date']);
                }
                $prevDate = $episode['start_date'] ?? null;
            }
        }
    }
    
    /**
     * Test that events are ordered by date descending
     */
    public function testEventsOrderedByDateDesc()
    {
        $patient = $this->patients('patient1');
        
        $events = $this->service->getEvents($patient->id, 50);
        
        if (count($events) > 1) {
            $prevDate = null;
            foreach ($events as $event) {
                if ($prevDate !== null && isset($event['event_date'])) {
                    $this->assertLessThanOrEqual($prevDate, $event['event_date']);
                }
                $prevDate = $event['event_date'] ?? null;
            }
        }
    }
    
    /**
     * Test search with family name filter
     */
    public function testSearchByFamilyName()
    {
        $patient = Patient::model()->with('contact')->findByPk($this->patients('patient1')->id);
        
        if (!$patient->contact || !$patient->contact->last_name) {
            $this->markTestSkipped('Test patient has no contact last name');
        }
        
        $results = $this->service->search(['family' => $patient->contact->last_name]);
        
        $this->assertIsArray($results);
    }
}
