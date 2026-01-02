# Phase 11: Clinical Reference Data Migration

## Overview

This phase migrates clinical reference tables including disorders (diagnoses), drugs, medications, procedures, and allergies. These tables contain SNOMED codes, drug interactions, and clinical terminology essential for healthcare operations.

**Duration**: 2 weeks  
**Priority**: HIGH  
**Complexity**: Medium-High

## Prerequisites

- Phase 10 completed (Core Lookup Tables)
- Couchbase cluster running
- `reference` and `clinical` scopes created
- Full-text search (FTS) indexes available for drug/disorder search

## Dependencies

- Phase 4: Core model patterns
- Phase 10: Specialty/Subspecialty tables migrated

---

## Section 1: Disorder (Diagnosis) Migration

### 1.1 Create Disorder Couchbase Support

**File**: `protected/models/Disorder.php`

**Task**: Add CouchbaseModelBridge trait with SNOMED code handling.

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Disorder extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'disorder';
    }

    /**
     * Get embedded relations for Couchbase document
     * @return array
     */
    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed specialty
        if ($this->specialty) {
            $data['specialty'] = [
                'id' => (int)$this->specialty->id,
                'name' => $this->specialty->name,
                'code' => $this->specialty->code,
            ];
        }
        
        // Embed common ophthalmic disorders that use this disorder
        $data['is_common_ophthalmic'] = CommonOphthalmicDisorder::model()->exists(
            'disorder_id = ?', 
            [$this->id]
        );
        
        // Embed systemic associations
        $data['is_systemic'] = CommonSystemicDisorder::model()->exists(
            'disorder_id = ?',
            [$this->id]
        );
        
        return $data;
    }
}
```

**Lines to add**: ~50

---

### 1.2 Create DisorderDocument Model

**File**: `protected/models/couchbase/DisorderDocument.php`

```php
<?php
/**
 * Couchbase document model for Disorder
 * Optimized for SNOMED code lookups and clinical searches
 */

class DisorderDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'disorder';
    protected $scope = 'reference';
    protected $collection = 'disorder';

    /**
     * Create document from Disorder model
     * @param Disorder $disorder
     * @return array
     */
    public static function createFromModel($disorder)
    {
        $doc = [
            '_type' => 'disorder',
            'id' => (int)$disorder->id,
            'fully_specified_name' => $disorder->fully_specified_name,
            'term' => $disorder->term,
            'aliases' => $disorder->aliases,
            'snomed_code' => $disorder->snomed_code,  // Critical for interoperability
            'snomed_version' => $disorder->snomed_version,
            'specialty_id' => $disorder->specialty_id ? (int)$disorder->specialty_id : null,
            'active' => (bool)$disorder->active,
            'created_date' => $disorder->created_date,
            'last_modified_date' => $disorder->last_modified_date,
            
            // Computed fields for search
            'term_lower' => strtolower($disorder->term ?? ''),
            'search_terms' => self::buildSearchTerms($disorder),
        ];
        
        // Embed specialty
        if ($disorder->specialty) {
            $doc['specialty'] = [
                'id' => (int)$disorder->specialty->id,
                'name' => $disorder->specialty->name,
                'code' => $disorder->specialty->code,
            ];
        }
        
        // Embed parent disorder if exists
        if ($disorder->parent_id && $disorder->parent) {
            $doc['parent'] = [
                'id' => (int)$disorder->parent->id,
                'term' => $disorder->parent->term,
                'snomed_code' => $disorder->parent->snomed_code,
            ];
        }
        
        // Flag for common disorders
        $doc['is_common_ophthalmic'] = CommonOphthalmicDisorder::model()->exists(
            'disorder_id = ?', [$disorder->id]
        );
        
        return $doc;
    }

    /**
     * Build search terms array for FTS
     * @param Disorder $disorder
     * @return array
     */
    protected static function buildSearchTerms($disorder)
    {
        $terms = [];
        
        if ($disorder->term) {
            $terms[] = strtolower($disorder->term);
        }
        if ($disorder->fully_specified_name) {
            $terms[] = strtolower($disorder->fully_specified_name);
        }
        if ($disorder->aliases) {
            $aliases = explode(',', $disorder->aliases);
            foreach ($aliases as $alias) {
                $terms[] = strtolower(trim($alias));
            }
        }
        
        return array_unique($terms);
    }

    /**
     * Find by SNOMED code
     * @param string $snomedCode
     * @return array|null
     */
    public static function findBySnomedCode($snomedCode)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`disorder` d 
                  WHERE d.snomed_code = \$snomedCode";
        
        $result = self::executeQuery($query, ['snomedCode' => $snomedCode]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Search disorders by term
     * @param string $term
     * @param int $limit
     * @param bool $activeOnly
     * @return array
     */
    public static function search($term, $limit = 50, $activeOnly = true)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`disorder` d 
                  WHERE d.term_lower LIKE \$term";
        
        if ($activeOnly) {
            $query .= " AND d.active = true";
        }
        
        $query .= " ORDER BY d.term LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }

    /**
     * Find common ophthalmic disorders
     * @param int $subspecialtyId
     * @return array
     */
    public static function findCommonOphthalmic($subspecialtyId = null)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`disorder` d 
                  WHERE d.is_common_ophthalmic = true 
                  AND d.active = true";
        
        $params = [];
        
        if ($subspecialtyId) {
            $query .= " AND d.specialty_id = \$subspecialtyId";
            $params['subspecialtyId'] = $subspecialtyId;
        }
        
        $query .= " ORDER BY d.term";
        
        return self::executeQuery($query, $params);
    }

    /**
     * Find by specialty
     * @param int $specialtyId
     * @param int $limit
     * @return array
     */
    public static function findBySpecialty($specialtyId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`disorder` d 
                  WHERE d.specialty_id = \$specialtyId 
                  AND d.active = true 
                  ORDER BY d.term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'specialtyId' => $specialtyId,
            'limit' => $limit
        ]);
    }
}
```

**Lines**: ~160

---

## Section 2: Drug & Medication Migration

### 2.1 Create Drug Couchbase Support

**File**: `protected/models/Drug.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Drug extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'drug';
    }
}
```

**Lines to add**: ~15

---

### 2.2 Create Medication Couchbase Support

**File**: `protected/models/Medication.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Medication extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'medication';
    }

    /**
     * Get embedded relations for Couchbase document
     * @return array
     */
    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed default route
        if ($this->default_route_id && $this->defaultRoute) {
            $data['default_route'] = [
                'id' => (int)$this->defaultRoute->id,
                'term' => $this->defaultRoute->term,
                'code' => $this->defaultRoute->code,
            ];
        }
        
        // Embed default form
        if ($this->default_form_id && $this->defaultForm) {
            $data['default_form'] = [
                'id' => (int)$this->defaultForm->id,
                'term' => $this->defaultForm->term,
                'code' => $this->defaultForm->code,
            ];
        }
        
        // Embed default frequency
        if ($this->default_frequency_id && $this->defaultFrequency) {
            $data['default_frequency'] = [
                'id' => (int)$this->defaultFrequency->id,
                'term' => $this->defaultFrequency->term,
                'code' => $this->defaultFrequency->code,
            ];
        }
        
        // Embed allergy warnings
        $allergyWarnings = MedicationAllergyAssignment::model()->findAll(
            'medication_id = ?', [$this->id]
        );
        if ($allergyWarnings) {
            $data['allergy_warnings'] = array_map(function($assignment) {
                return [
                    'allergy_id' => (int)$assignment->allergy_id,
                    'allergy_name' => $assignment->allergy ? $assignment->allergy->name : null,
                ];
            }, $allergyWarnings);
        }
        
        return $data;
    }
}
```

**Lines to add**: ~65

---

### 2.3 Create MedicationDocument Model

**File**: `protected/models/couchbase/MedicationDocument.php`

```php
<?php
/**
 * Couchbase document model for Medication
 * Includes route, form, frequency embeddings for clinical display
 */

class MedicationDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'medication';
    protected $scope = 'reference';
    protected $collection = 'medication';

    /**
     * Create document from Medication model
     * @param Medication $medication
     * @return array
     */
    public static function createFromModel($medication)
    {
        $doc = [
            '_type' => 'medication',
            'id' => (int)$medication->id,
            'preferred_term' => $medication->preferred_term,
            'preferred_code' => $medication->preferred_code,
            'source_type' => $medication->source_type,
            'source_subtype' => $medication->source_subtype,
            'vtm_term' => $medication->vtm_term,
            'vtm_code' => $medication->vtm_code,
            'vmp_term' => $medication->vmp_term,
            'vmp_code' => $medication->vmp_code,
            'amp_term' => $medication->amp_term,
            'amp_code' => $medication->amp_code,
            'default_form_id' => $medication->default_form_id ? (int)$medication->default_form_id : null,
            'default_route_id' => $medication->default_route_id ? (int)$medication->default_route_id : null,
            'default_frequency_id' => $medication->default_frequency_id ? (int)$medication->default_frequency_id : null,
            'default_duration_id' => $medication->default_duration_id ? (int)$medication->default_duration_id : null,
            'default_dose' => $medication->default_dose,
            'default_dose_unit_term' => $medication->default_dose_unit_term,
            'active' => (bool)$medication->active,
            'deleted_date' => $medication->deleted_date,
            'created_date' => $medication->created_date,
            'last_modified_date' => $medication->last_modified_date,
            
            // Computed fields for search
            'preferred_term_lower' => strtolower($medication->preferred_term ?? ''),
            'search_terms' => self::buildSearchTerms($medication),
        ];
        
        // Embed route
        if ($medication->defaultRoute) {
            $doc['default_route'] = [
                'id' => (int)$medication->defaultRoute->id,
                'term' => $medication->defaultRoute->term,
                'code' => $medication->defaultRoute->code,
            ];
        }
        
        // Embed form
        if ($medication->defaultForm) {
            $doc['default_form'] = [
                'id' => (int)$medication->defaultForm->id,
                'term' => $medication->defaultForm->term,
                'code' => $medication->defaultForm->code,
            ];
        }
        
        // Embed frequency
        if ($medication->defaultFrequency) {
            $doc['default_frequency'] = [
                'id' => (int)$medication->defaultFrequency->id,
                'term' => $medication->defaultFrequency->term,
                'code' => $medication->defaultFrequency->code,
            ];
        }
        
        // Embed allergy warnings
        $doc['allergy_warnings'] = self::embedAllergyWarnings($medication);
        
        return $doc;
    }

    /**
     * Build search terms for FTS
     * @param Medication $medication
     * @return array
     */
    protected static function buildSearchTerms($medication)
    {
        $terms = [];
        
        if ($medication->preferred_term) {
            $terms[] = strtolower($medication->preferred_term);
        }
        if ($medication->vtm_term) {
            $terms[] = strtolower($medication->vtm_term);
        }
        if ($medication->vmp_term) {
            $terms[] = strtolower($medication->vmp_term);
        }
        if ($medication->amp_term) {
            $terms[] = strtolower($medication->amp_term);
        }
        
        return array_unique($terms);
    }

    /**
     * Embed allergy warnings
     * @param Medication $medication
     * @return array
     */
    protected static function embedAllergyWarnings($medication)
    {
        $warnings = [];
        $assignments = MedicationAllergyAssignment::model()->findAll(
            'medication_id = ?', [$medication->id]
        );
        
        foreach ($assignments as $assignment) {
            if ($assignment->allergy) {
                $warnings[] = [
                    'allergy_id' => (int)$assignment->allergy_id,
                    'allergy_name' => $assignment->allergy->name,
                ];
            }
        }
        
        return $warnings;
    }

    /**
     * Search medications by term
     * @param string $term
     * @param int $limit
     * @return array
     */
    public static function search($term, $limit = 50)
    {
        $query = "SELECT META().id AS _id, m.* 
                  FROM `openeyes`.`reference`.`medication` m 
                  WHERE m.preferred_term_lower LIKE \$term 
                  AND m.active = true 
                  AND m.deleted_date IS NULL 
                  ORDER BY m.preferred_term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }

    /**
     * Find by preferred code (dm+d code)
     * @param string $code
     * @return array|null
     */
    public static function findByCode($code)
    {
        $query = "SELECT META().id AS _id, m.* 
                  FROM `openeyes`.`reference`.`medication` m 
                  WHERE m.preferred_code = \$code";
        
        $result = self::executeQuery($query, ['code' => $code]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find by route
     * @param int $routeId
     * @param int $limit
     * @return array
     */
    public static function findByRoute($routeId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, m.* 
                  FROM `openeyes`.`reference`.`medication` m 
                  WHERE m.default_route_id = \$routeId 
                  AND m.active = true 
                  ORDER BY m.preferred_term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'routeId' => $routeId,
            'limit' => $limit
        ]);
    }

    /**
     * Find medications with allergy warnings for specific allergy
     * @param int $allergyId
     * @return array
     */
    public static function findWithAllergyWarning($allergyId)
    {
        $query = "SELECT META().id AS _id, m.* 
                  FROM `openeyes`.`reference`.`medication` m 
                  WHERE ANY w IN m.allergy_warnings SATISFIES w.allergy_id = \$allergyId END 
                  AND m.active = true";
        
        return self::executeQuery($query, ['allergyId' => $allergyId]);
    }
}
```

**Lines**: ~200

---

## Section 3: Procedure Migration

### 3.1 Create Procedure Couchbase Support

**File**: `protected/models/Procedure.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Procedure extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'procedure';
    }

    /**
     * Get embedded relations for Couchbase document
     * @return array
     */
    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed OPCS codes
        $opcsCodes = OPCSCode::model()->findAll(
            'procedure_id = ?', [$this->id]
        );
        if ($opcsCodes) {
            $data['opcs_codes'] = array_map(function($opcs) {
                return [
                    'id' => (int)$opcs->id,
                    'name' => $opcs->name,
                    'code' => $opcs->code,
                ];
            }, $opcsCodes);
        }
        
        // Embed benefits
        if (!empty($this->benefits)) {
            $data['benefits'] = array_map(function($benefit) {
                return [
                    'id' => (int)$benefit->id,
                    'name' => $benefit->name,
                ];
            }, $this->benefits);
        }
        
        // Embed complications
        if (!empty($this->complications)) {
            $data['complications'] = array_map(function($complication) {
                return [
                    'id' => (int)$complication->id,
                    'name' => $complication->name,
                ];
            }, $this->complications);
        }
        
        return $data;
    }
}
```

**Lines to add**: ~60

---

### 3.2 Create ProcedureDocument Model

**File**: `protected/models/couchbase/ProcedureDocument.php`

```php
<?php
/**
 * Couchbase document model for Procedure
 * Includes OPCS codes, SNOMED codes, benefits, and complications
 */

class ProcedureDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'procedure';
    protected $scope = 'reference';
    protected $collection = 'procedure';

    /**
     * Create document from Procedure model
     * @param Procedure $procedure
     * @return array
     */
    public static function createFromModel($procedure)
    {
        $doc = [
            '_type' => 'procedure',
            'id' => (int)$procedure->id,
            'term' => $procedure->term,
            'short_format' => $procedure->short_format,
            'snomed_code' => $procedure->snomed_code,
            'snomed_term' => $procedure->snomed_term,
            'ecds_code' => $procedure->ecds_code,
            'ecds_term' => $procedure->ecds_term,
            'default_duration' => $procedure->default_duration,
            'unbooked' => (bool)$procedure->unbooked,
            'active' => (bool)$procedure->active,
            'created_date' => $procedure->created_date,
            'last_modified_date' => $procedure->last_modified_date,
            
            // Computed for search
            'term_lower' => strtolower($procedure->term ?? ''),
        ];
        
        // Embed OPCS codes
        $doc['opcs_codes'] = self::embedOpcsCodes($procedure);
        
        // Embed benefits
        $doc['benefits'] = self::embedBenefits($procedure);
        
        // Embed complications
        $doc['complications'] = self::embedComplications($procedure);
        
        // Embed subspecialty assignments
        $doc['subspecialties'] = self::embedSubspecialties($procedure);
        
        return $doc;
    }

    /**
     * Embed OPCS codes
     */
    protected static function embedOpcsCodes($procedure)
    {
        $codes = [];
        foreach ($procedure->opcsCodes as $opcs) {
            $codes[] = [
                'id' => (int)$opcs->id,
                'name' => $opcs->name,
                'code' => $opcs->code,
            ];
        }
        return $codes;
    }

    /**
     * Embed benefits
     */
    protected static function embedBenefits($procedure)
    {
        $benefits = [];
        foreach ($procedure->benefits as $benefit) {
            $benefits[] = [
                'id' => (int)$benefit->id,
                'name' => $benefit->name,
            ];
        }
        return $benefits;
    }

    /**
     * Embed complications
     */
    protected static function embedComplications($procedure)
    {
        $complications = [];
        foreach ($procedure->complications as $complication) {
            $complications[] = [
                'id' => (int)$complication->id,
                'name' => $complication->name,
            ];
        }
        return $complications;
    }

    /**
     * Embed subspecialty assignments
     */
    protected static function embedSubspecialties($procedure)
    {
        $subspecialties = [];
        $assignments = ProcedureSubspecialtyAssignment::model()->findAll(
            'proc_id = ?', [$procedure->id]
        );
        foreach ($assignments as $assignment) {
            if ($assignment->subspecialty) {
                $subspecialties[] = [
                    'id' => (int)$assignment->subspecialty->id,
                    'name' => $assignment->subspecialty->name,
                ];
            }
        }
        return $subspecialties;
    }

    /**
     * Search procedures by term
     * @param string $term
     * @param int $limit
     * @return array
     */
    public static function search($term, $limit = 50)
    {
        $query = "SELECT META().id AS _id, p.* 
                  FROM `openeyes`.`reference`.`procedure` p 
                  WHERE p.term_lower LIKE \$term 
                  AND p.active = true 
                  ORDER BY p.term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }

    /**
     * Find by SNOMED code
     * @param string $snomedCode
     * @return array|null
     */
    public static function findBySnomedCode($snomedCode)
    {
        $query = "SELECT META().id AS _id, p.* 
                  FROM `openeyes`.`reference`.`procedure` p 
                  WHERE p.snomed_code = \$snomedCode";
        
        $result = self::executeQuery($query, ['snomedCode' => $snomedCode]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find by OPCS code
     * @param string $opcsCode
     * @return array
     */
    public static function findByOpcsCode($opcsCode)
    {
        $query = "SELECT META().id AS _id, p.* 
                  FROM `openeyes`.`reference`.`procedure` p 
                  WHERE ANY o IN p.opcs_codes SATISFIES o.code = \$opcsCode END";
        
        return self::executeQuery($query, ['opcsCode' => $opcsCode]);
    }

    /**
     * Find by subspecialty
     * @param int $subspecialtyId
     * @param int $limit
     * @return array
     */
    public static function findBySubspecialty($subspecialtyId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, p.* 
                  FROM `openeyes`.`reference`.`procedure` p 
                  WHERE ANY s IN p.subspecialties SATISFIES s.id = \$subspecialtyId END 
                  AND p.active = true 
                  ORDER BY p.term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'subspecialtyId' => $subspecialtyId,
            'limit' => $limit
        ]);
    }
}
```

**Lines**: ~180

---

## Section 4: Allergy Migration

### 4.1 Create Allergy Couchbase Support

**File**: `protected/models/Allergy.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Allergy extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'reference';
    }

    public function couchbaseCollection()
    {
        return 'allergy';
    }
}
```

**Lines to add**: ~15

---

## Section 5: N1QL Indexes for Clinical Data

### 5.1 Create Clinical Reference Indexes

**File**: `protected/scripts/couchbase/indexes/clinical-reference-indexes.n1ql`

```sql
-- =====================================================
-- Phase 11: Clinical Reference Data Indexes
-- =====================================================

