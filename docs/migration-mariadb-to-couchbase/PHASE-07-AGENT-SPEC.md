# Phase 7: Services Layer Migration - Agent Executable Specification

**Version**: 1.0.0  
**Date**: December 22, 2025  
**Status**: READY FOR IMPLEMENTATION  
**Estimated Duration**: 2-3 weeks  
**Total Tasks**: 22 tasks across 7 sections  

---

## Executive Summary

This specification provides step-by-step instructions for migrating the OpenEyes services layer to support Couchbase while maintaining full backward compatibility with MariaDB. The migration uses a database-agnostic service pattern that allows gradual adoption without breaking existing functionality.

---

## Prerequisites

### Required Phase Completions
- [x] Phase 1: Infrastructure & Couchbase Setup (COMPLETED)
- [x] Phase 2: Abstract Database Layer (COMPLETED)
- [x] Phase 3: Data Modeling & Schema Translation (COMPLETED)
- [x] Phase 4: Core Model Migration (COMPLETED)
- [x] Phase 5: Module Model Migration (COMPLETED)
- [x] Phase 6: Query Migration (COMPLETED)

### Required Running Services
```bash
# Verify Couchbase is running
docker compose -f docker-compose.couchbase.yml ps
# Expected: openeyes-couchbase running and healthy

# Verify OpenEyes containers
docker compose -f .devcontainer/docker-compose.yml ps
# Expected: web (port 7777), db (port 3333) both healthy
```

### Required Files from Previous Phases
| File | Purpose | Location |
|------|---------|----------|
| `CouchbaseConnection.php` | Couchbase connection component | `protected/components/CouchbaseConnection.php` |
| `DatabaseAdapterFactory.php` | Adapter factory | `protected/components/database/DatabaseAdapterFactory.php` |
| `N1qlQueryBuilder.php` | Query builder | `protected/components/database/N1qlQueryBuilder.php` |
| `PatientDocument.php` | Patient document model | `protected/models/couchbase/PatientDocument.php` |
| `EpisodeDocument.php` | Episode document model | `protected/models/couchbase/EpisodeDocument.php` |
| `EventDocument.php` | Event document model | `protected/models/couchbase/EventDocument.php` |
| `CouchbasePatientSearch.php` | Patient search report | `protected/components/reports/CouchbasePatientSearch.php` |
| `CouchbaseEpisodeReport.php` | Episode report | `protected/components/reports/CouchbaseEpisodeReport.php` |

### Directory Structure to Create
```
protected/
├── services/
│   ├── DatabaseAgnosticService.php      # NEW - Base service class
│   ├── PatientService.php               # MODIFY - Add Couchbase support
│   ├── EpisodeService.php               # NEW - Episode service
│   ├── EventService.php                 # NEW - Event service
│   ├── ExaminationService.php           # NEW - Examination service
│   └── fhir/
│       ├── BaseFhirService.php          # NEW - FHIR base class
│       ├── FhirPatientService.php       # NEW - FHIR patient resource
│       └── FhirEpisodeService.php       # NEW - FHIR episode resource
├── commands/
│   ├── ServiceBenchmarkCommand.php      # NEW - Performance benchmark
│   └── ServiceVerifyCommand.php         # NEW - Service verification
├── tests/
│   └── unit/
│       └── services/
│           ├── DatabaseAgnosticServiceTest.php
│           ├── PatientServiceTest.php
│           ├── EpisodeServiceTest.php
│           └── fhir/
│               └── FhirPatientServiceTest.php
```

---

## Section 1: Base Service Infrastructure (Tasks 1-3)

### Task 1.1: Create DatabaseAgnosticService Base Class

**File**: `/protected/services/DatabaseAgnosticService.php`

```php
<?php
/**
 * Base service class supporting both MariaDB and Couchbase
 * 
 * Extend this class to create services that can transparently
 * switch between database backends based on configuration.
 */

namespace services;

use OE\Database\DatabaseAdapterFactory;
use OE\Database\DatabaseAdapterInterface;

abstract class DatabaseAgnosticService extends InternalService
{
    /** @var DatabaseAdapterInterface */
    protected $adapter;
    
    /** @var bool Whether to use Couchbase for reads */
    protected $useCouchbase = false;
    
    /** @var bool Whether dual-write is enabled */
    protected $dualWriteEnabled = false;
    
    /** @var string|null Override collection for this service */
    protected $collection = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->initializeAdapter();
    }
    
    /**
     * Initialize the database adapter based on configuration
     */
    protected function initializeAdapter()
    {
        $this->useCouchbase = \Yii::app()->params['enable_couchbase_read'] ?? false;
        $this->dualWriteEnabled = \Yii::app()->params['enable_dual_write'] ?? false;
        
        if ($this->useCouchbase && $this->collection) {
            $this->adapter = DatabaseAdapterFactory::getAdapterForCollection($this->collection);
        } else {
            $this->adapter = DatabaseAdapterFactory::getAdapter(
                $this->useCouchbase 
                    ? DatabaseAdapterFactory::ADAPTER_COUCHBASE 
                    : DatabaseAdapterFactory::ADAPTER_MARIADB
            );
        }
    }
    
    /**
     * Get adapter for specific collection
     * @param string $collection Collection name
     * @return DatabaseAdapterInterface
     */
    protected function getAdapterForCollection(string $collection): DatabaseAdapterInterface
    {
        return DatabaseAdapterFactory::getAdapterForCollection($collection);
    }
    
    /**
     * Check if using Couchbase for reads
     * @return bool
     */
    protected function isUsingCouchbase(): bool
    {
        return $this->useCouchbase;
    }
    
    /**
     * Check if dual-write is enabled
     * @return bool
     */
    protected function isDualWriteEnabled(): bool
    {
        return $this->dualWriteEnabled;
    }
    
    /**
     * Get Couchbase adapter directly
     * @return DatabaseAdapterInterface
     */
    protected function getCouchbaseAdapter(): DatabaseAdapterInterface
    {
        return DatabaseAdapterFactory::getAdapter(DatabaseAdapterFactory::ADAPTER_COUCHBASE);
    }
    
    /**
     * Get MariaDB adapter directly
     * @return DatabaseAdapterInterface
     */
    protected function getMariaDbAdapter(): DatabaseAdapterInterface
    {
        return DatabaseAdapterFactory::getAdapter(DatabaseAdapterFactory::ADAPTER_MARIADB);
    }
    
    /**
     * Execute read operation with fallback
     * Tries Couchbase first, falls back to MariaDB on error
     * 
     * @param callable $couchbaseOp Couchbase operation
     * @param callable $mariaDbOp MariaDB operation
     * @return mixed Operation result
     */
    protected function executeWithFallback(callable $couchbaseOp, callable $mariaDbOp)
    {
        if (!$this->useCouchbase) {
            return $mariaDbOp();
        }
        
        try {
            return $couchbaseOp();
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase operation failed, falling back to MariaDB: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.services'
            );
            return $mariaDbOp();
        }
    }
    
    /**
     * Normalize result to array format (handles both model and document results)
     * @param mixed $result Result from database operation
     * @return array|null Normalized array or null
     */
    protected function normalizeResult($result): ?array
    {
        if ($result === null) {
            return null;
        }
        
        if (is_array($result)) {
            return $result;
        }
        
        if (is_object($result)) {
            if (method_exists($result, 'getAttributes')) {
                return $result->getAttributes();
            }
            return (array)$result;
        }
        
        return null;
    }
    
    /**
     * Normalize multiple results to array format
     * @param array $results Array of results
     * @return array Normalized array of arrays
     */
    protected function normalizeResults(array $results): array
    {
        return array_map([$this, 'normalizeResult'], $results);
    }
}
```

**Acceptance Criteria**:
- [ ] Class extends InternalService
- [ ] Adapter initialization based on config
- [ ] Fallback mechanism works
- [ ] Result normalization handles both formats

---

### Task 1.2: Create Service Feature Flags Configuration

**File**: `/protected/config/core/common.php` (MODIFY)

**Add to params array** (find existing params array and add these):

```php
// Phase 7: Services Layer Feature Flags
'enable_couchbase_services' => strtolower(getenv('ENABLE_COUCHBASE_SERVICES') ?: '') === 'true',
'couchbase_service_collections' => [
    'patient' => true,
    'episode' => true,
    'event' => true,
    'examination' => false, // Enable gradually
],
```

**Acceptance Criteria**:
- [ ] Feature flags added to common.php
- [ ] Environment variable support
- [ ] Per-collection configuration

---

### Task 1.3: Create DatabaseAgnosticService Unit Test

**File**: `/protected/tests/unit/services/DatabaseAgnosticServiceTest.php`

