<?php
/**
 * Migration Performance Tests
 * Tests performance of data migration commands
 */

class MigrationPerformanceTest extends CDbTestCase
{
    private $couchbaseAvailable = false;
    
    // Performance thresholds
    const BATCH_SIZE = 100;
    const MAX_BATCH_TIME = 5000; // 5 seconds per batch
    const MAX_TRANSFORM_TIME = 10; // 10ms per record transformation
    
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
    }
    
    private function skipIfNoCouchbase()
    {
        if (!$this->couchbaseAvailable) {
            $this->markTestSkipped('Couchbase is not available');
        }
    }
    
    /**
     * Test TypeTransformer performance
     */
    public function testTypeTransformerPerformance()
    {
        if (!class_exists('OE\\Migration\\TypeTransformer')) {
            $this->markTestIncomplete('TypeTransformer class not available');
        }
        
        $transformer = new OE\Migration\TypeTransformer();
        
        $testData = [
            'id' => '12345',
            'name' => 'Test Name',
            'count' => '100',
            'price' => '99.99',
            'is_active' => '1',
            'created_date' => '2024-01-15',
            'created_datetime' => '2024-01-15 10:30:00',
            'metadata' => '{"key": "value"}',
        ];
        
        $schema = [
            'id' => 'int(11)',
            'name' => 'varchar(255)',
            'count' => 'int(11)',
            'price' => 'decimal(10,2)',
            'is_active' => 'tinyint(1)',
            'created_date' => 'date',
            'created_datetime' => 'datetime',
            'metadata' => 'json',
        ];
        
        $iterations = 1000;
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            foreach ($testData as $column => $value) {
                $transformer->transform($value, $schema[$column]);
            }
        }
        
        $elapsed = (microtime(true) - $start) * 1000; // ms
        $perRecord = $elapsed / $iterations;
        
        $this->assertLessThan(self::MAX_TRANSFORM_TIME, $perRecord,
            sprintf('Type transformation too slow: %.2fms per record (max: %dms)', 
                $perRecord, self::MAX_TRANSFORM_TIME));
    }
    
    /**
     * Test batch read performance from MariaDB
     */
    public function testBatchReadPerformance()
    {
        $totalPatients = Patient::model()->count();
        if ($totalPatients < self::BATCH_SIZE) {
            $this->markTestSkipped('Not enough patients for batch test');
        }
        
        $start = microtime(true);
        
        $patients = Patient::model()->findAll([
            'limit' => self::BATCH_SIZE,
            'order' => 'id ASC',
        ]);
        
        $elapsed = (microtime(true) - $start) * 1000;
        
        $this->assertEquals(self::BATCH_SIZE, count($patients));
        $this->assertLessThan(self::MAX_BATCH_TIME, $elapsed,
            sprintf('Batch read too slow: %.2fms (max: %dms)', $elapsed, self::MAX_BATCH_TIME));
    }
    
    /**
     * Test batch write performance to Couchbase
     */
    public function testBatchWritePerformance()
    {
        $this->skipIfNoCouchbase();
        
        if (!class_exists('PatientDocument')) {
            $this->markTestIncomplete('PatientDocument class not available');
        }
        
        // Get patients from MariaDB
        $patients = Patient::model()->findAll([
            'limit' => self::BATCH_SIZE,
            'order' => 'id ASC',
        ]);
        
        if (count($patients) < self::BATCH_SIZE) {
            $this->markTestSkipped('Not enough patients for batch test');
        }
        
        $start = microtime(true);
        
        foreach ($patients as $patient) {
            // Transform and save (dry run - don't actually save)
            $doc = new PatientDocument();
            $doc->setAttributes($patient->getAttributes());
            // $doc->save(); // Skip actual save in test
        }
        
        $elapsed = (microtime(true) - $start) * 1000;
        
        $this->assertLessThan(self::MAX_BATCH_TIME, $elapsed,
            sprintf('Batch transformation too slow: %.2fms (max: %dms)', $elapsed, self::MAX_BATCH_TIME));
    }
    
    /**
     * Test PatientMigrator performance
     */
    public function testPatientMigratorPerformance()
    {
        if (!class_exists('OE\\Migration\\PatientMigrator')) {
            $this->markTestIncomplete('PatientMigrator class not available');
        }
        
        $patient = Patient::model()->with('contact', 'gp', 'practice')->find(['limit' => 1]);
        if (!$patient) {
            $this->markTestSkipped('No patients with relations found');
        }
        
        $migrator = new OE\Migration\PatientMigrator();
        
        $iterations = 100;
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            // Just test the transformation, not actual migration
            $document = $migrator->transformRow($patient->getAttributes());
        }
        
        $elapsed = (microtime(true) - $start) * 1000;
        $perRecord = $elapsed / $iterations;
        
        $this->assertLessThan(self::MAX_TRANSFORM_TIME * 2, $perRecord, // Allow 2x for complex patient
            sprintf('Patient migration too slow: %.2fms per record', $perRecord));
    }
    
    /**
     * Test EpisodeMigrator performance
     */
    public function testEpisodeMigratorPerformance()
    {
        if (!class_exists('OE\\Migration\\EpisodeMigrator')) {
            $this->markTestIncomplete('EpisodeMigrator class not available');
        }
        
        $episode = Episode::model()->find(['limit' => 1]);
        if (!$episode) {
            $this->markTestSkipped('No episodes found');
        }
        
        $migrator = new OE\Migration\EpisodeMigrator();
        
        $iterations = 100;
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $document = $migrator->transformRow($episode->getAttributes());
        }
        
        $elapsed = (microtime(true) - $start) * 1000;
        $perRecord = $elapsed / $iterations;
        
        $this->assertLessThan(self::MAX_TRANSFORM_TIME, $perRecord,
            sprintf('Episode migration too slow: %.2fms per record', $perRecord));
    }
    
    /**
     * Test EventMigrator performance
     */
    public function testEventMigratorPerformance()
    {
        if (!class_exists('OE\\Migration\\EventMigrator')) {
            $this->markTestIncomplete('EventMigrator class not available');
        }
        
        $event = Event::model()->find(['limit' => 1]);
        if (!$event) {
            $this->markTestSkipped('No events found');
        }
        
        $migrator = new OE\Migration\EventMigrator();
        
        $iterations = 100;
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $document = $migrator->transformRow($event->getAttributes());
        }
        
        $elapsed = (microtime(true) - $start) * 1000;
        $perRecord = $elapsed / $iterations;
        
        $this->assertLessThan(self::MAX_TRANSFORM_TIME, $perRecord,
            sprintf('Event migration too slow: %.2fms per record', $perRecord));
    }
    
    /**
     * Test validation command performance
     */
    public function testValidationPerformance()
    {
        $sampleSize = 10;
        
        // Get sample patients
        $patients = Patient::model()->findAll(['limit' => $sampleSize]);
        if (count($patients) < $sampleSize) {
            $this->markTestSkipped('Not enough patients for validation test');
        }
        
        $start = microtime(true);
        
        foreach ($patients as $patient) {
            // Validate fields exist and have correct types
            $this->assertIsNumeric($patient->id);
            $this->assertNotEmpty($patient->hos_num);
        }
        
        $elapsed = (microtime(true) - $start) * 1000;
        
        $this->assertLessThan(100, $elapsed,
            sprintf('Validation too slow: %.2fms for %d records', $elapsed, $sampleSize));
    }
    
    /**
     * Test memory usage during migration
     */
    public function testMemoryUsageDuringMigration()
    {
        $initialMemory = memory_get_usage(true);
        
        // Simulate batch processing
        for ($batch = 0; $batch < 3; $batch++) {
            $patients = Patient::model()->findAll([
                'limit' => self::BATCH_SIZE,
                'offset' => $batch * self::BATCH_SIZE,
            ]);
            
            // Process batch
            foreach ($patients as $patient) {
                $data = $patient->getAttributes();
            }
            
            // Clear to simulate garbage collection between batches
            unset($patients);
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = ($finalMemory - $initialMemory) / 1024 / 1024; // MB
        
        // Should not increase more than 50MB for 300 records
        $this->assertLessThan(50, $memoryIncrease,
            sprintf('Memory usage increased too much: %.2f MB', $memoryIncrease));
    }
    
    /**
     * Test resume capability doesn't add significant overhead
     */
    public function testResumeCapabilityOverhead()
    {
        $checkpointFile = Yii::app()->basePath . '/runtime/test_checkpoint.json';
        
        // Simulate checkpoint writing
        $iterations = 100;
        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $checkpoint = [
                'table' => 'patient',
                'last_id' => $i * 100,
                'count' => $i * 100,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
            file_put_contents($checkpointFile, json_encode($checkpoint));
        }
        
        $elapsed = (microtime(true) - $start) * 1000;
        $perCheckpoint = $elapsed / $iterations;
        
        // Checkpoint should be < 1ms
        $this->assertLessThan(1, $perCheckpoint,
            sprintf('Checkpoint writing too slow: %.2fms', $perCheckpoint));
        
        // Cleanup
        @unlink($checkpointFile);
    }
}
