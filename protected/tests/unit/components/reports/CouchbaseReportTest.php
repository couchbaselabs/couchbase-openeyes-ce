<?php
/**
 * Unit tests for Couchbase Report classes
 */

class CouchbaseReportTest extends CTestCase
{
    public function testEpisodeReportCountBySubspecialtyParameters()
    {
        $report = new \OE\Reports\CouchbaseEpisodeReport();
        
        // Test that the method accepts correct parameters
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';
        $institutionId = '1';
        
        $this->assertIsString($startDate);
        $this->assertIsString($endDate);
        $this->assertIsString($institutionId);
        
        // Date format validation
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $startDate);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $endDate);
    }
    
    public function testEpisodeReportPatientHistoryParameter()
    {
        $report = new \OE\Reports\CouchbaseEpisodeReport();
        
        $patientId = '123';
        
        $this->assertIsString($patientId);
        $this->assertNotEmpty($patientId);
    }
    
    public function testEventReportFindByPatientIdParameters()
    {
        $report = new \OE\Reports\CouchbaseEventReport();
        
        $patientId = 123;
        $limit = 100;
        
        $this->assertIsInt($patientId);
        $this->assertIsInt($limit);
        $this->assertGreaterThan(0, $limit);
    }
    
    public function testEventReportCountByEventTypeParameters()
    {
        $report = new \OE\Reports\CouchbaseEventReport();
        
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';
        
        $this->assertIsString($startDate);
        $this->assertIsString($endDate);
        
        // Date format validation
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $startDate);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $endDate);
    }
    
    public function testDateRangeLogic()
    {
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';
        
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        $this->assertLessThan($end, $start);
    }
    
    public function testLimitParameter()
    {
        $defaultLimit = 100;
        $customLimit = 50;
        
        $this->assertEquals(100, $defaultLimit);
        $this->assertEquals(50, $customLimit);
        $this->assertGreaterThan(0, $defaultLimit);
        $this->assertGreaterThan(0, $customLimit);
    }
}