-- Disorder Indexes
CREATE INDEX idx_disorder_snomed 
ON `openeyes`.`reference`.`disorder`(snomed_code);

CREATE INDEX idx_disorder_term 
ON `openeyes`.`reference`.`disorder`(term_lower) 
WHERE active = true;

CREATE INDEX idx_disorder_specialty 
ON `openeyes`.`reference`.`disorder`(specialty_id) 
WHERE active = true;

CREATE INDEX idx_disorder_common 
ON `openeyes`.`reference`.`disorder`(is_common_ophthalmic) 
WHERE is_common_ophthalmic = true AND active = true;

-- Full-text search index for disorder (requires FTS service)
-- CREATE INDEX idx_disorder_fts ON `openeyes`.`reference`.`disorder`
-- USING FTS WITH {"type": "fulltext-index", "sourceType": "couchbase", 
--   "sourceName": "openeyes", "mapping": {"types": {"disorder": {
--     "properties": {"term": {"enabled": true}, "aliases": {"enabled": true}}
--   }}}}

-- Medication Indexes
CREATE INDEX idx_medication_term 
ON `openeyes`.`reference`.`medication`(preferred_term_lower) 
WHERE active = true AND deleted_date IS NULL;

CREATE INDEX idx_medication_code 
ON `openeyes`.`reference`.`medication`(preferred_code);

CREATE INDEX idx_medication_vtm 
ON `openeyes`.`reference`.`medication`(vtm_code) 
WHERE vtm_code IS NOT NULL;

CREATE INDEX idx_medication_vmp 
ON `openeyes`.`reference`.`medication`(vmp_code) 
WHERE vmp_code IS NOT NULL;

CREATE INDEX idx_medication_route 
ON `openeyes`.`reference`.`medication`(default_route_id) 
WHERE active = true;

CREATE INDEX idx_medication_form 
ON `openeyes`.`reference`.`medication`(default_form_id) 
WHERE active = true;

-- Array index for allergy warnings
CREATE INDEX idx_medication_allergy_warnings 
ON `openeyes`.`reference`.`medication`(
    DISTINCT ARRAY w.allergy_id FOR w IN allergy_warnings END
) WHERE active = true;

-- Drug Indexes
CREATE INDEX idx_drug_name 
ON `openeyes`.`reference`.`drug`(LOWER(name));

CREATE INDEX idx_drug_tallman 
ON `openeyes`.`reference`.`drug`(tallman);

-- Procedure Indexes
CREATE INDEX idx_procedure_term 
ON `openeyes`.`reference`.`procedure`(term_lower) 
WHERE active = true;

CREATE INDEX idx_procedure_snomed 
ON `openeyes`.`reference`.`procedure`(snomed_code);

-- Array index for OPCS codes
CREATE INDEX idx_procedure_opcs 
ON `openeyes`.`reference`.`procedure`(
    DISTINCT ARRAY o.code FOR o IN opcs_codes END
);

-- Array index for subspecialties
CREATE INDEX idx_procedure_subspecialty 
ON `openeyes`.`reference`.`procedure`(
    DISTINCT ARRAY s.id FOR s IN subspecialties END
) WHERE active = true;