```php
<?php
/**
 * Unit tests for DatabaseAgnosticService
 */

class DatabaseAgnosticServiceTest extends CTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Yii::app()->params['enable_couchbase_read'] = false;
        Yii::app()->params['enable_dual_write'] = false;
    }
    
    public function tearDown(): void
    {
        Yii::app()->params['enable_couchbase_read'] = false;
        Yii::app()->params['enable_dual_write'] = false;
        parent::tearDown();
    }
    
    /**
     * @test
     */
    public function testIsUsingCouchbaseDefaultFalse()
    {
        $service = new TestDatabaseAgnosticService();
        $this->assertFalse($service->isUsingCouchbasePublic());
    }
    
    /**
     * @test
     */
    public function testIsUsingCouchbaseWhenEnabled()
    {
        Yii::app()->params['enable_couchbase_read'] = true;
        $service = new TestDatabaseAgnosticService();
        $this->assertTrue($service->isUsingCouchbasePublic());
    }
    
    /**
     * @test
     */
    public function testNormalizeResultWithNull()
    {
        $service = new TestDatabaseAgnosticService();
        $this->assertNull($service->normalizeResultPublic(null));
    }
    
    /**
     * @test
     */
    public function testNormalizeResultWithArray()
    {
        $service = new TestDatabaseAgnosticService();
        $input = ['id' => 1, 'name' => 'Test'];
        $result = $service->normalizeResultPublic($input);
        $this->assertEquals($input, $result);
    }
    
    /**
     * @test
     */
    public function testNormalizeResultWithObject()
    {
        $service = new TestDatabaseAgnosticService();
        $obj = new stdClass();
        $obj->id = 1;
        $obj->name = 'Test';
        
        $result = $service->normalizeResultPublic($obj);
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['id']);
        $this->assertEquals('Test', $result['name']);
    }
    
    /**
     * @test
     */
    public function testNormalizeResults()
    {
        $service = new TestDatabaseAgnosticService();
        $results = [
            ['id' => 1, 'name' => 'Test1'],
            ['id' => 2, 'name' => 'Test2'],
        ];
        
        $normalized = $service->normalizeResultsPublic($results);
        $this->assertCount(2, $normalized);
        $this->assertEquals(1, $normalized[0]['id']);
        $this->assertEquals(2, $normalized[1]['id']);
    }
    
    /**
     * @test
     */
    public function testExecuteWithFallbackUsesMariaDbWhenCouchbaseDisabled()
    {
        $service = new TestDatabaseAgnosticService();
        
        $couchbaseCalled = false;
        $mariaDbCalled = false;
        
        $result = $service->executeWithFallbackPublic(
            function() use (&$couchbaseCalled) {
                $couchbaseCalled = true;
                return 'couchbase';
            },
            function() use (&$mariaDbCalled) {
                $mariaDbCalled = true;
                return 'mariadb';
            }
        );
        
        $this->assertFalse($couchbaseCalled);
        $this->assertTrue($mariaDbCalled);
        $this->assertEquals('mariadb', $result);
    }
    
    /**
     * @test
     */
    public function testExecuteWithFallbackUsesCouchbaseWhenEnabled()
    {
        Yii::app()->params['enable_couchbase_read'] = true;
        $service = new TestDatabaseAgnosticService();
        
        $couchbaseCalled = false;
        
        $result = $service->executeWithFallbackPublic(
            function() use (&$couchbaseCalled) {
                $couchbaseCalled = true;
                return 'couchbase';
            },
            function() {
                return 'mariadb';
            }
        );
        
        $this->assertTrue($couchbaseCalled);
        $this->assertEquals('couchbase', $result);
    }
    
    /**
     * @test
     */
    public function testExecuteWithFallbackFallsBackOnError()
    {
        Yii::app()->params['enable_couchbase_read'] = true;
        $service = new TestDatabaseAgnosticService();
        
        $result = $service->executeWithFallbackPublic(
            function() {
                throw new Exception('Couchbase error');
            },
            function() {
                return 'mariadb';
            }
        );
        
        $this->assertEquals('mariadb', $result);
    }
}

/**
 * Test implementation of DatabaseAgnosticService
 */
class TestDatabaseAgnosticService extends \services\DatabaseAgnosticService
{
    protected $collection = 'test';
    
    public function isUsingCouchbasePublic(): bool
    {
        return $this->isUsingCouchbase();
    }
    
    public function normalizeResultPublic($result): ?array
    {
        return $this->normalizeResult($result);
    }
    
    public function normalizeResultsPublic(array $results): array
    {
        return $this->normalizeResults($results);
    }
    
    public function executeWithFallbackPublic(callable $couchbaseOp, callable $mariaDbOp)
    {
        return $this->executeWithFallback($couchbaseOp, $mariaDbOp);
    }
}
```

**Acceptance Criteria**:
- [ ] All unit tests pass
- [ ] Tests cover initialization, normalization, fallback

---

## Section 2: Patient Service Migration (Tasks 4-7)

### Task 2.1: Update PatientService with Couchbase Support

**File**: `/protected/services/PatientService.php` (MODIFY)

Replace or update the class to extend DatabaseAgnosticService:

```php
<?php
/**
 * Patient Service with Couchbase support
 */

namespace services;

use OE\Reports\CouchbasePatientSearch;

class PatientService extends DatabaseAgnosticService
{
    protected $collection = 'patient';
    
    /**
     * Create a new patient
     * @param array $data Patient data
     * @return array Created patient
     * @throws ValidationFailure
     */
    public function create(array $data): array
    {
        // Always create in MariaDB first (source of truth)
        $patient = new \Patient();
        $patient->attributes = $data;
        
        if (!$patient->save()) {
            throw new ValidationFailure('Patient validation failed', $patient->getErrors());
        }
        
        // Dual-write handled by model's afterSave hook
        return $this->normalizeResult($patient);
    }
    
    /**
     * Read patient by ID
     * @param string $id Patient ID
     * @return array|null Patient data or null
     */
    public function read(string $id): ?array
    {
        return $this->executeWithFallback(
            function() use ($id) {
                return $this->readFromCouchbase($id);
            },
            function() use ($id) {
                return $this->readFromMariaDB($id);
            }
        );
    }
    
    /**
     * Read patient from Couchbase
     */
    private function readFromCouchbase(string $id): ?array
    {
        $doc = \PatientDocument::findByPk($id);
        return $doc ? $doc->getAttributes() : null;
    }
    
    /**
     * Read patient from MariaDB
     */
    private function readFromMariaDB(string $id): ?array
    {
        $patient = \Patient::model()->findByPk($id);
        return $this->normalizeResult($patient);
    }
    
    /**
     * Update patient
     * @param string $id Patient ID
     * @param array $data Updated data
     * @return array Updated patient
     * @throws NotFound
     * @throws ValidationFailure
     */
    public function update(string $id, array $data): array
    {
        $patient = \Patient::model()->findByPk($id);
        if (!$patient) {
            throw new NotFound("Patient not found: {$id}");
        }
        
        $patient->attributes = $data;
        if (!$patient->save()) {
            throw new ValidationFailure('Patient update failed', $patient->getErrors());
        }
        
        return $this->normalizeResult($patient);
    }
    
    /**
     * Delete patient (soft delete)
     * @param string $id Patient ID
     * @return bool Success
     * @throws NotFound
     */
    public function delete(string $id): bool
    {
        $patient = \Patient::model()->findByPk($id);
        if (!$patient) {
            throw new NotFound("Patient not found: {$id}");
        }
        
        $patient->deleted = 1;
        return $patient->save(false);
    }
    
    /**
     * Search patients
     * @param array $params Search parameters
     * @return array Search results
     */
    public function search(array $params): array
    {
        return $this->executeWithFallback(
            function() use ($params) {
                return $this->searchCouchbase($params);
            },
            function() use ($params) {
                return $this->searchMariaDB($params);
            }
        );
    }
    
    /**
     * Search patients in Couchbase
     */
    private function searchCouchbase(array $params): array
    {
        $searcher = new CouchbasePatientSearch();
        return $searcher->search($params);
    }
    
    /**
     * Search patients in MariaDB
     */
    private function searchMariaDB(array $params): array
    {
        $criteria = new \CDbCriteria();
        
        if (!empty($params['hos_num'])) {
            $criteria->compare('hos_num', $params['hos_num']);
        }
        if (!empty($params['nhs_num'])) {
            $criteria->compare('nhs_num', $params['nhs_num']);
        }
        if (!empty($params['last_name'])) {
            $criteria->with = ['contact'];
            $criteria->addCondition('contact.last_name LIKE :lastName');
            $criteria->params[':lastName'] = $params['last_name'] . '%';
        }
        if (!empty($params['first_name'])) {
            if (!isset($criteria->with)) {
                $criteria->with = ['contact'];
            }
            $criteria->addCondition('contact.first_name LIKE :firstName');
            $criteria->params[':firstName'] = $params['first_name'] . '%';
        }
        if (!empty($params['dob'])) {
            $criteria->compare('dob', $params['dob']);
        }
        
        $criteria->limit = $params['limit'] ?? 50;
        $criteria->offset = $params['offset'] ?? 0;
        
        $patients = \Patient::model()->findAll($criteria);
        return $this->normalizeResults($patients);
    }
    
    /**
     * Find patient by hospital number
     * @param string $hosNum Hospital number
     * @return array|null Patient data or null
     */
    public function findByHosNum(string $hosNum): ?array
    {
        return $this->executeWithFallback(
            function() use ($hosNum) {
                $doc = \PatientDocument::findByHosNum($hosNum);
                return $doc ? $doc->getAttributes() : null;
            },
            function() use ($hosNum) {
                $patient = \Patient::model()->findByAttributes(['hos_num' => $hosNum]);
                return $this->normalizeResult($patient);
            }
        );
    }
    
    /**
     * Find patient by NHS number
     * @param string $nhsNum NHS number
     * @return array|null Patient data or null
     */
    public function findByNhsNum(string $nhsNum): ?array
    {
        return $this->executeWithFallback(
            function() use ($nhsNum) {
                $doc = \PatientDocument::findByNhsNum($nhsNum);
                return $doc ? $doc->getAttributes() : null;
            },
            function() use ($nhsNum) {
                $patient = \Patient::model()->findByAttributes(['nhs_num' => $nhsNum]);
                return $this->normalizeResult($patient);
            }
        );
    }
    
    /**
     * Get patient's episodes
     * @param string $patientId Patient ID
     * @return array Episodes
     */
    public function getEpisodes(string $patientId): array
    {
        return $this->executeWithFallback(
            function() use ($patientId) {
                $episodes = \EpisodeDocument::findByPatientId($patientId);
                return array_map(function($ep) { return $ep->getAttributes(); }, $episodes);
            },
            function() use ($patientId) {
                $episodes = \Episode::model()->findAllByAttributes(
                    ['patient_id' => $patientId],
                    ['order' => 'start_date DESC']
                );
                return $this->normalizeResults($episodes);
            }
        );
    }
    
    /**
     * Get patient's events
     * @param string $patientId Patient ID
     * @param int $limit Maximum events to return
     * @return array Events
     */
    public function getEvents(string $patientId, int $limit = 50): array
    {
        return $this->executeWithFallback(
            function() use ($patientId, $limit) {
                $events = \EventDocument::findByPatientId($patientId, $limit);
                return array_map(function($ev) { return $ev->getAttributes(); }, $events);
            },
            function() use ($patientId, $limit) {
                $criteria = new \CDbCriteria();
                $criteria->with = ['episode'];
                $criteria->addCondition('episode.patient_id = :patientId');
                $criteria->params[':patientId'] = $patientId;
                $criteria->order = 't.event_date DESC';
                $criteria->limit = $limit;
                
                $events = \Event::model()->findAll($criteria);
                return $this->normalizeResults($events);
            }
        );
    }
}
```

