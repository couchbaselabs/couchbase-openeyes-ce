# Phase 5: Module Model Migration

## Overview
This phase migrates module-specific models (OphCiExamination, OphTrOperationbooking, OphCoCorrespondence, etc.) to support Couchbase. Due to the large number of modules, this phase is broken into sub-phases by module priority.

## Prerequisites
- Phase 4 completed (Core models migrated)
- Core data successfully synced to Couchbase
- Dual-write verified for core models

## Dependencies
- Phase 4: Core Model Migration

## Module Priority

### Priority 1 - High Usage (Weeks 1-3)
1. **OphCiExamination** - Clinical examination (50+ element tables)
2. **OphTrOperationbooking** - Operation scheduling
3. **OphCoCorrespondence** - Letters and correspondence

### Priority 2 - Medium Usage (Weeks 4-5)
4. **OphTrOperationnote** - Surgical notes
5. **OphDrPrescription** - Prescriptions
6. **OphTrConsent** - Consent forms

### Priority 3 - Lower Usage (Weeks 6-8)
7. **OphCoMessaging** - Internal messaging
8. **OphInBiometry** - Biometry data
9. **OphTrLaser** - Laser treatment
10. **OphTrIntravitrealinjection** - Injection treatment
11. Remaining modules

## Tasks

### 5.1 OphCiExamination Module Migration

#### 5.1.1 Examination Document Model
**File**: `/protected/modules/OphCiExamination/models/couchbase/ExaminationDocument.php`

```php
<?php
/**
 * Examination document model for Couchbase
 * Embeds all element data within a single document per event
 */

namespace OEModule\OphCiExamination\models;

class ExaminationDocument extends \CouchbaseActiveRecord
{
    public function documentType(): string
    {
        return 'examination';
    }
    
    public function scope(): string
    {
        return 'clinical';
    }
    
    public function collectionName(): string
    {
        return 'examination';
    }
    
    /**
     * Create examination document from event
     */
    public static function createFromEvent(\Event $event): self
    {
        $doc = new self();
        $doc->_pk = $event->id;
        
        $doc->_attributes = [
            'event_id' => (string) $event->id,
            'episode_id' => (string) $event->episode_id,
            'patient_id' => (string) $event->episode->patient_id,
            'event_date' => $event->event_date,
            'created_date' => $event->created_date,
            'created_user_id' => (string) $event->created_user_id,
            'institution_id' => (string) $event->institution_id,
            'site_id' => (string) $event->site_id,
            'elements' => [],
        ];
        
        // Load and embed all elements
        $elements = $event->getElements();
        foreach ($elements as $element) {
            $elementData = self::elementToArray($element);
            $doc->_attributes['elements'][$element->getElementTypeName()] = $elementData;
        }
        
        return $doc;
    }
    
    /**
     * Convert element to array for embedding
     */
    private static function elementToArray($element): array
    {
        $data = [];
        
        // Get all attributes
        foreach ($element->attributes as $attr => $value) {
            // Skip internal fields
            if (in_array($attr, ['id', 'event_id', 'created_user_id', 
                                  'created_date', 'last_modified_user_id', 
                                  'last_modified_date'])) {
                continue;
            }
            
            $data[$attr] = $value;
        }
        
        // Handle sided elements
        if ($element instanceof \SplitEventTypeElement) {
            $data['has_left'] = $element->hasLeft();
            $data['has_right'] = $element->hasRight();
        }
        
        // Handle element-specific relations
        if (method_exists($element, 'getCouchbaseEmbeddedData')) {
            $data = array_merge($data, $element->getCouchbaseEmbeddedData());
        }
        
        return $data;
    }
    
    /**
     * Get visual acuity data
     */
    public function getVisualAcuity(): ?array
    {
        return $this->_attributes['elements']['VisualAcuity'] ?? null;
    }
    
    /**
     * Get IOP data
     */
    public function getIntraocularPressure(): ?array
    {
        return $this->_attributes['elements']['IntraocularPressure'] ?? null;
    }
    
    /**
     * Get refraction data
     */
    public function getRefraction(): ?array
    {
        return $this->_attributes['elements']['Refraction'] ?? null;
    }
}
```

