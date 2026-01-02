# Phase 7: Services Layer Migration

## Overview
This phase migrates the services layer to support Couchbase, ensuring API endpoints and internal services work correctly with the new database backend.

## Prerequisites
- Phase 6 completed (Query migration done)
- All queries tested and working

## Dependencies
- Phase 6: Query Migration

## Tasks

### 7.1 Service Interface Updates

#### 7.1.1 Database-Agnostic Service Interface
**File**: `/protected/services/DatabaseAgnosticService.php`

```php
<?php
/**
 * Base service class supporting both MariaDB and Couchbase
 */

namespace services;

use OE\Database\DatabaseAdapterFactory;
use OE\Database\DatabaseAdapterInterface;

abstract class DatabaseAgnosticService
{
    protected $adapter;
    protected $useCouchbase = false;
    
    public function __construct()
    {
        $this->useCouchbase = \Yii::app()->params['enable_couchbase_read'] ?? false;
        $this->adapter = $this->useCouchbase
            ? DatabaseAdapterFactory::getAdapter(DatabaseAdapterFactory::ADAPTER_COUCHBASE)
            : DatabaseAdapterFactory::getAdapter(DatabaseAdapterFactory::ADAPTER_MARIADB);
    }
    
    /**
     * Get adapter for specific collection
     */
    protected function getAdapterForCollection(string $collection): DatabaseAdapterInterface
    {
        return DatabaseAdapterFactory::getAdapterForCollection($collection);
    }
    
    /**
     * Check if using Couchbase
     */
    protected function isUsingCouchbase(): bool
    {
        return $this->useCouchbase;
    }
}
```

### 7.2 Patient Service Migration

#### 7.2.1 Updated Patient Service
**File**: `/protected/services/PatientService.php` (update)

```php
<?php

namespace services;

class PatientService extends DatabaseAgnosticService
{
    private $resource = 'Patient';
    
    /**
     * Create a new patient
     */
    public function create(Patient $patient): Patient
    {
        if ($this->isUsingCouchbase()) {
            return $this->createInCouchbase($patient);
        }
        return $this->createInMariaDB($patient);
    }
    
    private function createInCouchbase(Patient $patient): Patient
    {
        $doc = $patient->toCouchbaseDocument();
        $id = $this->adapter->insert('patient', $doc);
        $patient->id = $id;
        return $patient;
    }
    
    private function createInMariaDB(Patient $patient): Patient
    {
        // Existing implementation
        return $patient;
    }
    
    /**
     * Read patient by ID
     */
    public function read(string $id): ?Patient
    {
        if ($this->isUsingCouchbase()) {
            return $this->readFromCouchbase($id);
        }
        return $this->readFromMariaDB($id);
    }
    
    private function readFromCouchbase(string $id): ?Patient
    {
        $doc = $this->adapter->findByPk('patient', $id);
        if (!$doc) {
            return null;
        }
        
        $patient = new \Patient();
        $patient->fromCouchbaseDocument($doc);
        return $patient;
    }
    
    private function readFromMariaDB(string $id): ?Patient
    {
        return \Patient::model()->findByPk($id);
    }
    
    /**
     * Search patients
     */
    public function search(array $params): array
    {
        if ($this->isUsingCouchbase()) {
            return $this->searchCouchbase($params);
        }
        return $this->searchMariaDB($params);
    }
    
    private function searchCouchbase(array $params): array
    {
        $searcher = new \OE\Reports\CouchbasePatientSearch();
        return $searcher->search($params);
    }
    
    private function searchMariaDB(array $params): array
    {
        // Existing search implementation
        $criteria = new \CDbCriteria();
        // Build criteria from params
        return \Patient::model()->findAll($criteria);
    }
}
```

### 7.3 Episode Service Migration

#### 7.3.1 Episode Service
**File**: `/protected/services/EpisodeService.php` (create)

```php
<?php

namespace services;

class EpisodeService extends DatabaseAgnosticService
{
    /**
     * Get episodes for patient
     */
    public function getForPatient(string $patientId): array
    {
        if ($this->isUsingCouchbase()) {
            return $this->getFromCouchbase($patientId);
        }
        return $this->getFromMariaDB($patientId);
    }
    
    private function getFromCouchbase(string $patientId): array
    {
        return $this->adapter->findByAttributes('episode', [
            'patient_id' => $patientId
        ], [
            'order' => 'start_date DESC'
        ]);
    }
    
    private function getFromMariaDB(string $patientId): array
    {
        return \Episode::model()->findAllByAttributes(
            ['patient_id' => $patientId],
            ['order' => 'start_date DESC']
        );
    }
    
    /**
     * Create episode
     */
    public function create(array $data): Episode
    {
        $episode = new \Episode();
        $episode->attributes = $data;
        
        if ($episode->save()) {
            // Dual-write handled in model
            return $episode;
        }
        
        throw new \Exception('Failed to create episode');
    }
}
```

### 7.4 FHIR API Updates

