# Phase 3: Data Modeling & Schema Translation

## Overview
This phase defines how MariaDB relational schemas will be translated to Couchbase document models. This is critical for maintaining data integrity and query performance.

## Prerequisites
- Phase 2 completed (Abstract Database Layer operational)
- Understanding of existing MySQL schema
- Couchbase indexing strategy defined

## Dependencies
- Phase 2: Abstract Database Layer

## Tasks

### 3.1 Schema Analysis

#### 3.1.1 Analyze Core Tables
**Document**: `/docs/migration-mariadb-to-couchbase/schema-analysis/core-tables.md`

Key tables to analyze:
```
Core System:
- patient (~15 columns)
- user (~40 columns)
- episode (~12 columns)
- event (~15 columns)
- event_type (~10 columns)
- element_type (~15 columns)
- firm (~10 columns)
- site (~15 columns)
- institution (~10 columns)
- contact (~20 columns)
- address (~15 columns)

Clinical Data:
- et_ophciexamination_* (~50+ tables)
- disorder (~10 columns)
- procedure (~15 columns)
- medication (~20 columns)
- allergy (~10 columns)

Operational:
- audit (~15 columns)
- audit_trail (~10 columns)
- user_session (~8 columns)
- setting_metadata (~15 columns)
```

**Acceptance Criteria**:
- [ ] All core tables documented
- [ ] Column types identified
- [ ] Foreign key relationships mapped
- [ ] Indexes documented

#### 3.1.2 Relationship Analysis Script
**File**: `/protected/scripts/couchbase/analyze-relationships.php`

```php
<?php
/**
 * Analyze MySQL table relationships for Couchbase migration
 */

require_once(dirname(__FILE__) . '/../../yiic.php');

class RelationshipAnalyzer
{
    private $db;
    private $relationships = [];
    
    public function __construct()
    {
        $this->db = Yii::app()->db;
    }
    
    public function analyze()
    {
        $tables = $this->getTables();
        
        foreach ($tables as $table) {
            $this->analyzeTable($table);
        }
        
        return $this->relationships;
    }
    
    private function getTables()
    {
        return $this->db->createCommand("SHOW TABLES")->queryColumn();
    }
    
    private function analyzeTable($tableName)
    {
        // Get foreign keys
        $sql = "
            SELECT 
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ";
        
        $fks = $this->db->createCommand($sql)
            ->queryAll([':table' => $tableName]);
        
        foreach ($fks as $fk) {
            $this->relationships[$tableName][] = [
                'type' => 'belongs_to',
                'column' => $fk['COLUMN_NAME'],
                'references_table' => $fk['REFERENCED_TABLE_NAME'],
                'references_column' => $fk['REFERENCED_COLUMN_NAME'],
            ];
            
            // Reverse relationship
            $this->relationships[$fk['REFERENCED_TABLE_NAME']][] = [
                'type' => 'has_many',
                'table' => $tableName,
                'column' => $fk['COLUMN_NAME'],
            ];
        }
        
        // Get row count
        $count = $this->db->createCommand("SELECT COUNT(*) FROM `{$tableName}`")->queryScalar();
        
        echo "{$tableName}: {$count} rows, " . count($fks) . " foreign keys\n";
    }
    
    public function exportToJson($filename)
    {
        file_put_contents($filename, json_encode($this->relationships, JSON_PRETTY_PRINT));
    }
}

$analyzer = new RelationshipAnalyzer();
$relationships = $analyzer->analyze();
$analyzer->exportToJson(dirname(__FILE__) . '/relationship-map.json');
```

**Acceptance Criteria**:
- [ ] Script runs successfully
- [ ] All relationships identified
- [ ] JSON export generated

### 3.2 Document Model Design

#### 3.2.1 Core Document Models

