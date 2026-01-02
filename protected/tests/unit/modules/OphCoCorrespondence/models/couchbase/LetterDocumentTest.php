<?php

namespace OphCoCorrespondence\tests\unit\models\couchbase;

use PHPUnit\Framework\TestCase;

class LetterDocumentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDocumentType()
    {
        $document = new \OphCoCorrespondence\models\couchbase\LetterDocument();
        $this->assertEquals('letter', $document->documentType());
    }

    public function testScope()
    {
        $document = new \OphCoCorrespondence\models\couchbase\LetterDocument();
        $this->assertEquals('correspondence', $document->scope());
    }

    public function testCollectionName()
    {
        $document = new \OphCoCorrespondence\models\couchbase\LetterDocument();
        $this->assertEquals('letter', $document->collectionName());
    }

    public function testDocumentStructure()
    {
        $document = new \OphCoCorrespondence\models\couchbase\LetterDocument();
        
        // Set test attributes
        $document->setAttribute('letter_id', '123');
        $document->setAttribute('event_id', '456');
        $document->setAttribute('patient_id', '789');
        $document->setAttribute('date', '2024-01-15');
        $document->setAttribute('draft', false);
        
        // Verify attributes are set correctly
        $this->assertEquals('123', $document->letter_id);
        $this->assertEquals('456', $document->event_id);
        $this->assertEquals('789', $document->patient_id);
        $this->assertEquals('2024-01-15', $document->date);
        $this->assertFalse($document->draft);
    }

    public function testRecipientEmbedding()
    {
        $document = new \OphCoCorrespondence\models\couchbase\LetterDocument();
        
        // Create mock recipients array
        $recipients = [
            [
                'type' => 'To',
                'name' => 'Dr. Smith',
                'address' => '123 Medical Street',
            ],
            [
                'type' => 'Cc',
                'name' => 'Dr. Jones',
                'address' => '456 Hospital Road',
            ],
        ];
        
        $document->setAttribute('recipients', $recipients);
        
        $this->assertCount(2, $document->recipients);
        $this->assertEquals('Dr. Smith', $document->recipients[0]['name']);
        $this->assertEquals('Cc', $document->recipients[1]['type']);
    }

    public function testLetterContent()
    {
        $document = new \OphCoCorrespondence\models\couchbase\LetterDocument();
        
        $document->setAttribute('address', 'Dr. John Smith\n123 Medical Street\nLondon, SW1 1AA');
        $document->setAttribute('introduction', 'Dear Dr. Smith,');
        $document->setAttribute('body', 'I am writing to inform you about the patient...');
        $document->setAttribute('footer', 'Yours sincerely,\nDr. Johnson');
        
        $this->assertStringContainsString('Dr. John Smith', $document->address);
        $this->assertStringContainsString('Dear Dr. Smith', $document->introduction);
        $this->assertStringContainsString('inform you', $document->body);
    }

    public function testEnclosureEmbedding()
    {
        $document = new \OphCoCorrespondence\models\couchbase\LetterDocument();
        
        $enclosures = [
            ['content' => 'Copy of referral letter'],
            ['content' => 'Test results'],
        ];
        
        $document->setAttribute('enclosures', $enclosures);
        
        $this->assertCount(2, $document->enclosures);
        $this->assertEquals('Copy of referral letter', $document->enclosures[0]['content']);
    }

    public function testStatusTracking()
    {
        $document = new \OphCoCorrespondence\models\couchbase\LetterDocument();
        
        $document->setAttribute('draft', true);
        $document->setAttribute('print', false);
        $document->setAttribute('locked', false);
        $document->setAttribute('is_signed_off', false);
        
        $this->assertTrue($document->draft);
        $this->assertFalse($document->print);
        $this->assertFalse($document->locked);
        $this->assertFalse($document->is_signed_off);
    }

    public function testLetterType()
    {
        $document = new \OphCoCorrespondence\models\couchbase\LetterDocument();
        
        $letterType = [
            'id' => 1,
            'name' => 'Clinical Letter',
        ];
        
        $document->setAttribute('letter_type', $letterType);
        
        $this->assertEquals('Clinical Letter', $document->letter_type['name']);
    }
}
