# Phase 4: Core Model Migration

## Overview
This phase migrates the core system models (Patient, User, Episode, Event, etc.) from the Yii CActiveRecord pattern to support the Couchbase adapter while maintaining backward compatibility with MariaDB.

## Prerequisites
- Phase 3 completed (Data models and schemas defined)
- Document schemas validated
- Indexes created in Couchbase

## Dependencies
- Phase 3: Data Modeling & Schema Translation

## Tasks

### 4.1 Model Bridge Pattern

#### 4.1.1 Create Model Bridge Trait
**File**: `/protected/models/traits/CouchbaseModelBridge.php`

```php
<?php
/**
 * Trait to add Couchbase support to existing CActiveRecord models
 * This allows gradual migration without breaking existing functionality
 */

namespace OE\Models\Traits;

use OE\Database\DatabaseAdapterFactory;
use OE\Database\Transformers\TypeTransformer;

trait CouchbaseModelBridge
{
    /**
     * Get the Couchbase scope for this model
     */
    public function couchbaseScope(): string
    {
        return 'core'; // Override in model if different
    }
    
    /**
     * Get the Couchbase collection name for this model
     */
    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }
    
    /**
     * Check if this model should use Couchbase
     */
    protected function shouldUseCouchbase(): bool
    {
        return DatabaseAdapterFactory::shouldUseCouchbase($this->tableName());
    }
    
    /**
     * Get adapter for this model
     */
    protected function getDatabaseAdapter()
    {
        return DatabaseAdapterFactory::getAdapterForCollection($this->tableName());
    }
    
    /**
     * Convert model attributes to Couchbase document format
     */
    public function toCouchbaseDocument(): array
    {
        $doc = [];
        $schema = $this->getMetaData()->columns;
        
        foreach ($this->attributes as $attr => $value) {
            if (isset($schema[$attr])) {
                $doc[$attr] = TypeTransformer::transform(
                    $value,
                    $schema[$attr]->dbType
                );
            } else {
                $doc[$attr] = $value;
            }
        }
        
        // Add metadata
        $doc['_type'] = $this->tableName();
        
        // Handle embedded relations if defined
        if (method_exists($this, 'getEmbeddedRelations')) {
            foreach ($this->getEmbeddedRelations() as $relation => $config) {
                $related = $this->$relation;
                if ($related) {
                    if (is_array($related)) {
                        $doc[$relation] = array_map(function($item) {
                            return $item->toCouchbaseDocument();
                        }, $related);
                    } else {
                        $doc[$relation] = $related->toCouchbaseDocument();
                    }
                }
            }
        }
        
        return $doc;
    }
    
    /**
     * Populate model from Couchbase document
     */
    public function fromCouchbaseDocument(array $doc): void
    {
        $schema = $this->getMetaData()->columns;
        
        foreach ($doc as $attr => $value) {
            // Skip metadata fields
            if (strpos($attr, '_') === 0) {
                continue;
            }
            
            if (isset($schema[$attr])) {
                $this->$attr = TypeTransformer::reverseTransform(
                    $value,
                    $schema[$attr]->dbType
                );
            } else {
                // Handle as-is for non-schema attributes
                $this->$attr = $value;
            }
        }
    }
    
    /**
     * Save to Couchbase (for dual-write support)
     */
    protected function saveToCouchbase(): bool
    {
        if (!\Yii::app()->params['enable_dual_write']) {
            return true;
        }
        
        try {
            $adapter = DatabaseAdapterFactory::getAdapter(
                DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
            
            $doc = $this->toCouchbaseDocument();
            
            if ($this->isNewRecord) {
                $adapter->insert($this->couchbaseCollection(), $doc);
            } else {
                $adapter->update(
                    $this->couchbaseCollection(),
                    $this->getPrimaryKey(),
                    $doc
                );
            }
            
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase save failed for {$this->tableName()}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING
            );
            return false;
        }
    }
    
    /**
     * Delete from Couchbase (for dual-write support)
     */
    protected function deleteFromCouchbase(): bool
    {
        if (!\Yii::app()->params['enable_dual_write']) {
            return true;
        }
        
        try {
            $adapter = DatabaseAdapterFactory::getAdapter(
                DatabaseAdapterFactory::ADAPTER_COUCHBASE
            );
            
            $adapter->delete(
                $this->couchbaseCollection(),
                $this->getPrimaryKey()
            );
            
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase delete failed for {$this->tableName()}: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING
            );
            return false;
        }
    }
}
```