##### Patient Document Model
**File**: `/protected/models/couchbase/schemas/patient.json`

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "title": "Patient Document",
    "type": "object",
    "required": ["_type", "hos_num", "dob"],
    "properties": {
        "_type": {
            "type": "string",
            "const": "patient"
        },
        "_created": {
            "type": "string",
            "format": "date-time"
        },
        "_modified": {
            "type": "string",
            "format": "date-time"
        },
        "hos_num": {
            "type": "string",
            "description": "Hospital number"
        },
        "nhs_num": {
            "type": ["string", "null"],
            "description": "NHS number"
        },
        "title": {
            "type": ["string", "null"]
        },
        "first_name": {
            "type": "string"
        },
        "last_name": {
            "type": "string"
        },
        "dob": {
            "type": "string",
            "format": "date"
        },
        "date_of_death": {
            "type": ["string", "null"],
            "format": "date"
        },
        "gender": {
            "type": "string",
            "enum": ["M", "F", "U"]
        },
        "ethnic_group_id": {
            "type": ["string", "null"],
            "description": "Reference to ethnic_group document"
        },
        "contact": {
            "type": "object",
            "description": "Embedded contact details",
            "properties": {
                "primary_phone": {"type": ["string", "null"]},
                "mobile_phone": {"type": ["string", "null"]},
                "email": {"type": ["string", "null"]},
                "address": {
                    "type": "object",
                    "properties": {
                        "address1": {"type": ["string", "null"]},
                        "address2": {"type": ["string", "null"]},
                        "city": {"type": ["string", "null"]},
                        "postcode": {"type": ["string", "null"]},
                        "county": {"type": ["string", "null"]},
                        "country_id": {"type": ["string", "null"]}
                    }
                }
            }
        },
        "gp_id": {
            "type": ["string", "null"],
            "description": "Reference to GP (practitioner) document"
        },
        "practice_id": {
            "type": ["string", "null"],
            "description": "Reference to practice document"
        },
        "is_deceased": {
            "type": "boolean",
            "default": false
        },
        "institution_id": {
            "type": "string",
            "description": "Reference to institution document"
        },
        "identifiers": {
            "type": "array",
            "description": "Additional patient identifiers",
            "items": {
                "type": "object",
                "properties": {
                    "type": {"type": "string"},
                    "value": {"type": "string"}
                }
            }
        },
        "created_user_id": {"type": "string"},
        "last_modified_user_id": {"type": "string"},
        "created_date": {"type": "string", "format": "date-time"},
        "last_modified_date": {"type": "string", "format": "date-time"}
    }
}
```

##### Episode Document Model
**File**: `/protected/models/couchbase/schemas/episode.json`

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "title": "Episode Document",
    "type": "object",
    "required": ["_type", "patient_id", "firm_id"],
    "properties": {
        "_type": {
            "type": "string",
            "const": "episode"
        },
        "patient_id": {
            "type": "string",
            "description": "Reference to patient document"
        },
        "firm_id": {
            "type": "string",
            "description": "Reference to firm document"
        },
        "start_date": {
            "type": "string",
            "format": "date"
        },
        "end_date": {
            "type": ["string", "null"],
            "format": "date"
        },
        "episode_status_id": {
            "type": "string"
        },
        "subspecialty_id": {
            "type": "string"
        },
        "support_services": {
            "type": "boolean",
            "default": false
        },
        "disorder_id": {
            "type": ["string", "null"],
            "description": "Principal diagnosis"
        },
        "disorder_date": {
            "type": ["string", "null"],
            "format": "date"
        },
        "eye_id": {
            "type": ["string", "null"]
        },
        "change_tracker": {
            "type": "object",
            "description": "Track last change user for specific fields"
        }
    }
}
```