#### 5.1.2 Element Bridge Trait
**File**: `/protected/modules/OphCiExamination/models/traits/CouchbaseElementBridge.php`

```php
<?php
/**
 * Trait for examination element models to support Couchbase
 */

namespace OEModule\OphCiExamination\models\traits;

trait CouchbaseElementBridge
{
    /**
     * Get data to embed in examination document
     * Override in specific elements for custom handling
     */
    public function getCouchbaseEmbeddedData(): array
    {
        $data = [];
        
        // Handle HasMany relations marked for embedding
        $relations = $this->relations();
        foreach ($relations as $name => $config) {
            if ($config[0] === \CActiveRecord::HAS_MANY) {
                $items = $this->$name;
                if (!empty($items)) {
                    $data[$name] = array_map(function($item) {
                        return $this->itemToArray($item);
                    }, $items);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Convert related item to array
     */
    protected function itemToArray($item): array
    {
        $arr = [];
        foreach ($item->attributes as $attr => $value) {
            if (!in_array($attr, ['id', 'element_id'])) {
                $arr[$attr] = $value;
            }
        }
        return $arr;
    }
}
```

#### 5.1.3 Visual Acuity Element Update
**File**: `/protected/modules/OphCiExamination/models/Element_OphCiExamination_VisualAcuity.php` (update)

```php
<?php
// Add to class
use OEModule\OphCiExamination\models\traits\CouchbaseElementBridge;

class Element_OphCiExamination_VisualAcuity extends \SplitEventTypeElement
{
    use CouchbaseElementBridge;
    
    // Existing code...
    
    public function getCouchbaseEmbeddedData(): array
    {
        $data = parent::getCouchbaseEmbeddedData();
        
        // Add readings with resolved lookups
        $data['left_readings'] = [];
        $data['right_readings'] = [];
        
        foreach ($this->left_readings as $reading) {
            $data['left_readings'][] = [
                'value' => $reading->value,
                'method' => $reading->method ? $reading->method->name : null,
                'unit' => $reading->unit ? $reading->unit->name : null,
                'source' => $reading->source ? $reading->source->name : null,
            ];
        }
        
        foreach ($this->right_readings as $reading) {
            $data['right_readings'][] = [
                'value' => $reading->value,
                'method' => $reading->method ? $reading->method->name : null,
                'unit' => $reading->unit ? $reading->unit->name : null,
                'source' => $reading->source ? $reading->source->name : null,
            ];
        }
        
        return $data;
    }
}
```

**Acceptance Criteria**:
- [ ] Examination documents created from events
- [ ] All elements embedded correctly
- [ ] Readings include resolved lookup values

### 5.2 OphTrOperationbooking Module Migration

#### 5.2.1 Operation Booking Document
**File**: `/protected/modules/OphTrOperationbooking/models/couchbase/OperationDocument.php`