**Acceptance Criteria**:
- [ ] Trait can be added to existing models
- [ ] Document conversion works correctly
- [ ] Dual-write is conditional

### 4.2 Patient Model Migration

#### 4.2.1 Update Patient Model
**File**: `/protected/models/Patient.php` (update)

Add to class:
```php
<?php
// At top of file
use OE\Models\Traits\CouchbaseModelBridge;

class Patient extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;
    
    // Existing code...
    
    /**
     * Define Couchbase scope
     */
    public function couchbaseScope(): string
    {
        return 'core';
    }
    
    /**
     * Define relations to embed in Couchbase document
     */
    public function getEmbeddedRelations(): array
    {
        return [
            'contact' => ['embed' => true],
            'address' => ['embed' => true, 'through' => 'contact'],
        ];
    }
    
    /**
     * Extended toCouchbaseDocument for patient-specific data
     */
    public function toCouchbaseDocument(): array
    {
        $doc = parent::toCouchbaseDocument();
        
        // Embed contact information
        if ($this->contact) {
            $doc['contact'] = [
                'primary_phone' => $this->contact->primary_phone,
                'mobile_phone' => $this->contact->mobile_phone,
                'email' => $this->contact->email,
            ];
            
            // Embed address
            if ($this->contact->address) {
                $doc['contact']['address'] = [
                    'address1' => $this->contact->address->address1,
                    'address2' => $this->contact->address->address2,
                    'city' => $this->contact->address->city,
                    'postcode' => $this->contact->address->postcode,
                    'county' => $this->contact->address->county,
                    'country_id' => $this->contact->address->country_id,
                ];
            }
        }
        
        // Add patient identifiers
        $doc['identifiers'] = [];
        foreach ($this->identifiers as $identifier) {
            $doc['identifiers'][] = [
                'type' => $identifier->patientIdentifierType->short_title,
                'value' => $identifier->value,
            ];
        }
        
        return $doc;
    }
    
    /**
     * Override afterSave to support dual-write
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
    
    /**
     * Override afterDelete to support dual-write
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
}
```

**Acceptance Criteria**:
- [ ] Patient model uses bridge trait
- [ ] Contact and address are embedded
- [ ] Dual-write works on save
- [ ] Existing functionality unchanged

#### 4.2.2 Create Couchbase Patient Model
**File**: `/protected/models/couchbase/PatientDocument.php`