##### Event Document Model
**File**: `/protected/models/couchbase/schemas/event.json`

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "title": "Event Document",
    "type": "object",
    "required": ["_type", "episode_id", "event_type_id"],
    "properties": {
        "_type": {
            "type": "string",
            "const": "event"
        },
        "episode_id": {
            "type": "string",
            "description": "Reference to episode document"
        },
        "event_type_id": {
            "type": "string",
            "description": "Reference to event_type document"
        },
        "event_date": {
            "type": "string",
            "format": "date-time"
        },
        "info": {
            "type": ["string", "null"]
        },
        "deleted": {
            "type": "boolean",
            "default": false
        },
        "delete_reason": {
            "type": ["string", "null"]
        },
        "delete_pending": {
            "type": "boolean",
            "default": false
        },
        "institution_id": {
            "type": "string"
        },
        "site_id": {
            "type": "string"
        },
        "firm_id": {
            "type": ["string", "null"]
        },
        "is_automated": {
            "type": "boolean",
            "default": false
        },
        "automated_source": {
            "type": ["string", "null"]
        },
        "parent_id": {
            "type": ["string", "null"],
            "description": "Reference to parent event if linked"
        },
        "worklist_patient_id": {
            "type": ["string", "null"]
        },
        "elements": {
            "type": "array",
            "description": "Embedded or referenced elements",
            "items": {
                "type": "object",
                "properties": {
                    "element_type_id": {"type": "string"},
                    "data": {"type": "object"}
                }
            }
        }
    }
}
```

**Acceptance Criteria**:
- [ ] All core document schemas defined
- [ ] Required fields identified
- [ ] References clearly documented
- [ ] Embedded vs referenced data decisions documented

#### 3.2.2 Embedding vs Referencing Strategy

**File**: `/docs/migration-mariadb-to-couchbase/schema-analysis/embedding-strategy.md`

```markdown
# Embedding vs Referencing Strategy

## Principles

1. **Embed when**:
   - Data is always accessed together
   - Data belongs exclusively to parent
   - Data size is bounded
   - Updates are infrequent
   
2. **Reference when**:
   - Data is shared across multiple documents
   - Data changes independently
   - Data size is unbounded
   - Need to query data independently

## Decisions

### Patient Document
| Related Data | Strategy | Rationale |
|--------------|----------|-----------|
| Contact | Embed | Always accessed with patient |
| Address | Embed | One address per patient |
| GP | Reference | GP shared across patients |
| Episodes | Reference | Large, changes independently |
| Allergies | Reference | Clinical data, queried separately |

### Episode Document
| Related Data | Strategy | Rationale |
|--------------|----------|-----------|
| Patient | Reference | Patient data large |
| Events | Reference | Many events, queried separately |
| Diagnosis | Reference | Shared disorder records |
| Firm | Reference | Shared organizational data |

### Event Document
| Related Data | Strategy | Rationale |
|--------------|----------|-----------|
| Episode | Reference | Episode shared |
| Elements | Hybrid | Small elements embed, large reference |
| Event Type | Reference | Shared lookup data |
| Site | Reference | Shared organizational data |

### Examination Elements
| Element | Strategy | Rationale |
|---------|----------|-----------|
| Visual Acuity | Embed in Event | Always part of exam |
| IOP | Embed in Event | Small, fixed structure |
| Refraction | Embed in Event | Bounded data |
| Fundus Drawing | Reference | Large binary data |
| OCT Images | Reference | Large binary data |
```

**Acceptance Criteria**:
- [ ] Each relationship has documented strategy
- [ ] Rationale provided for each decision
- [ ] Performance implications considered

### 3.3 Collection Mapping

#### 3.3.1 MySQL Table to Couchbase Collection Mapping
**File**: `/protected/config/couchbase-collection-map.php`

```php
<?php
/**
 * Maps MySQL tables to Couchbase scopes and collections
 */