**Acceptance Criteria**:
- [ ] CRUD operations work
- [ ] Search uses Couchbase when enabled
- [ ] Fallback to MariaDB on errors
- [ ] Results normalized consistently

---

### Task 2.2: Create PatientService Unit Test

**File**: `/protected/tests/unit/services/PatientServiceTest.php`

```php
<?php
/**
 * Unit tests for PatientService
 */

class PatientServiceTest extends CTestCase
{
    private $service;
    
    public function setUp(): void
    {
        parent::setUp();
        Yii::app()->params['enable_couchbase_read'] = false;
        $this->service = new \services\PatientService();
    }
    
    public function tearDown(): void
    {
        Yii::app()->params['enable_couchbase_read'] = false;
        parent::tearDown();
    }
    
    /**
     * @test
     */
    public function testSearchReturnsArray()
    {
        $results = $this->service->search(['limit' => 5]);
        $this->assertIsArray($results);
    }
    
    /**
     * @test
     */
    public function testSearchWithHosNum()
    {
        $results = $this->service->search([
            'hos_num' => 'TESTPATIENT123',
            'limit' => 1,
        ]);
        $this->assertIsArray($results);
    }
    
    /**
     * @test
     */
    public function testSearchWithLastName()
    {
        $results = $this->service->search([
            'last_name' => 'Smith',
            'limit' => 10,
        ]);
        $this->assertIsArray($results);
    }
    
    /**
     * @test
     */
    public function testReadReturnsNullForInvalidId()
    {
        $result = $this->service->read('999999999');
        $this->assertNull($result);
    }
    
    /**
     * @test
     */
    public function testFindByHosNumReturnsNullWhenNotFound()
    {
        $result = $this->service->findByHosNum('NONEXISTENT12345');
        $this->assertNull($result);
    }
    
    /**
     * @test
     */
    public function testFindByNhsNumReturnsNullWhenNotFound()
    {
        $result = $this->service->findByNhsNum('0000000000');
        $this->assertNull($result);
    }
    
    /**
     * @test
     */
    public function testGetEpisodesReturnsArray()
    {
        // Get a real patient ID if available
        $patient = \Patient::model()->find();
        if ($patient) {
            $episodes = $this->service->getEpisodes($patient->id);
            $this->assertIsArray($episodes);
        } else {
            $this->markTestSkipped('No patients in test database');
        }
    }
    
    /**
     * @test
     */
    public function testGetEventsReturnsArray()
    {
        $patient = \Patient::model()->find();
        if ($patient) {
            $events = $this->service->getEvents($patient->id);
            $this->assertIsArray($events);
        } else {
            $this->markTestSkipped('No patients in test database');
        }
    }
    
    /**
     * @test
     * @expectedException \services\NotFound
     */
    public function testUpdateThrowsNotFoundForInvalidId()
    {
        $this->expectException(\services\NotFound::class);
        $this->service->update('999999999', ['hos_num' => 'TEST']);
    }
    
    /**
     * @test
     * @expectedException \services\NotFound
     */
    public function testDeleteThrowsNotFoundForInvalidId()
    {
        $this->expectException(\services\NotFound::class);
        $this->service->delete('999999999');
    }
}
```

**Acceptance Criteria**:
- [ ] All unit tests pass
- [ ] Tests cover search, read, CRUD operations
- [ ] Exception handling tested

---

## Section 3: Episode Service (Tasks 8-10)

### Task 3.1: Create EpisodeService

**File**: `/protected/services/EpisodeService.php`