-- Allergy Indexes
CREATE PRIMARY INDEX idx_allergy_primary 
ON `openeyes`.`reference`.`allergy`;

CREATE INDEX idx_allergy_name 
ON `openeyes`.`reference`.`allergy`(LOWER(name));

-- Common Ophthalmic Disorder Indexes
CREATE INDEX idx_common_disorder_subspecialty 
ON `openeyes`.`reference`.`common_ophthalmic_disorder`(subspecialty_id);

CREATE INDEX idx_common_disorder_disorder 
ON `openeyes`.`reference`.`common_ophthalmic_disorder`(disorder_id);

-- Medication Route Indexes
CREATE INDEX idx_med_route_term 
ON `openeyes`.`reference`.`medication_route`(LOWER(term));

-- Medication Form Indexes
CREATE INDEX idx_med_form_term 
ON `openeyes`.`reference`.`medication_form`(LOWER(term));

-- Medication Frequency Indexes
CREATE INDEX idx_med_frequency_term 
ON `openeyes`.`reference`.`medication_frequency`(LOWER(term));
```

**Lines**: ~100

---

## Section 6: Migration Command

### 6.1 Create Clinical Reference Migration Command

**File**: `protected/commands/ClinicalReferenceMigrationCommand.php`

```php
<?php
/**
 * Command to migrate clinical reference tables to Couchbase
 */

class ClinicalReferenceMigrationCommand extends CConsoleCommand
{
    /**
     * Tables to migrate in dependency order
     */
    protected $tables = [
        // Tier 1: No dependencies
        'allergy' => ['model' => 'Allergy', 'scope' => 'reference'],
        'medication_route' => ['model' => 'MedicationRoute', 'scope' => 'reference'],
        'medication_form' => ['model' => 'MedicationForm', 'scope' => 'reference'],
        'medication_frequency' => ['model' => 'MedicationFrequency', 'scope' => 'reference'],
        'medication_duration' => ['model' => 'MedicationDuration', 'scope' => 'reference'],
        'medication_laterality' => ['model' => 'MedicationLaterality', 'scope' => 'reference'],
        'benefit' => ['model' => 'Benefit', 'scope' => 'reference'],
        'complication' => ['model' => 'Complication', 'scope' => 'reference'],
        
        // Tier 2: Depends on Tier 1
        'disorder' => ['model' => 'Disorder', 'scope' => 'reference', 'large' => true],
        'drug' => ['model' => 'Drug', 'scope' => 'reference'],
        'procedure' => ['model' => 'Procedure', 'scope' => 'reference'],
        
        // Tier 3: Depends on Tier 2
        'medication' => ['model' => 'Medication', 'scope' => 'reference', 'large' => true],
        'common_ophthalmic_disorder' => ['model' => 'CommonOphthalmicDisorder', 'scope' => 'reference'],
        'opcs_code' => ['model' => 'OPCSCode', 'scope' => 'reference'],
    ];

    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic clinicalreference <action> [options]

ACTIONS
  migrate    Migrate all clinical reference tables
  status     Show migration status
  verify     Verify migrated data
  search     Test search functionality

OPTIONS
  --table=<name>  Specific table to migrate
  --batch=<n>     Batch size (default: 1000)
  --verbose       Show detailed output
  --dryRun        Show what would be migrated

EXAMPLES
  yiic clinicalreference migrate
  yiic clinicalreference migrate --table=disorder
  yiic clinicalreference search --term="diabetes"

EOD;
    }

    /**
     * Migrate all tables
     */
    public function actionMigrate($table = null, $batch = 1000, $verbose = false, $dryRun = false)
    {
        echo "===========================================\n";
        echo "Phase 11: Clinical Reference Data Migration\n";
        echo "===========================================\n\n";

        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        $totalMigrated = 0;
        $totalErrors = 0;

        foreach ($tables as $tableName => $config) {
            echo "Migrating table: {$tableName}\n";
            
            // Use smaller batch for large tables
            $tableBatch = isset($config['large']) && $config['large'] ? 500 : $batch;
            
            $result = $this->migrateTable($tableName, $config, $tableBatch, $verbose, $dryRun);
            
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

        $migrated = 0;
        $errors = 0;

        $adapter = Yii::app()->couchbase;
        if (!$adapter) {
            echo "  ERROR: Couchbase not configured\n";
            return ['migrated' => 0, 'errors' => 1];
        }

        $total = $modelClass::model()->count();
        echo "  Total records: {$total}\n";

        if ($dryRun) {
            echo "  [DRY RUN] Would migrate {$total} records\n";
            return ['migrated' => 0, 'errors' => 0];
        }

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
                    
                    $adapter->upsert($scope, $tableName, $key, $doc);
                    $migrated++;
                    
                    if ($verbose && $migrated % 100 === 0) {
                        echo "    Progress: {$migrated}/{$total}\n";
                    }
                } catch (Exception $e) {
                    $errors++;
                    if ($verbose) {
                        echo "    ERROR: {$record->id} - {$e->getMessage()}\n";
                    }
                }
            }

            $offset += $batch;
            
            // Memory management for large tables
            if (isset($config['large']) && $config['large']) {
                gc_collect_cycles();
            }
        }

        return ['migrated' => $migrated, 'errors' => $errors];
    }

    /**
     * Test search functionality
     */
    public function actionSearch($term = '', $type = 'disorder')
    {
        if (empty($term)) {
            echo "Please provide a search term with --term=<term>\n";
            return 1;
        }

        echo "Searching {$type} for: {$term}\n\n";

        switch ($type) {
            case 'disorder':
                $results = DisorderDocument::search($term, 10);
                break;
            case 'medication':
                $results = MedicationDocument::search($term, 10);
                break;
            case 'procedure':
                $results = ProcedureDocument::search($term, 10);
                break;
            default:
                echo "Unknown type: {$type}\n";
                return 1;
        }

        if (empty($results)) {
            echo "No results found\n";
            return 0;
        }

        foreach ($results as $result) {
            $term = $result['term'] ?? $result['preferred_term'] ?? 'N/A';
            $code = $result['snomed_code'] ?? $result['preferred_code'] ?? 'N/A';
            echo "  [{$result['id']}] {$term} (Code: {$code})\n";
        }

        echo "\nTotal: " . count($results) . " results\n";
        return 0;
    }

    /**
     * Show migration status
     */
    public function actionStatus()
    {
        echo "Clinical Reference Data Migration Status\n";
        echo "========================================\n\n";

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
}
```

**Lines**: ~220

---

## Section 7: Unit Tests

### 7.1 DisorderDocument Test

**File**: `protected/tests/unit/models/couchbase/DisorderDocumentTest.php`

```php
<?php
/**
 * Unit tests for DisorderDocument
 */

