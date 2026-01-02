# Phase 4: Core Model Migration - Agent Executable Specification

**Version**: 1.0.0  
**Date**: December 19, 2025  
**Status**: READY FOR IMPLEMENTATION  
**Estimated Duration**: 4-6 weeks  
**Total Tasks**: 24 tasks across 8 sections  

---

## Executive Summary

This specification provides step-by-step instructions for migrating core OpenEyes models (Patient, User, Episode, Event, etc.) to support dual-database operations with Couchbase while maintaining full backward compatibility with MariaDB. The migration uses a bridge pattern that allows gradual adoption without breaking existing functionality.

---

## Prerequisites

### Required Phase Completions
- [x] Phase 1: Infrastructure & Couchbase Setup (COMPLETED)
- [x] Phase 2: Abstract Database Layer (COMPLETED)
- [x] Phase 3: Data Modeling & Schema Translation (COMPLETED)

### Required Running Services
```bash
# Verify Docker containers are running
docker compose -f .devcontainer/docker-compose.yml ps
# Expected: web (port 7777), db (port 3333) both healthy

# Verify MariaDB connectivity
docker compose -f .devcontainer/docker-compose.yml exec -T db mysql -u openeyes -popeneyes openeyes -e "SELECT COUNT(*) FROM patient;"
```

### Required Files from Previous Phases
| File | Purpose | Location |
|------|---------|----------|
| `CouchbaseActiveRecord.php` | Base document model | `protected/models/CouchbaseActiveRecord.php` |
| `TypeTransformer.php` | Type conversion | `protected/models/couchbase/transformers/TypeTransformer.php` |
| `DocumentTransformer.php` | Document transformation | `protected/models/couchbase/transformers/DocumentTransformer.php` |
| `PatientTransformer.php` | Patient-specific transformer | `protected/models/couchbase/transformers/PatientTransformer.php` |
| `DatabaseAdapterFactory.php` | Adapter factory | `protected/components/database/DatabaseAdapterFactory.php` |
| `DualWriteAdapter.php` | Dual-write support | `protected/components/database/DualWriteAdapter.php` |
| `couchbase-collections.php` | Collection mapping | `protected/config/couchbase-collections.php` |

### Directory Structure to Create
```
protected/
├── models/
│   ├── traits/
│   │   └── CouchbaseModelBridge.php    # Bridge trait for existing models
│   └── couchbase/
│       ├── PatientDocument.php         # Couchbase-native patient model
│       ├── EpisodeDocument.php         # Couchbase-native episode model
│       ├── EventDocument.php           # Couchbase-native event model
│       └── UserDocument.php            # Couchbase-native user model
├── commands/
│   └── CouchbaseSyncCommand.php        # Data sync command
├── scripts/
│   └── couchbase/
│       └── create-core-collections.sh  # Collection creation script
└── tests/
    └── unit/
        └── models/
            └── traits/
                └── CouchbaseModelBridgeTest.php
```

---

## Section 1: Model Bridge Trait (Tasks 1-3)

### Task 1.1: Create Traits Directory

**Command**:
```bash
mkdir -p protected/models/traits
```

### Task 1.2: Create CouchbaseModelBridge Trait

**File**: `/protected/models/traits/CouchbaseModelBridge.php`

```php
<?php
/**
 * Trait to add Couchbase support to existing CActiveRecord models
 * This allows gradual migration without breaking existing functionality
 */

namespace OE\Models\Traits;

use OE\Database\DatabaseAdapterFactory;
use OE\Couchbase\Transformers\TypeTransformer;

trait CouchbaseModelBridge
{
    /**
     * @var bool Flag to temporarily disable Couchbase sync
     */
    protected $_couchbaseSyncDisabled = false;
    
    /**
     * Get the Couchbase scope for this model
     * Override in model if different from 'core'
     * @return string
     */
    public function couchbaseScope()
    {
        return 'core';
    }
    
    /**
     * Get the Couchbase collection name for this model
     * Default is the table name
     * @return string
     */
    public function couchbaseCollection()
    {
        return $this->tableName();
    }
    
    /**
     * Get the Couchbase document type
     * @return string
     */
    public function couchbaseDocumentType()
    {
        return $this->tableName();
    }
    
    /**
     * Check if this model should use Couchbase for reads
     * @return bool
     */
    public function shouldUseCouchbase()
    {
        return DatabaseAdapterFactory::shouldUseCouchbase($this->tableName());
    }
    
    /**
     * Check if dual-write is enabled
     * @return bool
     */
    protected function isDualWriteEnabled()
    {
        return \Yii::app()->params['enable_dual_write'] ?? false;
    }
    
    /**
     * Temporarily disable Couchbase sync
     * Useful during batch operations or data fixes
     * @return $this
     */
    public function disableCouchbaseSync()
    {
        $this->_couchbaseSyncDisabled = true;
        return $this;
    }
    
    /**
     * Re-enable Couchbase sync
     * @return $this
     */
    public function enableCouchbaseSync()
    {
        $this->_couchbaseSyncDisabled = false;
        return $this;
    }
    
    /**
     * Get adapter for this model
     * @return \OE\Database\DatabaseAdapterInterface
     */
    protected function getDatabaseAdapter()
    {
        return DatabaseAdapterFactory::getAdapterForCollection($this->tableName());
    }
    
    /**
     * Get the Couchbase adapter directly
     * @return \OE\Database\CouchbaseAdapter
     */
    protected function getCouchbaseAdapter()
    {
        return DatabaseAdapterFactory::getAdapter(DatabaseAdapterFactory::ADAPTER_COUCHBASE);
    }
    
    /**
     * Generate the Couchbase document key
     * @return string
     */
    public function getCouchbaseDocumentKey()
    {
        return $this->couchbaseCollection() . '::' . $this->getPrimaryKey();
    }
    
    /**
     * Convert model attributes to Couchbase document format
     * Override in models for custom conversion logic
     * @return array
     */
    public function toCouchbaseDocument()
    {
        $doc = [];
        $schema = $this->getMetaData()->columns;
        
        // Transform each attribute
        foreach ($this->attributes as $attr => $value) {
            if (isset($schema[$attr])) {
                $doc[$attr] = TypeTransformer::toJson(
                    $value,
                    $schema[$attr]->dbType,
                    $attr
                );
            } else {
                $doc[$attr] = $value;
            }
        }
        
        // Add document metadata
        $doc['_type'] = $this->couchbaseDocumentType();
        $doc['_mysql_id'] = $this->getPrimaryKey();
        $doc['_modified'] = date('c');
        
        if ($this->isNewRecord) {
            $doc['_created'] = date('c');
            $doc['_version'] = 1;
        } else {
            // Increment version if exists
            $doc['_version'] = isset($doc['_version']) ? $doc['_version'] + 1 : 1;
        }
        
        // Handle embedded relations if defined
        if (method_exists($this, 'getEmbeddedRelations')) {
            foreach ($this->getEmbeddedRelations() as $relation => $config) {
                $related = $this->$relation;
                if ($related !== null) {
                    if (is_array($related)) {
                        $doc[$relation] = array_map(function($item) {
                            if (method_exists($item, 'toCouchbaseDocument')) {
                                return $item->toCouchbaseDocument();
                            }
                            return $item->attributes;
                        }, $related);
                    } else {
                        if (method_exists($related, 'toCouchbaseDocument')) {
                            $doc[$relation] = $related->toCouchbaseDocument();
                        } else {
                            $doc[$relation] = $related->attributes;
                        }
                    }
                }
            }
        }
        
        return $doc;
    }
    
    /**
     * Populate model from Couchbase document
     * @param array $doc Document data
     * @return void
     */
    public function fromCouchbaseDocument($doc)
    {
        $schema = $this->getMetaData()->columns;
        
        foreach ($doc as $attr => $value) {
            // Skip metadata fields
            if (strpos($attr, '_') === 0) {
                continue;
            }
            
            if (isset($schema[$attr])) {
                $this->$attr = TypeTransformer::toMysql(
                    $value,
                    $schema[$attr]->dbType,
                    $attr
                );
            } elseif ($this->hasAttribute($attr)) {
                $this->$attr = $value;
            }
        }
        
        // Set primary key from document
        if (isset($doc['_mysql_id'])) {
            $this->setPrimaryKey($doc['_mysql_id']);
        }
    }
    
    /**
     * Save to Couchbase (for dual-write support)
     * @return bool Success status
     */
    protected function saveToCouchbase()
    {
        // Check if sync is disabled or dual-write not enabled
        if ($this->_couchbaseSyncDisabled || !$this->isDualWriteEnabled()) {
            return true;
        }
        
        // Check hook
        if (method_exists($this, 'beforeCouchbaseSync') && !$this->beforeCouchbaseSync()) {
            return true; // Skip sync but don't fail
        }
        
        try {
            $adapter = $this->getCouchbaseAdapter();
            $doc = $this->toCouchbaseDocument();
            $collection = $this->couchbaseCollection();
            $pk = $this->getPrimaryKey();
            
            if ($this->isNewRecord || !$adapter->exists($collection, $pk)) {
                $adapter->insert($collection, $doc);
            } else {
                $adapter->update($collection, $pk, $doc);
            }
            
            // Call hook if exists
            if (method_exists($this, 'afterCouchbaseSync')) {
                $this->afterCouchbaseSync();
            }
            
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase save failed for {$this->tableName()} #{$this->getPrimaryKey()}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.couchbase'
            );
            // Don't fail the main operation - Couchbase sync is secondary
            return false;
        }
    }
    
    /**
     * Delete from Couchbase (for dual-write support)
     * @return bool Success status
     */
    protected function deleteFromCouchbase()
    {
        if ($this->_couchbaseSyncDisabled || !$this->isDualWriteEnabled()) {
            return true;
        }
        
        try {
            $adapter = $this->getCouchbaseAdapter();
            $adapter->delete($this->couchbaseCollection(), $this->getPrimaryKey());
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase delete failed for {$this->tableName()} #{$this->getPrimaryKey()}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.couchbase'
            );
            return false;
        }
    }
    
    /**
     * Sync this record to Couchbase manually
     * Useful for initial data migration or fixing sync issues
     * @return bool Success status
     */
    public function syncToCouchbase()
    {
        $wasDisabled = $this->_couchbaseSyncDisabled;
        $this->_couchbaseSyncDisabled = false;
        
        // Temporarily force dual-write
        $originalSetting = \Yii::app()->params['enable_dual_write'];
        \Yii::app()->params['enable_dual_write'] = true;
        
        $result = $this->saveToCouchbase();
        
        // Restore settings
        \Yii::app()->params['enable_dual_write'] = $originalSetting;
        $this->_couchbaseSyncDisabled = $wasDisabled;
        
        return $result;
    }
    
    /**
     * Compare this record with its Couchbase version
     * @return array|null Differences or null if not found
     */
    public function compareWithCouchbase()
    {
        try {
            $adapter = $this->getCouchbaseAdapter();
            $cbDoc = $adapter->findByPk($this->couchbaseCollection(), $this->getPrimaryKey());
            
            if (!$cbDoc) {
                return ['status' => 'missing', 'message' => 'Document not found in Couchbase'];
            }
            
            $myDoc = $this->toCouchbaseDocument();
            $differences = [];
            
            // Compare fields (excluding metadata)
            foreach ($myDoc as $key => $value) {
                if (strpos($key, '_') === 0) continue;
                
                $cbValue = isset($cbDoc[$key]) ? $cbDoc[$key] : null;
                if ($value !== $cbValue) {
                    $differences[$key] = [
                        'mysql' => $value,
                        'couchbase' => $cbValue,
                    ];
                }
            }
            
            return empty($differences) 
                ? ['status' => 'match', 'message' => 'Documents are identical']
                : ['status' => 'mismatch', 'differences' => $differences];
                
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
```

