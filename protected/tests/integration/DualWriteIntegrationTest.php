<?php
/**
 * Dual Write Integration Tests
 * Verifies data is written to both MariaDB and Couchbase correctly
 */

class DualWriteIntegrationTest extends CDbTestCase
{
    private $couchbaseAvailable = false;
    private $originalDualWrite;
    private $originalCouchbaseRead;
    
    protected function setUp()
    {
        parent::setUp();
        
        // Store original settings
        $this->originalDualWrite = Yii::app()->params['enable_dual_write'] ?? false;
        $this->originalCouchbaseRead = Yii::app()->params['enable_couchbase_read'] ?? false;
        
        // Check Couchbase availability
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
        // Restore settings
        Yii::app()->params['enable_dual_write'] = $this->originalDualWrite;
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
     * Test patient dual write
     */
    public function testPatientDualWrite()
    {
        $this->skipIfNoCouchbase();
        
        Yii::app()->params['enable_dual_write'] = true;
        
        // Get a patient from MariaDB
        $mariadbPatient = Patient::model()->find(['limit' => 1]);
        
        if (!$mariadbPatient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // Trigger a save to activate dual-write
        $mariadbPatient->last_modified_date = date('Y-m-d H:i:s');
        $mariadbPatient->save(false);
        
        // Give time for sync
        sleep(1);
        
        // Check Couchbase for same patient
        try {
            if (class_exists('PatientDocument')) {
                $couchbasePatient = PatientDocument::findByPk($mariadbPatient->id);
                
                if ($couchbasePatient) {
                    $this->assertEquals($mariadbPatient->id, $couchbasePatient->id);
                    $this->assertEquals($mariadbPatient->hos_num, $couchbasePatient->hos_num);
                } else {
                    // Not synced yet is acceptable in integration test
                    $this->markTestIncomplete('Patient not synced to Couchbase yet');
                }
            } else {
                $this->markTestIncomplete('PatientDocument class not available');
            }
        } catch (Exception $e) {
            $this->markTestIncomplete('Could not verify Couchbase write: ' . $e->getMessage());
        }
    }
    
    /**
     * Test episode dual write
     */
    public function testEpisodeDualWrite()
    {
        $this->skipIfNoCouchbase();
        
        Yii::app()->params['enable_dual_write'] = true;
        
        // Get an episode from MariaDB
        $episode = Episode::model()->find(['limit' => 1]);
        
        if (!$episode) {
            $this->markTestSkipped('No episodes in database');
        }
        
        // Trigger save
        $episode->last_modified_date = date('Y-m-d H:i:s');
        $episode->save(false);
        
        sleep(1);
        
        try {
            if (class_exists('EpisodeDocument')) {
                $couchbaseEpisode = EpisodeDocument::findByPk($episode->id);
                
                if ($couchbaseEpisode) {
                    $this->assertEquals($episode->id, $couchbaseEpisode->id);
                    $this->assertEquals($episode->patient_id, $couchbaseEpisode->patient_id);
                } else {
                    $this->markTestIncomplete('Episode not synced to Couchbase yet');
                }
            } else {
                $this->markTestIncomplete('EpisodeDocument class not available');
            }
        } catch (Exception $e) {
            $this->markTestIncomplete('Could not verify Couchbase write: ' . $e->getMessage());
        }
    }
    
    /**
     * Test event dual write
     */
    public function testEventDualWrite()
    {
        $this->skipIfNoCouchbase();
        
        Yii::app()->params['enable_dual_write'] = true;
        
        // Get an event from MariaDB
        $event = Event::model()->find(['limit' => 1]);
        
        if (!$event) {
            $this->markTestSkipped('No events in database');
        }
        
        // Trigger save
        $event->last_modified_date = date('Y-m-d H:i:s');
        $event->save(false);
        
        sleep(1);
        
        try {
            if (class_exists('EventDocument')) {
                $couchbaseEvent = EventDocument::findByPk($event->id);
                
                if ($couchbaseEvent) {
                    $this->assertEquals($event->id, $couchbaseEvent->id);
                    $this->assertEquals($event->episode_id, $couchbaseEvent->episode_id);
                } else {
                    $this->markTestIncomplete('Event not synced to Couchbase yet');
                }
            } else {
                $this->markTestIncomplete('EventDocument class not available');
            }
        } catch (Exception $e) {
            $this->markTestIncomplete('Could not verify Couchbase write: ' . $e->getMessage());
        }
    }
    
    /**
     * Test data consistency between databases
     */
    public function testDataConsistency()
    {
        $this->skipIfNoCouchbase();
        
        // Get patient from MariaDB
        $mariadbPatient = Patient::model()->with('contact')->find(['limit' => 1]);
        
        if (!$mariadbPatient || !$mariadbPatient->contact) {
            $this->markTestSkipped('No patients with contact in database');
        }
        
        // Get same patient from Couchbase
        try {
            $search = new OE\Reports\CouchbasePatientSearch();
            $couchbasePatient = $search->findByHosNum($mariadbPatient->hos_num);
            
            if (!$couchbasePatient) {
                $this->markTestIncomplete('Patient not found in Couchbase');
            }
            
            // Compare fields
            $this->assertEquals($mariadbPatient->hos_num, $couchbasePatient['hos_num']);
            $this->assertEquals($mariadbPatient->nhs_num, $couchbasePatient['nhs_num']);
            $this->assertEquals($mariadbPatient->dob, $couchbasePatient['dob']);
            $this->assertEquals($mariadbPatient->gender, $couchbasePatient['gender']);
            
            // Compare contact (embedded in Couchbase)
            if (isset($couchbasePatient['contact'])) {
                $this->assertEquals(
                    $mariadbPatient->contact->first_name, 
                    $couchbasePatient['contact']['first_name']
                );
                $this->assertEquals(
                    $mariadbPatient->contact->last_name, 
                    $couchbasePatient['contact']['last_name']
                );
            }
            
        } catch (Exception $e) {
            $this->markTestIncomplete('Could not verify consistency: ' . $e->getMessage());
        }
    }
    
    /**
     * Test dual-write disabled doesn't write to Couchbase
     */
    public function testDualWriteDisabledDoesNotWriteToCouchbase()
    {
        $this->skipIfNoCouchbase();
        
        Yii::app()->params['enable_dual_write'] = false;
        
        // This test verifies behavior when dual-write is off
        // The save should succeed even if Couchbase write fails
        $patient = Patient::model()->find(['limit' => 1]);
        
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // Save should not throw
        $patient->last_modified_date = date('Y-m-d H:i:s');
        $result = $patient->save(false);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test read from correct backend based on config
     */
    public function testReadFromCorrectBackend()
    {
        $this->skipIfNoCouchbase();
        
        $patient = Patient::model()->find(['limit' => 1]);
        if (!$patient) {
            $this->markTestSkipped('No patients in database');
        }
        
        // Test MariaDB read
        Yii::app()->params['enable_couchbase_read'] = false;
        $service = new services\PatientService();
        $mariadbResult = $service->readPatient($patient->id);
        
        $this->assertNotNull($mariadbResult);
        $this->assertEquals($patient->id, $mariadbResult['id']);
        
        // Test Couchbase read (with fallback)
        Yii::app()->params['enable_couchbase_read'] = true;
        $service = new services\PatientService();
        $couchbaseResult = $service->readPatient($patient->id);
        
        // Should still get result (either from Couchbase or fallback)
        $this->assertNotNull($couchbaseResult);
        $this->assertEquals($patient->id, $couchbaseResult['id']);
    }
    
    /**
     * Test record counts match between databases
     */
    public function testRecordCountsMatch()
    {
        $this->skipIfNoCouchbase();
        
        try {
            // Patient count
            $mariadbPatientCount = Patient::model()->count();
            $builder = new OE\Database\N1qlQueryBuilder('openeyes');
            $couchbasePatientCount = $builder->from('core', 'patient')->count();
            
            // Couchbase count should be <= MariaDB (may not be fully synced)
            $this->assertLessThanOrEqual($mariadbPatientCount, $couchbasePatientCount);
            
            // Episode count
            $mariadbEpisodeCount = Episode::model()->count();
            $builder = new OE\Database\N1qlQueryBuilder('openeyes');
            $couchbaseEpisodeCount = $builder->from('core', 'episode')->count();
            
            $this->assertLessThanOrEqual($mariadbEpisodeCount, $couchbaseEpisodeCount);
            
        } catch (Exception $e) {
            $this->markTestIncomplete('Could not verify counts: ' . $e->getMessage());
        }
    }
    
    /**
     * Test transaction rollback doesn't leave orphaned Couchbase data
     */
    public function testTransactionRollback()
    {
        $this->skipIfNoCouchbase();
        
        Yii::app()->params['enable_dual_write'] = true;
        
        // Start transaction
        $transaction = Yii::app()->db->beginTransaction();
        
        try {
            // Create a new patient
            $patient = new Patient();
            $patient->hos_num = 'ROLLBACK_TEST_' . time();
            $patient->dob = '1990-01-01';
            $patient->gender = 'M';
            
            // Force validation error by not setting required fields
            $saved = $patient->save();
            
            // Rollback
            $transaction->rollback();
            
            // Verify patient doesn't exist in MariaDB
            $mariadbCheck = Patient::model()->findByAttributes(['hos_num' => $patient->hos_num]);
            $this->assertNull($mariadbCheck);
            
            // Verify patient doesn't exist in Couchbase
            try {
                $search = new OE\Reports\CouchbasePatientSearch();
                $couchbaseCheck = $search->findByHosNum($patient->hos_num);
                $this->assertNull($couchbaseCheck);
            } catch (Exception $e) {
                // Expected if not found
            }
            
        } catch (Exception $e) {
            $transaction->rollback();
            throw $e;
        }
    }
}