```php
<?php
/**
 * Patient document model for direct Couchbase operations
 */

class PatientDocument extends CouchbaseActiveRecord
{
    public function documentType(): string
    {
        return 'patient';
    }
    
    public function scope(): string
    {
        return 'core';
    }
    
    public function collectionName(): string
    {
        return 'patient';
    }
    
    public function rules()
    {
        return [
            ['hos_num, first_name, last_name, dob', 'required'],
            ['hos_num', 'length', 'max' => 40],
            ['nhs_num', 'length', 'max' => 40],
            ['first_name, last_name', 'length', 'max' => 300],
            ['dob', 'date', 'format' => 'yyyy-MM-dd'],
            ['gender', 'in', 'range' => ['M', 'F', 'U']],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'hos_num' => 'Hospital Number',
            'nhs_num' => 'NHS Number',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'dob' => 'Date of Birth',
            'gender' => 'Gender',
        ];
    }
    
    /**
     * Find patient by hospital number
     */
    public static function findByHosNum(string $hosNum): ?PatientDocument
    {
        $results = self::findByN1QL(
            "SELECT META().id, * FROM `openeyes`.`core`.`patient` 
             WHERE hos_num = \$hosNum",
            ['hosNum' => $hosNum]
        );
        
        return $results[0] ?? null;
    }
    
    /**
     * Find patient by NHS number
     */
    public static function findByNhsNum(string $nhsNum): ?PatientDocument
    {
        $results = self::findByN1QL(
            "SELECT META().id, * FROM `openeyes`.`core`.`patient` 
             WHERE nhs_num = \$nhsNum",
            ['nhsNum' => $nhsNum]
        );
        
        return $results[0] ?? null;
    }
    
    /**
     * Search patients by name
     */
    public static function searchByName(
        string $lastName,
        string $firstName = null,
        int $limit = 50
    ): array {
        $query = "SELECT META().id, * FROM `openeyes`.`core`.`patient` 
                  WHERE last_name LIKE \$lastName";
        $params = ['lastName' => $lastName . '%'];
        
        if ($firstName) {
            $query .= " AND first_name LIKE \$firstName";
            $params['firstName'] = $firstName . '%';
        }
        
        $query .= " ORDER BY last_name, first_name LIMIT \$limit";
        $params['limit'] = $limit;
        
        return self::findByN1QL($query, $params);
    }
    
    /**
     * Get episodes for this patient
     */
    public function getEpisodes(): array
    {
        return EpisodeDocument::findByN1QL(
            "SELECT META().id, * FROM `openeyes`.`core`.`episode` 
             WHERE patient_id = \$patientId 
             ORDER BY start_date DESC",
            ['patientId' => $this->getPrimaryKey()]
        );
    }
    
    /**
     * Helper to run N1QL queries and return model instances
     */
    protected static function findByN1QL(string $query, array $params = []): array
    {
        $connection = \Yii::app()->couchbase;
        $result = $connection->query($query, $params);
        
        $models = [];
        foreach ($result->rows() as $row) {
            $model = new static();
            $model->_attributes = (array) ($row['patient'] ?? $row);
            $model->_pk = str_replace('patient::', '', $row['id'] ?? $row['META']['id'] ?? null);
            $model->_isNewRecord = false;
            $models[] = $model;
        }
        
        return $models;
    }
}
```

**Acceptance Criteria**:
- [ ] Document model provides Couchbase-native access
- [ ] Search methods work correctly
- [ ] Validation rules defined

### 4.3 Episode Model Migration

#### 4.3.1 Update Episode Model
**File**: `/protected/models/Episode.php` (update)

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Episode extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;
    
    // Existing code...
    
    public function couchbaseScope(): string
    {
        return 'core';
    }
    
    public function toCouchbaseDocument(): array
    {
        $doc = parent::toCouchbaseDocument();
        
        // Ensure references are strings for Couchbase
        $doc['patient_id'] = (string) $this->patient_id;
        $doc['firm_id'] = (string) $this->firm_id;
        
        return $doc;
    }
    
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
    
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
}
```

### 4.4 Event Model Migration

#### 4.4.1 Update Event Model
**File**: `/protected/models/Event.php` (update)

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Event extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;
    
    // Existing code...
    
    public function couchbaseScope(): string
    {
        return 'core';
    }
    
    public function toCouchbaseDocument(): array
    {
        $doc = parent::toCouchbaseDocument();
        
        // References as strings
        $doc['episode_id'] = (string) $this->episode_id;
        $doc['event_type_id'] = (string) $this->event_type_id;
        $doc['institution_id'] = (string) $this->institution_id;
        $doc['site_id'] = (string) $this->site_id;
        
        // Add event type info for easier querying
        if ($this->eventType) {
            $doc['event_type_name'] = $this->eventType->name;
            $doc['event_type_class'] = $this->eventType->class_name;
        }
        
        return $doc;
    }
    
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
    
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
}
```

### 4.5 User Model Migration

#### 4.5.1 Update User Model
**File**: `/protected/models/User.php` (update)

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class User extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;
    
    // Existing code...
    
    public function couchbaseScope(): string
    {
        return 'core';
    }
    
    /**
     * Exclude sensitive data from Couchbase document
     */
    public function toCouchbaseDocument(): array
    {
        $doc = parent::toCouchbaseDocument();
        
        // Remove sensitive fields
        unset($doc['password']);
        unset($doc['salt']);
        
        // Add contact info
        if ($this->contact) {
            $doc['contact'] = [
                'title' => $this->contact->title,
                'first_name' => $this->contact->first_name,
                'last_name' => $this->contact->last_name,
                'email' => $this->contact->email,
            ];
        }
        
        // Add role information
        $doc['roles'] = array_map(function($auth) {
            return $auth->item_name;
        }, AuthAssignment::model()->findAllByAttributes(['userid' => $this->id]));
        
        return $doc;
    }
    
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
}
```

**Acceptance Criteria**:
- [ ] Sensitive data excluded from Couchbase
- [ ] Role information included
- [ ] Contact embedded

### 4.6 Reference Data Models

#### 4.6.1 Site Model
**File**: `/protected/models/Site.php` (update)

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Site extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;
    
    public function couchbaseScope(): string
    {
        return 'reference';
    }
    
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
}
```