return [
    // Core scope
    'core' => [
        'patient' => [
            'source_tables' => ['patient', 'contact', 'address'],
            'document_type' => 'patient',
            'key_pattern' => 'patient::{id}',
        ],
        'user' => [
            'source_tables' => ['user', 'user_authentication'],
            'document_type' => 'user',
            'key_pattern' => 'user::{id}',
        ],
        'episode' => [
            'source_tables' => ['episode'],
            'document_type' => 'episode',
            'key_pattern' => 'episode::{id}',
        ],
        'event' => [
            'source_tables' => ['event'],
            'document_type' => 'event',
            'key_pattern' => 'event::{id}',
        ],
        'firm' => [
            'source_tables' => ['firm'],
            'document_type' => 'firm',
            'key_pattern' => 'firm::{id}',
        ],
        'site' => [
            'source_tables' => ['site', 'site_version'],
            'document_type' => 'site',
            'key_pattern' => 'site::{id}',
        ],
        'institution' => [
            'source_tables' => ['institution'],
            'document_type' => 'institution',
            'key_pattern' => 'institution::{id}',
        ],
    ],
    
    // Clinical scope
    'clinical' => [
        'examination' => [
            'source_tables' => [
                'et_ophciexamination_*',
            ],
            'document_type' => 'examination',
            'key_pattern' => 'examination::{event_id}',
            'embed_elements' => true,
        ],
        'diagnosis' => [
            'source_tables' => ['disorder', 'secondary_diagnosis'],
            'document_type' => 'diagnosis',
            'key_pattern' => 'diagnosis::{id}',
        ],
        'procedure' => [
            'source_tables' => ['proc', 'procedure_benefit', 'procedure_complication'],
            'document_type' => 'procedure',
            'key_pattern' => 'procedure::{id}',
        ],
        'medication' => [
            'source_tables' => ['medication', 'medication_route', 'medication_frequency'],
            'document_type' => 'medication',
            'key_pattern' => 'medication::{id}',
        ],
        'allergy' => [
            'source_tables' => ['patient_allergy_assignment', 'allergy'],
            'document_type' => 'allergy',
            'key_pattern' => 'allergy::{id}',
        ],
    ],
    
    // Correspondence scope
    'correspondence' => [
        'letter' => [
            'source_tables' => ['et_ophcocorrespondence_letter'],
            'document_type' => 'letter',
            'key_pattern' => 'letter::{id}',
        ],
        'message' => [
            'source_tables' => ['et_ophcomessaging_message'],
            'document_type' => 'message',
            'key_pattern' => 'message::{id}',
        ],
        'document' => [
            'source_tables' => ['et_ophcodocument_document', 'protected_file'],
            'document_type' => 'document',
            'key_pattern' => 'document::{id}',
        ],
    ],
    
    // Booking scope
    'booking' => [
        'operation' => [
            'source_tables' => [
                'et_ophtroperationbooking_operation',
                'ophtroperationbooking_operation_booking',
            ],
            'document_type' => 'operation',
            'key_pattern' => 'operation::{id}',
        ],
        'session' => [
            'source_tables' => ['ophtroperationbooking_operation_session'],
            'document_type' => 'session',
            'key_pattern' => 'session::{id}',
        ],
        'theatre' => [
            'source_tables' => ['ophtroperationbooking_operation_theatre'],
            'document_type' => 'theatre',
            'key_pattern' => 'theatre::{id}',
        ],
    ],
    
    // Admin scope
    'admin' => [
        'audit' => [
            'source_tables' => ['audit', 'audit_trail'],
            'document_type' => 'audit',
            'key_pattern' => 'audit::{id}',
        ],
        'setting' => [
            'source_tables' => ['setting_metadata', 'setting_*'],
            'document_type' => 'setting',
            'key_pattern' => 'setting::{key}',
        ],
    ],
    
    // Reference scope
    'reference' => [
        'event_type' => [
            'source_tables' => ['event_type'],
            'document_type' => 'event_type',
            'key_pattern' => 'event_type::{id}',
        ],
        'element_type' => [
            'source_tables' => ['element_type'],
            'document_type' => 'element_type',
            'key_pattern' => 'element_type::{id}',
        ],
        'specialty' => [
            'source_tables' => ['specialty', 'subspecialty'],
            'document_type' => 'specialty',
            'key_pattern' => 'specialty::{id}',
        ],
        'ethnic_group' => [
            'source_tables' => ['ethnic_group'],
            'document_type' => 'ethnic_group',
            'key_pattern' => 'ethnic_group::{id}',
        ],
        'gender' => [
            'source_tables' => ['gender'],
            'document_type' => 'gender',
            'key_pattern' => 'gender::{id}',
        ],
    ],
];
```

**Acceptance Criteria**:
- [ ] All significant tables mapped
- [ ] Scopes logically organized
- [ ] Key patterns consistent

### 3.4 Index Design

#### 3.4.1 Primary Indexes
**File**: `/protected/scripts/couchbase/indexes/primary-indexes.n1ql`

```sql
-- Core indexes
CREATE INDEX idx_patient_type ON `openeyes`.`core`.`patient`(_type) USING GSI;
CREATE INDEX idx_patient_hos_num ON `openeyes`.`core`.`patient`(hos_num) USING GSI;
CREATE INDEX idx_patient_nhs_num ON `openeyes`.`core`.`patient`(nhs_num) WHERE nhs_num IS NOT NULL USING GSI;
CREATE INDEX idx_patient_name ON `openeyes`.`core`.`patient`(last_name, first_name) USING GSI;
CREATE INDEX idx_patient_dob ON `openeyes`.`core`.`patient`(dob) USING GSI;
CREATE INDEX idx_patient_institution ON `openeyes`.`core`.`patient`(institution_id) USING GSI;