**Acceptance Criteria**:
- [ ] Trait can be added to any BaseActiveRecord model
- [ ] `toCouchbaseDocument()` correctly transforms all column types
- [ ] `fromCouchbaseDocument()` correctly populates model
- [ ] Dual-write respects `enable_dual_write` flag
- [ ] Errors don't break primary MariaDB operations
- [ ] Sync can be temporarily disabled

---

### Task 1.3: Create CouchbaseModelBridge Unit Test

**File**: `/protected/tests/unit/models/traits/CouchbaseModelBridgeTest.php`

```php
<?php
/**
 * Unit tests for CouchbaseModelBridge trait
 */

class CouchbaseModelBridgeTest extends CTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Ensure dual-write is disabled for tests
        Yii::app()->params['enable_dual_write'] = false;
    }
    
    public function tearDown(): void
    {
        Yii::app()->params['enable_dual_write'] = false;
        parent::tearDown();
    }
    
    /**
     * @test
     */
    public function testCouchbaseScopeDefault()
    {
        $model = new TestBridgeModel();
        $this->assertEquals('core', $model->couchbaseScope());
    }
    
    /**
     * @test
     */
    public function testCouchbaseCollectionDefault()
    {
        $model = new TestBridgeModel();
        $this->assertEquals('test_bridge', $model->couchbaseCollection());
    }
    
    /**
     * @test
     */
    public function testToCouchbaseDocumentBasic()
    {
        $model = new TestBridgeModel();
        $model->id = 1;
        $model->name = 'Test';
        $model->active = true;
        $model->created_date = '2023-12-19 10:00:00';
        
        $doc = $model->toCouchbaseDocument();
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('test_bridge', $doc['_type']);
        $this->assertArrayHasKey('_mysql_id', $doc);
        $this->assertEquals(1, $doc['_mysql_id']);
        $this->assertArrayHasKey('name', $doc);
        $this->assertEquals('Test', $doc['name']);
    }
    
    /**
     * @test
     */
    public function testSyncDisableEnable()
    {
        $model = new TestBridgeModel();
        
        $this->assertFalse($model->_couchbaseSyncDisabled);
        
        $model->disableCouchbaseSync();
        $this->assertTrue($model->_couchbaseSyncDisabled);
        
        $model->enableCouchbaseSync();
        $this->assertFalse($model->_couchbaseSyncDisabled);
    }
    
    /**
     * @test
     */
    public function testDocumentKey()
    {
        $model = new TestBridgeModel();
        $model->id = 123;
        
        $this->assertEquals('test_bridge::123', $model->getCouchbaseDocumentKey());
    }
    
    /**
     * @test
     */
    public function testDualWriteDisabledByDefault()
    {
        $model = new TestBridgeModel();
        $this->assertFalse($model->isDualWriteEnabled());
    }
    
    /**
     * @test
     */
    public function testSaveToCouchbaseSkipsWhenDisabled()
    {
        $model = new TestBridgeModel();
        $model->disableCouchbaseSync();
        
        // Should return true (skipped) without error
        $result = $model->saveToCouchbase();
        $this->assertTrue($result);
    }
}

/**
 * Test model class using the bridge trait
 */
class TestBridgeModel extends CActiveRecord
{
    use \OE\Models\Traits\CouchbaseModelBridge {
        saveToCouchbase as public;
        isDualWriteEnabled as public;
    }
    
    public $_couchbaseSyncDisabled = false;
    public $id;
    public $name;
    public $active;
    public $created_date;
    
    public function tableName()
    {
        return 'test_bridge';
    }
    
    public function getPrimaryKey()
    {
        return $this->id;
    }
    
    public function getMetaData()
    {
        // Return mock metadata
        $meta = new stdClass();
        $meta->columns = [
            'id' => (object)['dbType' => 'int(10) unsigned'],
            'name' => (object)['dbType' => 'varchar(100)'],
            'active' => (object)['dbType' => 'tinyint(1)'],
            'created_date' => (object)['dbType' => 'datetime'],
        ];
        return $meta;
    }
    
    public function hasAttribute($attr)
    {
        return in_array($attr, ['id', 'name', 'active', 'created_date']);
    }
    
    public function getAttributes($names = null)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
            'created_date' => $this->created_date,
        ];
    }
    
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        return null;
    }
}
```

**Acceptance Criteria**:
- [ ] All unit tests pass
- [ ] Trait methods are correctly tested
- [ ] Mock model demonstrates trait usage

---

## Section 2: Patient Model Migration (Tasks 4-6)

### Task 2.1: Update Patient Model with Bridge Trait

**File**: `/protected/models/Patient.php` (MODIFY - add trait and methods)

**Changes to add** (insert after class declaration and use statements):

```php
// Add at top of file with other use statements
use OE\Models\Traits\CouchbaseModelBridge;

// Inside class Patient, add after existing traits:
use CouchbaseModelBridge;
```

