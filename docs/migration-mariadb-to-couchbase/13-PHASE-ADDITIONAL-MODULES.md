# Phase 13: Additional Clinical Modules Migration

## Overview

This phase migrates remaining clinical modules including Operation Notes, Laser Treatment, Biometry, Prescriptions, Correspondence, and other specialty-specific modules.

**Duration**: 3-4 weeks  
**Priority**: HIGH  
**Complexity**: High (many interconnected modules)

## Prerequisites

- Phases 10-12 completed
- Core models (Patient, Episode, Event) migrated
- Examination module patterns established

## Dependencies

- Phase 5-8: Examination module patterns
- Phase 10: EventType, ElementType migrated
- Phase 11: Procedure, Medication migrated

---

## Section 1: Operation Notes Module (OphTrOperationnote)

### 1.1 Element Models to Migrate

| Element | Table | Priority |
|---------|-------|----------|
| Element_OphTrOperationnote_Surgeon | element_ophtroperationnote_surgeon | HIGH |
| Element_OphTrOperationnote_ProcedureList | element_ophtroperationnote_procedurelist | HIGH |
| Element_OphTrOperationnote_Cataract | element_ophtroperationnote_cataract | HIGH |
| Element_OphTrOperationnote_Anaesthetic | element_ophtroperationnote_anaesthetic | HIGH |
| Element_OphTrOperationnote_Complications | element_ophtroperationnote_complications | HIGH |
| Element_OphTrOperationnote_Comments | element_ophtroperationnote_comments | MEDIUM |
| Element_OphTrOperationnote_GenericProcedure | element_ophtroperationnote_genericprocedure | MEDIUM |

### 1.2 Add CouchbaseElementBridge to Cataract Element

**File**: `protected/modules/OphTrOperationnote/models/Element_OphTrOperationnote_Cataract.php`

```php
<?php
use OE\Models\Traits\CouchbaseElementBridge;

class Element_OphTrOperationnote_Cataract extends BaseEventTypeElement
{
    use CouchbaseElementBridge;

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed IOL details
        if ($this->iol_type_id && $this->iolType) {
            $data['iol_type'] = [
                'id' => (int)$this->iolType->id,
                'name' => $this->iolType->name,
                'display_name' => $this->iolType->display_name,
            ];
        }
        
        // Embed incision details
        if ($this->incision_site_id && $this->incisionSite) {
            $data['incision_site'] = [
                'id' => (int)$this->incisionSite->id,
                'name' => $this->incisionSite->name,
            ];
        }
        
        if ($this->incision_type_id && $this->incisionType) {
            $data['incision_type'] = [
                'id' => (int)$this->incisionType->id,
                'name' => $this->incisionType->name,
            ];
        }
        
        // Embed viscoelastic details
        if (!empty($this->viscoelastic_assignments)) {
            $data['viscoelastics'] = array_map(function($va) {
                return [
                    'id' => (int)$va->ophtrop_viscoelastic_id,
                    'name' => $va->viscoelastic ? $va->viscoelastic->name : null,
                ];
            }, $this->viscoelastic_assignments);
        }
        
        // Embed operative devices
        if (!empty($this->operative_device_assignments)) {
            $data['operative_devices'] = array_map(function($od) {
                return [
                    'id' => (int)$od->operative_device_id,
                    'name' => $od->operativeDevice ? $od->operativeDevice->name : null,
                ];
            }, $this->operative_device_assignments);
        }
        
        return $data;
    }
}
```

**Lines to add**: ~60

### 1.3 Add CouchbaseElementBridge to ProcedureList Element

**File**: `protected/modules/OphTrOperationnote/models/Element_OphTrOperationnote_ProcedureList.php`

```php
<?php
use OE\Models\Traits\CouchbaseElementBridge;

class Element_OphTrOperationnote_ProcedureList extends BaseEventTypeElement
{
    use CouchbaseElementBridge;

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed eye
        if ($this->eye) {
            $data['eye'] = [
                'id' => (int)$this->eye->id,
                'name' => $this->eye->name,
            ];
        }
        
        // Embed all procedures
        $procedures = [];
        foreach ($this->procedures as $proc) {
            $procedures[] = [
                'id' => (int)$proc->id,
                'term' => $proc->term,
                'snomed_code' => $proc->snomed_code,
            ];
        }
        $data['procedures'] = $procedures;
        
        // Embed booking event reference
        if ($this->booking_event_id && $this->bookingEvent) {
            $data['booking_event'] = [
                'id' => (int)$this->bookingEvent->id,
                'event_date' => $this->bookingEvent->event_date,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~45

---

## Section 2: Laser Treatment Module (OphTrLaser)

### 2.1 Element Models to Migrate

| Element | Priority |
|---------|----------|
| Element_OphTrLaser_Treatment | HIGH |
| Element_OphTrLaser_Site | HIGH |
| Element_OphTrLaser_AnteriorSegment | MEDIUM |
| Element_OphTrLaser_PosteriorPole | MEDIUM |

### 2.2 Add CouchbaseElementBridge to Laser Treatment

**File**: `protected/modules/OphTrLaser/models/Element_OphTrLaser_Treatment.php`

```php
<?php
use OE\Models\Traits\CouchbaseElementBridge;