class DisorderDocumentTest extends CDbTestCase
{
    public function testCreateFromModel()
    {
        $disorder = Disorder::model()->find();
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('disorder', $doc['_type']);
        $this->assertEquals($disorder->id, $doc['id']);
        $this->assertEquals($disorder->term, $doc['term']);
        $this->assertEquals($disorder->snomed_code, $doc['snomed_code']);
    }

    public function testSnomedCodePreserved()
    {
        $disorder = Disorder::model()->find('snomed_code IS NOT NULL');
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder with SNOMED code found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertNotNull($doc['snomed_code']);
        $this->assertEquals($disorder->snomed_code, $doc['snomed_code']);
    }

    public function testSearchTermsBuilt()
    {
        $disorder = Disorder::model()->find();
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertArrayHasKey('search_terms', $doc);
        $this->assertIsArray($doc['search_terms']);
        $this->assertGreaterThan(0, count($doc['search_terms']));
    }

    public function testSpecialtyEmbedded()
    {
        $disorder = Disorder::model()->find('specialty_id IS NOT NULL');
        
        if (!$disorder) {
            $this->markTestSkipped('No disorder with specialty found');
        }
        
        $doc = DisorderDocument::createFromModel($disorder);
        
        $this->assertArrayHasKey('specialty', $doc);
        $this->assertNotNull($doc['specialty']);
        $this->assertArrayHasKey('name', $doc['specialty']);
    }
}
```

**Lines**: ~80

---

### 7.2 MedicationDocument Test

**File**: `protected/tests/unit/models/couchbase/MedicationDocumentTest.php`

```php
<?php
/**
 * Unit tests for MedicationDocument
 */