#### 4.6.2 Firm Model
**File**: `/protected/models/Firm.php` (update)

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Firm extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;
    
    public function couchbaseScope(): string
    {
        return 'core';
    }
    
    public function toCouchbaseDocument(): array
    {
        $doc = parent::toCouchbaseDocument();
        
        // Denormalize subspecialty name
        if ($this->serviceSubspecialtyAssignment && 
            $this->serviceSubspecialtyAssignment->subspecialty) {
            $doc['subspecialty_name'] = 
                $this->serviceSubspecialtyAssignment->subspecialty->name;
        }
        
        return $doc;
    }
    
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }
}
```

### 4.7 Base Model Updates

#### 4.7.1 Update BaseActiveRecord
**File**: `/protected/models/BaseActiveRecord.php` (update)

Add hook methods:
```php
<?php
// Add these methods to support migration hooks

class BaseActiveRecord extends CActiveRecord
{
    // Existing code...
    
    /**
     * Hook called before Couchbase sync
     * Override in subclasses for custom behavior
     */
    protected function beforeCouchbaseSync(): bool
    {
        return true;
    }
    
    /**
     * Hook called after Couchbase sync
     * Override in subclasses for custom behavior
     */
    protected function afterCouchbaseSync(): void
    {
        // Default: no-op
    }
    
    /**
     * Check if model should participate in dual-write
     */
    public function supportsDualWrite(): bool
    {
        return method_exists($this, 'toCouchbaseDocument');
    }
}
```

### 4.8 Collection Creator Script

#### 4.8.1 Create Collections for Core Models
**File**: `/protected/scripts/couchbase/create-core-collections.sh`

```bash
#!/bin/bash
# Create collections for core models

CB_HOST="${CB_HOST:-localhost}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"
BUCKET="openeyes"

# Core collections
COLLECTIONS=(
    "core:patient"
    "core:user"
    "core:episode"
    "core:event"
    "core:firm"
    "core:site"
    "core:institution"
    "core:contact"
    "core:address"
)

for ENTRY in "${COLLECTIONS[@]}"; do
    SCOPE="${ENTRY%%:*}"
    COLL="${ENTRY##*:}"
    
    echo "Creating collection ${SCOPE}.${COLL}..."
    
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/${SCOPE}/collections" \
        -u ${CB_USER}:${CB_PASS} \
        -d name=${COLL}
done

echo "Core collections created"
```

**Acceptance Criteria**:
- [ ] All core collections exist
- [ ] Script is idempotent

### 4.9 Data Sync Command

#### 4.9.1 Initial Data Sync Command
**File**: `/protected/commands/CouchbaseSyncCommand.php`

```php
<?php
/**
 * Command to sync data from MariaDB to Couchbase
 */