**Methods to add** (add before the closing brace of the class):

```php
    // =========================================================================
    // COUCHBASE INTEGRATION METHODS
    // =========================================================================
    
    /**
     * Get the Couchbase scope for patients
     * @return string
     */
    public function couchbaseScope()
    {
        return 'core';
    }
    
    /**
     * Define relations to embed in Couchbase document
     * @return array
     */
    public function getEmbeddedRelations()
    {
        return [
            'contact' => ['embed' => true],
        ];
    }
    
    /**
     * Convert patient to Couchbase document with embedded data
     * @return array
     */
    public function toCouchbaseDocument()
    {
        // Get base document from trait
        $doc = [];
        $schema = $this->getMetaData()->columns;
        
        // Transform each attribute
        foreach ($this->attributes as $attr => $value) {
            if (isset($schema[$attr])) {
                $doc[$attr] = \OE\Couchbase\Transformers\TypeTransformer::toJson(
                    $value,
                    $schema[$attr]->dbType,
                    $attr
                );
            } else {
                $doc[$attr] = $value;
            }
        }
        
        // Add document metadata
        $doc['_type'] = 'patient';
        $doc['_mysql_id'] = $this->id;
        $doc['_modified'] = date('c');
        $doc['_created'] = $this->isNewRecord ? date('c') : ($doc['_created'] ?? date('c'));
        $doc['_version'] = isset($doc['_version']) ? $doc['_version'] + 1 : 1;
        
        // Embed contact information
        if ($this->contact) {
            $doc['contact'] = [
                'title' => $this->contact->title,
                'first_name' => $this->contact->first_name,
                'last_name' => $this->contact->last_name,
                'maiden_name' => $this->contact->maiden_name,
                'nick_name' => $this->contact->nick_name,
                'primary_phone' => $this->contact->primary_phone,
                'email' => $this->contact->email,
                'qualifications' => $this->contact->qualifications,
            ];
        }
        
        // Embed addresses via contact
        $doc['addresses'] = [];
        if ($this->contact) {
            $addresses = Address::model()->findAllByAttributes(
                ['contact_id' => $this->contact->id],
                ['order' => 'date_start DESC']
            );
            foreach ($addresses as $i => $addr) {
                $doc['addresses'][] = [
                    'address_type_id' => $addr->address_type_id,
                    'address_type' => $addr->type ? $addr->type->name : null,
                    'address1' => $addr->address1,
                    'address2' => $addr->address2,
                    'city' => $addr->city,
                    'postcode' => $addr->postcode,
                    'county' => $addr->county,
                    'country_id' => $addr->country_id,
                    'country' => $addr->country ? $addr->country->name : null,
                    'date_start' => $addr->date_start,
                    'date_end' => $addr->date_end,
                    'is_primary' => ($i === 0),
                ];
            }
        }
        
        // Embed patient identifiers
        $doc['identifiers'] = [];
        if ($this->identifiers) {
            foreach ($this->identifiers as $identifier) {
                if (!$identifier->deleted) {
                    $doc['identifiers'][] = [
                        'type' => $identifier->patientIdentifierType ? $identifier->patientIdentifierType->short_title : null,
                        'type_id' => $identifier->patient_identifier_type_id,
                        'value' => $identifier->value,
                        'institution_id' => $identifier->institution_id,
                    ];
                }
            }
        }
        
        // Add computed fields
        $doc['is_deceased'] = !empty($this->date_of_death);
        $doc['full_name'] = trim($this->first_name . ' ' . $this->last_name);
        
        // Remove contact_id since we're embedding
        unset($doc['contact_id']);
        
        return $doc;
    }
    
    /**
     * Hook: After saving to MariaDB, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
    
    /**
     * Hook: After deleting from MariaDB, delete from Couchbase
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
```

**Acceptance Criteria**:
- [ ] Patient model uses CouchbaseModelBridge trait
- [ ] Contact information is embedded
- [ ] Addresses are embedded as array
- [ ] Patient identifiers are embedded
- [ ] Computed fields (is_deceased, full_name) included
- [ ] afterSave triggers Couchbase sync
- [ ] afterDelete triggers Couchbase delete
- [ ] All existing Patient tests still pass

---

### Task 2.2: Create PatientDocument Model

**File**: `/protected/models/couchbase/PatientDocument.php`

```php
<?php
/**
 * Patient document model for direct Couchbase operations
 * 
 * Use this model when you need to:
 * - Query Couchbase directly (bypassing MariaDB)
 * - Perform Couchbase-specific operations
 * - Access embedded data efficiently
 */

class PatientDocument extends CouchbaseActiveRecord
{
    /**
     * @return string Document type
     */
    public function documentType()
    {
        return 'patient';
    }
    
    /**
     * @return string Couchbase scope
     */
    public function scope()
    {
        return 'core';
    }
    
    /**
     * @return string Collection name
     */
    public function collectionName()
    {
        return 'patient';
    }
    
    /**
     * @return array Validation rules
     */
    public function rules()
    {
        return [
            [['dob'], 'required'],
            ['hos_num', 'length', 'max' => 40],
            ['nhs_num', 'length', 'max' => 40],
            ['gender', 'in', 'range' => ['M', 'F', 'U', null]],
        ];
    }
    
    /**
     * @return array Attribute labels
     */
    public function attributeLabels()
    {
        return [
            'hos_num' => 'Hospital Number',
            'nhs_num' => 'NHS Number',
            'dob' => 'Date of Birth',
            'gender' => 'Gender',
            'contact.first_name' => 'First Name',
            'contact.last_name' => 'Last Name',
        ];
    }
    
    /**
     * Get contact first name (from embedded contact)
     * @return string|null
     */
    public function getFirstName()
    {
        return isset($this->contact['first_name']) ? $this->contact['first_name'] : null;
    }
    
    /**
     * Get contact last name (from embedded contact)
     * @return string|null
     */
    public function getLastName()
    {
        return isset($this->contact['last_name']) ? $this->contact['last_name'] : null;
    }
    
    /**
     * Get full name
     * @return string
     */
    public function getFullName()
    {
        return trim($this->getFirstName() . ' ' . $this->getLastName());
    }
    
    /**
     * Get primary address (from embedded addresses)
     * @return array|null
     */
    public function getPrimaryAddress()
    {
        if (empty($this->addresses)) {
            return null;
        }
        
        foreach ($this->addresses as $addr) {
            if (!empty($addr['is_primary'])) {
                return $addr;
            }
        }
        
        return $this->addresses[0] ?? null;
    }
    
    /**
     * Find patient by hospital number
     * @param string $hosNum Hospital number
     * @return PatientDocument|null
     */
    public static function findByHosNum($hosNum)
    {
        $results = static::findAllByAttributes(['hos_num' => $hosNum], ['limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find patient by NHS number
     * @param string $nhsNum NHS number
     * @return PatientDocument|null
     */
    public static function findByNhsNum($nhsNum)
    {
        $results = static::findAllByAttributes(['nhs_num' => $nhsNum], ['limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Search patients by name
     * @param string $lastName Last name (or partial)
     * @param string|null $firstName First name (or partial)
     * @param int $limit Maximum results
     * @return array Array of PatientDocument
     */
    public static function searchByName($lastName, $firstName = null, $limit = 50)
    {
        $conn = Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META().id as _key, p.* 
                  FROM `{$bucket}`.`core`.`patient` p 
                  WHERE p._type = 'patient' 
                  AND LOWER(p.contact.last_name) LIKE LOWER(\$lastName)";
        $params = ['lastName' => $lastName . '%'];
        
        if ($firstName) {
            $query .= " AND LOWER(p.contact.first_name) LIKE LOWER(\$firstName)";
            $params['firstName'] = $firstName . '%';
        }
        
        $query .= " ORDER BY p.contact.last_name, p.contact.first_name LIMIT \$limit";
        $params['limit'] = $limit;
        
        try {
            $results = $conn->query($query, $params);
            $models = [];
            
            foreach ($results as $row) {
                $model = new static();
                $data = is_object($row) ? (array)$row : $row;
                
                // Handle nested result structure
                if (isset($data['p'])) {
                    $data = array_merge($data, (array)$data['p']);
                    unset($data['p']);
                }
                
                $model->setAttributes($data);
                $model->setPrimaryKey(isset($data['_mysql_id']) ? $data['_mysql_id'] : null);
                $model->setIsNewRecord(false);
                $models[] = $model;
            }
            
            return $models;
        } catch (\Exception $e) {
            Yii::log("PatientDocument search error: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Search patients by date of birth
     * @param string $dob Date of birth (YYYY-MM-DD)
     * @param int $limit Maximum results
     * @return array Array of PatientDocument
     */
    public static function searchByDob($dob, $limit = 50)
    {
        return static::findAllByAttributes(['dob' => $dob], ['limit' => $limit]);
    }
    
    /**
     * Get episodes for this patient
     * @return array Array of EpisodeDocument
     */
    public function getEpisodes()
    {
        return EpisodeDocument::findAllByAttributes(
            ['patient_id' => $this->getPrimaryKey()],
            ['order' => 'start_date DESC']
        );
    }
    
    /**
     * Check if patient is deceased
     * @return bool
     */
    public function isDeceased()
    {
        return !empty($this->is_deceased) || !empty($this->date_of_death);
    }
    
    /**
     * Get age in years
     * @return int|null
     */
    public function getAge()
    {
        if (empty($this->dob)) {
            return null;
        }
        
        $dob = new DateTime($this->dob);
        $now = $this->isDeceased() && !empty($this->date_of_death) 
            ? new DateTime($this->date_of_death)
            : new DateTime();
            
        return $dob->diff($now)->y;
    }
    
    /**
     * Get corresponding MariaDB Patient model
     * @return Patient|null
     */
    public function getMariaDbModel()
    {
        $pk = $this->getPrimaryKey();
        return $pk ? Patient::model()->findByPk($pk) : null;
    }
}
```

