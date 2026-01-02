<?php
/**
 * Unit tests for DisorderDocument
 */

class DisorderDocumentTest extends CDbTestCase
{
    public function testCreateFromModel()
    {
        $disorder = Disorder::model()->find();
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('disorder', $doc['_type']);
        $this->assertEquals($disorder->id, $doc['id']);
        $this->assertEquals($disorder->term, $doc['term']);
    }

    public function testSnomedCodePreserved()
    {
        $disorder = Disorder::model()->find('snomed_code IS NOT NULL');
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder with SNOMED code found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertArrayHasKey('snomed_code', $doc);
        $this->assertNotNull($doc['snomed_code']);
        $this->assertEquals($disorder->snomed_code, $doc['snomed_code']);
    }

    public function testSearchTermsBuilt()
    {
        $disorder = Disorder::model()->find();
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertArrayHasKey('search_terms', $doc);
        $this->assertIsArray($doc['search_terms']);
        $this->assertGreaterThan(0, count($doc['search_terms']));
    }

    public function testSpecialtyEmbedded()
    {
        $disorder = Disorder::model()->find('specialty_id IS NOT NULL');
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder with specialty found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertArrayHasKey('specialty', $doc);
        $this->assertNotNull($doc['specialty']);
        $this->assertArrayHasKey('name', $doc['specialty']);
    }

    public function testCommonOphthalmicFlag()
    {
        $disorder = Disorder::model()->find();
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertArrayHasKey('is_common_ophthalmic', $doc);
        $this->assertIsBool($doc['is_common_ophthalmic']);
    }

    public function testTermLowerComputed()
    {
        $disorder = Disorder::model()->find();
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertArrayHasKey('term_lower', $doc);
        $this->assertEquals(strtolower($disorder->term), $doc['term_lower']);
    }
}
