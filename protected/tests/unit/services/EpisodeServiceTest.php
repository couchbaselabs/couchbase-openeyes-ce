<?php
/**
 * EpisodeService Unit Tests
 * Tests for Episode CRUD operations and statistics
 */

use services\EpisodeService;

class EpisodeServiceTest extends CDbTestCase
{
    private $service;
    private $originalCouchbaseRead;
    private $originalDualWrite;
    
    public $fixtures = array(
        'patients' => 'Patient',
        'episodes' => 'Episode',
        'events' => 'Event',
        'firms' => 'Firm',
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
        
        $this->service = new EpisodeService();
    }
    
    protected function tearDown(): void
    {
        // Restore original settings
        Yii::app()->params['enable_couchbase_read'] = $this->originalCouchbaseRead;
        Yii::app()->params['enable_dual_write'] = $this->originalDualWrite;
        
        parent::tearDown();
    }
    
    /**
     * Test reading episode by ID
     */
    public function testReadEpisode()
    {
        $episode = $this->episodes('episode1');
        
        $result = $this->service->readEpisode($episode->id);
        
        $this->assertNotNull($result);
        $this->assertEquals($episode->id, $result['id']);
        $this->assertEquals($episode->patient_id, $result['patient_id']);
    }
    
    /**
     * Test reading non-existent episode returns null
     */
    public function testReadNonExistentEpisodeReturnsNull()
    {
        $result = $this->service->readEpisode(999999);
        
        $this->assertNull($result);
    }
    
    /**
     * Test getting episodes for patient
     */
    public function testGetForPatient()
    {
        $patient = $this->patients('patient1');
        
        $episodes = $this->service->getForPatient($patient->id);
        
        $this->assertIsArray($episodes);
        
        foreach ($episodes as $episode) {
            $this->assertEquals($patient->id, $episode['patient_id']);
        }
    }
    
    /**
     * Test getting events for episode
     */
    public function testGetEvents()
    {
        $episode = $this->episodes('episode1');
        
        $events = $this->service->getEvents($episode->id);
        
        $this->assertIsArray($events);
        
        foreach ($events as $event) {
            $this->assertEquals($episode->id, $event['episode_id']);
        }
    }
    
    /**
     * Test isOpen returns true for open episodes
     */
    public function testIsOpenReturnsTrueForOpenEpisode()
    {
        $episode = Episode::model()->find("end_date IS NULL");
        
        if (!$episode) {
            $this->markTestSkipped('No open episodes found');
        }
        
        $isOpen = $this->service->isOpen($episode->id);
        
        $this->assertTrue($isOpen);
    }
    
    /**
     * Test isOpen returns false for closed episodes
     */
    public function testIsOpenReturnsFalseForClosedEpisode()
    {
        $episode = Episode::model()->find("end_date IS NOT NULL");
        
        if (!$episode) {
            $this->markTestSkipped('No closed episodes found');
        }
        
        $isOpen = $this->service->isOpen($episode->id);
        
        $this->assertFalse($isOpen);
    }
    
    /**
     * Test finding episodes by firm
     */
    public function testFindByFirm()
    {
        $firm = Firm::model()->find();
        
        if (!$firm) {
            $this->markTestSkipped('No firms found');
        }
        
        $episodes = $this->service->findByFirm($firm->id, 10);
        
        $this->assertIsArray($episodes);
        $this->assertLessThanOrEqual(10, count($episodes));
        
        foreach ($episodes as $episode) {
            $this->assertEquals($firm->id, $episode['firm_id']);
        }
    }
    
    /**
     * Test getting statistics
     */
    public function testGetStatistics()
    {
        $table = Yii::app()->db->schema->getTable('episode');
        if (!$table || !$table->getColumn('subspecialty_id')) {
            $this->markTestSkipped('subspecialty_id column not available in test schema');
        }

        $params = [
            'start_date' => date('Y-01-01'),
            'end_date' => date('Y-m-d'),
        ];
        
        $stats = $this->service->getStatistics($params);
        
        $this->assertIsArray($stats);
    }
    
    /**
     * Test closing an episode
     */
    public function testCloseEpisode()
    {
        $episode = Episode::model()->find("end_date IS NULL");
        
        if (!$episode) {
            $this->markTestSkipped('No open episodes found to close');
        }
        
        $endDate = date('Y-m-d');
        $result = $this->service->close($episode->id, $endDate);
        
        $this->assertNotNull($result);
        $this->assertEquals($endDate, $result['end_date']);
        
        // Verify it's closed
        $this->assertFalse($this->service->isOpen($episode->id));
        
        // Clean up - reopen the episode
        $episode->end_date = null;
        $episode->save(false);
    }
    
    /**
     * Test closing non-existent episode throws exception
     */
    public function testCloseNonExistentEpisodeThrowsException()
    {
        $this->expectException('services\NotFound');
        
        $this->service->close(999999);
    }
    
    /**
     * Test episodes ordered by start date descending
     */
    public function testEpisodesOrderedByStartDateDesc()
    {
        $patient = $this->patients('patient1');
        
        $episodes = $this->service->getForPatient($patient->id);
        
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
     * Test statistics returns count per subspecialty
     */
    public function testStatisticsGroupsBySubspecialty()
    {
        $table = Yii::app()->db->schema->getTable('episode');
        if (!$table || !$table->getColumn('subspecialty_id')) {
            $this->markTestSkipped('subspecialty_id column not available in test schema');
        }

        $stats = $this->service->getStatistics([
            'start_date' => '2020-01-01',
            'end_date' => date('Y-m-d'),
        ]);
        
        $this->assertIsArray($stats);
        
        foreach ($stats as $row) {
            $this->assertArrayHasKey('count', $row);
            $this->assertGreaterThan(0, $row['count']);
        }
    }
    
    /**
     * Test service uses MariaDB when Couchbase disabled
     */
    public function testUsesMariaDBWhenCouchbaseDisabled()
    {
        Yii::app()->params['enable_couchbase_read'] = false;
        $service = new EpisodeService();
        
        $episode = $this->episodes('episode1');
        $result = $service->readEpisode($episode->id);
        
        $this->assertNotNull($result);
    }
    
    /**
     * Test fallback to MariaDB on Couchbase error
     */
    public function testFallbackToMariaDBOnCouchbaseError()
    {
        Yii::app()->params['enable_couchbase_read'] = true;
        $service = new EpisodeService();
        
        $episode = $this->episodes('episode1');
        $result = $service->readEpisode($episode->id);
        
        // Should fall back and still return result
        $this->assertNotNull($result);
        $this->assertEquals($episode->id, $result['id']);
    }
    
    /**
     * Test events ordered by date descending
     */
    public function testEventsOrderedByDateDesc()
    {
        $episode = $this->episodes('episode1');
        
        $events = $this->service->getEvents($episode->id);
        
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
}