**Acceptance Criteria**:
- [ ] PatientDocument extends CouchbaseActiveRecord
- [ ] Can find patients by hos_num, nhs_num
- [ ] Name search works with partial matching
- [ ] Embedded data (contact, addresses, identifiers) accessible
- [ ] Helper methods (getAge, isDeceased, getFullName) work
- [ ] Can retrieve related MariaDB model

---

### Task 2.3: Create Patient Document Test

**File**: `/protected/tests/unit/models/couchbase/PatientDocumentTest.php`

```php
<?php
/**
 * Unit tests for PatientDocument
 */

class PatientDocumentTest extends CTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }
    
    /**
     * @test
     */
    public function testDocumentType()
    {
        $patient = new PatientDocument();
        $this->assertEquals('patient', $patient->documentType());
    }
    
    /**
     * @test
     */
    public function testScope()
    {
        $patient = new PatientDocument();
        $this->assertEquals('core', $patient->scope());
    }
    
    /**
     * @test
     */
    public function testCollectionName()
    {
        $patient = new PatientDocument();
        $this->assertEquals('patient', $patient->collectionName());
    }
    
    /**
     * @test
     */
    public function testGetFullName()
    {
        $patient = new PatientDocument();
        $patient->setAttributes([
            'contact' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]
        ]);
        
        $this->assertEquals('John Doe', $patient->getFullName());
    }
    
    /**
     * @test
     */
    public function testGetPrimaryAddress()
    {
        $patient = new PatientDocument();
        $patient->setAttributes([
            'addresses' => [
                ['address1' => '123 Main St', 'is_primary' => false],
                ['address1' => '456 Oak Ave', 'is_primary' => true],
            ]
        ]);
        
        $primary = $patient->getPrimaryAddress();
        $this->assertEquals('456 Oak Ave', $primary['address1']);
    }
    
    /**
     * @test
     */
    public function testGetPrimaryAddressFallback()
    {
        $patient = new PatientDocument();
        $patient->setAttributes([
            'addresses' => [
                ['address1' => '123 Main St'],
                ['address1' => '456 Oak Ave'],
            ]
        ]);
        
        // Should return first address when no primary set
        $primary = $patient->getPrimaryAddress();
        $this->assertEquals('123 Main St', $primary['address1']);
    }
    
    /**
     * @test
     */
    public function testIsDeceased()
    {
        $patient = new PatientDocument();
        $this->assertFalse($patient->isDeceased());
        
        $patient->setAttributes(['is_deceased' => true]);
        $this->assertTrue($patient->isDeceased());
        
        $patient2 = new PatientDocument();
        $patient2->setAttributes(['date_of_death' => '2023-01-01']);
        $this->assertTrue($patient2->isDeceased());
    }
    
    /**
     * @test
     */
    public function testGetAge()
    {
        $patient = new PatientDocument();
        $patient->setAttributes(['dob' => '1990-01-01']);
        
        $age = $patient->getAge();
        $this->assertGreaterThanOrEqual(34, $age);
        $this->assertLessThanOrEqual(35, $age);
    }
    
    /**
     * @test
     */
    public function testGetAgeNullWhenNoDob()
    {
        $patient = new PatientDocument();
        $this->assertNull($patient->getAge());
    }
    
    /**
     * @test
     */
    public function testValidationRules()
    {
        $patient = new PatientDocument();
        $rules = $patient->rules();
        
        $this->assertIsArray($rules);
        $this->assertNotEmpty($rules);
    }
}
```

---

## Section 3: Episode Model Migration (Tasks 7-8)

### Task 3.1: Update Episode Model with Bridge Trait

**File**: `/protected/models/Episode.php` (MODIFY)

**Add at top with use statements**:
```php
use OE\Models\Traits\CouchbaseModelBridge;
```

**Add after `use HasFactory;`**:
```php
use CouchbaseModelBridge;
```

**Add before class closing brace**:
```php
    // =========================================================================
    // COUCHBASE INTEGRATION METHODS
    // =========================================================================
    
    /**
     * Get the Couchbase scope
     * @return string
     */
    public function couchbaseScope()
    {
        return 'core';
    }
    
    /**
     * Convert episode to Couchbase document
     * @return array
     */
    public function toCouchbaseDocument()
    {
        $doc = [];
        $schema = $this->getMetaData()->columns;
        
        foreach ($this->attributes as $attr => $value) {
            if (isset($schema[$attr])) {
                $doc[$attr] = \OE\Couchbase\Transformers\TypeTransformer::toJson(
                    $value,
                    $schema[$attr]->dbType,
                    $attr
                );
            } else {
                $doc[$attr] = $value;
            }
        }
        
        // Document metadata
        $doc['_type'] = 'episode';
        $doc['_mysql_id'] = $this->id;
        $doc['_modified'] = date('c');
        $doc['_created'] = $this->isNewRecord ? date('c') : ($doc['_created'] ?? date('c'));
        $doc['_version'] = isset($doc['_version']) ? $doc['_version'] + 1 : 1;
        
        // Denormalize useful references
        if ($this->firm && $this->firm->serviceSubspecialtyAssignment) {
            $doc['subspecialty_name'] = $this->firm->serviceSubspecialtyAssignment->subspecialty 
                ? $this->firm->serviceSubspecialtyAssignment->subspecialty->name 
                : null;
        }
        
        if ($this->status) {
            $doc['status_name'] = $this->status->name;
        }
        
        if ($this->diagnosis) {
            $doc['principal_diagnosis'] = [
                'id' => $this->disorder_id,
                'term' => $this->diagnosis->term,
            ];
        }
        
        return $doc;
    }
    
    /**
     * Hook: After save, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
    
    /**
     * Hook: After delete, remove from Couchbase
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
```

---

### Task 3.2: Create EpisodeDocument Model

**File**: `/protected/models/couchbase/EpisodeDocument.php`