class Element_OphTrLaser_Treatment extends BaseEventTypeElement
{
    use CouchbaseElementBridge;

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed laser type
        if ($this->laser_id && $this->laser) {
            $data['laser'] = [
                'id' => (int)$this->laser->id,
                'name' => $this->laser->name,
                'type' => $this->laser->type,
            ];
        }
        
        // Embed operator
        if ($this->operator_id && $this->operator) {
            $data['operator'] = [
                'id' => (int)$this->operator->id,
                'name' => $this->operator->getFullName(),
            ];
        }
        
        // Embed treatment entries
        $entries = [];
        foreach ($this->entries as $entry) {
            $entries[] = [
                'eye_id' => (int)$entry->eye_id,
                'eye_name' => $entry->eye ? $entry->eye->name : null,
                'procedure_id' => (int)$entry->procedure_id,
                'procedure_name' => $entry->procedure ? $entry->procedure->term : null,
            ];
        }
        $data['treatment_entries'] = $entries;
        
        return $data;
    }
}
```

**Lines to add**: ~50

---

## Section 3: Biometry Module (OphInBiometry)

### 3.1 Element Models to Migrate

| Element | Priority |
|---------|----------|
| Element_OphInBiometry_Measurement | HIGH |
| Element_OphInBiometry_Calculation | HIGH |
| Element_OphInBiometry_Selection | HIGH |
| Element_OphInBiometry_IolRefValues | MEDIUM |

### 3.2 Add CouchbaseElementBridge to Biometry Measurement

**File**: `protected/modules/OphInBiometry/models/Element_OphInBiometry_Measurement.php`

```php
<?php
use OE\Models\Traits\CouchbaseElementBridge;

class Element_OphInBiometry_Measurement extends BaseEventTypeElement
{
    use CouchbaseElementBridge;

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed device info
        if ($this->device_id && $this->device) {
            $data['device'] = [
                'id' => (int)$this->device->id,
                'name' => $this->device->name,
                'model' => $this->device->model,
            ];
        }
        
        // Embed all measurements
        $data['measurements'] = [
            'axial_length_left' => $this->axial_length_left,
            'axial_length_right' => $this->axial_length_right,
            'k1_left' => $this->k1_left,
            'k1_right' => $this->k1_right,
            'k2_left' => $this->k2_left,
            'k2_right' => $this->k2_right,
            'acd_left' => $this->acd_left,
            'acd_right' => $this->acd_right,
            'snr_left' => $this->snr_left,
            'snr_right' => $this->snr_right,
        ];
        
        return $data;
    }
}
```

**Lines to add**: ~45

---

## Section 4: Prescription Module (OphDrPrescription)

### 4.1 Element Models to Migrate

| Element | Priority |
|---------|----------|
| Element_OphDrPrescription_Details | HIGH |
| OphDrPrescription_Item | HIGH |

### 4.2 Add CouchbaseElementBridge to Prescription Details

**File**: `protected/modules/OphDrPrescription/models/Element_OphDrPrescription_Details.php`

```php
<?php
use OE\Models\Traits\CouchbaseElementBridge;

