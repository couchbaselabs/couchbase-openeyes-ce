# Phase 10: Core Lookup Tables Migration

## Overview

This phase migrates essential lookup and reference tables that all modules depend on. These tables contain system-wide configuration data like event types, element types, sites, institutions, and contact information.

**Duration**: 2-3 weeks  
**Priority**: CRITICAL  
**Complexity**: Medium-High

## Prerequisites

- Phase 1-9 completed
- Couchbase cluster running with `openeyes` bucket
- Scopes created: `core`, `reference`, `admin`
- CouchbaseModelBridge trait available
- DatabaseAdapterFactory configured

## Dependencies

- Phase 4: Core model patterns (Patient, User, Episode, Event)
- Phase 5: Module element patterns
- Phase 8: Data migration commands

---

## Section 1: EventType & ElementType Migration

### 1.1 Create EventType Couchbase Support

**File**: `protected/models/EventType.php`

**Task**: Add CouchbaseModelBridge trait to EventType model.

```php
<?php
// Add at top of file after namespace/use statements
use OE\Models\Traits\CouchbaseModelBridge;

class EventType extends BaseActiveRecordVersioned
{
    use HasFactory;
    use CouchbaseModelBridge;  // ADD THIS LINE

    /**
     * Get the Couchbase scope for this model
     * @return string
     */
    public function couchbaseScope()
    {
        return 'reference';
    }

    /**
     * Get the Couchbase collection name
     * @return string
     */
    public function couchbaseCollection()
    {
        return 'event_type';
    }

    // ... rest of existing code
}
```

**Lines to add**: ~20

---

### 1.2 Create EventTypeDocument Model

**File**: `protected/models/couchbase/EventTypeDocument.php`

```php
<?php
/**
 * Couchbase document model for EventType
 */

class EventTypeDocument extends CouchbaseActiveRecord
{
    /**
     * @var string Document type identifier
     */
    protected $documentType = 'event_type';

    /**
     * @var string Couchbase scope
     */
    protected $scope = 'reference';

    /**
     * @var string Couchbase collection
     */
    protected $collection = 'event_type';

    /**
     * Create document from EventType model
     * @param EventType $eventType
     * @return array
     */
    public static function createFromModel($eventType)
    {
        return [
            '_type' => 'event_type',
            'id' => (int)$eventType->id,
            'name' => $eventType->name,
            'class_name' => $eventType->class_name,
            'event_group_id' => $eventType->event_group_id ? (int)$eventType->event_group_id : null,
            'support_services' => (bool)$eventType->support_services,
            'disabled' => (bool)$eventType->disabled,
            'custom_hint_text' => $eventType->custom_hint_text,
            'hint_position' => $eventType->hint_position,
            'created_date' => $eventType->created_date,
            'last_modified_date' => $eventType->last_modified_date,
            // Embed related data
            'event_group' => $eventType->eventGroup ? [
                'id' => (int)$eventType->eventGroup->id,
                'name' => $eventType->eventGroup->name,
                'code' => $eventType->eventGroup->code,
            ] : null,
            // Embed element types for this event
            'element_types' => self::embedElementTypes($eventType),
        ];
    }

    /**
     * Embed element types for quick access
     * @param EventType $eventType
     * @return array
     */
    protected static function embedElementTypes($eventType)
    {
        $elementTypes = [];
        foreach ($eventType->elementTypes as $et) {
            $elementTypes[] = [
                'id' => (int)$et->id,
                'name' => $et->name,
                'class_name' => $et->class_name,
                'display_order' => (int)$et->display_order,
                'required' => (bool)$et->required,
                'default' => (bool)$et->default,
            ];
        }
        return $elementTypes;
    }

    /**
     * Find by class name
     * @param string $className
     * @return array|null
     */
    public static function findByClassName($className)
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`event_type` e 
                  WHERE e.class_name = \$className";
        
        $result = self::executeQuery($query, ['className' => $className]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find all active event types
     * @return array
     */
    public static function findAllActive()
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`event_type` e 
                  WHERE e.disabled = false 
                  ORDER BY e.name";
        
        return self::executeQuery($query);
    }

    /**
     * Find by event group
     * @param int $groupId
     * @return array
     */
    public static function findByGroup($groupId)
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`event_type` e 
                  WHERE e.event_group_id = \$groupId 
                  ORDER BY e.name";
        
        return self::executeQuery($query, ['groupId' => $groupId]);
    }
}
```

**Lines**: ~120

---

### 1.3 Create ElementType Couchbase Support

**File**: `protected/models/ElementType.php`

**Task**: Add CouchbaseModelBridge trait.

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class ElementType extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'element_type';
    }

    // ... existing code
}
```

**Lines to add**: ~15

---

### 1.4 Create ElementTypeDocument Model

**File**: `protected/models/couchbase/ElementTypeDocument.php`

```php
<?php
/**
 * Couchbase document model for ElementType
 */

class ElementTypeDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'element_type';
    protected $scope = 'reference';
    protected $collection = 'element_type';

    /**
     * Create document from ElementType model
     * @param ElementType $elementType
     * @return array
     */
    public static function createFromModel($elementType)
    {
        return [
            '_type' => 'element_type',
            'id' => (int)$elementType->id,
            'name' => $elementType->name,
            'class_name' => $elementType->class_name,
            'event_type_id' => (int)$elementType->event_type_id,
            'display_order' => (int)$elementType->display_order,
            'required' => (bool)$elementType->required,
            'default' => (bool)$elementType->default,
            'parent_element_type_id' => $elementType->parent_element_type_id ? (int)$elementType->parent_element_type_id : null,
            'element_group_id' => $elementType->element_group_id ? (int)$elementType->element_group_id : null,
            'created_date' => $elementType->created_date,
            'last_modified_date' => $elementType->last_modified_date,
            // Embed element group
            'element_group' => $elementType->elementGroup ? [
                'id' => (int)$elementType->elementGroup->id,
                'name' => $elementType->elementGroup->name,
                'display_order' => (int)$elementType->elementGroup->display_order,
            ] : null,
            // Embed event type reference
            'event_type' => $elementType->eventType ? [
                'id' => (int)$elementType->eventType->id,
                'name' => $elementType->eventType->name,
                'class_name' => $elementType->eventType->class_name,
            ] : null,
        ];
    }

    /**
     * Find by event type ID
     * @param int $eventTypeId
     * @return array
     */
    public static function findByEventType($eventTypeId)
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`element_type` e 
                  WHERE e.event_type_id = \$eventTypeId 
                  ORDER BY e.display_order";
        
        return self::executeQuery($query, ['eventTypeId' => $eventTypeId]);
    }

    /**
     * Find by class name
     * @param string $className
     * @return array|null
     */
    public static function findByClassName($className)
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`element_type` e 
                  WHERE e.class_name = \$className";
        
        $result = self::executeQuery($query, ['className' => $className]);
        return !empty($result) ? $result[0] : null;
    }
}
```

**Lines**: ~90

---

### 1.5 Create ElementGroup Couchbase Support

**File**: `protected/models/ElementGroup.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class ElementGroup extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'element_group';
    }
}
```

**Lines to add**: ~15

---

## Section 2: Organization Tables (Site, Institution, Firm)

### 2.1 Create Site Couchbase Support

**File**: `protected/models/Site.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Site extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'core';
    }

    public function couchbaseCollection()
    {
        return 'site';
    }

    /**
     * Get embedded relations for Couchbase document
     * @return array
     */
    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed institution
        if ($this->institution) {
            $data['institution'] = [
                'id' => (int)$this->institution->id,
                'name' => $this->institution->name,
                'short_name' => $this->institution->short_name,
                'remote_id' => $this->institution->remote_id,
            ];
        }
        
        // Embed contact/address
        if ($this->contact) {
            $data['contact'] = [
                'id' => (int)$this->contact->id,
                'primary_phone' => $this->contact->primary_phone,
                'address' => $this->contact->address ? [
                    'address1' => $this->contact->address->address1,
                    'address2' => $this->contact->address->address2,
                    'city' => $this->contact->address->city,
                    'postcode' => $this->contact->address->postcode,
                    'county' => $this->contact->address->county,
                    'country' => $this->contact->address->country ? $this->contact->address->country->name : null,
                ] : null,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~55

---

### 2.2 Create SiteDocument Model

**File**: `protected/models/couchbase/SiteDocument.php`

```php
<?php
/**
 * Couchbase document model for Site
 */

class SiteDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'site';
    protected $scope = 'core';
    protected $collection = 'site';

    /**
     * Create document from Site model
     * @param Site $site
     * @return array
     */
    public static function createFromModel($site)
    {
        $doc = [
            '_type' => 'site',
            'id' => (int)$site->id,
            'name' => $site->name,
            'short_name' => $site->short_name,
            'remote_id' => $site->remote_id,
            'institution_id' => (int)$site->institution_id,
            'location' => $site->location,
            'telephone' => $site->telephone,
            'fax' => $site->fax,
            'active' => (bool)$site->active,
            'created_date' => $site->created_date,
            'last_modified_date' => $site->last_modified_date,
        ];
        
        // Embed institution
        if ($site->institution) {
            $doc['institution'] = [
                'id' => (int)$site->institution->id,
                'name' => $site->institution->name,
                'short_name' => $site->institution->short_name,
            ];
        }
        
        // Embed contact with address
        if ($site->contact) {
            $doc['contact'] = self::embedContact($site->contact);
        }
        
        return $doc;
    }

    /**
     * Embed contact data
     * @param Contact $contact
     * @return array
     */
    protected static function embedContact($contact)
    {
        $data = [
            'id' => (int)$contact->id,
            'nick_name' => $contact->nick_name,
            'primary_phone' => $contact->primary_phone,
            'email' => $contact->email,
        ];
        
        if ($contact->address) {
            $data['address'] = [
                'address1' => $contact->address->address1,
                'address2' => $contact->address->address2,
                'city' => $contact->address->city,
                'postcode' => $contact->address->postcode,
                'county' => $contact->address->county,
                'country_id' => $contact->address->country_id,
            ];
        }
        
        return $data;
    }

    /**
     * Find all active sites
     * @return array
     */
    public static function findAllActive()
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`core`.`site` s 
                  WHERE s.active = true 
                  ORDER BY s.name";
        
        return self::executeQuery($query);
    }

    /**
     * Find by institution
     * @param int $institutionId
     * @return array
     */
    public static function findByInstitution($institutionId)
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`core`.`site` s 
                  WHERE s.institution_id = \$institutionId 
                  AND s.active = true 
                  ORDER BY s.name";
        
        return self::executeQuery($query, ['institutionId' => $institutionId]);
    }

    /**
     * Search sites by name
     * @param string $term
     * @param int $limit
     * @return array
     */
    public static function search($term, $limit = 20)
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`core`.`site` s 
                  WHERE LOWER(s.name) LIKE \$term 
                  AND s.active = true 
                  ORDER BY s.name 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }
}
```

**Lines**: ~130

---

### 2.3 Create Institution Couchbase Support

**File**: `protected/models/Institution.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Institution extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'core';
    }

    public function couchbaseCollection()
    {
        return 'institution';
    }

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed contact/address
        if ($this->contact) {
            $data['contact'] = [
                'id' => (int)$this->contact->id,
                'primary_phone' => $this->contact->primary_phone,
                'address' => $this->contact->address ? [
                    'address1' => $this->contact->address->address1,
                    'address2' => $this->contact->address->address2,
                    'city' => $this->contact->address->city,
                    'postcode' => $this->contact->address->postcode,
                ] : null,
            ];
        }
        
        // Count sites
        $data['site_count'] = count($this->sites ?? []);
        
        return $data;
    }
}
```

**Lines to add**: ~45

---

### 2.4 Create Firm Couchbase Support

**File**: `protected/models/Firm.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Firm extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'core';
    }

    public function couchbaseCollection()
    {
        return 'firm';
    }

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed service subspecialty assignment
        if ($this->serviceSubspecialtyAssignment) {
            $ssa = $this->serviceSubspecialtyAssignment;
            $data['service_subspecialty'] = [
                'id' => (int)$ssa->id,
                'service_id' => (int)$ssa->service_id,
                'subspecialty_id' => (int)$ssa->subspecialty_id,
                'subspecialty' => $ssa->subspecialty ? [
                    'id' => (int)$ssa->subspecialty->id,
                    'name' => $ssa->subspecialty->name,
                    'ref_spec' => $ssa->subspecialty->ref_spec,
                ] : null,
            ];
        }
        
        // Embed consultant user
        if ($this->consultant) {
            $data['consultant'] = [
                'id' => (int)$this->consultant->id,
                'username' => $this->consultant->username,
                'first_name' => $this->consultant->first_name,
                'last_name' => $this->consultant->last_name,
                'title' => $this->consultant->title,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~55

---

## Section 3: Contact & Address Tables

### 3.1 Create Contact Couchbase Support

**File**: `protected/models/Contact.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Contact extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'core';
    }

    public function couchbaseCollection()
    {
        return 'contact';
    }

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed primary address
        if ($this->address) {
            $data['address'] = [
                'id' => (int)$this->address->id,
                'address1' => $this->address->address1,
                'address2' => $this->address->address2,
                'city' => $this->address->city,
                'postcode' => $this->address->postcode,
                'county' => $this->address->county,
                'country_id' => $this->address->country_id,
                'country_name' => $this->address->country ? $this->address->country->name : null,
                'address_type_id' => $this->address->address_type_id,
                'date_start' => $this->address->date_start,
                'date_end' => $this->address->date_end,
            ];
        }
        
        // Embed contact label
        if ($this->label) {
            $data['label'] = [
                'id' => (int)$this->label->id,
                'name' => $this->label->name,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~55

---

### 3.2 Create ContactDocument Model

**File**: `protected/models/couchbase/ContactDocument.php`

```php
<?php
/**
 * Couchbase document model for Contact
 * Embeds address and label for denormalized queries
 */

class ContactDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'contact';
    protected $scope = 'core';
    protected $collection = 'contact';

    /**
     * Create document from Contact model
     * @param Contact $contact
     * @return array
     */
    public static function createFromModel($contact)
    {
        $doc = [
            '_type' => 'contact',
            'id' => (int)$contact->id,
            'nick_name' => $contact->nick_name,
            'title' => $contact->title,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'maiden_name' => $contact->maiden_name,
            'qualifications' => $contact->qualifications,
            'contact_label_id' => $contact->contact_label_id ? (int)$contact->contact_label_id : null,
            'primary_phone' => $contact->primary_phone,
            'mobile_phone' => $contact->mobile_phone,
            'fax' => $contact->fax,
            'email' => $contact->email,
            'created_date' => $contact->created_date,
            'last_modified_date' => $contact->last_modified_date,
            // Computed fields for search
            'full_name' => trim($contact->first_name . ' ' . $contact->last_name),
            'full_name_lower' => strtolower(trim($contact->first_name . ' ' . $contact->last_name)),
        ];
        
        // Embed contact label
        if ($contact->label) {
            $doc['label'] = [
                'id' => (int)$contact->label->id,
                'name' => $contact->label->name,
            ];
        }
        
        // Embed addresses (all addresses, not just primary)
        $doc['addresses'] = self::embedAddresses($contact);
        
        // Primary address for convenience
        if ($contact->address) {
            $doc['primary_address'] = self::formatAddress($contact->address);
        }
        
        return $doc;
    }

    /**
     * Embed all addresses for contact
     * @param Contact $contact
     * @return array
     */
    protected static function embedAddresses($contact)
    {
        $addresses = [];
        foreach ($contact->addresses as $address) {
            $addresses[] = self::formatAddress($address);
        }
        return $addresses;
    }

    /**
     * Format address for embedding
     * @param Address $address
     * @return array
     */
    protected static function formatAddress($address)
    {
        return [
            'id' => (int)$address->id,
            'address1' => $address->address1,
            'address2' => $address->address2,
            'city' => $address->city,
            'postcode' => $address->postcode,
            'county' => $address->county,
            'country_id' => $address->country_id,
            'country_name' => $address->country ? $address->country->name : null,
            'address_type_id' => $address->address_type_id,
            'date_start' => $address->date_start,
            'date_end' => $address->date_end,
        ];
    }

    /**
     * Search contacts by name
     * @param string $term
     * @param int $limit
     * @return array
     */
    public static function search($term, $limit = 50)
    {
        $query = "SELECT META().id AS _id, c.* 
                  FROM `openeyes`.`core`.`contact` c 
                  WHERE c.full_name_lower LIKE \$term 
                  ORDER BY c.last_name, c.first_name 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }

    /**
     * Find by email
     * @param string $email
     * @return array|null
     */
    public static function findByEmail($email)
    {
        $query = "SELECT META().id AS _id, c.* 
                  FROM `openeyes`.`core`.`contact` c 
                  WHERE c.email = \$email";
        
        $result = self::executeQuery($query, ['email' => $email]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find by label type
     * @param int $labelId
     * @param int $limit
     * @return array
     */
    public static function findByLabel($labelId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, c.* 
                  FROM `openeyes`.`core`.`contact` c 
                  WHERE c.contact_label_id = \$labelId 
                  ORDER BY c.last_name, c.first_name 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'labelId' => $labelId,
            'limit' => $limit
        ]);
    }
}
```

**Lines**: ~150

---

### 3.3 Create Address Couchbase Support

**File**: `protected/models/Address.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Address extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'core';
    }

    public function couchbaseCollection()
    {
        return 'address';
    }

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        if ($this->country) {
            $data['country'] = [
                'id' => (int)$this->country->id,
                'name' => $this->country->name,
                'code' => $this->country->code,
            ];
        }
        
        if ($this->addressType) {
            $data['address_type'] = [
                'id' => (int)$this->addressType->id,
                'name' => $this->addressType->name,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~40

---

## Section 4: Specialty & Subspecialty Tables

### 4.1 Create Specialty Couchbase Support

**File**: `protected/models/Specialty.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Specialty extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'specialty';
    }
}
```

**Lines to add**: ~15

---

### 4.2 Create Subspecialty Couchbase Support

**File**: `protected/models/Subspecialty.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Subspecialty extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'subspecialty';
    }

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        if ($this->specialty) {
            $data['specialty'] = [
                'id' => (int)$this->specialty->id,
                'name' => $this->specialty->name,
                'code' => $this->specialty->code,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~35

---

## Section 5: Simple Lookup Tables

### 5.1 Create Eye Couchbase Support

**File**: `protected/models/Eye.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Eye extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'eye';
    }
}
```

**Lines to add**: ~15

---

### 5.2 Create Gender Couchbase Support

**File**: `protected/models/Gender.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Gender extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'gender';
    }
}
```

**Lines to add**: ~15

---

### 5.3 Create EthnicGroup Couchbase Support

**File**: `protected/models/EthnicGroup.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class EthnicGroup extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'ethnic_group';
    }
}
```

**Lines to add**: ~15

---

## Section 6: N1QL Indexes

### 6.1 Create Core Lookup Indexes

**File**: `protected/scripts/couchbase/indexes/core-lookup-indexes.n1ql`

```sql
-- =====================================================
-- Phase 10: Core Lookup Tables Indexes
-- =====================================================

-- Event Type Indexes
CREATE INDEX idx_event_type_class_name 
ON `openeyes`.`reference`.`event_type`(class_name);

CREATE INDEX idx_event_type_group 
ON `openeyes`.`reference`.`event_type`(event_group_id) 
WHERE disabled = false;

CREATE INDEX idx_event_type_name 
ON `openeyes`.`reference`.`event_type`(LOWER(name));

-- Element Type Indexes
CREATE INDEX idx_element_type_event 
ON `openeyes`.`reference`.`element_type`(event_type_id, display_order);

CREATE INDEX idx_element_type_class 
ON `openeyes`.`reference`.`element_type`(class_name);

CREATE INDEX idx_element_type_group 
ON `openeyes`.`reference`.`element_type`(element_group_id);

-- Element Group Indexes
CREATE INDEX idx_element_group_event 
ON `openeyes`.`reference`.`element_group`(event_type_id, display_order);

-- Site Indexes
CREATE INDEX idx_site_institution 
ON `openeyes`.`core`.`site`(institution_id) 
WHERE active = true;

CREATE INDEX idx_site_name 
ON `openeyes`.`core`.`site`(LOWER(name)) 
WHERE active = true;

CREATE INDEX idx_site_remote_id 
ON `openeyes`.`core`.`site`(remote_id);

-- Institution Indexes
CREATE INDEX idx_institution_name 
ON `openeyes`.`core`.`institution`(LOWER(name));

CREATE INDEX idx_institution_remote_id 
ON `openeyes`.`core`.`institution`(remote_id);

-- Firm Indexes
CREATE INDEX idx_firm_subspecialty 
ON `openeyes`.`core`.`firm`(service_subspecialty_assignment_id) 
WHERE active = true;

CREATE INDEX idx_firm_consultant 
ON `openeyes`.`core`.`firm`(consultant_id) 
WHERE active = true;

CREATE INDEX idx_firm_name 
ON `openeyes`.`core`.`firm`(LOWER(name)) 
WHERE active = true;

-- Contact Indexes
CREATE INDEX idx_contact_name 
ON `openeyes`.`core`.`contact`(full_name_lower);

CREATE INDEX idx_contact_email 
ON `openeyes`.`core`.`contact`(email) 
WHERE email IS NOT NULL;

CREATE INDEX idx_contact_label 
ON `openeyes`.`core`.`contact`(contact_label_id);

CREATE INDEX idx_contact_phone 
ON `openeyes`.`core`.`contact`(primary_phone) 
WHERE primary_phone IS NOT NULL;

-- Address Indexes
CREATE INDEX idx_address_contact 
ON `openeyes`.`core`.`address`(contact_id);

CREATE INDEX idx_address_postcode 
ON `openeyes`.`core`.`address`(postcode);

-- Specialty/Subspecialty Indexes
CREATE INDEX idx_subspecialty_specialty 
ON `openeyes`.`reference`.`subspecialty`(specialty_id);

CREATE INDEX idx_subspecialty_ref_spec 
ON `openeyes`.`reference`.`subspecialty`(ref_spec);

-- Simple Lookup Indexes
CREATE PRIMARY INDEX idx_eye_primary 
ON `openeyes`.`reference`.`eye`;

CREATE PRIMARY INDEX idx_gender_primary 
ON `openeyes`.`reference`.`gender`;

CREATE PRIMARY INDEX idx_ethnic_group_primary 
ON `openeyes`.`reference`.`ethnic_group`;
```

**Lines**: ~95

---

## Section 7: Migration Commands

### 7.1 Create Core Lookup Migration Command

**File**: `protected/commands/CoreLookupMigrationCommand.php`

```php
<?php
/**
 * Command to migrate core lookup tables to Couchbase
 */

class CoreLookupMigrationCommand extends CConsoleCommand
{
    /**
     * Tables to migrate in dependency order
     */
    protected $tables = [
        // Tier 1: No dependencies
        'eye' => ['model' => 'Eye', 'scope' => 'reference'],
        'gender' => ['model' => 'Gender', 'scope' => 'reference'],
        'ethnic_group' => ['model' => 'EthnicGroup', 'scope' => 'reference'],
        'country' => ['model' => 'Country', 'scope' => 'reference'],
        'address_type' => ['model' => 'AddressType', 'scope' => 'reference'],
        'contact_label' => ['model' => 'ContactLabel', 'scope' => 'reference'],
        'specialty' => ['model' => 'Specialty', 'scope' => 'reference'],
        'event_group' => ['model' => 'EventGroup', 'scope' => 'reference'],
        
        // Tier 2: Depends on Tier 1
        'subspecialty' => ['model' => 'Subspecialty', 'scope' => 'reference'],
        'service' => ['model' => 'Service', 'scope' => 'reference'],
        'institution' => ['model' => 'Institution', 'scope' => 'core'],
        
        // Tier 3: Depends on Tier 2
        'site' => ['model' => 'Site', 'scope' => 'core'],
        'service_subspecialty_assignment' => ['model' => 'ServiceSubspecialtyAssignment', 'scope' => 'reference'],
        'event_type' => ['model' => 'EventType', 'scope' => 'reference'],
        
        // Tier 4: Depends on Tier 3
        'firm' => ['model' => 'Firm', 'scope' => 'core'],
        'element_group' => ['model' => 'ElementGroup', 'scope' => 'reference'],
        'element_type' => ['model' => 'ElementType', 'scope' => 'reference'],
        
        // Tier 5: Large tables
        'contact' => ['model' => 'Contact', 'scope' => 'core'],
        'address' => ['model' => 'Address', 'scope' => 'core'],
    ];

    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic corelookup <action> [options]

ACTIONS
  migrate    Migrate all core lookup tables
  status     Show migration status
  verify     Verify migrated data
  table      Migrate specific table

OPTIONS
  --table=<name>  Specific table to migrate
  --batch=<n>     Batch size (default: 500)
  --verbose       Show detailed output
  --dryRun        Show what would be migrated without executing

EXAMPLES
  yiic corelookup migrate
  yiic corelookup migrate --table=event_type
  yiic corelookup status
  yiic corelookup verify --table=site

EOD;
    }

    /**
     * Migrate all tables
     */
    public function actionMigrate($table = null, $batch = 500, $verbose = false, $dryRun = false)
    {
        echo "===========================================\n";
        echo "Phase 10: Core Lookup Tables Migration\n";
        echo "===========================================\n\n";

        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        $totalMigrated = 0;
        $totalErrors = 0;

        foreach ($tables as $tableName => $config) {
            echo "Migrating table: {$tableName}\n";
            
            $result = $this->migrateTable($tableName, $config, $batch, $verbose, $dryRun);
            
            $totalMigrated += $result['migrated'];
            $totalErrors += $result['errors'];
            
            echo "  Migrated: {$result['migrated']}, Errors: {$result['errors']}\n\n";
        }

        echo "===========================================\n";
        echo "Migration Complete\n";
        echo "Total Migrated: {$totalMigrated}\n";
        echo "Total Errors: {$totalErrors}\n";
        echo "===========================================\n";

        return $totalErrors === 0 ? 0 : 1;
    }

    /**
     * Migrate single table
     */
    protected function migrateTable($tableName, $config, $batch, $verbose, $dryRun)
    {
        $modelClass = $config['model'];
        $scope = $config['scope'];
        $collection = $tableName;

        $migrated = 0;
        $errors = 0;

        // Get adapter
        $adapter = Yii::app()->couchbase;
        if (!$adapter) {
            echo "  ERROR: Couchbase not configured\n";
            return ['migrated' => 0, 'errors' => 1];
        }

        // Count total records
        $total = $modelClass::model()->count();
        echo "  Total records: {$total}\n";

        if ($dryRun) {
            echo "  [DRY RUN] Would migrate {$total} records\n";
            return ['migrated' => 0, 'errors' => 0];
        }

        // Process in batches
        $offset = 0;
        while ($offset < $total) {
            $records = $modelClass::model()->findAll([
                'limit' => $batch,
                'offset' => $offset,
            ]);

            foreach ($records as $record) {
                try {
                    $doc = $record->toCouchbaseDocument();
                    $key = $tableName . '::' . $record->id;
                    
                    $adapter->upsert($scope, $collection, $key, $doc);
                    $migrated++;
                    
                    if ($verbose) {
                        echo "    Migrated: {$key}\n";
                    }
                } catch (Exception $e) {
                    $errors++;
                    echo "    ERROR: {$record->id} - {$e->getMessage()}\n";
                }
            }

            $offset += $batch;
            echo "  Progress: {$migrated}/{$total}\n";
        }

        return ['migrated' => $migrated, 'errors' => $errors];
    }

    /**
     * Show migration status
     */
    public function actionStatus()
    {
        echo "Core Lookup Tables Migration Status\n";
        echo "====================================\n\n";

        $adapter = Yii::app()->couchbase;

        foreach ($this->tables as $tableName => $config) {
            $modelClass = $config['model'];
            $scope = $config['scope'];
            
            $mysqlCount = $modelClass::model()->count();
            
            try {
                $cbCount = $adapter->count($scope, $tableName);
            } catch (Exception $e) {
                $cbCount = 0;
            }
            
            $status = $mysqlCount === $cbCount ? '✓' : '✗';
            $pct = $mysqlCount > 0 ? round(($cbCount / $mysqlCount) * 100) : 0;
            
            printf("%-35s MySQL: %6d  CB: %6d  [%s %3d%%]\n",
                $tableName,
                $mysqlCount,
                $cbCount,
                $status,
                $pct
            );
        }
    }

    /**
     * Verify migrated data
     */
    public function actionVerify($table = null, $sample = 10)
    {
        echo "Verifying Core Lookup Tables\n";
        echo "============================\n\n";

        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        $adapter = Yii::app()->couchbase;

        foreach ($tables as $tableName => $config) {
            echo "Verifying: {$tableName}\n";
            
            $modelClass = $config['model'];
            $scope = $config['scope'];
            
            // Get random sample from MySQL
            $records = $modelClass::model()->findAll([
                'order' => 'RAND()',
                'limit' => $sample,
            ]);
            
            $matches = 0;
            $mismatches = 0;
            
            foreach ($records as $record) {
                $key = $tableName . '::' . $record->id;
                
                try {
                    $cbDoc = $adapter->get($scope, $tableName, $key);
                    
                    if ($cbDoc && $cbDoc['id'] == $record->id) {
                        $matches++;
                    } else {
                        $mismatches++;
                        echo "  MISMATCH: {$key}\n";
                    }
                } catch (Exception $e) {
                    $mismatches++;
                    echo "  MISSING: {$key}\n";
                }
            }
            
            echo "  Verified: {$matches}/{$sample}\n\n";
        }
    }
}
```

**Lines**: ~250

---

## Section 8: Unit Tests

### 8.1 EventTypeDocument Test

**File**: `protected/tests/unit/models/couchbase/EventTypeDocumentTest.php`

```php
<?php
/**
 * Unit tests for EventTypeDocument
 */

class EventTypeDocumentTest extends CDbTestCase
{
    public $fixtures = [
        'event_type' => 'EventType',
    ];

    public function testCreateFromModel()
    {
        $eventType = EventType::model()->findByPk(1);
        $doc = EventTypeDocument::createFromModel($eventType);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('event_type', $doc['_type']);
        $this->assertEquals($eventType->id, $doc['id']);
        $this->assertEquals($eventType->name, $doc['name']);
        $this->assertEquals($eventType->class_name, $doc['class_name']);
    }

    public function testElementTypesEmbedded()
    {
        $eventType = EventType::model()->find('class_name = ?', ['OphCiExamination']);
        
        if (!$eventType) {
            $this->markTestSkipped('OphCiExamination event type not found');
        }
        
        $doc = EventTypeDocument::createFromModel($eventType);
        
        $this->assertArrayHasKey('element_types', $doc);
        $this->assertIsArray($doc['element_types']);
        $this->assertGreaterThan(0, count($doc['element_types']));
        
        // Verify element type structure
        $et = $doc['element_types'][0];
        $this->assertArrayHasKey('id', $et);
        $this->assertArrayHasKey('name', $et);
        $this->assertArrayHasKey('class_name', $et);
    }

    public function testEventGroupEmbedded()
    {
        $eventType = EventType::model()->find('event_group_id IS NOT NULL');
        
        if (!$eventType) {
            $this->markTestSkipped('No event type with group found');
        }
        
        $doc = EventTypeDocument::createFromModel($eventType);
        
        $this->assertArrayHasKey('event_group', $doc);
        $this->assertNotNull($doc['event_group']);
        $this->assertArrayHasKey('id', $doc['event_group']);
        $this->assertArrayHasKey('name', $doc['event_group']);
    }

    public function testDisabledFlagPreserved()
    {
        $eventType = new EventType();
        $eventType->id = 999;
        $eventType->name = 'Test';
        $eventType->class_name = 'TestEvent';
        $eventType->disabled = true;
        
        $doc = EventTypeDocument::createFromModel($eventType);
        
        $this->assertTrue($doc['disabled']);
    }
}
```

**Lines**: ~90

---

### 8.2 SiteDocument Test

**File**: `protected/tests/unit/models/couchbase/SiteDocumentTest.php`

```php
<?php
/**
 * Unit tests for SiteDocument
 */

class SiteDocumentTest extends CDbTestCase
{
    public $fixtures = [
        'site' => 'Site',
        'institution' => 'Institution',
        'contact' => 'Contact',
    ];

    public function testCreateFromModel()
    {
        $site = Site::model()->findByPk(1);
        $doc = SiteDocument::createFromModel($site);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('site', $doc['_type']);
        $this->assertEquals($site->id, $doc['id']);
        $this->assertEquals($site->name, $doc['name']);
    }

    public function testInstitutionEmbedded()
    {
        $site = Site::model()->find('institution_id IS NOT NULL');
        
        if (!$site) {
            $this->markTestSkipped('No site with institution found');
        }
        
        $doc = SiteDocument::createFromModel($site);
        
        $this->assertArrayHasKey('institution', $doc);
        $this->assertNotNull($doc['institution']);
        $this->assertEquals($site->institution->id, $doc['institution']['id']);
        $this->assertEquals($site->institution->name, $doc['institution']['name']);
    }

    public function testContactEmbedded()
    {
        $site = Site::model()->find('contact_id IS NOT NULL');
        
        if (!$site || !$site->contact) {
            $this->markTestSkipped('No site with contact found');
        }
        
        $doc = SiteDocument::createFromModel($site);
        
        $this->assertArrayHasKey('contact', $doc);
        $this->assertNotNull($doc['contact']);
    }

    public function testActiveFlagPreserved()
    {
        $site = Site::model()->find();
        $doc = SiteDocument::createFromModel($site);
        
        $this->assertArrayHasKey('active', $doc);
        $this->assertIsBool($doc['active']);
    }
}
```

**Lines**: ~80

---

### 8.3 ContactDocument Test

**File**: `protected/tests/unit/models/couchbase/ContactDocumentTest.php`

```php
<?php
/**
 * Unit tests for ContactDocument
 */

class ContactDocumentTest extends CDbTestCase
{
    public $fixtures = [
        'contact' => 'Contact',
        'address' => 'Address',
    ];

    public function testCreateFromModel()
    {
        $contact = Contact::model()->findByPk(1);
        $doc = ContactDocument::createFromModel($contact);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('contact', $doc['_type']);
        $this->assertEquals($contact->id, $doc['id']);
    }

    public function testFullNameComputed()
    {
        $contact = Contact::model()->find();
        $doc = ContactDocument::createFromModel($contact);
        
        $this->assertArrayHasKey('full_name', $doc);
        $this->assertArrayHasKey('full_name_lower', $doc);
        
        $expectedFullName = trim($contact->first_name . ' ' . $contact->last_name);
        $this->assertEquals($expectedFullName, $doc['full_name']);
        $this->assertEquals(strtolower($expectedFullName), $doc['full_name_lower']);
    }

    public function testAddressesEmbedded()
    {
        $contact = Contact::model()->find();
        $doc = ContactDocument::createFromModel($contact);
        
        $this->assertArrayHasKey('addresses', $doc);
        $this->assertIsArray($doc['addresses']);
    }

    public function testPrimaryAddressEmbedded()
    {
        $contact = Contact::model()->with('address')->find('t.id IN (SELECT contact_id FROM address)');
        
        if (!$contact || !$contact->address) {
            $this->markTestSkipped('No contact with address found');
        }
        
        $doc = ContactDocument::createFromModel($contact);
        
        $this->assertArrayHasKey('primary_address', $doc);
        $this->assertNotNull($doc['primary_address']);
        $this->assertArrayHasKey('address1', $doc['primary_address']);
        $this->assertArrayHasKey('postcode', $doc['primary_address']);
    }

    public function testLabelEmbedded()
    {
        $contact = Contact::model()->find('contact_label_id IS NOT NULL');
        
        if (!$contact) {
            $this->markTestSkipped('No contact with label found');
        }
        
        $doc = ContactDocument::createFromModel($contact);
        
        $this->assertArrayHasKey('label', $doc);
        $this->assertNotNull($doc['label']);
        $this->assertArrayHasKey('name', $doc['label']);
    }
}
```

**Lines**: ~90

---

### 8.4 Core Lookup Migration Integration Test

**File**: `protected/tests/integration/CoreLookupMigrationTest.php`

```php
<?php
/**
 * Integration tests for core lookup table migration
 */

class CoreLookupMigrationTest extends CDbTestCase
{
    protected $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Skip if Couchbase not available
        if (!Yii::app()->hasComponent('couchbase')) {
            $this->markTestSkipped('Couchbase not configured');
        }
        
        $this->adapter = Yii::app()->couchbase;
    }

    public function testEventTypeMigration()
    {
        // Get MySQL count
        $mysqlCount = EventType::model()->count();
        
        // Get Couchbase count
        $cbCount = $this->adapter->count('reference', 'event_type');
        
        // After migration, counts should match
        $this->assertEquals($mysqlCount, $cbCount,
            "EventType count mismatch: MySQL={$mysqlCount}, CB={$cbCount}");
    }

    public function testSiteMigration()
    {
        $mysqlCount = Site::model()->count();
        $cbCount = $this->adapter->count('core', 'site');
        
        $this->assertEquals($mysqlCount, $cbCount,
            "Site count mismatch: MySQL={$mysqlCount}, CB={$cbCount}");
    }

    public function testContactMigration()
    {
        $mysqlCount = Contact::model()->count();
        $cbCount = $this->adapter->count('core', 'contact');
        
        $this->assertEquals($mysqlCount, $cbCount,
            "Contact count mismatch: MySQL={$mysqlCount}, CB={$cbCount}");
    }

    public function testEventTypeQueryByClassName()
    {
        $className = 'OphCiExamination';
        
        // Query MySQL
        $mysqlResult = EventType::model()->find('class_name = ?', [$className]);
        
        // Query Couchbase
        $cbResult = EventTypeDocument::findByClassName($className);
        
        if ($mysqlResult) {
            $this->assertNotNull($cbResult);
            $this->assertEquals($mysqlResult->id, $cbResult['id']);
            $this->assertEquals($mysqlResult->name, $cbResult['name']);
        }
    }

    public function testSiteQueryByInstitution()
    {
        $institution = Institution::model()->find();
        
        if (!$institution) {
            $this->markTestSkipped('No institution found');
        }
        
        // Query MySQL
        $mysqlResults = Site::model()->findAll('institution_id = ?', [$institution->id]);
        
        // Query Couchbase
        $cbResults = SiteDocument::findByInstitution($institution->id);
        
        $this->assertEquals(count($mysqlResults), count($cbResults));
    }

    public function testContactSearch()
    {
        $contact = Contact::model()->find('last_name IS NOT NULL AND last_name != ""');
        
        if (!$contact) {
            $this->markTestSkipped('No contact with last name found');
        }
        
        $searchTerm = substr($contact->last_name, 0, 3);
        
        // Search Couchbase
        $results = ContactDocument::search($searchTerm);
        
        $this->assertIsArray($results);
        // Should find at least the original contact
        $found = false;
        foreach ($results as $result) {
            if ($result['id'] == $contact->id) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Original contact not found in search results");
    }
}
```

**Lines**: ~120

---

## Section 9: Validation & Success Criteria

### 9.1 Validation Checklist

```markdown
## Phase 10 Validation Checklist

### Pre-Migration
- [ ] Couchbase cluster accessible
- [ ] Reference scope exists
- [ ] Core scope exists
- [ ] All collections created

### Table Migration
- [ ] eye: 100% records migrated
- [ ] gender: 100% records migrated
- [ ] ethnic_group: 100% records migrated
- [ ] specialty: 100% records migrated
- [ ] subspecialty: 100% records migrated
- [ ] event_type: 100% records migrated
- [ ] element_type: 100% records migrated
- [ ] element_group: 100% records migrated
- [ ] site: 100% records migrated
- [ ] institution: 100% records migrated
- [ ] firm: 100% records migrated
- [ ] contact: 100% records migrated
- [ ] address: 100% records migrated

### Index Verification
- [ ] All 25+ indexes created
- [ ] Indexes are online
- [ ] Query performance < 50ms

### Data Integrity
- [ ] EventType class_name unique
- [ ] Site-Institution relations valid
- [ ] Contact-Address relations embedded
- [ ] No orphan records

### Application Verification
- [ ] Patient summary page loads
- [ ] Event creation works
- [ ] Site selection works
- [ ] Firm selection works
```

### 9.2 Success Criteria

| Metric | Target | Method |
|--------|--------|--------|
| Record Count Match | 100% | `datavalidation counts` |
| Field Integrity | 99.9%+ | `datavalidation sample` |
| Query Latency (p95) | < 50ms | `querybenchmark` |
| Application Errors | 0 | Log monitoring |
| Unit Test Pass Rate | 100% | PHPUnit |

---

## Section 10: Rollback Procedures

### 10.1 Disable Couchbase for Lookups

```php
// In protected/config/core/common.php
$config["params"]["enable_couchbase_read"] = false;
$config["params"]["couchbase_migrated_collections"] = [
    // Remove lookup tables from this list
    // 'event_type',
    // 'element_type',
    // 'site',
    // etc.
];
```

### 10.2 Revert Model Changes

```bash
# Revert all model changes
git checkout HEAD -- protected/models/EventType.php
git checkout HEAD -- protected/models/ElementType.php
git checkout HEAD -- protected/models/Site.php
git checkout HEAD -- protected/models/Institution.php
git checkout HEAD -- protected/models/Firm.php
git checkout HEAD -- protected/models/Contact.php
git checkout HEAD -- protected/models/Address.php
git checkout HEAD -- protected/models/Specialty.php
git checkout HEAD -- protected/models/Subspecialty.php
git checkout HEAD -- protected/models/Eye.php
git checkout HEAD -- protected/models/Gender.php
git checkout HEAD -- protected/models/EthnicGroup.php
```

### 10.3 Drop Couchbase Collections (if needed)

```sql
-- Only if complete rollback needed
DROP COLLECTION `openeyes`.`reference`.`event_type`;
DROP COLLECTION `openeyes`.`reference`.`element_type`;
DROP COLLECTION `openeyes`.`reference`.`element_group`;
DROP COLLECTION `openeyes`.`core`.`site`;
DROP COLLECTION `openeyes`.`core`.`institution`;
DROP COLLECTION `openeyes`.`core`.`firm`;
DROP COLLECTION `openeyes`.`core`.`contact`;
DROP COLLECTION `openeyes`.`core`.`address`;
```

---

## Summary

### Files to Create
| File | Lines | Purpose |
|------|-------|---------|
| EventTypeDocument.php | 120 | Event type document model |
| ElementTypeDocument.php | 90 | Element type document model |
| SiteDocument.php | 130 | Site document model |
| ContactDocument.php | 150 | Contact document model |
| core-lookup-indexes.n1ql | 95 | N1QL indexes |
| CoreLookupMigrationCommand.php | 250 | Migration command |
| EventTypeDocumentTest.php | 90 | Unit tests |
| SiteDocumentTest.php | 80 | Unit tests |
| ContactDocumentTest.php | 90 | Unit tests |
| CoreLookupMigrationTest.php | 120 | Integration tests |

### Files to Modify
| File | Changes |
|------|---------|
| EventType.php | Add CouchbaseModelBridge (~20 lines) |
| ElementType.php | Add CouchbaseModelBridge (~15 lines) |
| ElementGroup.php | Add CouchbaseModelBridge (~15 lines) |
| Site.php | Add CouchbaseModelBridge + embeddings (~55 lines) |
| Institution.php | Add CouchbaseModelBridge + embeddings (~45 lines) |
| Firm.php | Add CouchbaseModelBridge + embeddings (~55 lines) |
| Contact.php | Add CouchbaseModelBridge + embeddings (~55 lines) |
| Address.php | Add CouchbaseModelBridge + embeddings (~40 lines) |
| Specialty.php | Add CouchbaseModelBridge (~15 lines) |
| Subspecialty.php | Add CouchbaseModelBridge + embeddings (~35 lines) |
| Eye.php | Add CouchbaseModelBridge (~15 lines) |
| Gender.php | Add CouchbaseModelBridge (~15 lines) |
| EthnicGroup.php | Add CouchbaseModelBridge (~15 lines) |

### Total Effort
- **New Files**: 10 files (~1,215 lines)
- **Modified Files**: 13 files (~395 lines)
- **Total Code**: ~1,610 lines
- **Estimated Duration**: 2-3 weeks

---

**Phase 10 Status**: SPECIFICATION COMPLETE  
**Ready for Implementation**: YES