```php
<?php
/**
 * Episode document model for direct Couchbase operations
 */

class EpisodeDocument extends CouchbaseActiveRecord
{
    public function documentType()
    {
        return 'episode';
    }
    
    public function scope()
    {
        return 'core';
    }
    
    public function collectionName()
    {
        return 'episode';
    }
    
    public function rules()
    {
        return [
            [['patient_id', 'start_date'], 'required'],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'patient_id' => 'Patient',
            'firm_id' => 'Firm',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
        ];
    }
    
    /**
     * Find episodes by patient ID
     * @param int $patientId Patient ID
     * @return array Array of EpisodeDocument
     */
    public static function findByPatientId($patientId)
    {
        return static::findAllByAttributes(
            ['patient_id' => (int)$patientId],
            ['order' => 'start_date DESC']
        );
    }
    
    /**
     * Find episodes by firm ID
     * @param int $firmId Firm ID
     * @param int $limit Maximum results
     * @return array Array of EpisodeDocument
     */
    public static function findByFirmId($firmId, $limit = 100)
    {
        return static::findAllByAttributes(
            ['firm_id' => (int)$firmId],
            ['order' => 'start_date DESC', 'limit' => $limit]
        );
    }
    
    /**
     * Get events for this episode
     * @return array Array of EventDocument
     */
    public function getEvents()
    {
        return EventDocument::findAllByAttributes(
            ['episode_id' => $this->getPrimaryKey()],
            ['order' => 'event_date DESC']
        );
    }
    
    /**
     * Get the patient document
     * @return PatientDocument|null
     */
    public function getPatient()
    {
        return PatientDocument::findByPk($this->patient_id);
    }
    
    /**
     * Check if episode is open
     * @return bool
     */
    public function isOpen()
    {
        return empty($this->end_date);
    }
    
    /**
     * Get corresponding MariaDB Episode model
     * @return Episode|null
     */
    public function getMariaDbModel()
    {
        $pk = $this->getPrimaryKey();
        return $pk ? Episode::model()->findByPk($pk) : null;
    }
}
```

---

## Section 4: Event Model Migration (Tasks 9-10)

### Task 4.1: Update Event Model with Bridge Trait

**File**: `/protected/models/Event.php` (MODIFY)

**Add at top with use statements**:
```php
use OE\Models\Traits\CouchbaseModelBridge;
```

**Add after `use HasFactory;`**:
```php
use CouchbaseModelBridge;
```

**Add before class closing brace**:
```php
    // =========================================================================
    // COUCHBASE INTEGRATION METHODS
    // =========================================================================
    
    /**
     * Get the Couchbase scope
     * @return string
     */
    public function couchbaseScope()
    {
        return 'core';
    }
    
    /**
     * Convert event to Couchbase document
     * @return array
     */
    public function toCouchbaseDocument()
    {
        $doc = [];
        $schema = $this->getMetaData()->columns;
        
        foreach ($this->attributes as $attr => $value) {
            if (isset($schema[$attr])) {
                $doc[$attr] = \OE\Couchbase\Transformers\TypeTransformer::toJson(
                    $value,
                    $schema[$attr]->dbType,
                    $attr
                );
            } else {
                $doc[$attr] = $value;
            }
        }
        
        // Document metadata
        $doc['_type'] = 'event';
        $doc['_mysql_id'] = $this->id;
        $doc['_modified'] = date('c');
        $doc['_created'] = $this->isNewRecord ? date('c') : ($doc['_created'] ?? date('c'));
        $doc['_version'] = isset($doc['_version']) ? $doc['_version'] + 1 : 1;
        
        // Denormalize event type info for easier querying
        if ($this->eventType) {
            $doc['event_type_name'] = $this->eventType->name;
            $doc['event_type_class'] = $this->eventType->class_name;
        }
        
        // Denormalize site/institution names
        if ($this->site) {
            $doc['site_name'] = $this->site->name;
        }
        if ($this->institution) {
            $doc['institution_name'] = $this->institution->name;
        }
        
        return $doc;
    }
    
    /**
     * Hook: After save, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
    
    /**
     * Hook: After delete, remove from Couchbase
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
```

---

### Task 4.2: Create EventDocument Model

**File**: `/protected/models/couchbase/EventDocument.php`

```php
<?php
/**
 * Event document model for direct Couchbase operations
 */

class EventDocument extends CouchbaseActiveRecord
{
    public function documentType()
    {
        return 'event';
    }
    
    public function scope()
    {
        return 'core';
    }
    
    public function collectionName()
    {
        return 'event';
    }
    
    public function rules()
    {
        return [
            [['event_type_id', 'event_date'], 'required'],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'episode_id' => 'Episode',
            'event_type_id' => 'Event Type',
            'event_date' => 'Event Date',
        ];
    }
    
    /**
     * Find events by episode ID
     * @param int $episodeId Episode ID
     * @return array Array of EventDocument
     */
    public static function findByEpisodeId($episodeId)
    {
        return static::findAllByAttributes(
            ['episode_id' => (int)$episodeId],
            ['order' => 'event_date DESC']
        );
    }
    
    /**
     * Find events by event type
     * @param int $eventTypeId Event type ID
     * @param int $limit Maximum results
     * @return array Array of EventDocument
     */
    public static function findByEventType($eventTypeId, $limit = 100)
    {
        return static::findAllByAttributes(
            ['event_type_id' => (int)$eventTypeId],
            ['order' => 'event_date DESC', 'limit' => $limit]
        );
    }
    
    /**
     * Find events for a patient (via episodes)
     * Uses N1QL JOIN for efficiency
     * @param int $patientId Patient ID
     * @param int $limit Maximum results
     * @return array Array of EventDocument
     */
    public static function findByPatientId($patientId, $limit = 100)
    {
        $conn = Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META(e).id as _key, e.* 
                  FROM `{$bucket}`.`core`.`event` e
                  JOIN `{$bucket}`.`core`.`episode` ep ON e.episode_id = ep._mysql_id
                  WHERE ep.patient_id = \$patientId
                  AND e._type = 'event'
                  ORDER BY e.event_date DESC
                  LIMIT \$limit";
        
        try {
            $results = $conn->query($query, [
                'patientId' => (int)$patientId,
                'limit' => $limit,
            ]);
            
            $models = [];
            foreach ($results as $row) {
                $model = new static();
                $data = is_object($row) ? (array)$row : $row;
                if (isset($data['e'])) {
                    $data = array_merge($data, (array)$data['e']);
                    unset($data['e']);
                }
                $model->setAttributes($data);
                $model->setPrimaryKey(isset($data['_mysql_id']) ? $data['_mysql_id'] : null);
                $model->setIsNewRecord(false);
                $models[] = $model;
            }
            
            return $models;
        } catch (\Exception $e) {
            Yii::log("EventDocument search error: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Get the episode document
     * @return EpisodeDocument|null
     */
    public function getEpisode()
    {
        return $this->episode_id ? EpisodeDocument::findByPk($this->episode_id) : null;
    }
    
    /**
     * Check if event is deleted
     * @return bool
     */
    public function isDeleted()
    {
        return !empty($this->deleted);
    }
    
    /**
     * Get corresponding MariaDB Event model
     * @return Event|null
     */
    public function getMariaDbModel()
    {
        $pk = $this->getPrimaryKey();
        return $pk ? Event::model()->findByPk($pk) : null;
    }
}
```

---

## Section 5: User Model Migration (Tasks 11-12)

### Task 5.1: Update User Model with Bridge Trait

**File**: `/protected/models/User.php` (MODIFY)

**Add at top with use statements**:
```php
use OE\Models\Traits\CouchbaseModelBridge;
```

**Add after `use HasFactory;`**:
```php
use CouchbaseModelBridge;
```

**Add before class closing brace**:
```php
    // =========================================================================
    // COUCHBASE INTEGRATION METHODS
    // =========================================================================
    
    /**
     * Get the Couchbase scope
     * @return string
     */
    public function couchbaseScope()
    {
        return 'core';
    }
    
    /**
     * Convert user to Couchbase document
     * IMPORTANT: Excludes sensitive data (password, salt)
     * @return array
     */
    public function toCouchbaseDocument()
    {
        $doc = [];
        $schema = $this->getMetaData()->columns;
        
        // Sensitive fields to exclude
        $excludeFields = ['password', 'salt', 'password_salt', 'password_hash'];
        
        foreach ($this->attributes as $attr => $value) {
            // Skip sensitive fields
            if (in_array($attr, $excludeFields)) {
                continue;
            }
            
            if (isset($schema[$attr])) {
                $doc[$attr] = \OE\Couchbase\Transformers\TypeTransformer::toJson(
                    $value,
                    $schema[$attr]->dbType,
                    $attr
                );
            } else {
                $doc[$attr] = $value;
            }
        }
        
        // Document metadata
        $doc['_type'] = 'user';
        $doc['_mysql_id'] = $this->id;
        $doc['_modified'] = date('c');
        $doc['_created'] = $this->isNewRecord ? date('c') : ($doc['_created'] ?? date('c'));
        $doc['_version'] = isset($doc['_version']) ? $doc['_version'] + 1 : 1;
        
        // Embed contact info
        if ($this->contact) {
            $doc['contact'] = [
                'title' => $this->contact->title,
                'first_name' => $this->contact->first_name,
                'last_name' => $this->contact->last_name,
                'email' => $this->contact->email,
                'primary_phone' => $this->contact->primary_phone,
                'qualifications' => $this->contact->qualifications,
            ];
        }
        
        // Add full name for convenience
        $doc['full_name'] = trim($this->first_name . ' ' . $this->last_name);
        
        // Add roles (from auth assignments)
        $doc['roles'] = [];
        $authAssignments = AuthAssignment::model()->findAllByAttributes(['userid' => $this->id]);
        foreach ($authAssignments as $auth) {
            $doc['roles'][] = $auth->itemname;
        }
        
        return $doc;
    }
    
    /**
     * Hook: After save, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
```

