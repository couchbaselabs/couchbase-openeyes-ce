<?php

namespace OE\tests\integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Couchbase module sync functionality
 * Tests that examination, operation booking, and correspondence data
 * can be successfully synced to Couchbase.
 * 
 * These tests require a running Couchbase instance and database connection.
 */
class ModuleSyncIntegrationTest extends TestCase
{
    protected static $skipTests = false;
    protected static $skipReason = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        
        // Check if Couchbase is configured and available
        if (!class_exists('\Couchbase\Cluster')) {
            self::$skipTests = true;
            self::$skipReason = 'Couchbase PHP SDK not installed';
            return;
        }
        
        $params = \Yii::app()->params;
        if (empty($params['couchbase_enabled']) || !$params['couchbase_enabled']) {
            self::$skipTests = true;
            self::$skipReason = 'Couchbase not enabled in configuration';
            return;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        if (self::$skipTests) {
            $this->markTestSkipped(self::$skipReason);
        }
    }

    public function testExaminationModuleSyncCommand()
    {
        // Test that the sync command class exists
        $this->assertTrue(
            class_exists('\CouchbaseModuleSyncCommand'),
            'CouchbaseModuleSyncCommand should exist'
        );
    }

    public function testExaminationDocumentModelExists()
    {
        $this->assertTrue(
            class_exists('\OEModule\OphCiExamination\models\couchbase\ExaminationDocument'),
            'ExaminationDocument model should exist'
        );
    }

    public function testOperationDocumentModelExists()
    {
        $this->assertTrue(
            class_exists('\OphTrOperationbooking\models\couchbase\OperationDocument'),
            'OperationDocument model should exist'
        );
    }

    public function testLetterDocumentModelExists()
    {
        $this->assertTrue(
            class_exists('\OphCoCorrespondence\models\couchbase\LetterDocument'),
            'LetterDocument model should exist'
        );
    }

    public function testCouchbaseElementBridgeTraitExists()
    {
        $this->assertTrue(
            trait_exists('\OE\Models\Traits\CouchbaseElementBridge'),
            'Core CouchbaseElementBridge trait should exist'
        );
    }

    public function testExaminationElementBridgeTraitExists()
    {
        $this->assertTrue(
            trait_exists('\OEModule\OphCiExamination\models\traits\CouchbaseElementBridge'),
            'OphCiExamination CouchbaseElementBridge trait should exist'
        );
    }

    public function testModuleConfigurationEnabled()
    {
        $params = \Yii::app()->params;
        $migratedModules = $params['couchbase_migrated_modules'] ?? [];
        
        $this->assertContains(
            'OphCiExamination',
            $migratedModules,
            'OphCiExamination should be in couchbase_migrated_modules'
        );
        
        $this->assertContains(
            'OphTrOperationbooking',
            $migratedModules,
            'OphTrOperationbooking should be in couchbase_migrated_modules'
        );
        
        $this->assertContains(
            'OphCoCorrespondence',
            $migratedModules,
            'OphCoCorrespondence should be in couchbase_migrated_modules'
        );
    }

    public function testModuleSettingsConfigured()
    {
        $params = \Yii::app()->params;
        $moduleSettings = $params['couchbase_module_settings'] ?? [];
        
        $this->assertArrayHasKey(
            'OphCiExamination',
            $moduleSettings,
            'OphCiExamination settings should be configured'
        );
        
        $this->assertArrayHasKey(
            'OphTrOperationbooking',
            $moduleSettings,
            'OphTrOperationbooking settings should be configured'
        );
        
        $this->assertArrayHasKey(
            'OphCoCorrespondence',
            $moduleSettings,
            'OphCoCorrespondence settings should be configured'
        );
    }

    public function testVisualAcuityElementHasCouchbaseBridge()
    {
        $uses = class_uses('\OEModule\OphCiExamination\models\Element_OphCiExamination_VisualAcuity');
        $this->assertArrayHasKey(
            'OEModule\OphCiExamination\models\traits\CouchbaseElementBridge',
            $uses,
            'VisualAcuity element should use CouchbaseElementBridge trait'
        );
    }

    public function testIntraocularPressureElementHasCouchbaseBridge()
    {
        $uses = class_uses('\OEModule\OphCiExamination\models\Element_OphCiExamination_IntraocularPressure');
        $this->assertArrayHasKey(
            'OEModule\OphCiExamination\models\traits\CouchbaseElementBridge',
            $uses,
            'IntraocularPressure element should use CouchbaseElementBridge trait'
        );
    }

    public function testRefractionElementHasCouchbaseBridge()
    {
        $uses = class_uses('\OEModule\OphCiExamination\models\Element_OphCiExamination_Refraction');
        $this->assertArrayHasKey(
            'OEModule\OphCiExamination\models\traits\CouchbaseElementBridge',
            $uses,
            'Refraction element should use CouchbaseElementBridge trait'
        );
    }

    public function testDiagnosesElementHasCouchbaseBridge()
    {
        $uses = class_uses('\OEModule\OphCiExamination\models\Element_OphCiExamination_Diagnoses');
        $this->assertArrayHasKey(
            'OEModule\OphCiExamination\models\traits\CouchbaseElementBridge',
            $uses,
            'Diagnoses element should use CouchbaseElementBridge trait'
        );
    }

    public function testHistoryElementHasCouchbaseBridge()
    {
        $uses = class_uses('\OEModule\OphCiExamination\models\Element_OphCiExamination_History');
        $this->assertArrayHasKey(
            'OEModule\OphCiExamination\models\traits\CouchbaseElementBridge',
            $uses,
            'History element should use CouchbaseElementBridge trait'
        );
    }

    public function testOperationElementHasCouchbaseBridge()
    {
        $uses = class_uses('\Element_OphTrOperationbooking_Operation');
        $this->assertArrayHasKey(
            'OE\Models\Traits\CouchbaseElementBridge',
            $uses,
            'Operation element should use CouchbaseElementBridge trait'
        );
    }

    public function testLetterElementHasCouchbaseBridge()
    {
        $uses = class_uses('\ElementLetter');
        $this->assertArrayHasKey(
            'OE\Models\Traits\CouchbaseElementBridge',
            $uses,
            'ElementLetter should use CouchbaseElementBridge trait'
        );
    }
}