class Element_OphDrPrescription_Details extends BaseEventTypeElement
{
    use CouchbaseElementBridge;

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed all prescription items
        $items = [];
        foreach ($this->items as $item) {
            $itemData = [
                'id' => (int)$item->id,
                'medication_id' => $item->medication_id ? (int)$item->medication_id : null,
                'dose' => $item->dose,
                'frequency_id' => $item->frequency_id ? (int)$item->frequency_id : null,
                'route_id' => $item->route_id ? (int)$item->route_id : null,
                'duration_id' => $item->duration_id ? (int)$item->duration_id : null,
                'dispense_condition_id' => $item->dispense_condition_id ? (int)$item->dispense_condition_id : null,
                'dispense_location_id' => $item->dispense_location_id ? (int)$item->dispense_location_id : null,
                'start_date' => $item->start_date,
                'end_date' => $item->end_date,
                'continue_by_gp' => (bool)$item->continue_by_gp,
            ];
            
            // Embed medication details
            if ($item->medication) {
                $itemData['medication'] = [
                    'id' => (int)$item->medication->id,
                    'preferred_term' => $item->medication->preferred_term,
                    'preferred_code' => $item->medication->preferred_code,
                ];
            }
            
            // Embed frequency
            if ($item->frequency) {
                $itemData['frequency'] = [
                    'id' => (int)$item->frequency->id,
                    'term' => $item->frequency->term,
                ];
            }
            
            // Embed route
            if ($item->route) {
                $itemData['route'] = [
                    'id' => (int)$item->route->id,
                    'term' => $item->route->term,
                ];
            }
            
            $items[] = $itemData;
        }
        $data['prescription_items'] = $items;
        
        // Print status
        $data['print_status'] = $this->printed ? 'Printed' : 'Not Printed';
        $data['printed_date'] = $this->printed_date;
        
        return $data;
    }
}
```

**Lines to add**: ~70

---

## Section 5: Correspondence Module (OphCoCorrespondence)

### 5.1 Element Models to Migrate

| Element | Priority |
|---------|----------|
| ElementLetter | HIGH |
| OphCoCorrespondence_EnclosureItem | MEDIUM |
| Document | MEDIUM |

### 5.2 Add CouchbaseElementBridge to ElementLetter

**File**: `protected/modules/OphCoCorrespondence/models/ElementLetter.php`

```php
// Add to existing ElementLetter class
use OE\Models\Traits\CouchbaseElementBridge;

// In class:
use CouchbaseElementBridge;

protected function getEmbeddedRelations()
{
    $data = [];
    
    // Embed letter type
    if ($this->letter_type_id && $this->letterType) {
        $data['letter_type'] = [
            'id' => (int)$this->letterType->id,
            'name' => $this->letterType->name,
        ];
    }
    
    // Embed recipients
    $recipients = [];
    if (!empty($this->to_address)) {
        $recipients['to'] = $this->to_address;
    }
    if (!empty($this->cc_targets)) {
        $recipients['cc'] = array_map(function($cc) {
            return [
                'contact_type' => $cc->contact_type,
                'contact_name' => $cc->contact_name,
                'address' => $cc->address,
            ];
        }, $this->cc_targets);
    }
    $data['recipients'] = $recipients;
    
    // Embed enclosures
    if (!empty($this->enclosures)) {
        $data['enclosures'] = array_map(function($enc) {
            return [
                'id' => (int)$enc->id,
                'content' => $enc->content,
            ];
        }, $this->enclosures);
    }
    
    // Embed associated documents
    if ($this->document_output) {
        $data['document_output'] = [
            'id' => (int)$this->document_output->id,
            'output_type' => $this->document_output->output_type,
            'output_status' => $this->document_output->output_status,
        ];
    }
    
    // Status tracking
    $data['draft'] = (bool)$this->draft;
    $data['print_all'] = (bool)$this->print_all;
    
    return $data;
}
```

**Lines to add**: ~60

---

## Section 6: Operation Booking Module (OphTrOperationbooking)

### 6.1 Element Models to Migrate

| Element | Priority |
|---------|----------|
| Element_OphTrOperationbooking_Operation | HIGH |
| Element_OphTrOperationbooking_Diagnosis | HIGH |
| Element_OphTrOperationbooking_ScheduleOperation | HIGH |
| OphTrOperationbooking_Operation_Booking | HIGH |

### 6.2 Add CouchbaseElementBridge to Operation Booking

**File**: `protected/modules/OphTrOperationbooking/models/Element_OphTrOperationbooking_Operation.php`

```php
// Add to existing class
use OE\Models\Traits\CouchbaseElementBridge;

// In class:
use CouchbaseElementBridge;