CREATE INDEX idx_episode_patient ON `openeyes`.`core`.`episode`(patient_id) USING GSI;
CREATE INDEX idx_episode_firm ON `openeyes`.`core`.`episode`(firm_id) USING GSI;
CREATE INDEX idx_episode_subspecialty ON `openeyes`.`core`.`episode`(subspecialty_id) USING GSI;
CREATE INDEX idx_episode_status ON `openeyes`.`core`.`episode`(episode_status_id, start_date) USING GSI;

CREATE INDEX idx_event_episode ON `openeyes`.`core`.`event`(episode_id) USING GSI;
CREATE INDEX idx_event_type ON `openeyes`.`core`.`event`(event_type_id) USING GSI;
CREATE INDEX idx_event_date ON `openeyes`.`core`.`event`(event_date DESC) USING GSI;
CREATE INDEX idx_event_deleted ON `openeyes`.`core`.`event`(deleted, delete_pending) USING GSI;
CREATE INDEX idx_event_institution ON `openeyes`.`core`.`event`(institution_id, site_id) USING GSI;

CREATE INDEX idx_user_username ON `openeyes`.`core`.`user`(username) USING GSI;
CREATE INDEX idx_user_active ON `openeyes`.`core`.`user`(active) WHERE active = true USING GSI;
```

#### 3.4.2 Clinical Indexes
**File**: `/protected/scripts/couchbase/indexes/clinical-indexes.n1ql`

```sql
-- Clinical indexes
CREATE INDEX idx_examination_event ON `openeyes`.`clinical`.`examination`(event_id) USING GSI;
CREATE INDEX idx_examination_date ON `openeyes`.`clinical`.`examination`(_modified DESC) USING GSI;

CREATE INDEX idx_diagnosis_disorder ON `openeyes`.`clinical`.`diagnosis`(disorder_id) USING GSI;
CREATE INDEX idx_diagnosis_patient ON `openeyes`.`clinical`.`diagnosis`(patient_id) USING GSI;

CREATE INDEX idx_medication_patient ON `openeyes`.`clinical`.`medication`(patient_id) USING GSI;
CREATE INDEX idx_medication_drug ON `openeyes`.`clinical`.`medication`(drug_id) USING GSI;

CREATE INDEX idx_allergy_patient ON `openeyes`.`clinical`.`allergy`(patient_id) USING GSI;
```

#### 3.4.3 Search Indexes (FTS)
**File**: `/protected/scripts/couchbase/indexes/fts-indexes.json`

```json
{
    "name": "patient_search",
    "type": "fulltext-index",
    "sourceType": "couchbase",
    "sourceName": "openeyes",
    "params": {
        "mapping": {
            "default_mapping": {
                "enabled": false
            },
            "types": {
                "core.patient": {
                    "enabled": true,
                    "dynamic": false,
                    "properties": {
                        "hos_num": {
                            "enabled": true,
                            "dynamic": false,
                            "fields": [{
                                "name": "hos_num",
                                "type": "text",
                                "analyzer": "keyword",
                                "index": true
                            }]
                        },
                        "nhs_num": {
                            "enabled": true,
                            "dynamic": false,
                            "fields": [{
                                "name": "nhs_num",
                                "type": "text",
                                "analyzer": "keyword",
                                "index": true
                            }]
                        },
                        "first_name": {
                            "enabled": true,
                            "dynamic": false,
                            "fields": [{
                                "name": "first_name",
                                "type": "text",
                                "analyzer": "standard",
                                "index": true
                            }]
                        },
                        "last_name": {
                            "enabled": true,
                            "dynamic": false,
                            "fields": [{
                                "name": "last_name",
                                "type": "text",
                                "analyzer": "standard",
                                "index": true
                            }]
                        },
                        "dob": {
                            "enabled": true,
                            "dynamic": false,
                            "fields": [{
                                "name": "dob",
                                "type": "datetime",
                                "index": true
                            }]
                        }
                    }
                }
            }
        }
    }
}
```

**Acceptance Criteria**:
- [ ] All search patterns identified and indexed
- [ ] Query performance tested
- [ ] Index sizes estimated

### 3.5 Data Transformation Rules

#### 3.5.1 Type Conversion Rules
**File**: `/protected/components/database/transformers/TypeTransformer.php`

```php
<?php
/**
 * Type transformation rules for MySQL to Couchbase
 */