class CouchbaseSyncCommand extends CConsoleCommand
{
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic couchbasesync <action> [options]

ACTIONS
  all       - Sync all supported models
  model     - Sync a specific model (--model=Patient)
  verify    - Verify sync integrity
  count     - Show record counts

OPTIONS
  --model=<name>    Model class name for single model sync
  --batch=<size>    Batch size for processing (default: 1000)
  --from=<id>       Start from specific ID
  --dry-run         Show what would be synced without syncing
EOD;
    }
    
    /**
     * Models to sync in order
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
    
    public function actionAll($batch = 1000, $dryRun = false)
    {
        foreach ($this->syncOrder as $model) {
            $this->syncModel($model, $batch, null, $dryRun);
        }
    }
    
    public function actionModel($model, $batch = 1000, $from = null, $dryRun = false)
    {
        if (!$model) {
            echo "Error: --model parameter required\n";
            return 1;
        }
        
        $this->syncModel($model, $batch, $from, $dryRun);
    }
    
    public function actionVerify($model = null)
    {
        $models = $model ? [$model] : $this->syncOrder;
        
        foreach ($models as $modelName) {
            $this->verifyModel($modelName);
        }
    }
    
    public function actionCount()
    {
        foreach ($this->syncOrder as $model) {
            $this->showCounts($model);
        }
    }
    
    private function syncModel($modelName, $batchSize, $fromId, $dryRun)
    {
        echo "Syncing {$modelName}...\n";
        
        $modelClass = $modelName;
        if (!class_exists($modelClass)) {
            echo "Error: Model class {$modelClass} not found\n";
            return;
        }
        
        $model = new $modelClass();
        
        if (!method_exists($model, 'toCouchbaseDocument')) {
            echo "Warning: {$modelName} does not support Couchbase bridge\n";
            return;
        }
        
        $criteria = new CDbCriteria();
        $criteria->order = 'id ASC';
        $criteria->limit = $batchSize;
        
        if ($fromId) {
            $criteria->addCondition('id > :fromId');
            $criteria->params[':fromId'] = $fromId;
        }
        
        $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
        
        $total = 0;
        $errors = 0;
        $lastId = $fromId ?? 0;
        
        while (true) {
            $criteria->addCondition('id > :lastId');
            $criteria->params[':lastId'] = $lastId;
            
            $records = $modelClass::model()->findAll($criteria);
            
            if (empty($records)) {
                break;
            }
            
            foreach ($records as $record) {
                $lastId = $record->id;
                
                if ($dryRun) {
                    echo "Would sync {$modelName} #{$record->id}\n";
                    $total++;
                    continue;
                }
                
                try {
                    $doc = $record->toCouchbaseDocument();
                    $doc['id'] = $record->id;
                    $adapter->insert($model->couchbaseCollection(), $doc);
                    $total++;
                    
                    if ($total % 100 === 0) {
                        echo "  Synced {$total} records...\n";
                    }
                } catch (\Exception $e) {
                    echo "  Error syncing #{$record->id}: {$e->getMessage()}\n";
                    $errors++;
                }
            }
        }
        
        echo "Completed: {$total} records synced, {$errors} errors\n\n";
    }
    
    private function verifyModel($modelName)
    {
        echo "Verifying {$modelName}...\n";
        
        $mysqlCount = $modelName::model()->count();
        
        $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
        
        $model = new $modelName();
        $cbCount = $adapter->count($model->couchbaseCollection());
        
        $match = $mysqlCount === $cbCount ? '✓' : '✗';
        echo "  MySQL: {$mysqlCount}, Couchbase: {$cbCount} {$match}\n";
        
        if ($mysqlCount !== $cbCount) {
            echo "  WARNING: Count mismatch!\n";
        }
    }
    
    private function showCounts($modelName)
    {
        $mysqlCount = $modelName::model()->count();
        echo "{$modelName}: {$mysqlCount} records in MySQL\n";
    }
}
```

**Acceptance Criteria**:
- [ ] Command syncs all core models
- [ ] Batch processing works
- [ ] Verification reports discrepancies
- [ ] Dry-run mode works

## Testing Criteria

### Unit Tests
- [ ] CouchbaseModelBridge trait methods work
- [ ] Document conversion is correct
- [ ] Type transformations are accurate

### Integration Tests
- [ ] Dual-write saves to both databases
- [ ] Data integrity maintained
- [ ] Sync command populates Couchbase

### Regression Tests
- [ ] All existing patient tests pass
- [ ] All existing episode tests pass
- [ ] All existing event tests pass
- [ ] API endpoints still work

### Performance Tests
- [ ] Save operation latency acceptable (<100ms increase)
- [ ] No memory leaks during batch sync

## Rollback Plan

1. Remove `use CouchbaseModelBridge` from models
2. Remove `afterSave` Couchbase hooks
3. Disable dual-write: `ENABLE_DUAL_WRITE=false`
4. Data in Couchbase remains but is not used

## Definition of Done

- [ ] All core models have CouchbaseModelBridge
- [ ] Dual-write operational for core models
- [ ] Sync command successfully migrates existing data
- [ ] All existing tests pass
- [ ] Performance within acceptable limits

---

*Phase 4 Completion Sign-off:*
- [ ] Technical Lead
- [ ] QA

*Estimated Duration: 4-6 weeks*
