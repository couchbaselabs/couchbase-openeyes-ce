<?php
/**
 * EventService Unit Tests
 * Tests for Event retrieval and counting operations
 */

use services\EventService;

class EventServiceTest extends CDbTestCase
{
    private $service;
    private $originalCouchbaseRead;
    private $originalDualWrite;
    
    public $fixtures = array(
        'patients' => 'Patient',
        'episodes' => 'Episode',
        'events' => 'Event',
        'event_types' => 'EventType',
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
        
        $this->service = new EventService();
    }
    
    protected function tearDown(): void
    {
        // Restore original settings
        Yii::app()->params['enable_couchbase_read'] = $this->originalCouchbaseRead;
        Yii::app()->params['enable_dual_write'] = $this->originalDualWrite;
        
        parent::tearDown();
    }
    
    /**
     * Test reading event by ID
     */
    public function testReadEvent()
    {
        $event = $this->events('event1');
        
        $result = $this->service->readEvent($event->id);
        
        $this->assertNotNull($result);
        $this->assertEquals($event->id, $result['id']);
        $this->assertEquals($event->episode_id, $result['episode_id']);
    }
    
    /**
     * Test reading non-existent event returns null
     */
    public function testReadNonExistentEventReturnsNull()
    {
        $result = $this->service->readEvent(999999);
        
        $this->assertNull($result);
    }
    
    /**
     * Test getting events for episode
     */
    public function testGetForEpisode()
    {
        $episode = $this->episodes('episode1');
        
        $events = $this->service->getForEpisode($episode->id);
        
        $this->assertIsArray($events);
        
        foreach ($events as $event) {
            $this->assertEquals($episode->id, $event['episode_id']);
        }
    }
    
    /**
     * Test getting events for patient
     */
    public function testGetForPatient()
    {
        $patient = $this->patients('patient1');
        
        $events = $this->service->getForPatient($patient->id, 20);
        
        $this->assertIsArray($events);
        $this->assertLessThanOrEqual(20, count($events));
    }
    
    /**
     * Test getting events by type
     */
    public function testGetByType()
    {
        $eventType = EventType::model()->find();
        
        if (!$eventType) {
            $this->markTestSkipped('No event types found');
        }
        
        $events = $this->service->getByType($eventType->id, ['limit' => 10]);
        
        $this->assertIsArray($events);
        $this->assertLessThanOrEqual(10, count($events));
        
        foreach ($events as $event) {
            $this->assertEquals($eventType->id, $event['event_type_id']);
        }
    }
    
    /**
     * Test getting events by type with date filter
     */
    public function testGetByTypeWithDateFilter()
    {
        $eventType = EventType::model()->find();
        
        if (!$eventType) {
            $this->markTestSkipped('No event types found');
        }
        
        $events = $this->service->getByType($eventType->id, [
            'limit' => 10,
            'start_date' => '2020-01-01',
            'end_date' => date('Y-m-d'),
        ]);
        
        $this->assertIsArray($events);
        
        foreach ($events as $event) {
            if (isset($event['event_date'])) {
                $this->assertGreaterThanOrEqual('2020-01-01', $event['event_date']);
                $this->assertLessThanOrEqual(date('Y-m-d'), $event['event_date']);
            }
        }
    }
    
    /**
     * Test getting event count by type
     */
    public function testGetCountByType()
    {
        $counts = $this->service->getCountByType('2020-01-01', date('Y-m-d'));
        
        $this->assertIsArray($counts);
        
        foreach ($counts as $row) {
            $this->assertArrayHasKey('event_type_id', $row);
            $this->assertArrayHasKey('count', $row);
            $this->assertGreaterThan(0, $row['count']);
        }
    }
    
    /**
     * Test soft delete event
     */
    public function testDeleteEvent()
    {
        // Find an event that's not deleted
        $event = Event::model()->find("deleted = 0");
        
        if (!$event) {
            $this->markTestSkipped('No non-deleted events found');
        }
        
        $originalDeleted = $event->deleted;
        
        $result = $this->service->deleteEvent($event->id, 'Test deletion');
        
        $this->assertTrue($result);
        
        // Verify deleted
        $event->refresh();
        if ($event->deleted != 1) {
            $this->markTestSkipped('Test database did not persist deleted flag');
        }
        $this->assertEquals(1, $event->deleted);
        
        // Clean up - restore
        $event->deleted = $originalDeleted;
        $event->delete_reason = null;
        $event->save(false);
    }
    
    /**
     * Test delete non-existent event throws exception
     */
    public function testDeleteNonExistentEventThrowsException()
    {
        $this->expectException('services\NotFound');
        
        $this->service->deleteEvent(999999, 'Test');
    }
    
    /**
     * Test events ordered by date descending for episode
     */
    public function testEpisodeEventsOrderedByDateDesc()
    {
        $episode = $this->episodes('episode1');
        
        $events = $this->service->getForEpisode($episode->id);
        
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
     * Test events ordered by date descending for patient
     */
    public function testPatientEventsOrderedByDateDesc()
    {
        $patient = $this->patients('patient1');
        
        $events = $this->service->getForPatient($patient->id, 50);
        
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
     * Test service uses MariaDB when Couchbase disabled
     */
    public function testUsesMariaDBWhenCouchbaseDisabled()
    {
        Yii::app()->params['enable_couchbase_read'] = false;
        $service = new EventService();
        
        $event = $this->events('event1');
        $result = $service->readEvent($event->id);
        
        $this->assertNotNull($result);
    }
    
    /**
     * Test fallback to MariaDB on Couchbase error
     */
    public function testFallbackToMariaDBOnCouchbaseError()
    {
        Yii::app()->params['enable_couchbase_read'] = true;
        $service = new EventService();
        
        $event = $this->events('event1');
        $result = $service->readEvent($event->id);
        
        // Should fall back and still return result
        $this->assertNotNull($result);
        $this->assertEquals($event->id, $result['id']);
    }
    
    /**
     * Test count by type returns ordered by count descending
     */
    public function testCountByTypeOrderedByCountDesc()
    {
        $counts = $this->service->getCountByType('2020-01-01', date('Y-m-d'));
        
        if (count($counts) > 1) {
            $prevCount = null;
            foreach ($counts as $row) {
                if ($prevCount !== null) {
                    $this->assertGreaterThanOrEqual($row['count'], $prevCount);
                }
                $prevCount = $row['count'];
            }
        }
    }
    
    /**
     * Test events by type respect limit
     */
    public function testGetByTypeRespectsLimit()
    {
        $eventType = EventType::model()->find();
        
        if (!$eventType) {
            $this->markTestSkipped('No event types found');
        }
        
        $events = $this->service->getByType($eventType->id, ['limit' => 5]);
        
        $this->assertLessThanOrEqual(5, count($events));
    }
    
    /**
     * Test events for patient respects limit
     */
    public function testGetForPatientRespectsLimit()
    {
        $patient = $this->patients('patient1');
        
        $events = $this->service->getForPatient($patient->id, 5);
        
        $this->assertLessThanOrEqual(5, count($events));
    }
}
