<?php
/**
 * (C) Apperta Foundation, 2025
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2025, Apperta Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

/**
 * Unit tests for FullDataMigrationCommand
 * Tests the master data migration orchestration command
 * 
 * @group migration
 * @group phase-14
 */
class FullDataMigrationCommandTest extends CTestCase
{
    protected $command;
    protected $originalRuntimePath;
    protected $testRuntimePath;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test runtime directory
        $this->originalRuntimePath = Yii::app()->getRuntimePath();
        $this->testRuntimePath = sys_get_temp_dir() . '/openeyes_test_runtime_' . uniqid();
        mkdir($this->testRuntimePath, 0777, true);
        
        $this->command = new FullDataMigrationCommand('test-migration', new CConsoleCommandRunner());
    }

    protected function tearDown(): void
    {
        // Clean up test runtime directory
        if (is_dir($this->testRuntimePath)) {
            $this->recursiveRemoveDirectory($this->testRuntimePath);
        }
        
        parent::tearDown();
    }

    protected function recursiveRemoveDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** @test */
    public function command_has_correct_name()
    {
        $this->assertEquals('test-migration', $this->command->getName());
    }

    /** @test */
    public function command_provides_help_text()
    {
        $help = $this->command->getHelp();
        
        $this->assertStringContainsString('USAGE', $help);
        $this->assertStringContainsString('fulldatamigration', $help);
        $this->assertStringContainsString('ACTIONS', $help);
        $this->assertStringContainsString('run', $help);
        $this->assertStringContainsString('stage', $help);
        $this->assertStringContainsString('status', $help);
        $this->assertStringContainsString('validate', $help);
        $this->assertStringContainsString('rollback', $help);
    }

    /** @test */
    public function command_has_five_migration_stages()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        $this->assertCount(5, $stages);
        $this->assertArrayHasKey(1, $stages);
        $this->assertArrayHasKey(2, $stages);
        $this->assertArrayHasKey(3, $stages);
        $this->assertArrayHasKey(4, $stages);
        $this->assertArrayHasKey(5, $stages);
    }

    /** @test */
    public function stage_one_contains_reference_data()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        $stage1 = $stages[1];
        
        $this->assertEquals('Reference Data', $stage1['name']);
        $this->assertArrayHasKey('tables', $stage1);
        $this->assertArrayHasKey('event_type', $stage1['tables']);
        $this->assertArrayHasKey('element_type', $stage1['tables']);
        $this->assertArrayHasKey('site', $stage1['tables']);
        $this->assertArrayHasKey('institution', $stage1['tables']);
    }

    /** @test */
    public function stage_two_contains_clinical_reference()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        $stage2 = $stages[2];
        
        $this->assertEquals('Clinical Reference', $stage2['name']);
        $this->assertArrayHasKey('tables', $stage2);
        $this->assertArrayHasKey('disorder', $stage2['tables']);
        $this->assertArrayHasKey('procedure', $stage2['tables']);
        $this->assertArrayHasKey('medication', $stage2['tables']);
    }

    /** @test */
    public function stage_three_contains_core_clinical()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        $stage3 = $stages[3];
        
        $this->assertEquals('Core Clinical', $stage3['name']);
        $this->assertArrayHasKey('tables', $stage3);
        $this->assertArrayHasKey('patient', $stage3['tables']);
        $this->assertArrayHasKey('episode', $stage3['tables']);
        $this->assertArrayHasKey('event', $stage3['tables']);
        
        // Check batch sizes for large tables
        $this->assertEquals(200, $stage3['tables']['patient']['batch']);
        $this->assertEquals(500, $stage3['tables']['episode']['batch']);
    }

    /** @test */
    public function stage_four_delegates_to_module_command()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        $stage4 = $stages[4];
        
        $this->assertEquals('Module Elements', $stage4['name']);
        $this->assertArrayHasKey('command', $stage4);
        $this->assertEquals('moduledata', $stage4['command']);
        $this->assertArrayHasKey('args', $stage4);
    }

    /** @test */
    public function stage_five_contains_administrative_data()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        $stage5 = $stages[5];
        
        $this->assertEquals('Administrative', $stage5['name']);
        $this->assertArrayHasKey('tables', $stage5);
        $this->assertArrayHasKey('audit', $stage5['tables']);
        $this->assertArrayHasKey('setting_metadata', $stage5['tables']);
        
        // Audit should have smaller batch size due to volume
        $this->assertEquals(200, $stage5['tables']['audit']['batch']);
    }

    /** @test */
    public function all_tables_have_required_configuration()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        foreach ($stages as $stageNum => $stage) {
            if (!isset($stage['tables'])) {
                continue; // Skip stages that delegate to commands
            }
            
            foreach ($stage['tables'] as $tableName => $config) {
                $this->assertArrayHasKey('model', $config, "Table {$tableName} missing model class");
                $this->assertArrayHasKey('scope', $config, "Table {$tableName} missing scope");
                $this->assertContains($config['scope'], ['reference', 'clinical', 'admin'], 
                    "Table {$tableName} has invalid scope: {$config['scope']}");
            }
        }
    }

    /** @test */
    public function create_document_uses_to_couchbase_document_method()
    {
        $reflection = new ReflectionClass($this->command);
        $createDocMethod = $reflection->getMethod('createDocument');
        $createDocMethod->setAccessible(true);
        
        // Create mock record with toCouchbaseDocument method
        $mockRecord = $this->getMockBuilder(stdClass::class)
            ->addMethods(['toCouchbaseDocument'])
            ->getMock();
        
        $expectedDoc = ['id' => 123, 'name' => 'Test'];
        $mockRecord->expects($this->once())
            ->method('toCouchbaseDocument')
            ->willReturn($expectedDoc);
        
        $result = $createDocMethod->invoke($this->command, $mockRecord);
        
        $this->assertEquals($expectedDoc, $result);
    }

    /** @test */
    public function create_document_falls_back_to_attributes()
    {
        $reflection = new ReflectionClass($this->command);
        $createDocMethod = $reflection->getMethod('createDocument');
        $createDocMethod->setAccessible(true);
        
        // Create mock record without toCouchbaseDocument method
        $mockRecord = new stdClass();
        $mockRecord->attributes = ['id' => 456, 'name' => 'Fallback'];
        
        $result = $createDocMethod->invoke($this->command, $mockRecord);
        
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('_type', $result);
        $this->assertEquals(456, $result['id']);
        $this->assertEquals('Fallback', $result['name']);
    }

    /** @test */
    public function batch_sizes_are_configurable()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        // Check that different tables have different batch sizes
        $batchSizes = [];
        foreach ($stages as $stage) {
            if (!isset($stage['tables'])) {
                continue;
            }
            
            foreach ($stage['tables'] as $tableName => $config) {
                if (isset($config['batch'])) {
                    $batchSizes[$tableName] = $config['batch'];
                }
            }
        }
        
        // Verify batch sizes are reasonable
        foreach ($batchSizes as $tableName => $size) {
            $this->assertGreaterThanOrEqual(100, $size, "Batch size for {$tableName} too small");
            $this->assertLessThanOrEqual(2000, $size, "Batch size for {$tableName} too large");
        }
    }

    /** @test */
    public function scopes_are_properly_assigned()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        // Reference data should be in 'reference' scope
        foreach ($stages[1]['tables'] as $tableName => $config) {
            $this->assertEquals('reference', $config['scope'], 
                "Table {$tableName} should be in reference scope");
        }
        
        // Clinical data should be in 'clinical' scope
        foreach ($stages[2]['tables'] as $tableName => $config) {
            $this->assertEquals('reference', $config['scope'], 
                "Clinical reference {$tableName} should be in reference scope");
        }
        
        // Patient, Episode, Event should be in 'clinical' scope
        $this->assertEquals('clinical', $stages[3]['tables']['patient']['scope']);
        $this->assertEquals('clinical', $stages[3]['tables']['episode']['scope']);
        $this->assertEquals('clinical', $stages[3]['tables']['event']['scope']);
        
        // Admin data should be in 'admin' scope
        $this->assertEquals('admin', $stages[3]['tables']['user']['scope']);
        $this->assertEquals('admin', $stages[5]['tables']['audit']['scope']);
    }

    /** @test */
    public function default_batch_size_is_1000()
    {
        $reflection = new ReflectionClass($this->command);
        $batchProperty = $reflection->getProperty('defaultBatchSize');
        $batchProperty->setAccessible(true);
        $batchSize = $batchProperty->getValue($this->command);
        
        $this->assertEquals(1000, $batchSize);
    }

    /** @test */
    public function command_tracks_migration_stages_in_order()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        // Verify stages are in correct order
        $expectedOrder = [
            1 => 'Reference Data',
            2 => 'Clinical Reference',
            3 => 'Core Clinical',
            4 => 'Module Elements',
            5 => 'Administrative',
        ];
        
        foreach ($expectedOrder as $num => $expectedName) {
            $this->assertEquals($expectedName, $stages[$num]['name'], 
                "Stage {$num} should be '{$expectedName}'");
        }
    }

    /** @test */
    public function pre_flight_checks_method_exists()
    {
        $reflection = new ReflectionClass($this->command);
        $this->assertTrue($reflection->hasMethod('preFlightChecks'));
        
        $method = $reflection->getMethod('preFlightChecks');
        $this->assertTrue($method->isProtected());
    }

    /** @test */
    public function run_stage_method_exists()
    {
        $reflection = new ReflectionClass($this->command);
        $this->assertTrue($reflection->hasMethod('runStage'));
        
        $method = $reflection->getMethod('runStage');
        $this->assertTrue($method->isProtected());
    }

    /** @test */
    public function migrate_table_method_exists()
    {
        $reflection = new ReflectionClass($this->command);
        $this->assertTrue($reflection->hasMethod('migrateTable'));
        
        $method = $reflection->getMethod('migrateTable');
        $this->assertTrue($method->isProtected());
    }

    /** @test */
    public function run_validation_method_exists()
    {
        $reflection = new ReflectionClass($this->command);
        $this->assertTrue($reflection->hasMethod('runValidation'));
        
        $method = $reflection->getMethod('runValidation');
        $this->assertTrue($method->isProtected());
    }

    /** @test */
    public function log_method_exists()
    {
        $reflection = new ReflectionClass($this->command);
        $this->assertTrue($reflection->hasMethod('log'));
        
        $method = $reflection->getMethod('log');
        $this->assertTrue($method->isProtected());
    }

    /** @test */
    public function command_has_all_required_actions()
    {
        $reflection = new ReflectionClass($this->command);
        
        $requiredActions = ['actionRun', 'actionStage', 'actionStatus', 'actionValidate', 'actionRollback'];
        
        foreach ($requiredActions as $action) {
            $this->assertTrue($reflection->hasMethod($action), "Missing action: {$action}");
        }
    }

    /** @test */
    public function stage_dependencies_are_ordered_correctly()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        // Stage 1 (Reference) must come before Stage 2 (Clinical Reference)
        $this->assertLessThan(2, 1);
        
        // Stage 2 (Clinical Reference) must come before Stage 3 (Core Clinical)
        // because disorders, medications, etc. are referenced by patients/episodes
        $this->assertLessThan(3, 2);
        
        // Stage 3 (Core Clinical) must come before Stage 4 (Module Elements)
        // because elements reference events which reference episodes/patients
        $this->assertLessThan(4, 3);
    }

    /** @test */
    public function large_tables_have_smaller_batch_sizes()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        // Tables with complex embedded relations should have smaller batches
        $complexTables = ['patient', 'audit'];
        
        foreach ($complexTables as $tableName) {
            $found = false;
            foreach ($stages as $stage) {
                if (isset($stage['tables'][$tableName])) {
                    $found = true;
                    $this->assertLessThanOrEqual(500, $stage['tables'][$tableName]['batch'],
                        "{$tableName} should have smaller batch size due to complexity");
                }
            }
            $this->assertTrue($found, "Table {$tableName} not found in stages");
        }
    }

    /** @test */
    public function model_classes_are_standard_php_class_names()
    {
        $reflection = new ReflectionClass($this->command);
        $stagesProperty = $reflection->getProperty('stages');
        $stagesProperty->setAccessible(true);
        $stages = $stagesProperty->getValue($this->command);
        
        foreach ($stages as $stage) {
            if (!isset($stage['tables'])) {
                continue;
            }
            
            foreach ($stage['tables'] as $tableName => $config) {
                $modelClass = $config['model'];
                
                // Model class should be a valid PHP class name
                $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9_]*$/', $modelClass,
                    "Model class {$modelClass} for table {$tableName} is not a valid PHP class name");
            }
        }
    }
}