```php
<?php
/**
 * Episode Service with Couchbase support
 */

namespace services;

use OE\Reports\CouchbaseEpisodeReport;

class EpisodeService extends DatabaseAgnosticService
{
    protected $collection = 'episode';
    
    /**
     * Get episodes for patient
     * @param string $patientId Patient ID
     * @return array Episodes
     */
    public function getForPatient(string $patientId): array
    {
        return $this->executeWithFallback(
            function() use ($patientId) {
                $episodes = \EpisodeDocument::findByPatientId($patientId);
                return array_map(function($ep) { return $ep->getAttributes(); }, $episodes);
            },
            function() use ($patientId) {
                $episodes = \Episode::model()->findAllByAttributes(
                    ['patient_id' => $patientId],
                    ['order' => 'start_date DESC']
                );
                return $this->normalizeResults($episodes);
            }
        );
    }
    
    /**
     * Create episode
     * @param array $data Episode data
     * @return array Created episode
     * @throws ValidationFailure
     */
    public function create(array $data): array
    {
        $episode = new \Episode();
        $episode->attributes = $data;
        
        if (!$episode->save()) {
            throw new ValidationFailure('Episode validation failed', $episode->getErrors());
        }
        
        return $this->normalizeResult($episode);
    }
    
    /**
     * Read episode by ID
     * @param string $id Episode ID
     * @return array|null Episode data or null
     */
    public function read(string $id): ?array
    {
        return $this->executeWithFallback(
            function() use ($id) {
                $doc = \EpisodeDocument::findByPk($id);
                return $doc ? $doc->getAttributes() : null;
            },
            function() use ($id) {
                $episode = \Episode::model()->findByPk($id);
                return $this->normalizeResult($episode);
            }
        );
    }
    
    /**
     * Update episode
     * @param string $id Episode ID
     * @param array $data Updated data
     * @return array Updated episode
     * @throws NotFound
     * @throws ValidationFailure
     */
    public function update(string $id, array $data): array
    {
        $episode = \Episode::model()->findByPk($id);
        if (!$episode) {
            throw new NotFound("Episode not found: {$id}");
        }
        
        $episode->attributes = $data;
        if (!$episode->save()) {
            throw new ValidationFailure('Episode update failed', $episode->getErrors());
        }
        
        return $this->normalizeResult($episode);
    }
    
    /**
     * Get events for episode
     * @param string $episodeId Episode ID
     * @return array Events
     */
    public function getEvents(string $episodeId): array
    {
        return $this->executeWithFallback(
            function() use ($episodeId) {
                $events = \EventDocument::findByEpisodeId($episodeId);
                return array_map(function($ev) { return $ev->getAttributes(); }, $events);
            },
            function() use ($episodeId) {
                $events = \Event::model()->findAllByAttributes(
                    ['episode_id' => $episodeId],
                    ['order' => 'event_date DESC']
                );
                return $this->normalizeResults($events);
            }
        );
    }
    
    /**
     * Get episode statistics for reporting
     * @param array $params Statistics parameters
     * @return array Statistics
     */
    public function getStatistics(array $params = []): array
    {
        if ($this->isUsingCouchbase()) {
            return $this->getStatisticsCouchbase($params);
        }
        return $this->getStatisticsMariaDB($params);
    }
    
    /**
     * Get statistics from Couchbase
     */
    private function getStatisticsCouchbase(array $params): array
    {
        $report = new CouchbaseEpisodeReport();
        return $report->countBySubspecialty(
            $params['start_date'] ?? date('Y-01-01'),
            $params['end_date'] ?? date('Y-m-d'),
            $params['institution_id'] ?? null
        );
    }
    
    /**
     * Get statistics from MariaDB
     */
    private function getStatisticsMariaDB(array $params): array
    {
        $sql = "SELECT subspecialty_id, COUNT(*) as count 
                FROM episode 
                WHERE start_date BETWEEN :start AND :end";
        $sqlParams = [
            ':start' => $params['start_date'] ?? date('Y-01-01'),
            ':end' => $params['end_date'] ?? date('Y-m-d'),
        ];
        
        if (!empty($params['institution_id'])) {
            $sql .= " AND institution_id = :institution";
            $sqlParams[':institution'] = $params['institution_id'];
        }
        
        $sql .= " GROUP BY subspecialty_id ORDER BY count DESC";
        
        $command = \Yii::app()->db->createCommand($sql);
        foreach ($sqlParams as $key => $value) {
            $command->bindValue($key, $value);
        }
        
        return $command->queryAll();
    }
    
    /**
     * Find episodes by firm
     * @param string $firmId Firm ID
     * @param int $limit Maximum results
     * @return array Episodes
     */
    public function findByFirm(string $firmId, int $limit = 100): array
    {
        return $this->executeWithFallback(
            function() use ($firmId, $limit) {
                $episodes = \EpisodeDocument::findByFirmId($firmId, $limit);
                return array_map(function($ep) { return $ep->getAttributes(); }, $episodes);
            },
            function() use ($firmId, $limit) {
                $criteria = new \CDbCriteria();
                $criteria->compare('firm_id', $firmId);
                $criteria->order = 'start_date DESC';
                $criteria->limit = $limit;
                
                $episodes = \Episode::model()->findAll($criteria);
                return $this->normalizeResults($episodes);
            }
        );
    }
    
    /**
     * Check if episode is open
     * @param string $id Episode ID
     * @return bool True if open
     */
    public function isOpen(string $id): bool
    {
        $episode = $this->read($id);
        return $episode && empty($episode['end_date']);
    }
    
    /**
     * Close episode
     * @param string $id Episode ID
     * @param string|null $endDate End date (defaults to today)
     * @return array Updated episode
     * @throws NotFound
     */
    public function close(string $id, ?string $endDate = null): array
    {
        $episode = \Episode::model()->findByPk($id);
        if (!$episode) {
            throw new NotFound("Episode not found: {$id}");
        }
        
        $episode->end_date = $endDate ?? date('Y-m-d');
        $episode->save(false);
        
        return $this->normalizeResult($episode);
    }
}
```

**Acceptance Criteria**:
- [ ] CRUD operations work
- [ ] getForPatient uses Couchbase when enabled
- [ ] Statistics methods work
- [ ] Episode close functionality works

---

### Task 3.2: Create EventService

**File**: `/protected/services/EventService.php`

```php
<?php
/**
 * Event Service with Couchbase support
 */

namespace services;

class EventService extends DatabaseAgnosticService
{
    protected $collection = 'event';
    
    /**
     * Read event by ID
     * @param string $id Event ID
     * @return array|null Event data or null
     */
    public function read(string $id): ?array
    {
        return $this->executeWithFallback(
            function() use ($id) {
                $doc = \EventDocument::findByPk($id);
                return $doc ? $doc->getAttributes() : null;
            },
            function() use ($id) {
                $event = \Event::model()->findByPk($id);
                return $this->normalizeResult($event);
            }
        );
    }
    
    /**
     * Get events for episode
     * @param string $episodeId Episode ID
     * @return array Events
     */
    public function getForEpisode(string $episodeId): array
    {
        return $this->executeWithFallback(
            function() use ($episodeId) {
                $events = \EventDocument::findByEpisodeId($episodeId);
                return array_map(function($ev) { return $ev->getAttributes(); }, $events);
            },
            function() use ($episodeId) {
                $events = \Event::model()->findAllByAttributes(
                    ['episode_id' => $episodeId],
                    ['order' => 'event_date DESC']
                );
                return $this->normalizeResults($events);
            }
        );
    }
    
    /**
     * Get events for patient
     * @param string $patientId Patient ID
     * @param int $limit Maximum results
     * @return array Events
     */
    public function getForPatient(string $patientId, int $limit = 100): array
    {
        return $this->executeWithFallback(
            function() use ($patientId, $limit) {
                $events = \EventDocument::findByPatientId($patientId, $limit);
                return array_map(function($ev) { return $ev->getAttributes(); }, $events);
            },
            function() use ($patientId, $limit) {
                $criteria = new \CDbCriteria();
                $criteria->with = ['episode'];
                $criteria->addCondition('episode.patient_id = :patientId');
                $criteria->params[':patientId'] = $patientId;
                $criteria->order = 't.event_date DESC';
                $criteria->limit = $limit;
                
                $events = \Event::model()->findAll($criteria);
                return $this->normalizeResults($events);
            }
        );
    }
    
    /**
     * Get events by type
     * @param int $eventTypeId Event type ID
     * @param array $options Query options
     * @return array Events
     */
    public function getByType(int $eventTypeId, array $options = []): array
    {
        return $this->executeWithFallback(
            function() use ($eventTypeId, $options) {
                $events = \EventDocument::findByEventType($eventTypeId, $options['limit'] ?? 100);
                return array_map(function($ev) { return $ev->getAttributes(); }, $events);
            },
            function() use ($eventTypeId, $options) {
                $criteria = new \CDbCriteria();
                $criteria->compare('event_type_id', $eventTypeId);
                $criteria->order = 'event_date DESC';
                $criteria->limit = $options['limit'] ?? 100;
                
                if (!empty($options['start_date'])) {
                    $criteria->addCondition('event_date >= :startDate');
                    $criteria->params[':startDate'] = $options['start_date'];
                }
                
                if (!empty($options['end_date'])) {
                    $criteria->addCondition('event_date <= :endDate');
                    $criteria->params[':endDate'] = $options['end_date'];
                }
                
                $events = \Event::model()->findAll($criteria);
                return $this->normalizeResults($events);
            }
        );
    }
    
    /**
     * Delete event (soft delete)
     * @param string $id Event ID
     * @param string $reason Delete reason
     * @return bool Success
     * @throws NotFound
     */
    public function delete(string $id, string $reason = ''): bool
    {
        $event = \Event::model()->findByPk($id);
        if (!$event) {
            throw new NotFound("Event not found: {$id}");
        }
        
        $event->deleted = 1;
        $event->delete_reason = $reason;
        return $event->save(false);
    }
    
    /**
     * Get event count by type for date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Counts by event type
     */
    public function getCountByType(string $startDate, string $endDate): array
    {
        $sql = "SELECT event_type_id, COUNT(*) as count 
                FROM event 
                WHERE event_date BETWEEN :start AND :end 
                AND deleted = 0
                GROUP BY event_type_id
                ORDER BY count DESC";
        
        $command = \Yii::app()->db->createCommand($sql);
        $command->bindValue(':start', $startDate);
        $command->bindValue(':end', $endDate);
        
        return $command->queryAll();
    }
}
```

**Acceptance Criteria**:
- [ ] CRUD operations work
- [ ] Event retrieval by episode, patient, type
- [ ] Soft delete functionality
- [ ] Statistics methods work

---

### Task 3.3: Create EpisodeService Tests

**File**: `/protected/tests/unit/services/EpisodeServiceTest.php`

```php
<?php
/**
 * Unit tests for EpisodeService
 */

class EpisodeServiceTest extends CTestCase
{
    private $service;
    
    public function setUp(): void
    {
        parent::setUp();
        Yii::app()->params['enable_couchbase_read'] = false;
        $this->service = new \services\EpisodeService();
    }
    
    /**
     * @test
     */
    public function testGetForPatientReturnsArray()
    {
        $patient = \Patient::model()->find();
        if ($patient) {
            $episodes = $this->service->getForPatient($patient->id);
            $this->assertIsArray($episodes);
        } else {
            $this->markTestSkipped('No patients in test database');
        }
    }
    
    /**
     * @test
     */
    public function testReadReturnsNullForInvalidId()
    {
        $result = $this->service->read('999999999');
        $this->assertNull($result);
    }
    
    /**
     * @test
     */
    public function testGetEventsReturnsArray()
    {
        $episode = \Episode::model()->find();
        if ($episode) {
            $events = $this->service->getEvents($episode->id);
            $this->assertIsArray($events);
        } else {
            $this->markTestSkipped('No episodes in test database');
        }
    }
    
    /**
     * @test
     */
    public function testGetStatisticsReturnsArray()
    {
        $stats = $this->service->getStatistics([
            'start_date' => date('Y-01-01'),
            'end_date' => date('Y-m-d'),
        ]);
        $this->assertIsArray($stats);
    }
    
    /**
     * @test
     */
    public function testIsOpenReturnsFalseForInvalidId()
    {
        $result = $this->service->isOpen('999999999');
        $this->assertFalse($result);
    }
}
```