```php
<?php
/**
 * Operation booking document model for Couchbase
 */

namespace OEModule\OphTrOperationbooking\models;

class OperationDocument extends \CouchbaseActiveRecord
{
    public function documentType(): string
    {
        return 'operation';
    }
    
    public function scope(): string
    {
        return 'booking';
    }
    
    public function collectionName(): string
    {
        return 'operation';
    }
    
    /**
     * Create from operation element
     */
    public static function createFromElement(
        Element_OphTrOperationbooking_Operation $element
    ): self {
        $doc = new self();
        $doc->_pk = $element->event_id;
        
        $event = $element->event;
        $booking = $element->booking;
        
        $doc->_attributes = [
            'event_id' => (string) $element->event_id,
            'episode_id' => (string) $event->episode_id,
            'patient_id' => (string) $event->episode->patient_id,
            
            // Operation details
            'eye_id' => $element->eye_id,
            'eye_name' => $element->eye ? $element->eye->name : null,
            'consultant_required' => (bool) $element->consultant_required,
            'senior_fellow_to_do' => (bool) $element->senior_fellow_to_do,
            'anaesthetic_type_id' => $element->anaesthetic_type_id,
            'overnight_stay' => (bool) $element->overnight_stay,
            'priority_id' => $element->priority_id,
            'priority_name' => $element->priority ? $element->priority->name : null,
            'decision_date' => $element->decision_date,
            'comments' => $element->comments,
            'comments_rtt' => $element->comments_rtt,
            
            // Procedures
            'procedures' => array_map(function($proc) {
                return [
                    'id' => (string) $proc->id,
                    'term' => $proc->term,
                    'short_format' => $proc->short_format,
                    'snomed_code' => $proc->snomed_code,
                ];
            }, $element->procedures),
            
            // Booking details (if booked)
            'booking' => $booking ? [
                'session_date' => $booking->session->date,
                'session_start_time' => $booking->session->start_time,
                'theatre_id' => (string) $booking->session->theatre_id,
                'theatre_name' => $booking->session->theatre->name,
                'admission_time' => $booking->admission_time,
                'ward_id' => $booking->ward_id ? (string) $booking->ward_id : null,
                'confirmed' => (bool) $booking->confirmed,
            ] : null,
            
            // Status
            'status_id' => $element->status_id,
            'status_name' => $element->status ? $element->status->name : null,
            
            'created_date' => $event->created_date,
            'last_modified_date' => $event->last_modified_date,
        ];
        
        return $doc;
    }
    
    /**
     * Find operations by patient
     */
    public static function findByPatient(string $patientId): array
    {
        return self::findByN1QL(
            "SELECT META().id, * FROM `openeyes`.`booking`.`operation` 
             WHERE patient_id = \$patientId 
             ORDER BY decision_date DESC",
            ['patientId' => $patientId]
        );
    }
    
    /**
     * Find pending operations
     */
    public static function findPending(
        string $institutionId = null,
        int $limit = 100
    ): array {
        $query = "SELECT META().id, * FROM `openeyes`.`booking`.`operation` 
                  WHERE booking IS NULL";
        $params = [];
        
        if ($institutionId) {
            // Would need to join with event data or denormalize
        }
        
        $query .= " ORDER BY decision_date ASC LIMIT \$limit";
        $params['limit'] = $limit;
        
        return self::findByN1QL($query, $params);
    }
}
```

#### 5.2.2 Session Document
**File**: `/protected/modules/OphTrOperationbooking/models/couchbase/SessionDocument.php`

```php
<?php
/**
 * Theatre session document for Couchbase
 */

namespace OEModule\OphTrOperationbooking\models;

class SessionDocument extends \CouchbaseActiveRecord
{
    public function documentType(): string
    {
        return 'session';
    }
    
    public function scope(): string
    {
        return 'booking';
    }
    
    public function collectionName(): string
    {
        return 'session';
    }
    
    public static function createFromModel(
        OphTrOperationbooking_Operation_Session $session
    ): self {
        $doc = new self();
        $doc->_pk = $session->id;
        
        $doc->_attributes = [
            'sequence_id' => (string) $session->sequence_id,
            'theatre_id' => (string) $session->theatre_id,
            'theatre_name' => $session->theatre->name,
            'site_id' => (string) $session->theatre->site_id,
            'date' => $session->date,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'default_admission_time' => $session->default_admission_time,
            
            // Firm/consultant
            'firm_id' => $session->firm_id ? (string) $session->firm_id : null,
            'firm_name' => $session->firm ? $session->firm->name : null,
            
            // Capacity
            'max_procedures' => $session->max_procedures,
            'max_complex_bookings' => $session->max_complex_bookings,
            'available' => (bool) $session->available,
            
            // Comments
            'comments' => $session->comments,
        ];
        
        return $doc;
    }
    
    /**
     * Find available sessions
     */
    public static function findAvailable(
        string $theatreId = null,
        string $fromDate = null
    ): array {
        $query = "SELECT META().id, * FROM `openeyes`.`booking`.`session` 
                  WHERE available = true";
        $params = [];
        
        if ($theatreId) {
            $query .= " AND theatre_id = \$theatreId";
            $params['theatreId'] = $theatreId;
        }
        
        if ($fromDate) {
            $query .= " AND date >= \$fromDate";
            $params['fromDate'] = $fromDate;
        }
        
        $query .= " ORDER BY date, start_time";
        
        return self::findByN1QL($query, $params);
    }
}
```