**Acceptance Criteria**:
- [ ] Sensitive data (password, salt) is NOT synced to Couchbase
- [ ] Contact information is embedded
- [ ] Roles are included as array
- [ ] User can still authenticate via MariaDB

---

### Task 5.2: Create UserDocument Model

**File**: `/protected/models/couchbase/UserDocument.php`

```php
<?php
/**
 * User document model for direct Couchbase operations
 * NOTE: Does NOT contain password/authentication data
 */

class UserDocument extends CouchbaseActiveRecord
{
    public function documentType()
    {
        return 'user';
    }
    
    public function scope()
    {
        return 'core';
    }
    
    public function collectionName()
    {
        return 'user';
    }
    
    public function rules()
    {
        return [
            [['username', 'first_name', 'last_name'], 'required'],
            ['username', 'length', 'max' => 40],
            ['email', 'email'],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'username' => 'Username',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'active' => 'Active',
        ];
    }
    
    /**
     * Find user by username
     * @param string $username Username
     * @return UserDocument|null
     */
    public static function findByUsername($username)
    {
        $results = static::findAllByAttributes(['username' => $username], ['limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find active users
     * @param int $limit Maximum results
     * @return array Array of UserDocument
     */
    public static function findActiveUsers($limit = 100)
    {
        return static::findAllByAttributes(
            ['active' => true],
            ['limit' => $limit, 'order' => 'last_name ASC, first_name ASC']
        );
    }
    
    /**
     * Find users by role
     * @param string $role Role name
     * @return array Array of UserDocument
     */
    public static function findByRole($role)
    {
        $conn = Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META().id as _key, u.* 
                  FROM `{$bucket}`.`core`.`user` u 
                  WHERE u._type = 'user' 
                  AND \$role IN u.roles";
        
        try {
            $results = $conn->query($query, ['role' => $role]);
            
            $models = [];
            foreach ($results as $row) {
                $model = new static();
                $data = is_object($row) ? (array)$row : $row;
                if (isset($data['u'])) {
                    $data = array_merge($data, (array)$data['u']);
                    unset($data['u']);
                }
                $model->setAttributes($data);
                $model->setPrimaryKey(isset($data['_mysql_id']) ? $data['_mysql_id'] : null);
                $model->setIsNewRecord(false);
                $models[] = $model;
            }
            
            return $models;
        } catch (\Exception $e) {
            Yii::log("UserDocument search error: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Get full name
     * @return string
     */
    public function getFullName()
    {
        if (!empty($this->full_name)) {
            return $this->full_name;
        }
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
    /**
     * Check if user has a specific role
     * @param string $role Role name
     * @return bool
     */
    public function hasRole($role)
    {
        return is_array($this->roles) && in_array($role, $this->roles);
    }
    
    /**
     * Check if user is active
     * @return bool
     */
    public function isActive()
    {
        return !empty($this->active);
    }
    
    /**
     * Get corresponding MariaDB User model
     * NOTE: Use this for authentication operations
     * @return User|null
     */
    public function getMariaDbModel()
    {
        $pk = $this->getPrimaryKey();
        return $pk ? User::model()->findByPk($pk) : null;
    }
}
```

---

## Section 6: Data Sync Command (Tasks 13-16)

### Task 6.1: Create CouchbaseSyncCommand

**File**: `/protected/commands/CouchbaseSyncCommand.php`