---

## Section 4: FHIR API Services (Tasks 11-14)

### Task 4.1: Create FHIR Service Base

**File**: `/protected/services/fhir/BaseFhirService.php`

```php
<?php
/**
 * Base class for FHIR services
 */

namespace services\fhir;

use services\DatabaseAgnosticService;

abstract class BaseFhirService extends DatabaseAgnosticService
{
    /** @var string FHIR version */
    protected $fhirVersion = '4.0.1';
    
    /**
     * Convert internal data to FHIR resource
     * @param array $data Internal data
     * @return array FHIR resource
     */
    abstract protected function toFhirResource(array $data): array;
    
    /**
     * Convert FHIR resource to internal format
     * @param array $resource FHIR resource
     * @return array Internal data
     */
    abstract protected function fromFhirResource(array $resource): array;
    
    /**
     * Build FHIR identifier
     * @param string $system Identifier system URI
     * @param string|null $value Identifier value
     * @return array|null Identifier or null if no value
     */
    protected function buildIdentifier(string $system, ?string $value): ?array
    {
        if (empty($value)) {
            return null;
        }
        
        return [
            'system' => $system,
            'value' => $value,
        ];
    }
    
    /**
     * Build FHIR reference
     * @param string $resourceType Resource type
     * @param string $id Resource ID
     * @return array Reference object
     */
    protected function buildReference(string $resourceType, string $id): array
    {
        return [
            'reference' => "{$resourceType}/{$id}",
        ];
    }
    
    /**
     * Build FHIR Bundle
     * @param string $type Bundle type (searchset, collection, etc.)
     * @param array $resources Array of resources
     * @return array FHIR Bundle
     */
    protected function buildBundle(string $type, array $resources): array
    {
        return [
            'resourceType' => 'Bundle',
            'type' => $type,
            'total' => count($resources),
            'entry' => array_map(function($resource) {
                return [
                    'resource' => $resource,
                ];
            }, $resources),
        ];
    }
    
    /**
     * Build FHIR meta element
     * @param string|null $versionId Version ID
     * @param string|null $lastUpdated Last updated timestamp
     * @return array Meta element
     */
    protected function buildMeta(?string $versionId = null, ?string $lastUpdated = null): array
    {
        return [
            'versionId' => $versionId ?? '1',
            'lastUpdated' => $lastUpdated ?? date('c'),
        ];
    }
    
    /**
     * Build FHIR coding element
     * @param string $system Coding system URI
     * @param string $code Code value
     * @param string|null $display Display text
     * @return array Coding element
     */
    protected function buildCoding(string $system, string $code, ?string $display = null): array
    {
        $coding = [
            'system' => $system,
            'code' => $code,
        ];
        
        if ($display !== null) {
            $coding['display'] = $display;
        }
        
        return $coding;
    }
    
    /**
     * Build FHIR codeable concept
     * @param array $codings Array of coding elements
     * @param string|null $text Text description
     * @return array CodeableConcept element
     */
    protected function buildCodeableConcept(array $codings, ?string $text = null): array
    {
        $concept = ['coding' => $codings];
        
        if ($text !== null) {
            $concept['text'] = $text;
        }
        
        return $concept;
    }
}
```

**Acceptance Criteria**:
- [ ] Abstract methods defined
- [ ] Helper methods for FHIR data structures
- [ ] Bundle building works

---

### Task 4.2: Create FhirPatientService

**File**: `/protected/services/fhir/FhirPatientService.php`

```php
<?php
/**
 * FHIR Patient Resource Service
 */

namespace services\fhir;

use services\PatientService;
use services\NotFound;

class FhirPatientService extends BaseFhirService
{
    protected $collection = 'patient';
    
    /** @var PatientService */
    private $patientService;
    
    public function __construct()
    {
        parent::__construct();
        $this->patientService = new PatientService();
    }
    
    /**
     * Get patient as FHIR Patient resource
     * @param string $id Patient ID
     * @return array FHIR Patient resource
     * @throws NotFound
     */
    public function getPatient(string $id): array
    {
        $patient = $this->patientService->read($id);
        
        if (!$patient) {
            throw new NotFound("Patient not found: {$id}");
        }
        
        return $this->toFhirResource($patient);
    }
    
    /**
     * Search patients returning FHIR Bundle
     * @param array $params Search parameters
     * @return array FHIR Bundle
     */
    public function searchPatients(array $params): array
    {
        $results = $this->patientService->search($params);
        
        $resources = array_map([$this, 'toFhirResource'], $results);
        return $this->buildBundle('searchset', $resources);
    }
    
    /**
     * Create patient from FHIR resource
     * @param array $resource FHIR Patient resource
     * @return array Created FHIR Patient resource
     */
    public function createPatient(array $resource): array
    {
        $data = $this->fromFhirResource($resource);
        $patient = $this->patientService->create($data);
        return $this->toFhirResource($patient);
    }
    
    /**
     * Update patient from FHIR resource
     * @param string $id Patient ID
     * @param array $resource FHIR Patient resource
     * @return array Updated FHIR Patient resource
     */
    public function updatePatient(string $id, array $resource): array
    {
        $data = $this->fromFhirResource($resource);
        $patient = $this->patientService->update($id, $data);
        return $this->toFhirResource($patient);
    }
    
    /**
     * Convert patient data to FHIR Patient resource
     * @param array $patient Internal patient data
     * @return array FHIR Patient resource
     */
    protected function toFhirResource(array $patient): array
    {
        $resource = [
            'resourceType' => 'Patient',
            'id' => (string)($patient['id'] ?? $patient['_mysql_id'] ?? ''),
            'meta' => $this->buildMeta(
                (string)($patient['_version'] ?? '1'),
                $patient['_modified'] ?? null
            ),
            'identifier' => array_values(array_filter([
                $this->buildIdentifier(
                    'http://hospital.example.org/patients',
                    $patient['hos_num'] ?? null
                ),
                $this->buildIdentifier(
                    'https://fhir.nhs.uk/Id/nhs-number',
                    $patient['nhs_num'] ?? null
                ),
            ])),
            'name' => [
                [
                    'use' => 'official',
                    'family' => $this->extractName($patient, 'last_name'),
                    'given' => array_filter([$this->extractName($patient, 'first_name')]),
                ],
            ],
            'gender' => $this->mapGender($patient['gender'] ?? 'U'),
            'birthDate' => $patient['dob'] ?? null,
        ];
        
        // Add deceased information
        if (!empty($patient['date_of_death'])) {
            $resource['deceasedDateTime'] = $patient['date_of_death'];
        } elseif (!empty($patient['is_deceased'])) {
            $resource['deceasedBoolean'] = true;
        }
        
        // Add addresses if embedded
        if (!empty($patient['addresses'])) {
            $resource['address'] = array_map([$this, 'mapAddress'], $patient['addresses']);
        }
        
        // Add telecom
        $telecom = [];
        $phone = $patient['contact']['primary_phone'] ?? $patient['primary_phone'] ?? null;
        $email = $patient['contact']['email'] ?? $patient['email'] ?? null;
        
        if (!empty($phone)) {
            $telecom[] = [
                'system' => 'phone',
                'value' => $phone,
                'use' => 'home',
            ];
        }
        if (!empty($email)) {
            $telecom[] = [
                'system' => 'email',
                'value' => $email,
            ];
        }
        if (!empty($telecom)) {
            $resource['telecom'] = $telecom;
        }
        
        return $resource;
    }
    
    /**
     * Convert FHIR Patient resource to internal format
     * @param array $resource FHIR Patient resource
     * @return array Internal patient data
     */
    protected function fromFhirResource(array $resource): array
    {
        $data = [];
        
        // Extract identifiers
        foreach ($resource['identifier'] ?? [] as $identifier) {
            $system = $identifier['system'] ?? '';
            $value = $identifier['value'] ?? null;
            
            if (strpos($system, 'nhs-number') !== false) {
                $data['nhs_num'] = $value;
            } elseif (strpos($system, 'patients') !== false) {
                $data['hos_num'] = $value;
            }
        }
        
        // Extract name
        if (!empty($resource['name'][0])) {
            $name = $resource['name'][0];
            $data['last_name'] = $name['family'] ?? null;
            $data['first_name'] = $name['given'][0] ?? null;
        }
        
        // Map gender
        $data['gender'] = $this->reverseMapGender($resource['gender'] ?? 'unknown');
        
        // Birth date
        $data['dob'] = $resource['birthDate'] ?? null;
        
        // Deceased
        if (isset($resource['deceasedDateTime'])) {
            $data['date_of_death'] = $resource['deceasedDateTime'];
        } elseif (!empty($resource['deceasedBoolean'])) {
            $data['is_deceased'] = true;
        }
        
        return $data;
    }
    
    /**
     * Extract name from patient data
     */
    private function extractName(array $patient, string $field): ?string
    {
        if (isset($patient['contact'][$field])) {
            return $patient['contact'][$field];
        }
        return $patient[$field] ?? null;
    }
    
    /**
     * Map internal gender to FHIR
     */
    private function mapGender(string $gender): string
    {
        $map = ['M' => 'male', 'F' => 'female', 'U' => 'unknown'];
        return $map[$gender] ?? 'unknown';
    }
    
    /**
     * Map FHIR gender to internal
     */
    private function reverseMapGender(string $gender): string
    {
        $map = ['male' => 'M', 'female' => 'F', 'unknown' => 'U', 'other' => 'U'];
        return $map[$gender] ?? 'U';
    }
    
    /**
     * Map internal address to FHIR
     */
    private function mapAddress(array $address): array
    {
        return [
            'use' => ($address['is_primary'] ?? false) ? 'home' : 'temp',
            'type' => 'physical',
            'line' => array_values(array_filter([
                $address['address1'] ?? null,
                $address['address2'] ?? null,
            ])),
            'city' => $address['city'] ?? null,
            'state' => $address['county'] ?? null,
            'postalCode' => $address['postcode'] ?? null,
            'country' => $address['country'] ?? null,
        ];
    }
}
```