**Acceptance Criteria**:
- [ ] Operation documents include all relevant data
- [ ] Session documents created correctly
- [ ] Queries return expected results

### 5.3 OphCoCorrespondence Module Migration

#### 5.3.1 Letter Document
**File**: `/protected/modules/OphCoCorrespondence/models/couchbase/LetterDocument.php`

```php
<?php
/**
 * Correspondence letter document for Couchbase
 */

namespace OEModule\OphCoCorrespondence\models;

class LetterDocument extends \CouchbaseActiveRecord
{
    public function documentType(): string
    {
        return 'letter';
    }
    
    public function scope(): string
    {
        return 'correspondence';
    }
    
    public function collectionName(): string
    {
        return 'letter';
    }
    
    public static function createFromElement(
        Element_OphCoCorrespondence_Letter $letter
    ): self {
        $doc = new self();
        $doc->_pk = $letter->event_id;
        
        $event = $letter->event;
        
        $doc->_attributes = [
            'event_id' => (string) $letter->event_id,
            'episode_id' => (string) $event->episode_id,
            'patient_id' => (string) $event->episode->patient_id,
            
            // Letter content
            'date' => $letter->date,
            'letter_type_id' => $letter->letter_type_id,
            'letter_type' => $letter->letterType ? $letter->letterType->name : null,
            'site_id' => (string) $letter->site_id,
            
            // Addresses
            'address' => $letter->address,
            're' => $letter->re,
            'introduction' => $letter->introduction,
            'body' => $letter->body,
            'footer' => $letter->footer,
            
            // Recipients
            'recipients' => [],
            
            // Status
            'draft' => (bool) $letter->draft,
            'print' => (bool) $letter->print,
            'locked' => (bool) $letter->locked,
            
            'created_date' => $event->created_date,
        ];
        
        // Add recipients
        foreach ($letter->recipients as $recipient) {
            $doc->_attributes['recipients'][] = [
                'type' => $recipient->recipient_type,
                'contact_name' => $recipient->contact_name,
                'address' => $recipient->address,
            ];
        }
        
        return $doc;
    }
    
    /**
     * Search letters by patient
     */
    public static function findByPatient(
        string $patientId,
        bool $includeDrafts = false
    ): array {
        $query = "SELECT META().id, * FROM `openeyes`.`correspondence`.`letter` 
                  WHERE patient_id = \$patientId";
        $params = ['patientId' => $patientId];
        
        if (!$includeDrafts) {
            $query .= " AND draft = false";
        }
        
        $query .= " ORDER BY date DESC";
        
        return self::findByN1QL($query, $params);
    }
}
```

**Acceptance Criteria**:
- [ ] Letter documents include all content
- [ ] Recipients embedded correctly
- [ ] Search by patient works

### 5.4 Module Sync Command Extension

#### 5.4.1 Extended Sync Command
**File**: `/protected/commands/CouchbaseModuleSyncCommand.php`