protected function getEmbeddedRelations()
{
    $data = [];
    
    // Embed eye
    if ($this->eye) {
        $data['eye'] = [
            'id' => (int)$this->eye->id,
            'name' => $this->eye->name,
        ];
    }
    
    // Embed procedures
    $procedures = [];
    foreach ($this->procedures as $proc) {
        $procedures[] = [
            'id' => (int)$proc->id,
            'term' => $proc->term,
            'snomed_code' => $proc->snomed_code,
            'default_duration' => $proc->default_duration,
        ];
    }
    $data['procedures'] = $procedures;
    
    // Embed priority
    if ($this->priority) {
        $data['priority'] = [
            'id' => (int)$this->priority->id,
            'name' => $this->priority->name,
        ];
    }
    
    // Embed status
    if ($this->status) {
        $data['status'] = [
            'id' => (int)$this->status->id,
            'name' => $this->status->name,
        ];
    }
    
    // Embed booking info if scheduled
    if ($this->booking) {
        $data['booking'] = [
            'id' => (int)$this->booking->id,
            'session_date' => $this->booking->session ? $this->booking->session->date : null,
            'session_start_time' => $this->booking->session ? $this->booking->session->start_time : null,
            'theatre' => $this->booking->session && $this->booking->session->theatre ? 
                $this->booking->session->theatre->name : null,
            'admission_time' => $this->booking->admission_time,
            'display_order' => (int)$this->booking->display_order,
        ];
    }
    
    // Embed cancellation info if cancelled
    if ($this->cancellation_date) {
        $data['cancellation'] = [
            'date' => $this->cancellation_date,
            'reason_id' => $this->cancellation_reason_id ? (int)$this->cancellation_reason_id : null,
            'reason' => $this->cancellationReason ? $this->cancellationReason->text : null,
            'user_id' => $this->cancellation_user_id ? (int)$this->cancellation_user_id : null,
            'comment' => $this->cancellation_comment,
        ];
    }
    
    return $data;
}
```

**Lines to add**: ~75

---

## Section 7: CVI Module (OphCoCvi)

### 7.1 Element Models to Migrate

| Element | Priority |
|---------|----------|
| Element_OphCoCvi_EventInfo | HIGH |
| Element_OphCoCvi_ClinicalInfo | HIGH |
| Element_OphCoCvi_ClericalInfo | MEDIUM |
| Element_OphCoCvi_ConsentSignature | MEDIUM |

### 7.2 Add CouchbaseElementBridge to CVI EventInfo

**File**: `protected/modules/OphCoCvi/models/Element_OphCoCvi_EventInfo.php`

```php
<?php
use OE\Models\Traits\CouchbaseElementBridge;

class Element_OphCoCvi_EventInfo extends BaseEventTypeElement
{
    use CouchbaseElementBridge;

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed consultant
        if ($this->consultant_id && $this->consultant) {
            $data['consultant'] = [
                'id' => (int)$this->consultant->id,
                'name' => $this->consultant->getFullName(),
            ];
        }
        
        // Embed examining doctor
        if ($this->examining_doctor_id && $this->examiningDoctor) {
            $data['examining_doctor'] = [
                'id' => (int)$this->examiningDoctor->id,
                'name' => $this->examiningDoctor->getFullName(),
            ];
        }
        
        // Status info
        $data['is_draft'] = (bool)$this->is_draft;
        $data['generated_document_id'] = $this->generated_document_id ? 
            (int)$this->generated_document_id : null;
        
        return $data;
    }
}
```

**Lines to add**: ~40

---

## Section 8: Module Migration Command

### 8.1 Create Module Migration Command

**File**: `protected/commands/ModuleMigrationCommand.php`

```php
<?php
/**
 * Command to migrate clinical module elements to Couchbase
 */

class ModuleMigrationCommand extends CConsoleCommand
{
    protected $modules = [
        'OphTrOperationnote' => [
            'elements' => [
                'Element_OphTrOperationnote_Surgeon',
                'Element_OphTrOperationnote_ProcedureList',
                'Element_OphTrOperationnote_Cataract',
                'Element_OphTrOperationnote_Anaesthetic',
                'Element_OphTrOperationnote_Complications',
                'Element_OphTrOperationnote_Comments',
                'Element_OphTrOperationnote_GenericProcedure',
            ],
        ],
        'OphTrLaser' => [
            'elements' => [
                'Element_OphTrLaser_Treatment',
                'Element_OphTrLaser_Site',
                'Element_OphTrLaser_AnteriorSegment',
                'Element_OphTrLaser_PosteriorPole',
            ],
        ],
        'OphInBiometry' => [
            'elements' => [
                'Element_OphInBiometry_Measurement',
                'Element_OphInBiometry_Calculation',
                'Element_OphInBiometry_Selection',
            ],
        ],
        'OphDrPrescription' => [
            'elements' => [
                'Element_OphDrPrescription_Details',
            ],
        ],
        'OphCoCorrespondence' => [
            'elements' => [
                'ElementLetter',
            ],
        ],
        'OphTrOperationbooking' => [
            'elements' => [
                'Element_OphTrOperationbooking_Operation',
                'Element_OphTrOperationbooking_Diagnosis',
                'Element_OphTrOperationbooking_ScheduleOperation',
            ],
        ],
        'OphCoCvi' => [
            'elements' => [
                'Element_OphCoCvi_EventInfo',
                'Element_OphCoCvi_ClinicalInfo',
                'Element_OphCoCvi_ClericalInfo',
            ],
        ],
    ];

