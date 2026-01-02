<?php

namespace OEModule\OphCiExamination\tests\unit\models\couchbase;

use PHPUnit\Framework\TestCase;

class ExaminationDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDocumentType()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        $this->assertEquals('examination', $document->documentType());
    }

    public function testScope()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        $this->assertEquals('clinical', $document->scope());
    }

    public function testCollectionName()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        $this->assertEquals('examination', $document->collectionName());
    }

    public function testDocumentStructure()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        
        // Set test attributes
        $document->setAttribute('event_id', '123');
        $document->setAttribute('episode_id', '456');
        $document->setAttribute('patient_id', '789');
        $document->setAttribute('event_date', '2024-01-01');
        $document->setAttribute('elements', []);
        
        // Verify attributes are set correctly
        $this->assertEquals('123', $document->event_id);
        $this->assertEquals('456', $document->episode_id);
        $this->assertEquals('789', $document->patient_id);
        $this->assertEquals('2024-01-01', $document->event_date);
        $this->assertIsArray($document->elements);
    }

    public function testElementEmbedding()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        
        // Create mock elements array
        $elements = [
            'VisualAcuity' => [
                'left_readings' => [
                    ['value' => '6/6', 'method' => 'Snellen']
                ],
                'right_readings' => [
                    ['value' => '6/9', 'method' => 'Snellen']
                ],
            ],
            'IntraocularPressure' => [
                'left_reading' => ['value' => 15, 'instrument' => 'Goldmann'],
                'right_reading' => ['value' => 16, 'instrument' => 'Goldmann'],
            ],
        ];
        
        $document->setAttribute('elements', $elements);
        
        $this->assertArrayHasKey('VisualAcuity', $document->elements);
        $this->assertArrayHasKey('IntraocularPressure', $document->elements);
    }

    public function testGetVisualAcuity()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        
        $elements = [
            'VisualAcuity' => [
                'left_readings' => [['value' => '6/6']],
            ],
        ];
        
        $document->setAttribute('elements', $elements);
        
        $va = $document->getVisualAcuity();
        $this->assertIsArray($va);
        $this->assertArrayHasKey('left_readings', $va);
    }

    public function testGetIntraocularPressure()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        
        $elements = [
            'IntraocularPressure' => [
                'left_reading' => ['value' => 15],
            ],
        ];
        
        $document->setAttribute('elements', $elements);
        
        $iop = $document->getIntraocularPressure();
        $this->assertIsArray($iop);
        $this->assertArrayHasKey('left_reading', $iop);
    }

    public function testGetRefraction()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        
        $elements = [
            'Refraction' => [
                'left_sphere' => -2.50,
                'left_cylinder' => -0.75,
                'left_axis' => 180,
            ],
        ];
        
        $document->setAttribute('elements', $elements);
        
        $refraction = $document->getRefraction();
        $this->assertIsArray($refraction);
        $this->assertEquals(-2.50, $refraction['left_sphere']);
    }

    public function testGetDiagnoses()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        
        $elements = [
            'Diagnoses' => [
                'diagnoses' => [
                    ['disorder_id' => 1, 'disorder_name' => 'Glaucoma'],
                ],
            ],
        ];
        
        $document->setAttribute('elements', $elements);
        
        $diagnoses = $document->getDiagnoses();
        $this->assertIsArray($diagnoses);
        $this->assertArrayHasKey('diagnoses', $diagnoses);
    }

    public function testMissingElementReturnsNull()
    {
        $document = new \OEModule\OphCiExamination\models\couchbase\ExaminationDocument();
        $document->setAttribute('elements', []);
        
        $this->assertNull($document->getVisualAcuity());
        $this->assertNull($document->getIntraocularPressure());
        $this->assertNull($document->getRefraction());
        $this->assertNull($document->getDiagnoses());
    }
}
