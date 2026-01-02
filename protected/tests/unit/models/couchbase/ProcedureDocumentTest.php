<?php
/**
 * Unit tests for ProcedureDocument
 */

class ProcedureDocumentTest extends CDbTestCase
{
    public function testCreateFromModel()
    {
        $procedure = Procedure::model()->find();
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('procedure', $doc['_type']);
        $this->assertEquals($procedure->id, $doc['id']);
        $this->assertEquals($procedure->term, $doc['term']);
    }

    public function testSnomedCodePreserved()
    {
        $procedure = Procedure::model()->find('snomed_code IS NOT NULL');
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure with SNOMED code found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('snomed_code', $doc);
        $this->assertEquals($procedure->snomed_code, $doc['snomed_code']);
    }

    public function testOpcsCodesEmbedded()
    {
        $procedure = Procedure::model()->find();
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('opcs_codes', $doc);
        $this->assertIsArray($doc['opcs_codes']);
    }

    public function testBenefitsEmbedded()
    {
        $procedure = Procedure::model()->find();
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('benefits', $doc);
        $this->assertIsArray($doc['benefits']);
    }

    public function testComplicationsEmbedded()
    {
        $procedure = Procedure::model()->find();
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('complications', $doc);
        $this->assertIsArray($doc['complications']);
    }

    public function testSubspecialtiesEmbedded()
    {
        $procedure = Procedure::model()->find();
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('subspecialties', $doc);
        $this->assertIsArray($doc['subspecialties']);
    }

    public function testTermLowerComputed()
    {
        $procedure = Procedure::model()->find();
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('term_lower', $doc);
        $this->assertEquals(strtolower($procedure->term), $doc['term_lower']);
    }

    public function testDefaultDurationIncluded()
    {
        $procedure = Procedure::model()->find('default_duration IS NOT NULL');
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure with default duration found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('default_duration', $doc);
        $this->assertEquals($procedure->default_duration, $doc['default_duration']);
    }
}