```php
<?php
/**
 * Sync module data to Couchbase
 */

class CouchbaseModuleSyncCommand extends CConsoleCommand
{
    /**
     * Module configurations
     */
    private $modules = [
        'OphCiExamination' => [
            'document_class' => 'OEModule\\OphCiExamination\\models\\ExaminationDocument',
            'source_model' => 'Event',
            'source_criteria' => [
                'condition' => 'event_type_id = :etid',
                'params' => [':etid' => null], // Set dynamically
            ],
            'create_method' => 'createFromEvent',
        ],
        'OphTrOperationbooking' => [
            'document_class' => 'OEModule\\OphTrOperationbooking\\models\\OperationDocument',
            'source_model' => 'Element_OphTrOperationbooking_Operation',
            'create_method' => 'createFromElement',
        ],
        'OphCoCorrespondence' => [
            'document_class' => 'OEModule\\OphCoCorrespondence\\models\\LetterDocument',
            'source_model' => 'Element_OphCoCorrespondence_Letter',
            'create_method' => 'createFromElement',
        ],
    ];
    
    public function actionSync($module = null, $batch = 500, $from = null)
    {
        $modulesToSync = $module ? [$module] : array_keys($this->modules);
        
        foreach ($modulesToSync as $moduleName) {
            if (!isset($this->modules[$moduleName])) {
                echo "Unknown module: {$moduleName}\n";
                continue;
            }
            
            $this->syncModule($moduleName, $batch, $from);
        }
    }
    
    private function syncModule($moduleName, $batchSize, $fromId)
    {
        echo "Syncing module: {$moduleName}...\n";
        
        $config = $this->modules[$moduleName];
        $docClass = $config['document_class'];
        $sourceModel = $config['source_model'];
        $createMethod = $config['create_method'];
        
        // Handle examination specially
        if ($moduleName === 'OphCiExamination') {
            $eventType = EventType::model()->findByAttributes([
                'class_name' => 'OphCiExamination'
            ]);
            $config['source_criteria']['params'][':etid'] = $eventType->id;
        }
        
        $criteria = new CDbCriteria();
        $criteria->order = 'id ASC';
        $criteria->limit = $batchSize;
        
        if (isset($config['source_criteria'])) {
            $criteria->condition = $config['source_criteria']['condition'];
            $criteria->params = $config['source_criteria']['params'];
        }
        
        if ($fromId) {
            $criteria->addCondition('id > :fromId');
            $criteria->params[':fromId'] = $fromId;
        }
        
        $total = 0;
        $errors = 0;
        $lastId = $fromId ?? 0;
        
        while (true) {
            $tempCriteria = clone $criteria;
            $tempCriteria->addCondition('id > :lastId');
            $tempCriteria->params[':lastId'] = $lastId;
            
            $records = $sourceModel::model()->findAll($tempCriteria);
            
            if (empty($records)) {
                break;
            }
            
            foreach ($records as $record) {
                $lastId = $record->id;
                
                try {
                    $doc = call_user_func([$docClass, $createMethod], $record);
                    $doc->save();
                    $total++;
                    
                    if ($total % 100 === 0) {
                        echo "  Synced {$total} documents...\n";
                    }
                } catch (\Exception $e) {
                    echo "  Error syncing #{$record->id}: {$e->getMessage()}\n";
                    $errors++;
                }
            }
        }
        
        echo "Completed {$moduleName}: {$total} synced, {$errors} errors\n\n";
    }
    
    public function actionVerify($module = null)
    {
        $modulesToVerify = $module ? [$module] : array_keys($this->modules);
        
        foreach ($modulesToVerify as $moduleName) {
            $this->verifyModule($moduleName);
        }
    }
    
    private function verifyModule($moduleName)
    {
        echo "Verifying {$moduleName}...\n";
        
        $config = $this->modules[$moduleName];
        $docClass = $config['document_class'];
        $sourceModel = $config['source_model'];
        
        // Get MySQL count
        $criteria = new CDbCriteria();
        if (isset($config['source_criteria'])) {
            $criteria->condition = $config['source_criteria']['condition'];
            $criteria->params = $config['source_criteria']['params'];
        }
        $mysqlCount = $sourceModel::model()->count($criteria);
        
        // Get Couchbase count
        $doc = new $docClass();
        $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
        $cbCount = $adapter->count($doc->collectionName());
        
        $match = $mysqlCount === $cbCount ? '✓' : '✗';
        echo "  MySQL: {$mysqlCount}, Couchbase: {$cbCount} {$match}\n";
    }
}
```

**Acceptance Criteria**:
- [ ] Command syncs all configured modules
- [ ] Batch processing works efficiently
- [ ] Verification reports correct counts

### 5.5 Collection Creation for Modules

#### 5.5.1 Module Collections Script
**File**: `/protected/scripts/couchbase/create-module-collections.sh`