```php
<?php
/**
 * Command to sync data from MariaDB to Couchbase
 * 
 * Usage:
 *   yiic couchbasesync all                    - Sync all supported models
 *   yiic couchbasesync model --model=Patient  - Sync specific model
 *   yiic couchbasesync verify                 - Verify sync integrity
 *   yiic couchbasesync count                  - Show record counts
 */

class CouchbaseSyncCommand extends CConsoleCommand
{
    /**
     * Models to sync in order (respects foreign key dependencies)
     */
    private $syncOrder = [
        'Institution',
        'Site',
        'Firm',
        'User',
        'Patient',
        'Episode',
        'Event',
    ];
    
    /**
     * @return string Command help text
     */
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic couchbasesync <action> [options]

ACTIONS
  all       - Sync all supported models
  model     - Sync a specific model
  verify    - Verify sync integrity (count comparison)
  count     - Show record counts in both databases
  compare   - Compare specific records between databases
  clean     - Remove orphaned documents from Couchbase

OPTIONS
  --model=<name>    Model class name for single model sync
  --batch=<size>    Batch size for processing (default: 1000)
  --from=<id>       Start from specific ID
  --to=<id>         End at specific ID
  --dry-run         Show what would be synced without syncing
  --verbose         Show detailed progress
  --force           Force sync even if counts match

EXAMPLES
  yiic couchbasesync all --batch=500
  yiic couchbasesync model --model=Patient --from=1000 --to=2000
  yiic couchbasesync verify --verbose
  yiic couchbasesync count
EOD;
    }
    
    /**
     * Sync all models
     * @param int $batch Batch size
     * @param bool $dryRun Dry run mode
     * @param bool $verbose Verbose output
     */
    public function actionAll($batch = 1000, $dryRun = false, $verbose = false)
    {
        echo "=== Syncing all models to Couchbase ===\n\n";
        
        $startTime = microtime(true);
        $totalSynced = 0;
        $totalErrors = 0;
        
        foreach ($this->syncOrder as $model) {
            list($synced, $errors) = $this->syncModel($model, $batch, null, null, $dryRun, $verbose);
            $totalSynced += $synced;
            $totalErrors += $errors;
        }
        
        $duration = round(microtime(true) - $startTime, 2);
        
        echo "\n=== Sync Complete ===\n";
        echo "Total synced: {$totalSynced}\n";
        echo "Total errors: {$totalErrors}\n";
        echo "Duration: {$duration} seconds\n";
    }
    
    /**
     * Sync a specific model
     * @param string $model Model class name
     * @param int $batch Batch size
     * @param int $from Start ID
     * @param int $to End ID
     * @param bool $dryRun Dry run mode
     * @param bool $verbose Verbose output
     */
    public function actionModel($model = null, $batch = 1000, $from = null, $to = null, $dryRun = false, $verbose = false)
    {
        if (!$model) {
            echo "Error: --model parameter required\n";
            echo "Available models: " . implode(', ', $this->syncOrder) . "\n";
            return 1;
        }
        
        if (!class_exists($model)) {
            echo "Error: Model class '{$model}' not found\n";
            return 1;
        }
        
        $this->syncModel($model, $batch, $from, $to, $dryRun, $verbose);
    }
    
    /**
     * Verify sync integrity
     * @param string $model Specific model to verify (optional)
     * @param bool $verbose Show detailed output
     */
    public function actionVerify($model = null, $verbose = false)
    {
        $models = $model ? [$model] : $this->syncOrder;
        
        echo "=== Verifying Sync Integrity ===\n\n";
        
        $allMatch = true;
        
        foreach ($models as $modelName) {
            $result = $this->verifyModel($modelName, $verbose);
            if (!$result) {
                $allMatch = false;
            }
        }
        
        echo "\n";
        if ($allMatch) {
            echo "✓ All models are in sync\n";
        } else {
            echo "✗ Some models have mismatches\n";
        }
    }
    
    /**
     * Show record counts
     */
    public function actionCount()
    {
        echo "=== Record Counts ===\n\n";
        echo str_pad("Model", 20) . str_pad("MariaDB", 12) . str_pad("Couchbase", 12) . "Status\n";
        echo str_repeat("-", 56) . "\n";
        
        foreach ($this->syncOrder as $model) {
            $this->showCounts($model);
        }
    }
    
    /**
     * Compare specific record
     * @param string $model Model name
     * @param int $id Record ID
     */
    public function actionCompare($model = null, $id = null)
    {
        if (!$model || !$id) {
            echo "Error: --model and --id parameters required\n";
            return 1;
        }
        
        $modelClass = $model;
        if (!class_exists($modelClass)) {
            echo "Error: Model class '{$modelClass}' not found\n";
            return 1;
        }
        
        $record = $modelClass::model()->findByPk($id);
        if (!$record) {
            echo "Error: Record not found in MariaDB\n";
            return 1;
        }
        
        if (!method_exists($record, 'compareWithCouchbase')) {
            echo "Error: Model does not support Couchbase comparison\n";
            return 1;
        }
        
        $result = $record->compareWithCouchbase();
        
        echo "=== Comparison: {$model} #{$id} ===\n\n";
        echo "Status: {$result['status']}\n";
        
        if ($result['status'] === 'mismatch' && isset($result['differences'])) {
            echo "\nDifferences:\n";
            foreach ($result['differences'] as $field => $values) {
                echo "  {$field}:\n";
                echo "    MariaDB:   " . json_encode($values['mysql']) . "\n";
                echo "    Couchbase: " . json_encode($values['couchbase']) . "\n";
            }
        } elseif (isset($result['message'])) {
            echo "Message: {$result['message']}\n";
        }
    }
    
    /**
     * Sync a single model
     * @return array [synced, errors]
     */
    private function syncModel($modelName, $batchSize, $fromId, $toId, $dryRun, $verbose)
    {
        echo "Syncing {$modelName}...\n";
        
        $modelClass = $modelName;
        if (!class_exists($modelClass)) {
            echo "  Error: Model class not found\n\n";
            return [0, 1];
        }
        
        $model = new $modelClass();
        
        if (!method_exists($model, 'toCouchbaseDocument')) {
            echo "  Skipping: Model does not support Couchbase bridge\n\n";
            return [0, 0];
        }
        
        $criteria = new CDbCriteria();
        $criteria->order = 'id ASC';
        $criteria->limit = $batchSize;
        
        if ($fromId) {
            $criteria->addCondition('id >= :fromId');
            $criteria->params[':fromId'] = $fromId;
        }
        if ($toId) {
            $criteria->addCondition('id <= :toId');
            $criteria->params[':toId'] = $toId;
        }
        
        $total = 0;
        $errors = 0;
        $lastId = $fromId ? $fromId - 1 : 0;
        
        try {
            $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
                \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
        } catch (\Exception $e) {
            echo "  Error: Could not get Couchbase adapter: {$e->getMessage()}\n\n";
            return [0, 1];
        }
        
        while (true) {
            // Update criteria for pagination
            $criteria->addCondition('id > :lastId');
            $criteria->params[':lastId'] = $lastId;
            
            $records = $modelClass::model()->findAll($criteria);
            
            if (empty($records)) {
                break;
            }
            
            foreach ($records as $record) {
                $lastId = $record->id;
                
                if ($dryRun) {
                    if ($verbose) {
                        echo "  Would sync {$modelName} #{$record->id}\n";
                    }
                    $total++;
                    continue;
                }
                
                try {
                    $doc = $record->toCouchbaseDocument();
                    $collection = $model->couchbaseCollection();
                    
                    // Use upsert to handle both insert and update
                    if ($adapter->exists($collection, $record->id)) {
                        $adapter->update($collection, $record->id, $doc);
                    } else {
                        $adapter->insert($collection, $doc);
                    }
                    
                    $total++;
                    
                    if ($verbose && $total % 100 === 0) {
                        echo "  Progress: {$total} records synced\n";
                    }
                } catch (\Exception $e) {
                    if ($verbose) {
                        echo "  Error syncing #{$record->id}: {$e->getMessage()}\n";
                    }
                    $errors++;
                }
            }
            
            // Clear entity cache to prevent memory issues
            $modelClass::model()->resetScope();
        }
        
        $status = $errors > 0 ? "({$errors} errors)" : "";
        echo "  Completed: {$total} records synced {$status}\n\n";
        
        return [$total, $errors];
    }
    
    /**
     * Verify a single model
     * @return bool True if counts match
     */
    private function verifyModel($modelName, $verbose)
    {
        $modelClass = $modelName;
        if (!class_exists($modelClass)) {
            return false;
        }
        
        try {
            $mysqlCount = $modelClass::model()->count();
        } catch (\Exception $e) {
            echo "{$modelName}: Error counting MariaDB records\n";
            return false;
        }
        
        try {
            $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
                \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
            
            $model = new $modelClass();
            if (!method_exists($model, 'couchbaseCollection')) {
                echo "{$modelName}: Not Couchbase-enabled\n";
                return true;
            }
            
            $cbCount = $adapter->count($model->couchbaseCollection());
        } catch (\Exception $e) {
            echo "{$modelName}: Error counting Couchbase records\n";
            return false;
        }
        
        $match = ($mysqlCount === $cbCount);
        $symbol = $match ? '✓' : '✗';
        $diff = $cbCount - $mysqlCount;
        $diffStr = $diff >= 0 ? "+{$diff}" : "{$diff}";
        
        echo "{$modelName}: MySQL={$mysqlCount}, Couchbase={$cbCount} ({$diffStr}) {$symbol}\n";
        
        return $match;
    }
    
    /**
     * Show counts for a model
     */
    private function showCounts($modelName)
    {
        $modelClass = $modelName;
        
        try {
            $mysqlCount = class_exists($modelClass) ? $modelClass::model()->count() : 0;
        } catch (\Exception $e) {
            $mysqlCount = 'Error';
        }
        
        try {
            $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
                \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
            $model = new $modelClass();
            $cbCount = method_exists($model, 'couchbaseCollection') 
                ? $adapter->count($model->couchbaseCollection()) 
                : 'N/A';
        } catch (\Exception $e) {
            $cbCount = 'Error';
        }
        
        $status = ($mysqlCount === $cbCount) ? '✓' : '✗';
        echo str_pad($modelName, 20) . str_pad($mysqlCount, 12) . str_pad($cbCount, 12) . $status . "\n";
    }
}
```

**Acceptance Criteria**:
- [ ] `yiic couchbasesync all` syncs all models
- [ ] `yiic couchbasesync model --model=Patient` syncs specific model
- [ ] `yiic couchbasesync verify` reports count discrepancies
- [ ] `yiic couchbasesync count` shows both database counts
- [ ] Batch processing prevents memory issues
- [ ] Dry-run mode shows what would be synced
- [ ] Errors don't stop the sync process

---

## Section 7: Configuration Updates (Tasks 17-19)

### Task 7.1: Add Dual-Write Configuration

**File**: `/protected/config/core/common.php` (MODIFY)

**Add/update in 'params' array**:
```php
'params' => [
    // ... existing params ...
    
    // Database adapter configuration
    'database_adapter' => 'mariadb',  // Default: mariadb, couchbase, or dual_write
    
    // Dual-write mode - writes to both MariaDB and Couchbase
    'enable_dual_write' => false,
    
    // Couchbase read mode - reads from Couchbase for migrated collections
    'enable_couchbase_read' => false,
    
    // Collections that have been fully migrated to Couchbase
    'couchbase_migrated_collections' => [
        // Add collection names here as they are migrated and verified
        // 'patient',
        // 'episode',
        // 'event',
        // 'user',
    ],
],
```

### Task 7.2: Create Environment Variable Support

**File**: `/protected/config/local/common.php` (MODIFY or CREATE)

```php
<?php
/**
 * Local configuration overrides
 * Environment variables take precedence
 */

return [
    'params' => [
        // Database adapter - can be overridden via OPENEYES_DATABASE_ADAPTER env var
        'database_adapter' => getenv('OPENEYES_DATABASE_ADAPTER') ?: 'mariadb',
        
        // Dual-write mode - can be overridden via OPENEYES_ENABLE_DUAL_WRITE env var
        'enable_dual_write' => filter_var(
            getenv('OPENEYES_ENABLE_DUAL_WRITE') ?: false, 
            FILTER_VALIDATE_BOOLEAN
        ),
        
        // Couchbase read mode - can be overridden via OPENEYES_ENABLE_COUCHBASE_READ env var
        'enable_couchbase_read' => filter_var(
            getenv('OPENEYES_ENABLE_COUCHBASE_READ') ?: false, 
            FILTER_VALIDATE_BOOLEAN
        ),
    ],
];
```

