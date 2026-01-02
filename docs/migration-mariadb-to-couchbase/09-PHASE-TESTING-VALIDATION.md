# Phase 9: Testing & Validation

## Overview
This phase ensures comprehensive testing of the migrated system before production cutover.

## Prerequisites
- Phase 8 completed (Data migration done)
- All data validated
- Staging environment available

## Dependencies
- Phase 8: Data Migration Scripts

## Tasks

### 9.1 Test Environment Setup

#### 9.1.1 Couchbase Test Configuration
**File**: `/protected/config/local.sample/couchbase.test.php`

```php
<?php
/**
 * Couchbase test configuration
 */

return [
    'connection' => [
        'host' => getenv('COUCHBASE_TEST_HOST') ?: 'localhost',
        'username' => 'Administrator',
        'password' => 'password',
    ],
    'bucket' => 'openeyes_test',
    'scopes' => [
        'core' => 'core',
        'clinical' => 'clinical',
        'correspondence' => 'correspondence',
        'booking' => 'booking',
        'admin' => 'admin',
        'reference' => 'reference',
    ],
];
```

#### 9.1.2 Test Database Setup Script
**File**: `/protected/scripts/couchbase/setup-test-db.sh`

```bash
#!/bin/bash
# Setup Couchbase test database

CB_HOST="${CB_HOST:-localhost}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"

# Create test bucket
curl -X POST http://${CB_HOST}:8091/pools/default/buckets \
    -u ${CB_USER}:${CB_PASS} \
    -d name=openeyes_test \
    -d ramQuotaMB=256 \
    -d bucketType=couchbase

# Create scopes
for SCOPE in core clinical correspondence booking admin reference; do
    curl -X POST "http://${CB_HOST}:8091/pools/default/buckets/openeyes_test/scopes" \
        -u ${CB_USER}:${CB_PASS} \
        -d name=${SCOPE}
done

echo "Test database setup complete"
```

### 9.2 Unit Tests

#### 9.2.1 Adapter Unit Tests
**File**: `/protected/tests/unit/components/database/CouchbaseAdapterTest.php`

```php
<?php

namespace tests\unit\components\database;

use OE\Database\CouchbaseAdapter;

class CouchbaseAdapterTest extends \CDbTestCase
{
    private $adapter;
    
    protected function setUp()
    {
        parent::setUp();
        $this->adapter = new CouchbaseAdapter();
    }
    
    public function testFindByPkReturnsDocument()
    {
        // Insert test document
        $id = $this->adapter->insert('patient', [
            'hos_num' => 'TEST001',
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'dob' => '1990-01-01',
        ]);
        
        // Find it
        $result = $this->adapter->findByPk('patient', $id);
        
        $this->assertNotNull($result);
        $this->assertEquals('TEST001', $result['hos_num']);
        
        // Cleanup
        $this->adapter->delete('patient', $id);
    }
    
    public function testFindByAttributesReturnsArray()
    {
        $results = $this->adapter->findByAttributes('patient', [
            'last_name' => 'Smith'
        ]);
        
        $this->assertIsArray($results);
    }
    
    public function testInsertReturnsId()
    {
        $id = $this->adapter->insert('patient', [
            'hos_num' => 'TEST002',
            'first_name' => 'Another',
            'last_name' => 'Test',
            'dob' => '1985-06-15',
        ]);
        
        $this->assertNotNull($id);
        
        // Cleanup
        $this->adapter->delete('patient', $id);
    }
    
    public function testUpdateModifiesDocument()
    {
        // Insert
        $id = $this->adapter->insert('patient', [
            'hos_num' => 'TEST003',
            'first_name' => 'Update',
            'last_name' => 'Test',
            'dob' => '1980-03-20',
        ]);
        
        // Update
        $result = $this->adapter->update('patient', $id, [
            'hos_num' => 'TEST003',
            'first_name' => 'Updated',
            'last_name' => 'Test',
            'dob' => '1980-03-20',
        ]);
        
        $this->assertTrue($result);
        
        // Verify
        $doc = $this->adapter->findByPk('patient', $id);
        $this->assertEquals('Updated', $doc['first_name']);
        
        // Cleanup
        $this->adapter->delete('patient', $id);
    }
    
    public function testDeleteRemovesDocument()
    {
        // Insert
        $id = $this->adapter->insert('patient', [
            'hos_num' => 'TEST004',
            'first_name' => 'Delete',
            'last_name' => 'Me',
            'dob' => '1975-12-01',
        ]);
        
        // Delete
        $result = $this->adapter->delete('patient', $id);
        $this->assertTrue($result);
        
        // Verify
        $doc = $this->adapter->findByPk('patient', $id);
        $this->assertNull($doc);
    }
    
    public function testCountReturnsInteger()
    {
        $count = $this->adapter->count('patient');
        $this->assertIsInt($count);
    }
}
```

#### 9.2.2 Model Bridge Tests
**File**: `/protected/tests/unit/models/CouchbaseModelBridgeTest.php`