```bash
#!/bin/bash
# Create collections for module documents

CB_HOST="${CB_HOST:-localhost}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"
BUCKET="openeyes"

# Clinical collections (OphCiExamination)
curl -s -X POST \
    "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/clinical/collections" \
    -u ${CB_USER}:${CB_PASS} \
    -d name=examination

# Booking collections (OphTrOperationbooking)
BOOKING_COLLECTIONS=("operation" "session" "whiteboard")
for COLL in "${BOOKING_COLLECTIONS[@]}"; do
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/booking/collections" \
        -u ${CB_USER}:${CB_PASS} \
        -d name=${COLL}
done

# Correspondence collections
CORR_COLLECTIONS=("letter" "message" "document")
for COLL in "${CORR_COLLECTIONS[@]}"; do
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/correspondence/collections" \
        -u ${CB_USER}:${CB_PASS} \
        -d name=${COLL}
done

echo "Module collections created"
```

### 5.6 Module Indexes

#### 5.6.1 Module-Specific Indexes
**File**: `/protected/scripts/couchbase/indexes/module-indexes.n1ql`

```sql
-- Examination indexes
CREATE INDEX idx_exam_event ON `openeyes`.`clinical`.`examination`(event_id) USING GSI;
CREATE INDEX idx_exam_patient ON `openeyes`.`clinical`.`examination`(patient_id) USING GSI;
CREATE INDEX idx_exam_date ON `openeyes`.`clinical`.`examination`(event_date DESC) USING GSI;
CREATE INDEX idx_exam_patient_date ON `openeyes`.`clinical`.`examination`(patient_id, event_date DESC) USING GSI;

-- Operation booking indexes
CREATE INDEX idx_op_patient ON `openeyes`.`booking`.`operation`(patient_id) USING GSI;
CREATE INDEX idx_op_status ON `openeyes`.`booking`.`operation`(status_id) USING GSI;
CREATE INDEX idx_op_decision_date ON `openeyes`.`booking`.`operation`(decision_date) USING GSI;
CREATE INDEX idx_op_pending ON `openeyes`.`booking`.`operation`(booking) 
    WHERE booking IS NULL USING GSI;

CREATE INDEX idx_session_date ON `openeyes`.`booking`.`session`(date) USING GSI;
CREATE INDEX idx_session_theatre ON `openeyes`.`booking`.`session`(theatre_id, date) USING GSI;
CREATE INDEX idx_session_available ON `openeyes`.`booking`.`session`(available, date) 
    WHERE available = true USING GSI;

-- Correspondence indexes
CREATE INDEX idx_letter_patient ON `openeyes`.`correspondence`.`letter`(patient_id) USING GSI;
CREATE INDEX idx_letter_date ON `openeyes`.`correspondence`.`letter`(date DESC) USING GSI;
CREATE INDEX idx_letter_draft ON `openeyes`.`correspondence`.`letter`(draft, date DESC) USING GSI;
```

**Acceptance Criteria**:
- [ ] All indexes created successfully
- [ ] Query plans use indexes

## Testing Criteria

### Module Tests
- [ ] ExaminationDocument creates correctly from events
- [ ] OperationDocument includes all booking data
- [ ] LetterDocument includes recipients

### Integration Tests
- [ ] Module sync command works for all modules
- [ ] Data integrity verified
- [ ] Existing module tests pass

### Performance Tests
- [ ] Examination queries perform well
- [ ] Operation queries perform well
- [ ] Correspondence queries perform well

## Rollback Plan

1. Disable dual-write for modules
2. Remove Couchbase document models (optional)
3. Continue using MariaDB

## Definition of Done

- [ ] Priority 1 modules fully migrated
- [ ] Priority 2 modules fully migrated
- [ ] Priority 3 modules fully migrated
- [ ] All module data synced to Couchbase
- [ ] All tests passing
- [ ] Performance acceptable

---

*Phase 5 Completion Sign-off:*
- [ ] Technical Lead
- [ ] QA

*Estimated Duration: 6-8 weeks*