### Task 7.3: Update Docker Compose for Dual-Write Testing

**File**: `/.devcontainer/docker-compose.yml` (MODIFY)

**Add environment variables to web service**:
```yaml
services:
  web:
    # ... existing config ...
    environment:
      # ... existing env vars ...
      - OPENEYES_ENABLE_DUAL_WRITE=${OPENEYES_ENABLE_DUAL_WRITE:-false}
      - OPENEYES_ENABLE_COUCHBASE_READ=${OPENEYES_ENABLE_COUCHBASE_READ:-false}
      - OPENEYES_DATABASE_ADAPTER=${OPENEYES_DATABASE_ADAPTER:-mariadb}
```

---

## Section 8: Collection Creation Script (Tasks 20-21)

### Task 8.1: Create Core Collections Script

**File**: `/protected/scripts/couchbase/create-core-collections.sh`

```bash
#!/bin/bash
#
# Create Couchbase collections for core models
# Usage: ./create-core-collections.sh
#
# Environment variables:
#   CB_HOST - Couchbase host (default: localhost)
#   CB_USER - Couchbase admin username (default: Administrator)
#   CB_PASS - Couchbase admin password (default: password)
#   CB_BUCKET - Bucket name (default: openeyes)
#

set -e

CB_HOST="${CB_HOST:-localhost}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"
CB_BUCKET="${CB_BUCKET:-openeyes}"

echo "=== Creating Core Collections ==="
echo "Host: ${CB_HOST}"
echo "Bucket: ${CB_BUCKET}"
echo ""

# Define scopes and their collections
declare -A SCOPE_COLLECTIONS=(
    ["core"]="patient user episode event firm site institution contact address"
    ["clinical"]="examination diagnosis medication allergy"
    ["correspondence"]="letter message document"
    ["booking"]="operation session theatre"
    ["admin"]="audit setting"
    ["reference"]="event_type element_type specialty subspecialty disorder ethnic_group gender country"
)

# First, ensure scopes exist
echo "Creating scopes..."
for scope in "${!SCOPE_COLLECTIONS[@]}"; do
    echo "  Creating scope: ${scope}"
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${CB_BUCKET}/scopes" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${scope}" \
        2>/dev/null || true
done

echo ""
echo "Creating collections..."

# Create collections in each scope
for scope in "${!SCOPE_COLLECTIONS[@]}"; do
    collections=${SCOPE_COLLECTIONS[$scope]}
    for coll in $collections; do
        echo "  Creating ${scope}.${coll}"
        curl -s -X POST \
            "http://${CB_HOST}:8091/pools/default/buckets/${CB_BUCKET}/scopes/${scope}/collections" \
            -u "${CB_USER}:${CB_PASS}" \
            -d "name=${coll}" \
            2>/dev/null || true
    done
done

echo ""
echo "=== Collection Creation Complete ==="
echo ""
echo "Verify collections at: http://${CB_HOST}:8091/ui/index.html#!/buckets/${CB_BUCKET}"
```

### Task 8.2: Make Script Executable

**Command**:
```bash
chmod +x protected/scripts/couchbase/create-core-collections.sh
```

---

## Testing & Verification

### Task 22: Run Unit Tests

**Command**:
```bash
# Run all new unit tests
docker compose -f .devcontainer/docker-compose.yml exec web php /var/www/openeyes/bin/phpunit \
    --configuration /var/www/openeyes/protected/tests/phpunit.xml \
    /var/www/openeyes/protected/tests/unit/models/traits/

# Run model document tests
docker compose -f .devcontainer/docker-compose.yml exec web php /var/www/openeyes/bin/phpunit \
    --configuration /var/www/openeyes/protected/tests/phpunit.xml \
    /var/www/openeyes/protected/tests/unit/models/couchbase/
```

### Task 23: Integration Testing

**Test Dual-Write Mode**:
```bash
# 1. Enable dual-write
docker compose -f .devcontainer/docker-compose.yml exec web bash -c "
export OPENEYES_ENABLE_DUAL_WRITE=true
php -r \"
require_once '/var/www/openeyes/protected/yii.php';
Yii::app()->params['enable_dual_write'] = true;
echo 'Dual-write enabled: ' . (Yii::app()->params['enable_dual_write'] ? 'YES' : 'NO') . PHP_EOL;
\"
"

# 2. Test patient save (creates in both databases)
docker compose -f .devcontainer/docker-compose.yml exec web php -r "
require_once '/var/www/openeyes/protected/yii.php';
Yii::app()->params['enable_dual_write'] = true;

\$patient = Patient::model()->findByPk(1);
if (\$patient) {
    echo 'Testing sync for Patient #1: ' . \$patient->contact->first_name . ' ' . \$patient->contact->last_name . PHP_EOL;
    \$result = \$patient->syncToCouchbase();
    echo 'Sync result: ' . (\$result ? 'SUCCESS' : 'FAILED') . PHP_EOL;
    
    // Verify
    \$comparison = \$patient->compareWithCouchbase();
    echo 'Comparison status: ' . \$comparison['status'] . PHP_EOL;
}
"
```

### Task 24: Full Sync Test

**Command**:
```bash
# Run full sync (dry-run first)
docker compose -f .devcontainer/docker-compose.yml exec web php /var/www/openeyes/protected/yiic.php couchbasesync all --dry-run --verbose

# Then actual sync
docker compose -f .devcontainer/docker-compose.yml exec web php /var/www/openeyes/protected/yiic.php couchbasesync all --batch=100 --verbose

# Verify
docker compose -f .devcontainer/docker-compose.yml exec web php /var/www/openeyes/protected/yiic.php couchbasesync verify
```

---

## Acceptance Criteria Summary

### Section 1: Model Bridge Trait
- [ ] CouchbaseModelBridge trait created and tested
- [ ] Trait can be added to any BaseActiveRecord model
- [ ] Document conversion works correctly
- [ ] Dual-write respects enable_dual_write flag

### Section 2: Patient Model
- [ ] Patient model uses bridge trait
- [ ] Contact, addresses, identifiers embedded
- [ ] PatientDocument model works standalone
- [ ] All existing Patient tests pass

### Section 3: Episode Model
- [ ] Episode model uses bridge trait
- [ ] EpisodeDocument model works standalone
- [ ] Related data (firm, status) denormalized

### Section 4: Event Model
- [ ] Event model uses bridge trait
- [ ] EventDocument model works standalone
- [ ] Event type info denormalized

### Section 5: User Model
- [ ] User model uses bridge trait (NO passwords)
- [ ] UserDocument model works standalone
- [ ] Roles included as array

### Section 6: Data Sync Command
- [ ] Sync command works for all models
- [ ] Batch processing prevents memory issues
- [ ] Verify command reports discrepancies
- [ ] Dry-run mode works

### Section 7: Configuration
- [ ] Dual-write can be enabled/disabled
- [ ] Environment variables supported
- [ ] Docker compose updated

### Section 8: Collections
- [ ] Collection creation script works
- [ ] All core collections exist

---

## Rollback Plan

If issues arise, rollback is straightforward:

### 1. Disable Dual-Write
```bash
# Set environment variable
export OPENEYES_ENABLE_DUAL_WRITE=false

# Or update config
# In protected/config/core/common.php, set:
# 'enable_dual_write' => false,
```

### 2. Remove Trait Usage (if needed)
```php
// In each model, comment out:
// use CouchbaseModelBridge;

// And remove afterSave/afterDelete hooks
```

### 3. Data Remains Safe
- MariaDB remains primary and unchanged
- Couchbase data remains but is not used
- Application continues to work normally

---

## Definition of Done

- [ ] All 24 tasks completed
- [ ] All acceptance criteria met
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] Existing functionality unchanged
- [ ] Performance within acceptable limits (<100ms overhead)
- [ ] Documentation updated

---

**Specification Author**: AI Agent (Droid)  
**Date**: December 19, 2025  
**Ready for Implementation**: YES  
**Estimated Duration**: 4-6 weeks  
**Total Tasks**: 24  
**Total New Files**: ~15 files  
**Total Modified Files**: ~8 files  

---

*This specification provides complete, step-by-step instructions for implementing Phase 4. Each task includes file paths, complete code, acceptance criteria, and testing commands.*