#### 7.4.1 FHIR Patient Resource
**File**: `/protected/services/fhir/FhirPatientService.php` (create)

```php
<?php

namespace services\fhir;

use services\DatabaseAgnosticService;

class FhirPatientService extends DatabaseAgnosticService
{
    /**
     * Get patient as FHIR resource
     */
    public function getPatient(string $id): array
    {
        $patient = $this->isUsingCouchbase()
            ? $this->getFromCouchbase($id)
            : $this->getFromMariaDB($id);
        
        if (!$patient) {
            throw new \services\NotFound("Patient not found: {$id}");
        }
        
        return $this->toFhirResource($patient);
    }
    
    private function getFromCouchbase(string $id): ?array
    {
        return $this->adapter->findByPk('patient', $id);
    }
    
    private function getFromMariaDB(string $id): ?array
    {
        $patient = \Patient::model()->findByPk($id);
        return $patient ? $patient->attributes : null;
    }
    
    private function toFhirResource(array $patient): array
    {
        return [
            'resourceType' => 'Patient',
            'id' => $patient['id'],
            'identifier' => [
                [
                    'system' => 'http://hospital.example.org/patients',
                    'value' => $patient['hos_num'],
                ],
                [
                    'system' => 'https://fhir.nhs.uk/Id/nhs-number',
                    'value' => $patient['nhs_num'] ?? null,
                ],
            ],
            'name' => [
                [
                    'family' => $patient['last_name'],
                    'given' => [$patient['first_name']],
                ],
            ],
            'birthDate' => $patient['dob'],
            'gender' => $this->mapGender($patient['gender'] ?? 'U'),
        ];
    }
    
    private function mapGender(string $gender): string
    {
        $map = ['M' => 'male', 'F' => 'female', 'U' => 'unknown'];
        return $map[$gender] ?? 'unknown';
    }
}
```

### 7.5 API Controllers Update

#### 7.5.1 Patient API Controller
**File**: `/protected/modules/Api/controllers/PatientController.php` (update)

```php
<?php
// Add methods for Couchbase-backed operations

class PatientController extends BaseApiController
{
    private $patientService;
    
    public function init()
    {
        parent::init();
        $this->patientService = new \services\PatientService();
    }
    
    /**
     * Search action supporting both backends
     */
    public function actionSearch()
    {
        $params = $this->getSearchParams();
        
        try {
            $results = $this->patientService->search($params);
            
            $this->renderJson([
                'success' => true,
                'data' => array_map(function($p) {
                    return $this->formatPatient($p);
                }, $results),
                'count' => count($results),
            ]);
        } catch (\Exception $e) {
            $this->renderError($e->getMessage());
        }
    }
    
    private function getSearchParams(): array
    {
        return [
            'hos_num' => Yii::app()->request->getParam('hos_num'),
            'nhs_num' => Yii::app()->request->getParam('nhs_num'),
            'last_name' => Yii::app()->request->getParam('last_name'),
            'first_name' => Yii::app()->request->getParam('first_name'),
            'dob' => Yii::app()->request->getParam('dob'),
            'limit' => (int) Yii::app()->request->getParam('limit', 50),
            'offset' => (int) Yii::app()->request->getParam('offset', 0),
        ];
    }
    
    private function formatPatient($patient): array
    {
        // Handle both array (Couchbase) and model (MariaDB)
        if (is_array($patient)) {
            return $patient;
        }
        return $patient->attributes;
    }
}
```

### 7.6 Service Manager Updates

#### 7.6.1 Update Service Manager
**File**: `/protected/services/ServiceManager.php` (update)

```php
<?php
// Add Couchbase-aware service registration

class ServiceManager
{
    private $services = [];
    
    /**
     * Register services with database backend awareness
     */
    public function registerServices()
    {
        $this->services['patient'] = new \services\PatientService();
        $this->services['episode'] = new \services\EpisodeService();
        // Add more services
    }
    
    /**
     * Get service instance
     */
    public function getService(string $name)
    {
        if (!isset($this->services[$name])) {
            throw new \Exception("Unknown service: {$name}");
        }
        return $this->services[$name];
    }
}
```

## Testing Criteria

### Service Tests
- [ ] PatientService works with both backends
- [ ] EpisodeService works with both backends
- [ ] FHIR endpoints return correct format

### API Tests
- [ ] Patient search API works
- [ ] Patient create API works
- [ ] Episode API works

### Integration Tests
- [ ] Frontend uses services correctly
- [ ] Data consistency maintained

## Acceptance Criteria
- [ ] All services support both databases
- [ ] API responses identical regardless of backend
- [ ] Performance acceptable
- [ ] Error handling consistent

## Rollback Plan
1. Set `enable_couchbase_read` to false
2. Services automatically use MariaDB

## Definition of Done
- [ ] All services migrated
- [ ] API tests passing
- [ ] Documentation updated

---

*Phase 7 Completion Sign-off:*
- [ ] Technical Lead
- [ ] QA

*Estimated Duration: 2-3 weeks*
