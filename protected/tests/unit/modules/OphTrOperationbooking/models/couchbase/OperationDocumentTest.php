<?php

namespace OphTrOperationbooking\tests\unit\models\couchbase;

use PHPUnit\Framework\TestCase;

class OperationDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDocumentType()
    {
        $document = new \OphTrOperationbooking\models\couchbase\OperationDocument();
        $this->assertEquals('operation', $document->documentType());
    }

    public function testScope()
    {
        $document = new \OphTrOperationbooking\models\couchbase\OperationDocument();
        $this->assertEquals('booking', $document->scope());
    }

    public function testCollectionName()
    {
        $document = new \OphTrOperationbooking\models\couchbase\OperationDocument();
        $this->assertEquals('operation', $document->collectionName());
    }

    public function testDocumentStructure()
    {
        $document = new \OphTrOperationbooking\models\couchbase\OperationDocument();
        
        // Set test attributes
        $document->setAttribute('operation_id', '123');
        $document->setAttribute('event_id', '456');
        $document->setAttribute('patient_id', '789');
        $document->setAttribute('eye_id', 3);
        $document->setAttribute('status', 'scheduled');
        
        // Verify attributes are set correctly
        $this->assertEquals('123', $document->operation_id);
        $this->assertEquals('456', $document->event_id);
        $this->assertEquals('789', $document->patient_id);
        $this->assertEquals(3, $document->eye_id);
        $this->assertEquals('scheduled', $document->status);
    }

    public function testProcedureEmbedding()
    {
        $document = new \OphTrOperationbooking\models\couchbase\OperationDocument();
        
        // Create mock procedures array
        $procedures = [
            ['id' => 1, 'term' => 'Cataract extraction', 'snomed_code' => '231790005'],
            ['id' => 2, 'term' => 'Lens implantation', 'snomed_code' => '231744001'],
        ];
        
        $document->setAttribute('procedures', $procedures);
        
        $this->assertCount(2, $document->procedures);
        $this->assertEquals('Cataract extraction', $document->procedures[0]['term']);
    }

    public function testBookingDetails()
    {
        $document = new \OphTrOperationbooking\models\couchbase\OperationDocument();
        
        $booking = [
            'session_id' => '100',
            'session_date' => '2024-02-15',
            'theatre' => 'Theatre 1',
            'ward' => 'Day Case Ward',
            'admission_time' => '08:00',
        ];
        
        $document->setAttribute('booking', $booking);
        
        $this->assertIsArray($document->booking);
        $this->assertEquals('2024-02-15', $document->booking['session_date']);
        $this->assertEquals('Theatre 1', $document->booking['theatre']);
    }

    public function testPriorityAndAnaesthetic()
    {
        $document = new \OphTrOperationbooking\models\couchbase\OperationDocument();
        
        $document->setAttribute('priority', ['id' => 1, 'name' => 'Routine']);
        $document->setAttribute('anaesthetic_type', ['id' => 1, 'name' => 'Local']);
        
        $this->assertEquals('Routine', $document->priority['name']);
        $this->assertEquals('Local', $document->anaesthetic_type['name']);
    }

    public function testStatusTracking()
    {
        $document = new \OphTrOperationbooking\models\couchbase\OperationDocument();
        
        $document->setAttribute('status', 'pending');
        $document->setAttribute('decision_date', '2024-01-15');
        $document->setAttribute('consultant_required', true);
        
        $this->assertEquals('pending', $document->status);
        $this->assertEquals('2024-01-15', $document->decision_date);
        $this->assertTrue($document->consultant_required);
    }
}