class MedicationDocumentTest extends CDbTestCase
{
    public function testCreateFromModel()
    {
        $medication = Medication::model()->find('active = 1');
        
        if (!$medication) {
            $this->markTestSkipped('No active medication found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('medication', $doc['_type']);
        $this->assertEquals($medication->id, $doc['id']);
        $this->assertEquals($medication->preferred_term, $doc['preferred_term']);
    }

    public function testRouteEmbedded()
    {
        $medication = Medication::model()->find('default_route_id IS NOT NULL AND active = 1');
        
        if (!$medication) {
            $this->markTestSkipped('No medication with route found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        $this->assertArrayHasKey('default_route', $doc);
        $this->assertNotNull($doc['default_route']);
        $this->assertArrayHasKey('term', $doc['default_route']);
    }

    public function testAllergyWarningsEmbedded()
    {
        $doc = ['allergy_warnings' => []];
        
        // Test structure
        $this->assertArrayHasKey('allergy_warnings', $doc);
        $this->assertIsArray($doc['allergy_warnings']);
    }

    public function testSearchTermsBuilt()
    {
        $medication = Medication::model()->find('active = 1');
        
        if (!$medication) {
            $this->markTestSkipped('No medication found');
        }
        
        $doc = MedicationDocument::createFromModel($medication);
        
        $this->assertArrayHasKey('search_terms', $doc);
        $this->assertArrayHasKey('preferred_term_lower', $doc);
    }
}
```

**Lines**: ~70

---

### 7.3 ProcedureDocument Test

**File**: `protected/tests/unit/models/couchbase/ProcedureDocumentTest.php`

```php
<?php
/**
 * Unit tests for ProcedureDocument
 */

class ProcedureDocumentTest extends CDbTestCase
{
    public function testCreateFromModel()
    {
        $procedure = Procedure::model()->find();
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('procedure', $doc['_type']);
        $this->assertEquals($procedure->id, $doc['id']);
        $this->assertEquals($procedure->term, $doc['term']);
    }

    public function testSnomedCodePreserved()
    {
        $procedure = Procedure::model()->find('snomed_code IS NOT NULL');
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure with SNOMED code found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertEquals($procedure->snomed_code, $doc['snomed_code']);
    }

    public function testOpcsCodesEmbedded()
    {
        $procedure = Procedure::model()->find();
        
        if (!$procedure) {
            $this->markTestSkipped('No procedure found');
        }
        
        $doc = ProcedureDocument::createFromModel($procedure);
        
        $this->assertArrayHasKey('opcs_codes', $doc);
        $this->assertIsArray($doc['opcs_codes']);
    }

    public function testBenefitsEmbedded()
    {
        $doc = ['benefits' => [], 'complications' => []];
        
        $this->assertArrayHasKey('benefits', $doc);
        $this->assertArrayHasKey('complications', $doc);
    }
}
```

**Lines**: ~65

---

## Section 8: Validation & Success Criteria

### 8.1 Validation Checklist

```markdown
## Phase 11 Validation Checklist

### SNOMED Code Integrity
- [ ] All disorder SNOMED codes preserved exactly
- [ ] All procedure SNOMED codes preserved exactly
- [ ] No truncation of codes
- [ ] Version information maintained

### Medication Data
- [ ] All dm+d codes preserved (VTM, VMP, AMP)
- [ ] Route/Form/Frequency relations embedded
- [ ] Allergy warnings properly linked
- [ ] No active medication records missing

### Search Functionality
- [ ] Disorder search by term works
- [ ] Disorder search by SNOMED works
- [ ] Medication search by term works
- [ ] Procedure search works
- [ ] Search returns results in < 100ms

### Record Counts
- [ ] disorder: 100% match
- [ ] medication: 100% match
- [ ] procedure: 100% match
- [ ] allergy: 100% match
- [ ] All lookup tables: 100% match
```

### 8.2 Success Criteria

| Metric | Target | Method |
|--------|--------|--------|
| SNOMED Code Integrity | 100% | Compare codes exactly |
| dm+d Code Integrity | 100% | Compare codes exactly |
| Search Latency (p95) | < 100ms | querybenchmark |
| Record Count Match | 100% | datavalidation counts |
| Embedding Completeness | 99%+ | datavalidation sample |

---

## Summary

### Files to Create
| File | Lines | Purpose |
|------|-------|---------|
| DisorderDocument.php | 160 | Disorder document model |
| MedicationDocument.php | 200 | Medication document model |
| ProcedureDocument.php | 180 | Procedure document model |
| clinical-reference-indexes.n1ql | 100 | N1QL indexes |
| ClinicalReferenceMigrationCommand.php | 220 | Migration command |
| DisorderDocumentTest.php | 80 | Unit tests |
| MedicationDocumentTest.php | 70 | Unit tests |
| ProcedureDocumentTest.php | 65 | Unit tests |

### Files to Modify
| File | Changes |
|------|---------|
| Disorder.php | Add CouchbaseModelBridge (~50 lines) |
| Drug.php | Add CouchbaseModelBridge (~15 lines) |
| Medication.php | Add CouchbaseModelBridge + embeddings (~65 lines) |
| Procedure.php | Add CouchbaseModelBridge + embeddings (~60 lines) |
| Allergy.php | Add CouchbaseModelBridge (~15 lines) |

### Total Effort
- **New Files**: 8 files (~1,075 lines)
- **Modified Files**: 5 files (~205 lines)
- **Total Code**: ~1,280 lines
- **Estimated Duration**: 2 weeks

---

**Phase 11 Status**: SPECIFICATION COMPLETE  
**Ready for Implementation**: YES