**Acceptance Criteria**:
- [ ] FHIR Patient resource format correct
- [ ] All identifiers mapped properly
- [ ] Bundle search results work
- [ ] Bidirectional conversion works

---

### Task 4.3: Create FhirEpisodeService

**File**: `/protected/services/fhir/FhirEpisodeService.php`

```php
<?php
/**
 * FHIR EpisodeOfCare Resource Service
 */

namespace services\fhir;

use services\EpisodeService;
use services\NotFound;

class FhirEpisodeService extends BaseFhirService
{
    protected $collection = 'episode';
    
    /** @var EpisodeService */
    private $episodeService;
    
    public function __construct()
    {
        parent::__construct();
        $this->episodeService = new EpisodeService();
    }
    
    /**
     * Get episode as FHIR EpisodeOfCare resource
     * @param string $id Episode ID
     * @return array FHIR EpisodeOfCare resource
     * @throws NotFound
     */
    public function getEpisode(string $id): array
    {
        $episode = $this->episodeService->read($id);
        
        if (!$episode) {
            throw new NotFound("Episode not found: {$id}");
        }
        
        return $this->toFhirResource($episode);
    }
    
    /**
     * Get episodes for patient as FHIR Bundle
     * @param string $patientId Patient ID
     * @return array FHIR Bundle
     */
    public function getForPatient(string $patientId): array
    {
        $episodes = $this->episodeService->getForPatient($patientId);
        
        $resources = array_map([$this, 'toFhirResource'], $episodes);
        return $this->buildBundle('searchset', $resources);
    }
    
    /**
     * Convert episode data to FHIR EpisodeOfCare resource
     * @param array $episode Internal episode data
     * @return array FHIR EpisodeOfCare resource
     */
    protected function toFhirResource(array $episode): array
    {
        $resource = [
            'resourceType' => 'EpisodeOfCare',
            'id' => (string)($episode['id'] ?? $episode['_mysql_id'] ?? ''),
            'meta' => $this->buildMeta(
                (string)($episode['_version'] ?? '1'),
                $episode['_modified'] ?? null
            ),
            'status' => $this->mapStatus($episode),
            'patient' => $this->buildReference('Patient', (string)($episode['patient_id'] ?? '')),
            'period' => [
                'start' => $episode['start_date'] ?? null,
            ],
        ];
        
        // Add end date if episode is closed
        if (!empty($episode['end_date'])) {
            $resource['period']['end'] = $episode['end_date'];
        }
        
        // Add managing organization if available
        if (!empty($episode['institution_id'])) {
            $resource['managingOrganization'] = $this->buildReference(
                'Organization',
                (string)$episode['institution_id']
            );
        }
        
        // Add diagnosis if available
        if (!empty($episode['disorder_id']) || !empty($episode['principal_diagnosis'])) {
            $resource['diagnosis'] = [
                [
                    'condition' => $this->buildReference(
                        'Condition',
                        (string)($episode['disorder_id'] ?? $episode['principal_diagnosis']['id'] ?? '')
                    ),
                    'rank' => 1,
                ],
            ];
        }
        
        // Add subspecialty as type
        if (!empty($episode['subspecialty_name'])) {
            $resource['type'] = [
                $this->buildCodeableConcept(
                    [$this->buildCoding(
                        'http://openeyes.org.uk/subspecialty',
                        (string)($episode['subspecialty_id'] ?? ''),
                        $episode['subspecialty_name']
                    )],
                    $episode['subspecialty_name']
                ),
            ];
        }
        
        return $resource;
    }
    
    /**
     * Convert FHIR EpisodeOfCare resource to internal format
     * @param array $resource FHIR EpisodeOfCare resource
     * @return array Internal episode data
     */
    protected function fromFhirResource(array $resource): array
    {
        $data = [];
        
        // Extract patient reference
        if (!empty($resource['patient']['reference'])) {
            $parts = explode('/', $resource['patient']['reference']);
            $data['patient_id'] = end($parts);
        }
        
        // Extract period
        if (!empty($resource['period'])) {
            $data['start_date'] = $resource['period']['start'] ?? null;
            $data['end_date'] = $resource['period']['end'] ?? null;
        }
        
        // Extract managing organization
        if (!empty($resource['managingOrganization']['reference'])) {
            $parts = explode('/', $resource['managingOrganization']['reference']);
            $data['institution_id'] = end($parts);
        }
        
        return $data;
    }
    
    /**
     * Map internal episode status to FHIR status
     */
    private function mapStatus(array $episode): string
    {
        if (!empty($episode['end_date'])) {
            return 'finished';
        }
        
        $statusName = $episode['status_name'] ?? '';
        
        switch (strtolower($statusName)) {
            case 'new':
                return 'planned';
            case 'active':
            case 'open':
                return 'active';
            case 'on hold':
            case 'waiting':
                return 'onhold';
            case 'cancelled':
                return 'cancelled';
            case 'finished':
            case 'closed':
                return 'finished';
            default:
                return 'active';
        }
    }
}
```

**Acceptance Criteria**:
- [ ] FHIR EpisodeOfCare resource format correct
- [ ] Status mapping works
- [ ] Patient reference correct
- [ ] Bundle for patient works

---

### Task 4.4: Create FHIR Service Tests

**File**: `/protected/tests/unit/services/fhir/FhirPatientServiceTest.php`

```php
<?php
/**
 * Unit tests for FhirPatientService
 */

class FhirPatientServiceTest extends CTestCase
{
    private $service;
    
    public function setUp(): void
    {
        parent::setUp();
        Yii::app()->params['enable_couchbase_read'] = false;
        $this->service = new \services\fhir\FhirPatientService();
    }
    
    /**
     * @test
     */
    public function testToFhirResourceReturnsValidStructure()
    {
        $patient = [
            'id' => 123,
            'hos_num' => 'HOS123',
            'nhs_num' => '1234567890',
            'dob' => '1990-01-15',
            'gender' => 'M',
            'contact' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ],
        ];
        
        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('toFhirResource');
        $method->setAccessible(true);
        
        $resource = $method->invoke($this->service, $patient);
        
        $this->assertEquals('Patient', $resource['resourceType']);
        $this->assertEquals('123', $resource['id']);
        $this->assertEquals('male', $resource['gender']);
        $this->assertEquals('1990-01-15', $resource['birthDate']);
        $this->assertEquals('Doe', $resource['name'][0]['family']);
        $this->assertContains('John', $resource['name'][0]['given']);
    }
    
    /**
     * @test
     */
    public function testFromFhirResourceExtractsData()
    {
        $resource = [
            'resourceType' => 'Patient',
            'id' => '123',
            'identifier' => [
                ['system' => 'http://hospital.example.org/patients', 'value' => 'HOS123'],
                ['system' => 'https://fhir.nhs.uk/Id/nhs-number', 'value' => '1234567890'],
            ],
            'name' => [
                ['family' => 'Doe', 'given' => ['John']],
            ],
            'gender' => 'male',
            'birthDate' => '1990-01-15',
        ];
        
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('fromFhirResource');
        $method->setAccessible(true);
        
        $data = $method->invoke($this->service, $resource);
        
        $this->assertEquals('HOS123', $data['hos_num']);
        $this->assertEquals('1234567890', $data['nhs_num']);
        $this->assertEquals('Doe', $data['last_name']);
        $this->assertEquals('John', $data['first_name']);
        $this->assertEquals('M', $data['gender']);
        $this->assertEquals('1990-01-15', $data['dob']);
    }
    
    /**
     * @test
     */
    public function testSearchPatientsReturnsBundle()
    {
        $result = $this->service->searchPatients(['limit' => 5]);
        
        $this->assertEquals('Bundle', $result['resourceType']);
        $this->assertEquals('searchset', $result['type']);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('entry', $result);
    }
    
    /**
     * @test
     * @expectedException \services\NotFound
     */
    public function testGetPatientThrowsNotFoundForInvalidId()
    {
        $this->expectException(\services\NotFound::class);
        $this->service->getPatient('999999999');
    }
}
```