namespace OE\Database\Transformers;

class TypeTransformer
{
    /**
     * MySQL type to JSON type mapping
     */
    private static $typeMap = [
        'int' => 'integer',
        'tinyint' => 'integer',
        'smallint' => 'integer',
        'mediumint' => 'integer',
        'bigint' => 'integer',
        'decimal' => 'number',
        'float' => 'number',
        'double' => 'number',
        'char' => 'string',
        'varchar' => 'string',
        'text' => 'string',
        'mediumtext' => 'string',
        'longtext' => 'string',
        'date' => 'string', // ISO 8601 format
        'datetime' => 'string', // ISO 8601 format
        'timestamp' => 'string', // ISO 8601 format
        'time' => 'string',
        'tinyint(1)' => 'boolean',
        'enum' => 'string',
        'set' => 'array',
        'blob' => 'string', // Base64 encoded
        'json' => 'object',
    ];
    
    /**
     * Transform a MySQL value to Couchbase JSON value
     */
    public static function transform($value, string $mysqlType)
    {
        if ($value === null) {
            return null;
        }
        
        $baseType = preg_replace('/\(\d+\)/', '', strtolower($mysqlType));
        
        switch ($baseType) {
            case 'tinyint':
                // Check if boolean (tinyint(1))
                if (strpos($mysqlType, '(1)') !== false) {
                    return (bool) $value;
                }
                return (int) $value;
                
            case 'int':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
                return (int) $value;
                
            case 'decimal':
            case 'float':
            case 'double':
                return (float) $value;
                
            case 'date':
                return date('Y-m-d', strtotime($value));
                
            case 'datetime':
            case 'timestamp':
                return date('c', strtotime($value)); // ISO 8601
                
            case 'blob':
            case 'mediumblob':
            case 'longblob':
                return base64_encode($value);
                
            case 'json':
                return json_decode($value, true);
                
            case 'set':
                return explode(',', $value);
                
            default:
                return (string) $value;
        }
    }
    
    /**
     * Transform Couchbase value back to MySQL format
     */
    public static function reverseTransform($value, string $mysqlType)
    {
        if ($value === null) {
            return null;
        }
        
        $baseType = preg_replace('/\(\d+\)/', '', strtolower($mysqlType));
        
        switch ($baseType) {
            case 'tinyint':
                if (strpos($mysqlType, '(1)') !== false) {
                    return $value ? 1 : 0;
                }
                return (int) $value;
                
            case 'datetime':
            case 'timestamp':
                return date('Y-m-d H:i:s', strtotime($value));
                
            case 'blob':
            case 'mediumblob':
            case 'longblob':
                return base64_decode($value);
                
            case 'json':
                return json_encode($value);
                
            case 'set':
                return implode(',', (array) $value);
                
            default:
                return $value;
        }
    }
}
```

**Acceptance Criteria**:
- [ ] All MySQL types have transformation rules
- [ ] Round-trip transformation preserves data
- [ ] Edge cases handled (nulls, empty strings, etc.)

### 3.6 Document Model Classes

#### 3.6.1 Base Couchbase Model
**File**: `/protected/models/CouchbaseActiveRecord.php`

```php
<?php
/**
 * Base class for Couchbase document models
 */

abstract class CouchbaseActiveRecord extends CModel
{
    protected $_attributes = [];
    protected $_isNewRecord = true;
    protected $_pk;
    
    /**
     * Document type identifier
     */
    abstract public function documentType(): string;
    
    /**
     * Scope for this document type
     */
    abstract public function scope(): string;
    
    /**
     * Collection name
     */
    abstract public function collectionName(): string;
    