```php
<?php

namespace tests\unit\models;

class CouchbaseModelBridgeTest extends \CDbTestCase
{
    public function testPatientToCouchbaseDocument()
    {
        $patient = new \Patient();
        $patient->hos_num = 'TEST100';
        $patient->first_name = 'Bridge';
        $patient->last_name = 'Test';
        $patient->dob = '1990-05-15';
        
        $doc = $patient->toCouchbaseDocument();
        
        $this->assertEquals('patient', $doc['_type']);
        $this->assertEquals('TEST100', $doc['hos_num']);
        $this->assertEquals('Bridge', $doc['first_name']);
    }
    
    public function testPatientFromCouchbaseDocument()
    {
        $doc = [
            'id' => '123',
            'hos_num' => 'TEST101',
            'first_name' => 'From',
            'last_name' => 'Doc',
            'dob' => '1985-08-20',
            '_type' => 'patient',
        ];
        
        $patient = new \Patient();
        $patient->fromCouchbaseDocument($doc);
        
        $this->assertEquals('TEST101', $patient->hos_num);
        $this->assertEquals('From', $patient->first_name);
    }
}
```

### 9.3 Integration Tests

#### 9.3.1 Dual-Write Integration Tests
**File**: `/protected/tests/integration/DualWriteTest.php`

```php
<?php

namespace tests\integration;

class DualWriteTest extends \CDbTestCase
{
    protected function setUp()
    {
        parent::setUp();
        // Enable dual-write for tests
        \Yii::app()->params['enable_dual_write'] = true;
    }
    
    public function testPatientSavesInBothDatabases()
    {
        $patient = new \Patient();
        $patient->hos_num = 'DUAL001';
        $patient->first_name = 'Dual';
        $patient->last_name = 'Write';
        $patient->dob = '1990-01-01';
        $patient->save();
        
        // Verify in MariaDB
        $mysqlPatient = \Patient::model()->findByAttributes([
            'hos_num' => 'DUAL001'
        ]);
        $this->assertNotNull($mysqlPatient);
        
        // Verify in Couchbase
        $cbAdapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
        $cbPatient = $cbAdapter->findByPk('patient', $mysqlPatient->id);
        $this->assertNotNull($cbPatient);
        $this->assertEquals('DUAL001', $cbPatient['hos_num']);
        
        // Cleanup
        $patient->delete();
    }
    
    public function testPatientUpdateSyncsToCouhbase()
    {
        // Create
        $patient = new \Patient();
        $patient->hos_num = 'DUAL002';
        $patient->first_name = 'Before';
        $patient->last_name = 'Update';
        $patient->dob = '1985-06-15';
        $patient->save();
        
        // Update
        $patient->first_name = 'After';
        $patient->save();
        
        // Verify in Couchbase
        $cbAdapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
        $cbPatient = $cbAdapter->findByPk('patient', $patient->id);
        $this->assertEquals('After', $cbPatient['first_name']);
        
        // Cleanup
        $patient->delete();
    }
}
```

#### 9.3.2 Service Integration Tests
**File**: `/protected/tests/integration/PatientServiceTest.php`

```php
<?php

namespace tests\integration;

class PatientServiceTest extends \CDbTestCase
{
    private $service;
    
    protected function setUp()
    {
        parent::setUp();
        $this->service = new \services\PatientService();
    }
    
    public function testSearchWorksWithBothBackends()
    {
        // Search with MariaDB
        \Yii::app()->params['enable_couchbase_read'] = false;
        $mysqlResults = $this->service->search(['last_name' => 'Smith']);
        
        // Search with Couchbase
        \Yii::app()->params['enable_couchbase_read'] = true;
        $cbResults = $this->service->search(['last_name' => 'Smith']);
        
        // Results should be comparable
        $this->assertEquals(count($mysqlResults), count($cbResults));
    }
}
```

### 9.4 Performance Tests

#### 9.4.1 Query Performance Test
**File**: `/protected/tests/performance/QueryPerformanceTest.php`

```php
<?php

namespace tests\performance;

class QueryPerformanceTest extends \CTestCase
{
    /**
     * @test
     */
    public function patientSearchPerformance()
    {
        $iterations = 100;
        $mysqlTimes = [];
        $cbTimes = [];
        
        // MySQL performance
        \Yii::app()->params['enable_couchbase_read'] = false;
        $service = new \services\PatientService();
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->search(['last_name' => 'Smith']);
            $mysqlTimes[] = (microtime(true) - $start) * 1000;
        }
        
        // Couchbase performance
        \Yii::app()->params['enable_couchbase_read'] = true;
        \OE\Database\DatabaseAdapterFactory::clearInstances();
        $service = new \services\PatientService();
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $service->search(['last_name' => 'Smith']);
            $cbTimes[] = (microtime(true) - $start) * 1000;
        }
        
        $mysqlAvg = array_sum($mysqlTimes) / count($mysqlTimes);
        $cbAvg = array_sum($cbTimes) / count($cbTimes);
        
        echo "\nPatient Search Performance:\n";
        echo "  MySQL avg: " . round($mysqlAvg, 2) . "ms\n";
        echo "  Couchbase avg: " . round($cbAvg, 2) . "ms\n";
        echo "  Difference: " . round($cbAvg - $mysqlAvg, 2) . "ms\n";
        
        // Couchbase should not be more than 50% slower
        $this->assertLessThan($mysqlAvg * 1.5, $cbAvg);
    }
}
```