---

## Section 5: Service Verification Commands (Tasks 15-17)

### Task 5.1: Create Service Benchmark Command

**File**: `/protected/commands/ServiceBenchmarkCommand.php`

```php
<?php
/**
 * Benchmark service layer performance
 * 
 * Usage:
 *   yiic servicebenchmark run                    - Run all benchmarks
 *   yiic servicebenchmark patient                - Benchmark patient service
 *   yiic servicebenchmark compare                - Compare MariaDB vs Couchbase
 */

class ServiceBenchmarkCommand extends CConsoleCommand
{
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic servicebenchmark <action> [options]

ACTIONS
  run       - Run all service benchmarks
  patient   - Benchmark PatientService only
  episode   - Benchmark EpisodeService only
  compare   - Compare MariaDB vs Couchbase performance

OPTIONS
  --iterations=<n>  Number of iterations (default: 100)
  --verbose         Show detailed output

EXAMPLES
  yiic servicebenchmark run --iterations=50
  yiic servicebenchmark compare --verbose
EOD;
    }
    
    public function actionRun($iterations = 100, $verbose = false)
    {
        echo "=== Service Layer Benchmark ===\n\n";
        echo "Iterations: {$iterations}\n\n";
        
        $this->benchmarkPatientService($iterations, $verbose);
        echo "\n";
        $this->benchmarkEpisodeService($iterations, $verbose);
        echo "\n";
        $this->benchmarkEventService($iterations, $verbose);
    }
    
    public function actionPatient($iterations = 100, $verbose = false)
    {
        echo "=== PatientService Benchmark ===\n\n";
        $this->benchmarkPatientService($iterations, $verbose);
    }
    
    public function actionEpisode($iterations = 100, $verbose = false)
    {
        echo "=== EpisodeService Benchmark ===\n\n";
        $this->benchmarkEpisodeService($iterations, $verbose);
    }
    
    public function actionCompare($iterations = 50, $verbose = false)
    {
        echo "=== MariaDB vs Couchbase Comparison ===\n\n";
        
        // Get sample patient
        $patient = Patient::model()->find();
        if (!$patient) {
            echo "No patients found for testing\n";
            return;
        }
        
        $service = new \services\PatientService();
        
        // MariaDB only
        Yii::app()->params['enable_couchbase_read'] = false;
        $service = new \services\PatientService();
        
        $mariaDbTimes = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->search(['last_name' => 'Smith', 'limit' => 10]);
            $mariaDbTimes[] = (microtime(true) - $start) * 1000;
        }
        
        // Couchbase (if available)
        $couchbaseTimes = [];
        if ($this->isCouchbaseAvailable()) {
            Yii::app()->params['enable_couchbase_read'] = true;
            $service = new \services\PatientService();
            
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                $service->search(['last_name' => 'Smith', 'limit' => 10]);
                $couchbaseTimes[] = (microtime(true) - $start) * 1000;
            }
        }
        
        // Results
        echo "Patient Search (name prefix):\n";
        echo str_pad("Backend", 15) . str_pad("Avg (ms)", 12) . str_pad("Min", 12) . str_pad("Max", 12) . "\n";
        echo str_repeat("-", 51) . "\n";
        
        $avg = array_sum($mariaDbTimes) / count($mariaDbTimes);
        printf("%-15s%-12.2f%-12.2f%-12.2f\n", "MariaDB", $avg, min($mariaDbTimes), max($mariaDbTimes));
        
        if (!empty($couchbaseTimes)) {
            $avg = array_sum($couchbaseTimes) / count($couchbaseTimes);
            printf("%-15s%-12.2f%-12.2f%-12.2f\n", "Couchbase", $avg, min($couchbaseTimes), max($couchbaseTimes));
        } else {
            echo "Couchbase      Not available\n";
        }
        
        // Reset
        Yii::app()->params['enable_couchbase_read'] = false;
    }
    
    private function benchmarkPatientService($iterations, $verbose)
    {
        echo "PatientService:\n";
        
        $service = new \services\PatientService();
        
        // Get a sample patient
        $patient = Patient::model()->find();
        
        // Search benchmark
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->search(['last_name' => 'Sm', 'limit' => 10]);
            $times[] = (microtime(true) - $start) * 1000;
        }
        $this->printStats("  search()", $times, $verbose);
        
        // Read benchmark
        if ($patient) {
            $times = [];
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                $service->read($patient->id);
                $times[] = (microtime(true) - $start) * 1000;
            }
            $this->printStats("  read()", $times, $verbose);
            
            // getEpisodes benchmark
            $times = [];
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                $service->getEpisodes($patient->id);
                $times[] = (microtime(true) - $start) * 1000;
            }
            $this->printStats("  getEpisodes()", $times, $verbose);
        }
    }
    
    private function benchmarkEpisodeService($iterations, $verbose)
    {
        echo "EpisodeService:\n";
        
        $service = new \services\EpisodeService();
        
        $patient = Patient::model()->find();
        if (!$patient) {
            echo "  Skipped: No patients\n";
            return;
        }
        
        // getForPatient
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->getForPatient($patient->id);
            $times[] = (microtime(true) - $start) * 1000;
        }
        $this->printStats("  getForPatient()", $times, $verbose);
        
        // getStatistics
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->getStatistics([
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-m-d'),
            ]);
            $times[] = (microtime(true) - $start) * 1000;
        }
        $this->printStats("  getStatistics()", $times, $verbose);
    }
    
    private function benchmarkEventService($iterations, $verbose)
    {
        echo "EventService:\n";
        
        $service = new \services\EventService();
        
        $patient = Patient::model()->find();
        if (!$patient) {
            echo "  Skipped: No patients\n";
            return;
        }
        
        $times = [];
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->getForPatient($patient->id, 20);
            $times[] = (microtime(true) - $start) * 1000;
        }
        $this->printStats("  getForPatient()", $times, $verbose);
    }
    
    private function printStats($label, $times, $verbose)
    {
        $avg = array_sum($times) / count($times);
        $min = min($times);
        $max = max($times);
        
        printf("%-20s avg: %6.2fms, min: %6.2fms, max: %6.2fms\n", $label, $avg, $min, $max);
        
        if ($verbose) {
            $p95 = $this->percentile($times, 95);
            $p99 = $this->percentile($times, 99);
            printf("%-20s p95: %6.2fms, p99: %6.2fms\n", "", $p95, $p99);
        }
    }
    
    private function percentile($array, $percentile)
    {
        sort($array);
        $index = ($percentile / 100) * count($array);
        
        if (floor($index) == $index) {
            return ($array[$index - 1] + $array[$index]) / 2;
        }
        
        return $array[floor($index)];
    }
    
    private function isCouchbaseAvailable()
    {
        try {
            $conn = Yii::app()->getComponent('couchbase');
            return $conn !== null;
        } catch (Exception $e) {
            return false;
        }
    }
}
```

**Acceptance Criteria**:
- [ ] Benchmarks run without errors
- [ ] Stats display correctly
- [ ] Comparison mode works

---

### Task 5.2: Create Service Verification Command

**File**: `/protected/commands/ServiceVerifyCommand.php`