    /**
     * Define attribute rules for validation
     */
    public function rules()
    {
        return [];
    }
    
    /**
     * Define attribute labels
     */
    public function attributeLabels()
    {
        return [];
    }
    
    /**
     * Get all attribute names
     */
    public function attributeNames()
    {
        return array_keys($this->_attributes);
    }
    
    /**
     * Get the document key
     */
    public function getDocumentKey(): string
    {
        return $this->collectionName() . '::' . $this->getPrimaryKey();
    }
    
    /**
     * Get primary key
     */
    public function getPrimaryKey()
    {
        return $this->_pk;
    }
    
    /**
     * Set primary key
     */
    public function setPrimaryKey($pk)
    {
        $this->_pk = $pk;
    }
    
    /**
     * Check if new record
     */
    public function getIsNewRecord(): bool
    {
        return $this->_isNewRecord;
    }
    
    /**
     * Get the Couchbase connection
     */
    protected function getConnection(): CouchbaseConnection
    {
        return Yii::app()->couchbase;
    }
    
    /**
     * Get the collection
     */
    protected function getCollection()
    {
        return $this->getConnection()->getCollection(
            $this->scope(),
            $this->collectionName()
        );
    }
    
    /**
     * Find by primary key
     */
    public static function findByPk($pk)
    {
        $model = new static();
        
        try {
            $result = $model->getCollection()->get($model->collectionName() . '::' . $pk);
            $model->_attributes = (array) $result->content();
            $model->_pk = $pk;
            $model->_isNewRecord = false;
            return $model;
        } catch (\Couchbase\Exception\DocumentNotFoundException $e) {
            return null;
        }
    }
    
    /**
     * Save the document
     */
    public function save($runValidation = true)
    {
        if ($runValidation && !$this->validate()) {
            return false;
        }
        
        $data = $this->_attributes;
        $data['_type'] = $this->documentType();
        $data['_modified'] = date('c');
        
        if ($this->_isNewRecord) {
            if (!$this->_pk) {
                $this->_pk = uniqid('', true);
            }
            $data['_created'] = date('c');
            $this->getCollection()->insert($this->getDocumentKey(), $data);
            $this->_isNewRecord = false;
        } else {
            $this->getCollection()->replace($this->getDocumentKey(), $data);
        }
        
        return true;
    }
    
    /**
     * Delete the document
     */
    public function delete()
    {
        if ($this->_isNewRecord) {
            return false;
        }
        
        try {
            $this->getCollection()->remove($this->getDocumentKey());
            return true;
        } catch (\Exception $e) {
            Yii::log("Delete failed: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * Magic getter
     */
    public function __get($name)
    {
        if (array_key_exists($name, $this->_attributes)) {
            return $this->_attributes[$name];
        }
        return parent::__get($name);
    }
    
    /**
     * Magic setter
     */
    public function __set($name, $value)
    {
        $this->_attributes[$name] = $value;
    }
    
    /**
     * Magic isset
     */
    public function __isset($name)
    {
        return isset($this->_attributes[$name]);
    }
}
```

**Acceptance Criteria**:
- [ ] Base model provides CRUD operations
- [ ] Validation integration works
- [ ] Magic methods provide attribute access
- [ ] Document key generation is consistent

## Testing Criteria

### Schema Validation
- [ ] All JSON schemas are valid
- [ ] Required fields properly defined
- [ ] References are bidirectional

### Transformation Tests
- [ ] All MySQL types transform correctly
- [ ] Round-trip transformation preserves data
- [ ] Date/time formats are consistent

### Model Tests
- [ ] CouchbaseActiveRecord saves documents
- [ ] CouchbaseActiveRecord finds documents
- [ ] Validation works correctly

## Rollback Plan

1. Document models can be removed without affecting existing code
2. Schema files are documentation only
3. No production data is affected in this phase

## Definition of Done

- [ ] All document schemas defined
- [ ] Collection mapping complete
- [ ] Index design documented
- [ ] Type transformations tested
- [ ] Base model class functional
- [ ] Documentation complete

---

*Phase 3 Completion Sign-off:*
- [ ] Technical Lead
- [ ] Data Architect

*Estimated Duration: 3-4 weeks*