### 9.5 End-to-End Tests

#### 9.5.1 Cypress E2E Tests
**File**: `/cypress/e2e/couchbase/patient-workflow.cy.js`

```javascript
describe('Patient Workflow with Couchbase', () => {
    beforeEach(() => {
        // Enable Couchbase reads
        cy.setCouchbaseMode(true);
    });

    it('can search for patients', () => {
        cy.visit('/patient/search');
        cy.get('#Patient_last_name').type('Smith');
        cy.get('button[type="submit"]').click();
        
        cy.get('.patient-result').should('have.length.greaterThan', 0);
    });

    it('can view patient summary', () => {
        cy.visit('/patient/view/1');
        
        cy.get('.patient-name').should('be.visible');
        cy.get('.patient-dob').should('be.visible');
        cy.get('.episode-list').should('be.visible');
    });

    it('can create examination event', () => {
        cy.visit('/patient/view/1');
        cy.get('.add-event').click();
        cy.get('[data-event-type="OphCiExamination"]').click();
        
        // Fill in examination
        cy.get('.element-visual-acuity').should('be.visible');
        
        cy.get('button[type="submit"]').click();
        cy.url().should('include', '/view/');
    });
});
```

### 9.6 Regression Test Suite

#### 9.6.1 Run All Tests Script
**File**: `/protected/scripts/run-couchbase-tests.sh`

```bash
#!/bin/bash
# Run all Couchbase-related tests

set -e

echo "=== Couchbase Test Suite ==="

# Unit tests
echo "Running unit tests..."
./vendor/bin/phpunit --testsuite=couchbase-unit

# Integration tests
echo "Running integration tests..."
./vendor/bin/phpunit --testsuite=couchbase-integration

# Performance tests
echo "Running performance tests..."
./vendor/bin/phpunit --testsuite=couchbase-performance

# E2E tests (if Cypress available)
if command -v cypress &> /dev/null; then
    echo "Running E2E tests..."
    cypress run --spec "cypress/e2e/couchbase/**/*"
fi

echo "All tests completed!"
```

### 9.7 Validation Checklist

#### 9.7.1 Pre-Cutover Checklist
**File**: `/docs/migration-mariadb-to-couchbase/pre-cutover-checklist.md`

```markdown
# Pre-Cutover Validation Checklist

## Data Integrity
- [ ] All tables migrated
- [ ] Record counts match
- [ ] Sample validation passes (>99.9%)
- [ ] Foreign key relationships valid in documents

## Functionality
- [ ] Patient search works
- [ ] Patient create works
- [ ] Patient update works
- [ ] Episode creation works
- [ ] Event creation works
- [ ] Examination elements work
- [ ] Operation booking works
- [ ] Correspondence works
- [ ] Reports work

## Performance
- [ ] Patient search < 100ms
- [ ] Document retrieval < 50ms
- [ ] Complex queries < 500ms
- [ ] No memory leaks
- [ ] Connection pooling working

## Security
- [ ] Authentication working
- [ ] Authorization working
- [ ] Audit logging working
- [ ] No data exposure

## Operations
- [ ] Health checks pass
- [ ] Monitoring configured
- [ ] Alerts configured
- [ ] Backup working
- [ ] Restore tested

## Documentation
- [ ] Runbook complete
- [ ] Troubleshooting guide complete
- [ ] Rollback plan documented
```

## Testing Criteria

### Test Coverage
- [ ] >80% code coverage for new components
- [ ] All critical paths tested
- [ ] Edge cases covered

### Performance Benchmarks
- [ ] All queries within acceptable limits
- [ ] No performance regressions

### Integration Verification
- [ ] All modules work with Couchbase
- [ ] All APIs work correctly
- [ ] All reports generate correctly

## Acceptance Criteria
- [ ] All tests pass
- [ ] Performance acceptable
- [ ] Pre-cutover checklist complete
- [ ] Sign-off from QA

## Rollback Plan
1. Disable Couchbase reads
2. All traffic goes to MariaDB
3. No application changes needed

## Definition of Done
- [ ] All test suites pass
- [ ] Performance validated
- [ ] Checklist complete
- [ ] QA sign-off obtained

---

*Phase 9 Completion Sign-off:*
- [ ] Technical Lead
- [ ] QA Lead
- [ ] Operations

*Estimated Duration: 3-4 weeks*