```php
<?php
/**
 * Verify service layer functionality
 * 
 * Usage:
 *   yiic serviceverify all       - Verify all services
 *   yiic serviceverify patient   - Verify patient service
 *   yiic serviceverify fhir      - Verify FHIR services
 */

class ServiceVerifyCommand extends CConsoleCommand
{
    private $passed = 0;
    private $failed = 0;
    
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic serviceverify <action>

ACTIONS
  all       - Verify all services
  patient   - Verify PatientService
  episode   - Verify EpisodeService
  event     - Verify EventService
  fhir      - Verify FHIR services
  
EXAMPLES
  yiic serviceverify all
  yiic serviceverify patient
EOD;
    }
    
    public function actionAll()
    {
        echo "=== Service Layer Verification ===\n\n";
        
        $this->verifyPatientService();
        echo "\n";
        $this->verifyEpisodeService();
        echo "\n";
        $this->verifyEventService();
        echo "\n";
        $this->verifyFhirServices();
        
        echo "\n" . str_repeat("=", 40) . "\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        
        return $this->failed > 0 ? 1 : 0;
    }
    
    public function actionPatient()
    {
        $this->verifyPatientService();
        $this->printSummary();
    }
    
    public function actionEpisode()
    {
        $this->verifyEpisodeService();
        $this->printSummary();
    }
    
    public function actionEvent()
    {
        $this->verifyEventService();
        $this->printSummary();
    }
    
    public function actionFhir()
    {
        $this->verifyFhirServices();
        $this->printSummary();
    }
    
    private function verifyPatientService()
    {
        echo "PatientService:\n";
        
        try {
            $service = new \services\PatientService();
            $this->check("  Instantiation", true);
        } catch (Exception $e) {
            $this->check("  Instantiation", false, $e->getMessage());
            return;
        }
        
        // Search
        try {
            $results = $service->search(['limit' => 5]);
            $this->check("  search()", is_array($results));
        } catch (Exception $e) {
            $this->check("  search()", false, $e->getMessage());
        }
        
        // Read with valid patient
        $patient = Patient::model()->find();
        if ($patient) {
            try {
                $result = $service->read($patient->id);
                $this->check("  read(existing)", $result !== null && is_array($result));
            } catch (Exception $e) {
                $this->check("  read(existing)", false, $e->getMessage());
            }
        }
        
        // Read with invalid ID
        try {
            $result = $service->read('999999999');
            $this->check("  read(invalid)", $result === null);
        } catch (Exception $e) {
            $this->check("  read(invalid)", false, $e->getMessage());
        }
        
        // findByHosNum
        try {
            $result = $service->findByHosNum('NONEXISTENT');
            $this->check("  findByHosNum()", $result === null);
        } catch (Exception $e) {
            $this->check("  findByHosNum()", false, $e->getMessage());
        }
        
        // getEpisodes
        if ($patient) {
            try {
                $episodes = $service->getEpisodes($patient->id);
                $this->check("  getEpisodes()", is_array($episodes));
            } catch (Exception $e) {
                $this->check("  getEpisodes()", false, $e->getMessage());
            }
        }
    }
    
    private function verifyEpisodeService()
    {
        echo "EpisodeService:\n";
        
        try {
            $service = new \services\EpisodeService();
            $this->check("  Instantiation", true);
        } catch (Exception $e) {
            $this->check("  Instantiation", false, $e->getMessage());
            return;
        }
        
        $patient = Patient::model()->find();
        
        if ($patient) {
            try {
                $episodes = $service->getForPatient($patient->id);
                $this->check("  getForPatient()", is_array($episodes));
            } catch (Exception $e) {
                $this->check("  getForPatient()", false, $e->getMessage());
            }
        }
        
        // Statistics
        try {
            $stats = $service->getStatistics([
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-m-d'),
            ]);
            $this->check("  getStatistics()", is_array($stats));
        } catch (Exception $e) {
            $this->check("  getStatistics()", false, $e->getMessage());
        }
        
        // Read invalid
        try {
            $result = $service->read('999999999');
            $this->check("  read(invalid)", $result === null);
        } catch (Exception $e) {
            $this->check("  read(invalid)", false, $e->getMessage());
        }
    }
    
    private function verifyEventService()
    {
        echo "EventService:\n";
        
        try {
            $service = new \services\EventService();
            $this->check("  Instantiation", true);
        } catch (Exception $e) {
            $this->check("  Instantiation", false, $e->getMessage());
            return;
        }
        
        $patient = Patient::model()->find();
        
        if ($patient) {
            try {
                $events = $service->getForPatient($patient->id, 10);
                $this->check("  getForPatient()", is_array($events));
            } catch (Exception $e) {
                $this->check("  getForPatient()", false, $e->getMessage());
            }
        }
        
        // Read invalid
        try {
            $result = $service->read('999999999');
            $this->check("  read(invalid)", $result === null);
        } catch (Exception $e) {
            $this->check("  read(invalid)", false, $e->getMessage());
        }
    }
    
    private function verifyFhirServices()
    {
        echo "FHIR Services:\n";
        
        // FhirPatientService
        try {
            $service = new \services\fhir\FhirPatientService();
            $this->check("  FhirPatientService", true);
        } catch (Exception $e) {
            $this->check("  FhirPatientService", false, $e->getMessage());
        }
        
        // Search returns bundle
        try {
            $service = new \services\fhir\FhirPatientService();
            $result = $service->searchPatients(['limit' => 1]);
            $this->check("  searchPatients() bundle", 
                isset($result['resourceType']) && $result['resourceType'] === 'Bundle');
        } catch (Exception $e) {
            $this->check("  searchPatients() bundle", false, $e->getMessage());
        }
        
        // FhirEpisodeService
        try {
            $service = new \services\fhir\FhirEpisodeService();
            $this->check("  FhirEpisodeService", true);
        } catch (Exception $e) {
            $this->check("  FhirEpisodeService", false, $e->getMessage());
        }
    }
    
    private function check($label, $passed, $error = '')
    {
        if ($passed) {
            echo "{$label}: PASS\n";
            $this->passed++;
        } else {
            echo "{$label}: FAIL";
            if ($error) {
                echo " ({$error})";
            }
            echo "\n";
            $this->failed++;
        }
    }
    
    private function printSummary()
    {
        echo "\n" . str_repeat("-", 40) . "\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
    }
}
```

**Acceptance Criteria**:
- [ ] All verifications run without crashes
- [ ] Pass/fail results displayed
- [ ] Error messages helpful

---

### Task 5.3: Create Implementation Summary

**File**: `/docs/migration-mariadb-to-couchbase/PHASE-07-IMPLEMENTATION-SUMMARY.md`

```markdown
# Phase 7: Services Layer Migration - Implementation Summary

**Status**: COMPLETED  
**Date**: [Current Date]  

## Overview

Phase 7 migrated the OpenEyes services layer to support Couchbase while maintaining full backward compatibility with MariaDB.

## Components Implemented

### Base Infrastructure
- `DatabaseAgnosticService.php` - Base service class with dual-database support
- Feature flags in `common.php` for gradual rollout

### Core Services
- `PatientService.php` - Updated with Couchbase support
- `EpisodeService.php` - New episode service
- `EventService.php` - New event service

### FHIR Services
- `BaseFhirService.php` - FHIR service base class
- `FhirPatientService.php` - FHIR Patient resource
- `FhirEpisodeService.php` - FHIR EpisodeOfCare resource

### Commands
- `ServiceBenchmarkCommand.php` - Performance benchmarking
- `ServiceVerifyCommand.php` - Service verification

## Key Features

1. **Transparent Backend Switching**
   - Services automatically use Couchbase when enabled
   - Fallback to MariaDB on errors

2. **Result Normalization**
   - Consistent array format regardless of backend
   - Handles both CActiveRecord models and Couchbase documents

3. **FHIR Support**
   - FHIR R4 compliant resources
   - Bidirectional conversion (FHIR <-> internal)

## Configuration

Enable Couchbase services:
```bash
export ENABLE_COUCHBASE_READ=true
export ENABLE_COUCHBASE_SERVICES=true
```

## Verification

Run service verification:
```bash
php protected/yiic.php serviceverify all
```

Run benchmarks:
```bash
php protected/yiic.php servicebenchmark run
```

## Performance Results

| Service | Operation | MariaDB | Couchbase |
|---------|-----------|---------|-----------|
| PatientService | search() | ~15ms | ~12ms |
| PatientService | read() | ~5ms | ~3ms |
| EpisodeService | getForPatient() | ~8ms | ~5ms |

## Rollback

Set environment variables to disable:
```bash
export ENABLE_COUCHBASE_READ=false
export ENABLE_COUCHBASE_SERVICES=false
```

## Next Steps

- Phase 8: Data Migration Scripts
- Phase 9: Testing & Validation
```

**Acceptance Criteria**:
- [ ] Summary document created
- [ ] All components listed
- [ ] Verification steps included

---

## Testing Criteria

### Unit Tests
- [ ] DatabaseAgnosticService base class tests pass
- [ ] PatientService tests pass
- [ ] EpisodeService tests pass
- [ ] EventService tests pass
- [ ] FhirPatientService tests pass

### Integration Tests
- [ ] Services work with MariaDB backend
- [ ] Services work with Couchbase backend (when enabled)
- [ ] Fallback mechanism works correctly
- [ ] FHIR resources correctly formatted

### Performance Benchmarks
- [ ] Service operations: < 100ms average
- [ ] Search operations: < 200ms average
- [ ] FHIR conversions: < 50ms average

---

## Rollback Plan

1. **Immediate Rollback**:
   ```bash
   export ENABLE_COUCHBASE_SERVICES=false
   export ENABLE_COUCHBASE_READ=false
   ```

2. **All services automatically use MariaDB**

3. **No data changes required**

4. **Zero downtime rollback**

---

## Definition of Done

- [ ] DatabaseAgnosticService base class functional
- [ ] PatientService with Couchbase support
- [ ] EpisodeService with Couchbase support
- [ ] EventService with Couchbase support
- [ ] FHIR services functional
- [ ] All unit tests passing
- [ ] Verification commands working
- [ ] Benchmark commands working
- [ ] Documentation complete

---

*Phase 7 Completion Sign-off:*
- [ ] Technical Lead
- [ ] QA

*Estimated Duration: 2-3 weeks*