    public function actionMigrate($module = null, $batch = 500, $verbose = false)
    {
        echo "===========================================\n";
        echo "Phase 13: Clinical Modules Migration\n";
        echo "===========================================\n\n";

        $modules = $module ? [$module => $this->modules[$module]] : $this->modules;
        
        foreach ($modules as $moduleName => $config) {
            echo "Module: {$moduleName}\n";
            echo str_repeat('-', 40) . "\n";
            
            foreach ($config['elements'] as $elementClass) {
                $this->migrateElement($elementClass, $batch, $verbose);
            }
            echo "\n";
        }
    }

    protected function migrateElement($elementClass, $batch, $verbose)
    {
        echo "  Element: {$elementClass}\n";
        
        if (!class_exists($elementClass)) {
            echo "    ERROR: Class not found\n";
            return;
        }
        
        $total = $elementClass::model()->count();
        echo "    Records: {$total}\n";
        
        if ($total === 0) {
            return;
        }
        
        $migrated = 0;
        $errors = 0;
        $offset = 0;
        
        $adapter = Yii::app()->couchbase;
        
        while ($offset < $total) {
            $elements = $elementClass::model()->findAll([
                'limit' => $batch,
                'offset' => $offset,
            ]);
            
            foreach ($elements as $element) {
                try {
                    if (method_exists($element, 'toCouchbaseDocument')) {
                        $doc = $element->toCouchbaseDocument();
                        $key = $element->getTableSchema()->name . '::' . $element->id;
                        $adapter->upsert('clinical', $element->getTableSchema()->name, $key, $doc);
                        $migrated++;
                    } else {
                        // Fallback: basic document without embeddings
                        $doc = $element->attributes;
                        $doc['_type'] = $elementClass;
                        $key = $element->getTableSchema()->name . '::' . $element->id;
                        $adapter->upsert('clinical', $element->getTableSchema()->name, $key, $doc);
                        $migrated++;
                    }
                } catch (Exception $e) {
                    $errors++;
                    if ($verbose) {
                        echo "      ERROR [{$element->id}]: {$e->getMessage()}\n";
                    }
                }
            }
            
            $offset += $batch;
        }
        
        echo "    Migrated: {$migrated}, Errors: {$errors}\n";
    }

    public function actionStatus()
    {
        echo "Clinical Modules Migration Status\n";
        echo "==================================\n\n";
        
        foreach ($this->modules as $moduleName => $config) {
            echo "{$moduleName}:\n";
            
            foreach ($config['elements'] as $elementClass) {
                if (!class_exists($elementClass)) {
                    echo "  {$elementClass}: NOT FOUND\n";
                    continue;
                }
                
                $count = $elementClass::model()->count();
                $hasBridge = in_array('CouchbaseElementBridge', 
                    class_uses($elementClass) ?: []);
                
                $status = $hasBridge ? '✓' : '✗';
                printf("  %-50s %6d records [%s]\n", $elementClass, $count, $status);
            }
            echo "\n";
        }
    }
}
```

**Lines**: ~150

---

## Section 9: N1QL Indexes for Modules

**File**: `protected/scripts/couchbase/indexes/module-indexes.n1ql`

```sql
-- =====================================================
-- Phase 13: Clinical Module Indexes
-- =====================================================

-- Operation Note Indexes
CREATE INDEX idx_opnote_cataract_event 
ON `openeyes`.`clinical`.`element_ophtroperationnote_cataract`(event_id);

CREATE INDEX idx_opnote_procedure_event 
ON `openeyes`.`clinical`.`element_ophtroperationnote_procedurelist`(event_id);

CREATE INDEX idx_opnote_iol_type 
ON `openeyes`.`clinical`.`element_ophtroperationnote_cataract`(iol_type.id);

-- Laser Indexes
CREATE INDEX idx_laser_treatment_event 
ON `openeyes`.`clinical`.`element_ophtrlaser_treatment`(event_id);

