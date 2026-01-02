<?php
/**
 * Unit tests for MedicationDocument
 */

class MedicationDocumentTest extends CDbTestCase
{
    public function testCreateFromModel()
    {
        $medication = Medication::model()->find('deleted_date IS NULL');
        
        if (!$medication) {
            $this->markTestSkipped('No active medication found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('medication', $doc['_type']);
        $this->assertEquals($medication->id, $doc['id']);
        $this->assertEquals($medication->preferred_term, $doc['preferred_term']);
    }

    public function testDmdCodesPreserved()
    {
        $medication = Medication::model()->find('vtm_code IS NOT NULL OR vmp_code IS NOT NULL OR amp_code IS NOT NULL');
        
        if (!$medication) {
            $this->markTestSkipped('No medication with dm+d codes found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        // Check at least one dm+d code is present
        $hasDmdCode = !empty($doc['vtm_code']) || !empty($doc['vmp_code']) || !empty($doc['amp_code']);
        $this->assertTrue($hasDmdCode, 'At least one dm+d code should be preserved');
    }

    public function testRouteEmbedded()
    {
        $medication = Medication::model()->find('default_route_id IS NOT NULL');
        
        if (!$medication) {
            $this->markTestSkipped('No medication with route found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        $this->assertArrayHasKey('default_route', $doc);
        $this->assertNotNull($doc['default_route']);
        $this->assertArrayHasKey('term', $doc['default_route']);
    }

    public function testFormEmbedded()
    {
        $medication = Medication::model()->find('default_form_id IS NOT NULL');
        
        if (!$medication) {
            $this->markTestSkipped('No medication with form found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        $this->assertArrayHasKey('default_form', $doc);
        $this->assertNotNull($doc['default_form']);
        $this->assertArrayHasKey('term', $doc['default_form']);
    }

    public function testAllergyWarningsEmbedded()
    {
        $medication = Medication::model()->find();
        
        if (!$medication) {
            $this->markTestSkipped('No medication found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        $this->assertArrayHasKey('allergy_warnings', $doc);
        $this->assertIsArray($doc['allergy_warnings']);
    }

    public function testSearchTermsBuilt()
    {
        $medication = Medication::model()->find();
        
        if (!$medication) {
            $this->markTestSkipped('No medication found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        $this->assertArrayHasKey('search_terms', $doc);
        $this->assertArrayHasKey('preferred_term_lower', $doc);
    }

    public function testPreferredCodePresent()
    {
        $medication = Medication::model()->find('preferred_code IS NOT NULL');
        
        if (!$medication) {
            $this->markTestSkipped('No medication with preferred code found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        $this->assertArrayHasKey('preferred_code', $doc);
        $this->assertEquals($medication->preferred_code, $doc['preferred_code']);
    }
}