CREATE INDEX idx_laser_procedure 
ON `openeyes`.`clinical`.`element_ophtrlaser_treatment`(
    DISTINCT ARRAY e.procedure_id FOR e IN treatment_entries END
);

-- Biometry Indexes
CREATE INDEX idx_biometry_event 
ON `openeyes`.`clinical`.`element_ophinbiometry_measurement`(event_id);

-- Prescription Indexes
CREATE INDEX idx_prescription_event 
ON `openeyes`.`clinical`.`element_ophdrprescription_details`(event_id);

CREATE INDEX idx_prescription_medication 
ON `openeyes`.`clinical`.`element_ophdrprescription_details`(
    DISTINCT ARRAY i.medication_id FOR i IN prescription_items END
);

-- Correspondence Indexes
CREATE INDEX idx_letter_event 
ON `openeyes`.`clinical`.`element_letter`(event_id);

CREATE INDEX idx_letter_type 
ON `openeyes`.`clinical`.`element_letter`(letter_type.id);

CREATE INDEX idx_letter_draft 
ON `openeyes`.`clinical`.`element_letter`(draft) 
WHERE draft = true;

-- Operation Booking Indexes
CREATE INDEX idx_booking_event 
ON `openeyes`.`clinical`.`element_ophtroperationbooking_operation`(event_id);

CREATE INDEX idx_booking_status 
ON `openeyes`.`clinical`.`element_ophtroperationbooking_operation`(status.id);

CREATE INDEX idx_booking_date 
ON `openeyes`.`clinical`.`element_ophtroperationbooking_operation`(booking.session_date);

-- CVI Indexes
CREATE INDEX idx_cvi_event 
ON `openeyes`.`clinical`.`element_ophcocvi_eventinfo`(event_id);

CREATE INDEX idx_cvi_draft 
ON `openeyes`.`clinical`.`element_ophcocvi_eventinfo`(is_draft);
```

**Lines**: ~60

---

## Section 10: Validation & Success Criteria

### 10.1 Validation Checklist

```markdown
## Phase 13 Validation Checklist

### Operation Notes
- [ ] Cataract elements with IOL details embedded
- [ ] Procedure lists with SNOMED codes
- [ ] Complications properly linked
- [ ] All 7 element types migrated

### Laser Treatment
- [ ] Treatment entries with procedures embedded
- [ ] Laser device info embedded
- [ ] Eye laterality preserved

### Biometry
- [ ] All measurements preserved
- [ ] Device info embedded
- [ ] Left/Right values correctly mapped

### Prescriptions
- [ ] All items with medication embedded
- [ ] Route/Frequency/Duration embedded
- [ ] Print status preserved

### Correspondence
- [ ] Letters with recipients embedded
- [ ] Enclosures linked
- [ ] Document output tracked

### Operation Booking
- [ ] Booking with session info embedded
- [ ] Procedures with durations
- [ ] Status tracking accurate
- [ ] Cancellation info preserved
```

### 10.2 Success Criteria

| Module | Elements | Target Migration |
|--------|----------|------------------|
| OphTrOperationnote | 7 | 100% |
| OphTrLaser | 4 | 100% |
| OphInBiometry | 3 | 100% |
| OphDrPrescription | 1 | 100% |
| OphCoCorrespondence | 1 | 100% |
| OphTrOperationbooking | 3 | 100% |
| OphCoCvi | 3 | 100% |

---

## Summary

### Files to Create
| File | Lines | Purpose |
|------|-------|---------|
| ModuleMigrationCommand.php | 150 | Migration command |
| module-indexes.n1ql | 60 | N1QL indexes |

### Files to Modify
| File | Changes |
|------|---------|
| Element_OphTrOperationnote_Cataract.php | +60 lines |
| Element_OphTrOperationnote_ProcedureList.php | +45 lines |
| Element_OphTrLaser_Treatment.php | +50 lines |
| Element_OphInBiometry_Measurement.php | +45 lines |
| Element_OphDrPrescription_Details.php | +70 lines |
| ElementLetter.php | +60 lines |
| Element_OphTrOperationbooking_Operation.php | +75 lines |
| Element_OphCoCvi_EventInfo.php | +40 lines |

### Total Effort
- **New Files**: 2 files (~210 lines)
- **Modified Files**: 8 files (~445 lines)
- **Total Code**: ~655 lines
- **Estimated Duration**: 3-4 weeks

---

**Phase 13 Status**: SPECIFICATION COMPLETE  
**Ready for Implementation**: YES
