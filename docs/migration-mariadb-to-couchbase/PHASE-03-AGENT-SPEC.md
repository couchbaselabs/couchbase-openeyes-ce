# Phase 3: Data Modeling & Schema Translation - Agent Executable Specification

**Version**: 1.0.0  
**Date**: December 19, 2025  
**Status**: READY FOR IMPLEMENTATION  
**Estimated Duration**: 3-4 weeks  
**Total Tasks**: 18 tasks across 6 sections  

---

## Executive Summary

This specification provides step-by-step instructions for implementing the data modeling and schema translation layer for the MariaDB to Couchbase migration. This phase converts relational schemas into document models while maintaining data integrity and query performance.

---

## Prerequisites

### Required Phase Completions
- [x] Phase 1: Infrastructure & Couchbase Setup (COMPLETED)
- [x] Phase 2: Abstract Database Layer (COMPLETED)

### Required Running Services
```bash
# Verify Docker containers are running
docker compose -f .devcontainer/docker-compose.yml ps
# Expected: web (port 7777), db (port 3333) both healthy

# Verify MariaDB connectivity
docker compose -f .devcontainer/docker-compose.yml exec -T db mysql -u openeyes -popeneyes openeyes -e "SELECT 1;"
```

### Required Files from Previous Phases
| File | Purpose | Location |
|------|---------|----------|
| `CouchbaseConnection.php` | Couchbase connectivity | `protected/components/CouchbaseConnection.php` |
| `DatabaseAdapterInterface.php` | Adapter contract | `protected/components/database/DatabaseAdapterInterface.php` |
| `CouchbaseAdapter.php` | Couchbase implementation | `protected/components/database/CouchbaseAdapter.php` |
| `MariaDbAdapter.php` | MariaDB implementation | `protected/components/database/MariaDbAdapter.php` |
| `couchbase.php` | Couchbase config | `protected/config/couchbase.php` |

### Directory Structure to Create
```
protected/
├── models/
│   └── couchbase/
│       ├── schemas/           # JSON Schema definitions
│       ├── transformers/      # Data transformation classes
│       └── CouchbaseActiveRecord.php
├── scripts/
│   └── couchbase/
│       ├── analyzers/         # Schema analysis scripts
│       └── indexes/           # Index definitions (exists)
└── tests/
    └── unit/
        └── models/
            └── couchbase/     # Unit tests for this phase
```

---

## Section 1: Schema Analysis (Tasks 1-3)

### Task 1.1: Create Relationship Analyzer Script

**File**: `/protected/scripts/couchbase/analyzers/RelationshipAnalyzer.php`

**Purpose**: Analyze MariaDB foreign keys and relationships for document design decisions.

```php
<?php
/**
 * Analyzes MySQL table relationships for Couchbase document design
 * 
 * Usage: php protected/yiic.php analyzerelationships
 * Output: protected/scripts/couchbase/analyzers/relationship-map.json
 */

class RelationshipAnalyzer
{
    /** @var CDbConnection */
    private $db;
    
    /** @var array Collected relationships */
    private $relationships = [];
    
    /** @var array Table metadata */
    private $tableMetadata = [];
    
    public function __construct()
    {
        $this->db = Yii::app()->db;
    }
    
    /**
     * Run the full analysis
     * @return array Analysis results
     */
    public function analyze(): array
    {
        echo "Starting relationship analysis...\n";
        
        $tables = $this->getTables();
        echo "Found " . count($tables) . " tables\n";
        
        foreach ($tables as $table) {
            $this->analyzeTable($table);
        }
        
        $this->categorizeRelationships();
        
        return [
            'tables' => $this->tableMetadata,
            'relationships' => $this->relationships,
            'summary' => $this->generateSummary(),
        ];
    }
    
    /**
     * Get all tables in the database
     * @return array Table names
     */
    private function getTables(): array
    {
        return $this->db->createCommand("SHOW TABLES")->queryColumn();
    }
    
    /**
     * Analyze a single table
     * @param string $tableName Table to analyze
     */
    private function analyzeTable(string $tableName): void
    {
        // Get column information
        $columns = $this->db->createCommand("DESCRIBE `{$tableName}`")->queryAll();
        
        // Get foreign keys
        $sql = "
            SELECT 
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ";
        
        $foreignKeys = $this->db->createCommand($sql)
            ->queryAll(true, [':table' => $tableName]);
        
        // Get row count
        $rowCount = $this->db->createCommand("SELECT COUNT(*) FROM `{$tableName}`")->queryScalar();
        
        // Get indexes
        $indexes = $this->db->createCommand("SHOW INDEX FROM `{$tableName}`")->queryAll();
        
        // Store metadata
        $this->tableMetadata[$tableName] = [
            'columns' => array_map(function($col) {
                return [
                    'name' => $col['Field'],
                    'type' => $col['Type'],
                    'null' => $col['Null'] === 'YES',
                    'key' => $col['Key'],
                    'default' => $col['Default'],
                ];
            }, $columns),
            'row_count' => (int)$rowCount,
            'foreign_keys' => count($foreignKeys),
            'indexes' => $this->groupIndexes($indexes),
        ];
        
        // Store relationships
        foreach ($foreignKeys as $fk) {
            $this->relationships[$tableName][] = [
                'type' => 'belongs_to',
                'column' => $fk['COLUMN_NAME'],
                'references_table' => $fk['REFERENCED_TABLE_NAME'],
                'references_column' => $fk['REFERENCED_COLUMN_NAME'],
                'constraint' => $fk['CONSTRAINT_NAME'],
            ];
            
            // Add reverse relationship
            if (!isset($this->relationships[$fk['REFERENCED_TABLE_NAME']])) {
                $this->relationships[$fk['REFERENCED_TABLE_NAME']] = [];
            }
            $this->relationships[$fk['REFERENCED_TABLE_NAME']][] = [
                'type' => 'has_many',
                'table' => $tableName,
                'column' => $fk['COLUMN_NAME'],
            ];
        }
        
        echo "  {$tableName}: {$rowCount} rows, " . count($foreignKeys) . " FKs\n";
    }
    
    /**
     * Group indexes by name
     * @param array $indexes Raw index data
     * @return array Grouped indexes
     */
    private function groupIndexes(array $indexes): array
    {
        $grouped = [];
        foreach ($indexes as $idx) {
            $name = $idx['Key_name'];
            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'columns' => [],
                    'unique' => !$idx['Non_unique'],
                    'type' => $idx['Index_type'],
                ];
            }
            $grouped[$name]['columns'][] = $idx['Column_name'];
        }
        return $grouped;
    }
    
    /**
     * Categorize relationships for embedding decisions
     */
    private function categorizeRelationships(): void
    {
        foreach ($this->relationships as $table => &$rels) {
            foreach ($rels as &$rel) {
                if ($rel['type'] === 'belongs_to') {
                    $rel['embed_strategy'] = $this->determineEmbedStrategy($table, $rel);
                }
            }
        }
    }
    
    /**
     * Determine embedding strategy based on relationship characteristics
     * @param string $table Parent table
     * @param array $relationship Relationship info
     * @return string Strategy: 'embed', 'reference', or 'hybrid'
     */
    private function determineEmbedStrategy(string $table, array $relationship): string
    {
        $refTable = $relationship['references_table'];
        $refMeta = $this->tableMetadata[$refTable] ?? null;
        
        if (!$refMeta) {
            return 'reference';
        }
        
        // Lookup tables (small, rarely changing) - reference
        if ($refMeta['row_count'] < 100 && $this->isLookupTable($refTable)) {
            return 'reference';
        }
        
        // Large tables - always reference
        if ($refMeta['row_count'] > 10000) {
            return 'reference';
        }
        
        // One-to-one relationships - embed
        if ($this->isOneToOne($table, $relationship)) {
            return 'embed';
        }
        
        // Contact/Address relationships - embed
        if (in_array($refTable, ['contact', 'address'])) {
            return 'embed';
        }
        
        return 'reference';
    }
    
    /**
     * Check if table is a lookup/reference table
     * @param string $table Table name
     * @return bool
     */
    private function isLookupTable(string $table): bool
    {
        $lookupPatterns = [
            '_status', '_type', '_reason', '_method', '_unit',
            'gender', 'ethnic_group', 'country', 'specialty',
        ];
        
        foreach ($lookupPatterns as $pattern) {
            if (strpos($table, $pattern) !== false || $table === $pattern) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if relationship is one-to-one
     * @param string $table Table name
     * @param array $rel Relationship
     * @return bool
     */
    private function isOneToOne(string $table, array $rel): bool
    {
        $meta = $this->tableMetadata[$table] ?? null;
        if (!$meta) {
            return false;
        }
        
        // Check if FK column has unique constraint
        foreach ($meta['indexes'] as $idx) {
            if ($idx['unique'] && in_array($rel['column'], $idx['columns'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate summary statistics
     * @return array Summary data
     */
    private function generateSummary(): array
    {
        $totalTables = count($this->tableMetadata);
        $totalRows = array_sum(array_column($this->tableMetadata, 'row_count'));
        $totalRelationships = 0;
        $embedCount = 0;
        $referenceCount = 0;
        
        foreach ($this->relationships as $rels) {
            foreach ($rels as $rel) {
                $totalRelationships++;
                if (isset($rel['embed_strategy'])) {
                    if ($rel['embed_strategy'] === 'embed') {
                        $embedCount++;
                    } else {
                        $referenceCount++;
                    }
                }
            }
        }
        
        return [
            'total_tables' => $totalTables,
            'total_rows' => $totalRows,
            'total_relationships' => $totalRelationships,
            'embed_candidates' => $embedCount,
            'reference_candidates' => $referenceCount,
            'analysis_date' => date('Y-m-d H:i:s'),
        ];
    }
    
    /**
     * Export analysis to JSON file
     * @param string $filename Output file path
     */
    public function exportToJson(string $filename): void
    {
        $data = $this->analyze();
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "\nExported to: {$filename}\n";
    }
}
```

**Yii Console Command**: `/protected/commands/AnalyzerelationshipsCommand.php`

```php
<?php
/**
 * Console command to run relationship analysis
 * 
 * Usage: php protected/yiic.php analyzerelationships
 */

class AnalyzerelationshipsCommand extends CConsoleCommand
{
    public function run($args)
    {
        require_once(Yii::getPathOfAlias('application.scripts.couchbase.analyzers') . '/RelationshipAnalyzer.php');
        
        $analyzer = new RelationshipAnalyzer();
        $outputPath = Yii::getPathOfAlias('application.scripts.couchbase.analyzers') . '/relationship-map.json';
        $analyzer->exportToJson($outputPath);
        
        echo "Analysis complete!\n";
    }
}
```

**Acceptance Criteria**:
- [ ] Script executes without errors: `docker compose exec web php protected/yiic.php analyzerelationships`
- [ ] Output file `relationship-map.json` is valid JSON
- [ ] Output contains all tables (500+ expected)
- [ ] Each relationship has `embed_strategy` classification
- [ ] Summary statistics are accurate

**Test Command**:
```bash
# Run analyzer
docker compose -f .devcontainer/docker-compose.yml exec web php /var/www/openeyes/protected/yiic.php analyzerelationships

# Verify output
docker compose -f .devcontainer/docker-compose.yml exec web cat /var/www/openeyes/protected/scripts/couchbase/analyzers/relationship-map.json | head -100

# Validate JSON
docker compose -f .devcontainer/docker-compose.yml exec web php -r "json_decode(file_get_contents('/var/www/openeyes/protected/scripts/couchbase/analyzers/relationship-map.json')); echo json_last_error() === JSON_ERROR_NONE ? 'Valid JSON' : 'Invalid JSON';"
```

---

### Task 1.2: Create Core Table Documentation

**File**: `/docs/migration-mariadb-to-couchbase/schema-analysis/core-tables.md`

**Purpose**: Document all core tables with column types, relationships, and migration notes.

```markdown
# Core Table Analysis

## Patient Tables

### patient
| Column | Type | Nullable | Key | Notes |
|--------|------|----------|-----|-------|
| id | int(10) unsigned | NO | PRI | Auto-increment |
| hos_num | varchar(40) | YES | MUL | Hospital number (indexed) |
| nhs_num | varchar(40) | YES | MUL | NHS number (indexed) |
| dob | date | NO | MUL | Date of birth |
| date_of_death | date | YES | | Nullable |
| gender | varchar(1) | YES | | M/F/U |
| ethnic_group_id | int(10) unsigned | YES | MUL | FK to ethnic_group |
| contact_id | int(10) unsigned | NO | MUL | FK to contact |
| gp_id | int(10) unsigned | YES | MUL | FK to gp |
| practice_id | int(10) unsigned | YES | MUL | FK to practice |
| primary_institution_id | int(10) unsigned | YES | MUL | FK to institution |
| deleted | tinyint(1) unsigned | NO | | Soft delete flag |
| created_user_id | int(10) unsigned | NO | MUL | Audit field |
| created_date | datetime | NO | | Audit field |
| last_modified_user_id | int(10) unsigned | NO | MUL | Audit field |
| last_modified_date | datetime | NO | | Audit field |

**Relationships**:
- belongs_to: contact (EMBED - always accessed together)
- belongs_to: ethnic_group (REFERENCE - lookup table)
- belongs_to: gp (REFERENCE - shared across patients)
- belongs_to: practice (REFERENCE - shared)
- belongs_to: institution (REFERENCE - shared)
- has_many: episode (REFERENCE - large, independent)
- has_many: patient_allergy_assignment (REFERENCE - clinical)
- has_many: patient_identifier (EMBED - bounded, always needed)

**Row Count**: ~50,000 (varies by installation)

### contact
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | int(10) unsigned | NO | PRI |
| nick_name | varchar(80) | YES | |
| primary_phone | varchar(20) | YES | |
| title | varchar(20) | YES | |
| first_name | varchar(100) | NO | |
| last_name | varchar(100) | NO | |
| maiden_name | varchar(100) | YES | |
| qualifications | varchar(200) | YES | |
| email | varchar(255) | YES | |

**Embedding Decision**: EMBED in patient document

### address
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | int(10) unsigned | NO | PRI |
| contact_id | int(10) unsigned | NO | FK to contact |
| address1 | varchar(255) | YES | |
| address2 | varchar(255) | YES | |
| city | varchar(100) | YES | |
| postcode | varchar(20) | YES | |
| county | varchar(100) | YES | |
| country_id | int(10) unsigned | YES | FK to country |
| address_type_id | int(10) unsigned | YES | FK |
| date_start | date | YES | |
| date_end | date | YES | |

**Embedding Decision**: EMBED within contact in patient document

## Episode/Event Tables

### episode
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | int(10) unsigned | NO | PRI |
| patient_id | int(10) unsigned | NO | FK to patient |
| firm_id | int(10) unsigned | YES | FK to firm |
| start_date | date | NO | |
| end_date | date | YES | |
| episode_status_id | int(10) unsigned | YES | FK |
| disorder_id | bigint(20) unsigned | YES | Principal diagnosis |
| eye_id | int(10) unsigned | YES | FK |
| subspecialty_id | int(10) unsigned | YES | FK |
| support_services | tinyint(1) | NO | |

**Relationships**:
- belongs_to: patient (REFERENCE)
- belongs_to: firm (REFERENCE)
- has_many: event (REFERENCE - many events per episode)

### event
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | int(10) unsigned | NO | PRI |
| episode_id | int(10) unsigned | YES | FK to episode |
| event_type_id | int(10) unsigned | NO | FK |
| event_date | datetime | NO | |
| created_user_id | int(10) unsigned | NO | |
| info | text | YES | |
| deleted | tinyint(1) unsigned | NO | |
| delete_reason | varchar(1024) | YES | |
| institution_id | int(10) unsigned | YES | FK |
| site_id | int(10) unsigned | YES | FK |
| worklist_patient_id | int(10) unsigned | YES | |
| parent_id | int(10) unsigned | YES | Self-reference |

**Relationships**:
- belongs_to: episode (REFERENCE)
- belongs_to: event_type (REFERENCE - lookup)
- has_many: elements (HYBRID - small embed, large reference)

## User/Admin Tables

### user
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | int(10) unsigned | NO | PRI |
| username | varchar(40) | NO | Unique |
| first_name | varchar(40) | NO | |
| last_name | varchar(40) | NO | |
| email | varchar(80) | YES | |
| active | tinyint(1) unsigned | NO | |
| global_firm_rights | tinyint(1) unsigned | NO | |
| contact_id | int(10) unsigned | YES | FK |
| title | varchar(40) | YES | |
| qualifications | varchar(200) | YES | |
| role | varchar(200) | YES | |
| is_doctor | tinyint(1) unsigned | NO | |
| is_surgeon | tinyint(1) unsigned | NO | |
| is_clinical | tinyint(1) unsigned | NO | |

**Embedding Decision**: REFERENCE user authentication separately

## Examination Element Tables

### et_ophciexamination_*
Over 50 examination element tables exist. Pattern:
- Always have `event_id` FK
- Created/modified audit fields
- Eye-specific data (left/right columns)

**Embedding Decision**: HYBRID
- Small elements (< 10 columns): Embed in event document
- Large elements (images, drawings): Reference separately

---

## Summary Statistics

| Category | Table Count | Estimated Rows |
|----------|-------------|----------------|
| Core (patient, user, episode, event) | 15 | 200,000 |
| Clinical (examination elements) | 55 | 500,000 |
| Reference/Lookup | 120 | 50,000 |
| Audit | 5 | 1,000,000+ |
| Correspondence | 10 | 100,000 |
| Booking | 20 | 50,000 |

**Total**: ~225 significant tables
```

**Acceptance Criteria**:
- [ ] Document includes all core tables (patient, episode, event, user)
- [ ] Each table has complete column definitions
- [ ] Embedding decisions are documented with rationale
- [ ] Summary statistics reflect actual database

---

### Task 1.3: Create Embedding Strategy Document

**File**: `/docs/migration-mariadb-to-couchbase/schema-analysis/embedding-strategy.md`

```markdown
# Embedding vs Referencing Strategy

## Decision Framework

### When to EMBED
1. **Ownership**: Data exclusively belongs to parent document
2. **Access Pattern**: Data always accessed with parent
3. **Size**: Data is bounded and small (< 50KB typically)
4. **Mutability**: Data changes with parent, not independently
5. **Cardinality**: One-to-one or bounded one-to-few

### When to REFERENCE
1. **Sharing**: Data shared across multiple documents
2. **Independence**: Data queried/updated independently
3. **Size**: Unbounded or large data
4. **Mutability**: Data changes independently of parent
5. **Cardinality**: One-to-many unbounded

### When to use HYBRID
1. **Variable Size**: Small entries embed, large entries reference
2. **Optional Data**: Core data embeds, optional data references
3. **Versioning**: Current version embeds, history references

---

## Detailed Decisions

### Patient Document

| Related Data | Strategy | Rationale |
|--------------|----------|-----------|
| contact | EMBED | Always accessed together, 1:1 relationship |
| address (via contact) | EMBED | Bounded (1-3 addresses), always needed |
| ethnic_group | REFERENCE | Lookup table, shared across patients |
| gp | REFERENCE | Shared across many patients |
| practice | REFERENCE | Shared organizational data |
| institution | REFERENCE | Shared organizational data |
| episodes | REFERENCE | Large, queried independently |
| patient_identifier | EMBED | Bounded array, always needed for patient lookup |
| allergies | REFERENCE | Clinical data, queried separately for safety checks |

**Estimated Document Size**: 2-5 KB

### Episode Document

| Related Data | Strategy | Rationale |
|--------------|----------|-----------|
| patient | REFERENCE | Large document, shared |
| firm | REFERENCE | Organizational data, shared |
| disorder | REFERENCE | Shared diagnosis codes |
| events | REFERENCE | Many events, queried independently |
| episode_status | REFERENCE | Lookup table |

**Estimated Document Size**: 1-2 KB

### Event Document

| Related Data | Strategy | Rationale |
|--------------|----------|-----------|
| episode | REFERENCE | Already referenced from episode |
| event_type | REFERENCE | Lookup table |
| site | REFERENCE | Organizational data |
| institution | REFERENCE | Organizational data |
| small_elements | EMBED | Visual acuity, IOP - bounded, always needed |
| large_elements | REFERENCE | Drawings, images - large binary data |

**Element Embedding Thresholds**:
- Elements with < 20 columns: EMBED
- Elements with BLOB/TEXT > 64KB: REFERENCE
- Elements with unbounded arrays: REFERENCE

**Estimated Document Size**: 5-50 KB depending on embedded elements

### Examination Elements Classification

#### EMBED (in Event document)
- `et_ophciexamination_visualacuity` - Small, always needed
- `et_ophciexamination_intraocularpressure` - Small, always needed
- `et_ophciexamination_refraction` - Bounded structure
- `et_ophciexamination_anteriorsegment_cct` - Small measurements
- `et_ophciexamination_dilation` - Simple status

#### REFERENCE (separate documents)
- `et_ophciexamination_fundus` - Contains eyedraw data (large)
- `et_ophciexamination_oct` - References binary images
- `et_ophciexamination_anterior_segment` - Contains drawings
- `et_ophciexamination_gonioscopy` - Contains drawings
- `et_ophciexamination_opticdisc` - Contains drawings

---

## Document Size Guidelines

| Document Type | Target Size | Maximum Size |
|---------------|-------------|--------------|
| Patient | 2-5 KB | 20 KB |
| Episode | 1-2 KB | 5 KB |
| Event (with elements) | 5-50 KB | 200 KB |
| Examination Element | 1-10 KB | 1 MB (images) |
| Audit Entry | 0.5-2 KB | 10 KB |

**Couchbase Limits**: Maximum document size is 20 MB, but target < 1 MB for performance.

---

## Query Pattern Considerations

### High-Frequency Queries (optimize for these)
1. Find patient by hos_num/nhs_num
2. Get patient's episodes
3. Get episode's events
4. Get event's elements
5. Search patients by name/DOB

### Embedding Impact on Queries
- **Embedded data**: Single document fetch (fast)
- **Referenced data**: Additional lookup required (slower but more flexible)
- **Hybrid**: Best of both when properly designed

---

## Migration Order Based on Strategy

**Phase 1: Reference Tables First**
1. Lookup tables (gender, ethnic_group, etc.)
2. Organizational tables (institution, site, firm)
3. Event types, element types

**Phase 2: Core Entities**
1. User (with embedded contact)
2. Patient (with embedded contact, address, identifiers)
3. Episode (references only)
4. Event (with embedded small elements)

**Phase 3: Clinical Data**
1. Small examination elements (embed preparation)
2. Large examination elements (as separate documents)
3. Medications, allergies, diagnoses

**Phase 4: Operational Data**
1. Audit records
2. Correspondence
3. Booking/scheduling
```

**Acceptance Criteria**:
- [ ] Every major entity type has documented strategy
- [ ] Rationale provided for each decision
- [ ] Size estimates are reasonable
- [ ] Query patterns are considered
- [ ] Migration order is defined

---

## Section 2: Document Schema Definitions (Tasks 4-8)

### Task 2.1: Create JSON Schema Directory Structure

**Commands**:
```bash
mkdir -p protected/models/couchbase/schemas
mkdir -p protected/models/couchbase/transformers
mkdir -p protected/tests/unit/models/couchbase
```

### Task 2.2: Patient Document Schema

**File**: `/protected/models/couchbase/schemas/patient.schema.json`

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "$id": "https://openeyes.org/schemas/patient.schema.json",
    "title": "Patient Document",
    "description": "Couchbase document schema for patient records",
    "type": "object",
    "required": ["_type", "_id", "dob", "created_date"],
    "properties": {
        "_type": {
            "type": "string",
            "const": "patient",
            "description": "Document type identifier"
        },
        "_id": {
            "type": "string",
            "description": "Document key (patient::{id})"
        },
        "_mysql_id": {
            "type": "integer",
            "description": "Original MySQL primary key for reference"
        },
        "_created": {
            "type": "string",
            "format": "date-time",
            "description": "Couchbase document creation timestamp"
        },
        "_modified": {
            "type": "string",
            "format": "date-time",
            "description": "Couchbase document modification timestamp"
        },
        "_version": {
            "type": "integer",
            "default": 1,
            "description": "Document version for optimistic locking"
        },
        "hos_num": {
            "type": ["string", "null"],
            "maxLength": 40,
            "description": "Hospital number"
        },
        "nhs_num": {
            "type": ["string", "null"],
            "maxLength": 40,
            "pattern": "^[0-9]{10}$",
            "description": "NHS number (10 digits)"
        },
        "dob": {
            "type": "string",
            "format": "date",
            "description": "Date of birth (YYYY-MM-DD)"
        },
        "date_of_death": {
            "type": ["string", "null"],
            "format": "date",
            "description": "Date of death if deceased"
        },
        "gender": {
            "type": ["string", "null"],
            "enum": ["M", "F", "U", null],
            "description": "Gender: Male, Female, Unknown"
        },
        "is_deceased": {
            "type": "boolean",
            "default": false
        },
        "deleted": {
            "type": "boolean",
            "default": false,
            "description": "Soft delete flag"
        },
        "patient_source": {
            "type": "integer",
            "enum": [0, 1, 2],
            "description": "0=Other, 1=Referral, 2=Self-register"
        },
        "contact": {
            "type": "object",
            "description": "Embedded contact information",
            "properties": {
                "title": {"type": ["string", "null"], "maxLength": 20},
                "first_name": {"type": "string", "maxLength": 100},
                "last_name": {"type": "string", "maxLength": 100},
                "maiden_name": {"type": ["string", "null"], "maxLength": 100},
                "nick_name": {"type": ["string", "null"], "maxLength": 80},
                "primary_phone": {"type": ["string", "null"], "maxLength": 20},
                "email": {"type": ["string", "null"], "format": "email", "maxLength": 255},
                "qualifications": {"type": ["string", "null"], "maxLength": 200}
            },
            "required": ["first_name", "last_name"]
        },
        "addresses": {
            "type": "array",
            "description": "Embedded addresses (typically 1-3)",
            "maxItems": 10,
            "items": {
                "type": "object",
                "properties": {
                    "address_type_id": {"type": ["integer", "null"]},
                    "address_type": {"type": ["string", "null"]},
                    "address1": {"type": ["string", "null"], "maxLength": 255},
                    "address2": {"type": ["string", "null"], "maxLength": 255},
                    "city": {"type": ["string", "null"], "maxLength": 100},
                    "postcode": {"type": ["string", "null"], "maxLength": 20},
                    "county": {"type": ["string", "null"], "maxLength": 100},
                    "country_id": {"type": ["integer", "null"]},
                    "country": {"type": ["string", "null"]},
                    "date_start": {"type": ["string", "null"], "format": "date"},
                    "date_end": {"type": ["string", "null"], "format": "date"},
                    "is_primary": {"type": "boolean", "default": false}
                }
            }
        },
        "identifiers": {
            "type": "array",
            "description": "Patient identifiers (hos_num, nhs_num, local IDs)",
            "items": {
                "type": "object",
                "properties": {
                    "type": {"type": "string"},
                    "type_id": {"type": "integer"},
                    "value": {"type": "string"},
                    "institution_id": {"type": ["integer", "null"]}
                },
                "required": ["type", "value"]
            }
        },
        "gp_id": {
            "type": ["integer", "null"],
            "description": "Reference to GP document"
        },
        "practice_id": {
            "type": ["integer", "null"],
            "description": "Reference to practice document"
        },
        "ethnic_group_id": {
            "type": ["integer", "null"],
            "description": "Reference to ethnic_group lookup"
        },
        "primary_institution_id": {
            "type": ["integer", "null"],
            "description": "Reference to primary institution"
        },
        "created_user_id": {
            "type": "integer",
            "description": "User who created the record"
        },
        "created_date": {
            "type": "string",
            "format": "date-time"
        },
        "last_modified_user_id": {
            "type": "integer",
            "description": "User who last modified"
        },
        "last_modified_date": {
            "type": "string",
            "format": "date-time"
        }
    },
    "additionalProperties": false
}
```

**Acceptance Criteria**:
- [ ] Schema validates with JSON Schema Draft-07
- [ ] All patient table columns are represented
- [ ] Contact is properly embedded as nested object
- [ ] Addresses are embedded as array
- [ ] References use integer IDs (not embedded)
- [ ] Required fields match MySQL NOT NULL constraints

---

### Task 2.3: Episode Document Schema

**File**: `/protected/models/couchbase/schemas/episode.schema.json`

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "$id": "https://openeyes.org/schemas/episode.schema.json",
    "title": "Episode Document",
    "description": "Couchbase document schema for clinical episodes",
    "type": "object",
    "required": ["_type", "_id", "patient_id", "start_date"],
    "properties": {
        "_type": {
            "type": "string",
            "const": "episode"
        },
        "_id": {
            "type": "string"
        },
        "_mysql_id": {
            "type": "integer"
        },
        "_created": {
            "type": "string",
            "format": "date-time"
        },
        "_modified": {
            "type": "string",
            "format": "date-time"
        },
        "_version": {
            "type": "integer",
            "default": 1
        },
        "patient_id": {
            "type": "integer",
            "description": "Reference to patient document"
        },
        "firm_id": {
            "type": ["integer", "null"],
            "description": "Reference to firm (clinical team)"
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
            "type": ["integer", "null"],
            "description": "Reference to episode_status lookup"
        },
        "subspecialty_id": {
            "type": ["integer", "null"]
        },
        "support_services": {
            "type": "boolean",
            "default": false
        },
        "disorder_id": {
            "type": ["integer", "null"],
            "description": "Principal diagnosis (SNOMED code reference)"
        },
        "disorder_date": {
            "type": ["string", "null"],
            "format": "date"
        },
        "eye_id": {
            "type": ["integer", "null"],
            "description": "Eye: 1=Left, 2=Right, 3=Both"
        },
        "deleted": {
            "type": "boolean",
            "default": false
        },
        "change_tracker": {
            "type": ["object", "null"],
            "description": "Tracks which user last changed specific fields",
            "additionalProperties": {
                "type": "object",
                "properties": {
                    "user_id": {"type": "integer"},
                    "date": {"type": "string", "format": "date-time"}
                }
            }
        },
        "created_user_id": {"type": "integer"},
        "created_date": {"type": "string", "format": "date-time"},
        "last_modified_user_id": {"type": "integer"},
        "last_modified_date": {"type": "string", "format": "date-time"}
    },
    "additionalProperties": false
}
```

---

### Task 2.4: Event Document Schema

**File**: `/protected/models/couchbase/schemas/event.schema.json`

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "$id": "https://openeyes.org/schemas/event.schema.json",
    "title": "Event Document",
    "description": "Couchbase document schema for clinical events",
    "type": "object",
    "required": ["_type", "_id", "event_type_id", "event_date"],
    "properties": {
        "_type": {
            "type": "string",
            "const": "event"
        },
        "_id": {
            "type": "string"
        },
        "_mysql_id": {
            "type": "integer"
        },
        "_created": {
            "type": "string",
            "format": "date-time"
        },
        "_modified": {
            "type": "string",
            "format": "date-time"
        },
        "_version": {
            "type": "integer",
            "default": 1
        },
        "episode_id": {
            "type": ["integer", "null"],
            "description": "Reference to episode document"
        },
        "event_type_id": {
            "type": "integer",
            "description": "Reference to event_type lookup"
        },
        "event_type_name": {
            "type": ["string", "null"],
            "description": "Denormalized event type name for display"
        },
        "event_date": {
            "type": "string",
            "format": "date-time"
        },
        "info": {
            "type": ["string", "null"],
            "description": "Additional event information"
        },
        "deleted": {
            "type": "boolean",
            "default": false
        },
        "delete_reason": {
            "type": ["string", "null"],
            "maxLength": 1024
        },
        "delete_pending": {
            "type": "boolean",
            "default": false
        },
        "institution_id": {
            "type": ["integer", "null"]
        },
        "site_id": {
            "type": ["integer", "null"]
        },
        "firm_id": {
            "type": ["integer", "null"]
        },
        "is_automated": {
            "type": "boolean",
            "default": false
        },
        "automated_source": {
            "type": ["string", "null"]
        },
        "parent_id": {
            "type": ["integer", "null"],
            "description": "Reference to parent event if linked"
        },
        "worklist_patient_id": {
            "type": ["integer", "null"]
        },
        "elements": {
            "type": "object",
            "description": "Embedded examination elements (small ones only)",
            "additionalProperties": {
                "type": "object",
                "description": "Element data keyed by element type"
            }
        },
        "element_refs": {
            "type": "array",
            "description": "References to large elements stored separately",
            "items": {
                "type": "object",
                "properties": {
                    "element_type_id": {"type": "integer"},
                    "element_type_name": {"type": "string"},
                    "document_id": {"type": "string"}
                },
                "required": ["element_type_id", "document_id"]
            }
        },
        "created_user_id": {"type": "integer"},
        "created_date": {"type": "string", "format": "date-time"},
        "last_modified_user_id": {"type": "integer"},
        "last_modified_date": {"type": "string", "format": "date-time"}
    },
    "additionalProperties": false
}
```

---

### Task 2.5: User Document Schema

**File**: `/protected/models/couchbase/schemas/user.schema.json`

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "$id": "https://openeyes.org/schemas/user.schema.json",
    "title": "User Document",
    "description": "Couchbase document schema for system users",
    "type": "object",
    "required": ["_type", "_id", "username", "first_name", "last_name"],
    "properties": {
        "_type": {
            "type": "string",
            "const": "user"
        },
        "_id": {
            "type": "string"
        },
        "_mysql_id": {
            "type": "integer"
        },
        "_created": {
            "type": "string",
            "format": "date-time"
        },
        "_modified": {
            "type": "string",
            "format": "date-time"
        },
        "_version": {
            "type": "integer",
            "default": 1
        },
        "username": {
            "type": "string",
            "maxLength": 40
        },
        "first_name": {
            "type": "string",
            "maxLength": 40
        },
        "last_name": {
            "type": "string",
            "maxLength": 40
        },
        "email": {
            "type": ["string", "null"],
            "format": "email",
            "maxLength": 80
        },
        "title": {
            "type": ["string", "null"],
            "maxLength": 40
        },
        "qualifications": {
            "type": ["string", "null"],
            "maxLength": 200
        },
        "role": {
            "type": ["string", "null"],
            "maxLength": 200
        },
        "active": {
            "type": "boolean",
            "default": true
        },
        "global_firm_rights": {
            "type": "boolean",
            "default": false
        },
        "is_doctor": {
            "type": "boolean",
            "default": false
        },
        "is_surgeon": {
            "type": "boolean",
            "default": false
        },
        "is_clinical": {
            "type": "boolean",
            "default": false
        },
        "is_consultant": {
            "type": "boolean",
            "default": false
        },
        "doctor_grade_id": {
            "type": ["integer", "null"]
        },
        "registration_code": {
            "type": ["string", "null"]
        },
        "contact": {
            "type": ["object", "null"],
            "description": "Embedded contact information",
            "properties": {
                "title": {"type": ["string", "null"]},
                "first_name": {"type": ["string", "null"]},
                "last_name": {"type": ["string", "null"]},
                "primary_phone": {"type": ["string", "null"]},
                "email": {"type": ["string", "null"]}
            }
        },
        "primary_institution_id": {
            "type": ["integer", "null"]
        },
        "created_user_id": {"type": "integer"},
        "created_date": {"type": "string", "format": "date-time"},
        "last_modified_user_id": {"type": "integer"},
        "last_modified_date": {"type": "string", "format": "date-time"}
    },
    "additionalProperties": false
}
```

---

## Section 3: Type Transformation (Tasks 9-11)

### Task 3.1: Type Transformer Class

**File**: `/protected/models/couchbase/transformers/TypeTransformer.php`

```php
<?php
/**
 * Handles MySQL to Couchbase type transformations
 * 
 * Converts MySQL column values to appropriate JSON types
 * and back for bidirectional data flow.
 */

namespace OE\Couchbase\Transformers;

class TypeTransformer
{
    /**
     * MySQL to JSON type mapping
     */
    private static array $typeMap = [
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
        'date' => 'date',
        'datetime' => 'datetime',
        'timestamp' => 'datetime',
        'time' => 'time',
        'tinyint(1)' => 'boolean',
        'enum' => 'string',
        'set' => 'array',
        'blob' => 'binary',
        'mediumblob' => 'binary',
        'longblob' => 'binary',
        'json' => 'object',
    ];
    
    /**
     * Boolean field patterns (tinyint(1) columns that are booleans)
     */
    private static array $booleanFields = [
        'active', 'deleted', 'is_', 'has_', 'can_', 'allow_',
        'enabled', 'disabled', 'visible', 'hidden', 'default',
        'global_firm_rights', 'support_services', 'delete_pending',
    ];
    
    /**
     * Transform MySQL value to Couchbase JSON value
     * 
     * @param mixed $value The MySQL value
     * @param string $mysqlType MySQL column type (e.g., 'int(10) unsigned')
     * @param string $columnName Column name for context
     * @return mixed Transformed value
     */
    public static function toJson($value, string $mysqlType, string $columnName = ''): mixed
    {
        if ($value === null) {
            return null;
        }
        
        // Normalize type string
        $baseType = strtolower(preg_replace('/\([^)]+\)/', '', $mysqlType));
        $baseType = trim(str_replace(['unsigned', 'signed'], '', $baseType));
        
        // Check for boolean by column name pattern
        if (self::isBooleanField($columnName, $mysqlType)) {
            return (bool)$value;
        }
        
        switch ($baseType) {
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return (int)$value;
                
            case 'decimal':
            case 'float':
            case 'double':
                return (float)$value;
                
            case 'date':
                // Convert to ISO 8601 date format
                if ($value === '0000-00-00') {
                    return null;
                }
                return date('Y-m-d', strtotime($value));
                
            case 'datetime':
            case 'timestamp':
                // Convert to ISO 8601 datetime format
                if ($value === '0000-00-00 00:00:00') {
                    return null;
                }
                return date('c', strtotime($value));
                
            case 'time':
                return $value; // Keep as HH:MM:SS string
                
            case 'blob':
            case 'mediumblob':
            case 'longblob':
                // Base64 encode binary data
                return base64_encode($value);
                
            case 'json':
                // Decode JSON string to object/array
                $decoded = json_decode($value, true);
                return $decoded ?? $value;
                
            case 'set':
                // Convert comma-separated to array
                return $value ? explode(',', $value) : [];
                
            case 'enum':
            case 'char':
            case 'varchar':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            default:
                return (string)$value;
        }
    }
    
    /**
     * Transform Couchbase JSON value back to MySQL format
     * 
     * @param mixed $value The JSON value
     * @param string $mysqlType Target MySQL column type
     * @param string $columnName Column name for context
     * @return mixed MySQL-compatible value
     */
    public static function toMysql($value, string $mysqlType, string $columnName = ''): mixed
    {
        if ($value === null) {
            return null;
        }
        
        $baseType = strtolower(preg_replace('/\([^)]+\)/', '', $mysqlType));
        $baseType = trim(str_replace(['unsigned', 'signed'], '', $baseType));
        
        // Boolean to tinyint
        if (self::isBooleanField($columnName, $mysqlType)) {
            return $value ? 1 : 0;
        }
        
        switch ($baseType) {
            case 'datetime':
            case 'timestamp':
                // Convert ISO 8601 back to MySQL format
                if (is_string($value) && $value) {
                    return date('Y-m-d H:i:s', strtotime($value));
                }
                return $value;
                
            case 'date':
                if (is_string($value) && $value) {
                    return date('Y-m-d', strtotime($value));
                }
                return $value;
                
            case 'blob':
            case 'mediumblob':
            case 'longblob':
                // Decode base64
                return base64_decode($value);
                
            case 'json':
                // Encode to JSON string
                return json_encode($value);
                
            case 'set':
                // Convert array to comma-separated
                return is_array($value) ? implode(',', $value) : $value;
                
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                return (int)$value;
                
            case 'decimal':
            case 'float':
            case 'double':
                return (float)$value;
                
            default:
                return (string)$value;
        }
    }
    
    /**
     * Check if column should be treated as boolean
     * 
     * @param string $columnName Column name
     * @param string $mysqlType MySQL type
     * @return bool
     */
    private static function isBooleanField(string $columnName, string $mysqlType): bool
    {
        // tinyint(1) is typically boolean
        if (strpos($mysqlType, 'tinyint(1)') !== false) {
            // Check common boolean patterns
            foreach (self::$booleanFields as $pattern) {
                if (strpos($columnName, $pattern) === 0 || $columnName === $pattern) {
                    return true;
                }
            }
            // If tinyint(1) and has boolean-like name, treat as bool
            if (preg_match('/^(is_|has_|can_|allow_|enable|disable|show_|hide_)/', $columnName)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Transform an entire row from MySQL to Couchbase format
     * 
     * @param array $row MySQL row data
     * @param array $columnTypes Column name => MySQL type mapping
     * @return array Transformed row
     */
    public static function transformRow(array $row, array $columnTypes): array
    {
        $result = [];
        
        foreach ($row as $column => $value) {
            $type = $columnTypes[$column] ?? 'varchar(255)';
            $result[$column] = self::toJson($value, $type, $column);
        }
        
        return $result;
    }
    
    /**
     * Get JSON Schema type for a MySQL type
     * 
     * @param string $mysqlType MySQL column type
     * @param bool $nullable Whether column allows NULL
     * @return array JSON Schema type definition
     */
    public static function getJsonSchemaType(string $mysqlType, bool $nullable = false): array
    {
        $baseType = strtolower(preg_replace('/\([^)]+\)/', '', $mysqlType));
        $baseType = trim(str_replace(['unsigned', 'signed'], '', $baseType));
        
        $jsonType = match($baseType) {
            'tinyint', 'smallint', 'mediumint', 'int', 'bigint' => 'integer',
            'decimal', 'float', 'double' => 'number',
            'date' => ['type' => 'string', 'format' => 'date'],
            'datetime', 'timestamp' => ['type' => 'string', 'format' => 'date-time'],
            'json' => 'object',
            'set' => 'array',
            default => 'string',
        };
        
        // Handle nullable
        if ($nullable && is_string($jsonType)) {
            return ['type' => [$jsonType, 'null']];
        } elseif ($nullable && is_array($jsonType)) {
            $jsonType['type'] = [$jsonType['type'], 'null'];
            return $jsonType;
        }
        
        return is_array($jsonType) ? $jsonType : ['type' => $jsonType];
    }
}
```

**Acceptance Criteria**:
- [ ] All MySQL types have transformation rules
- [ ] Boolean detection works for common patterns
- [ ] Date/datetime conversion produces valid ISO 8601
- [ ] Round-trip transformation preserves data integrity
- [ ] NULL handling is consistent

---

### Task 3.2: Document Transformer Base Class

**File**: `/protected/models/couchbase/transformers/DocumentTransformer.php`

```php
<?php
/**
 * Base class for transforming MySQL records to Couchbase documents
 */

namespace OE\Couchbase\Transformers;

abstract class DocumentTransformer
{
    /** @var array Column type cache */
    protected array $columnTypes = [];
    
    /** @var \CDbConnection */
    protected \CDbConnection $db;
    
    public function __construct()
    {
        $this->db = \Yii::app()->db;
        $this->loadColumnTypes();
    }
    
    /**
     * Get the MySQL table name
     * @return string
     */
    abstract public function getTableName(): string;
    
    /**
     * Get the Couchbase document type
     * @return string
     */
    abstract public function getDocumentType(): string;
    
    /**
     * Get the Couchbase scope
     * @return string
     */
    abstract public function getScope(): string;
    
    /**
     * Get the Couchbase collection
     * @return string
     */
    abstract public function getCollection(): string;
    
    /**
     * Transform a MySQL row to a Couchbase document
     * @param array $row MySQL row
     * @return array Couchbase document
     */
    public function transform(array $row): array
    {
        // Start with base document structure
        $document = [
            '_type' => $this->getDocumentType(),
            '_id' => $this->generateDocumentKey($row),
            '_mysql_id' => (int)$row['id'],
            '_created' => date('c'),
            '_modified' => date('c'),
            '_version' => 1,
        ];
        
        // Transform base columns
        $transformed = TypeTransformer::transformRow($row, $this->columnTypes);
        
        // Apply custom transformations
        $transformed = $this->customTransform($transformed, $row);
        
        // Merge, with document metadata taking precedence
        return array_merge($transformed, $document);
    }
    
    /**
     * Apply custom transformations specific to this document type
     * Override in subclasses for entity-specific logic
     * 
     * @param array $transformed Already type-transformed data
     * @param array $originalRow Original MySQL row
     * @return array Further transformed data
     */
    protected function customTransform(array $transformed, array $originalRow): array
    {
        return $transformed;
    }
    
    /**
     * Generate the document key
     * @param array $row MySQL row
     * @return string Document key
     */
    protected function generateDocumentKey(array $row): string
    {
        return $this->getCollection() . '::' . $row['id'];
    }
    
    /**
     * Load column types from database schema
     */
    protected function loadColumnTypes(): void
    {
        $tableName = $this->getTableName();
        $columns = $this->db->createCommand("DESCRIBE `{$tableName}`")->queryAll();
        
        foreach ($columns as $col) {
            $this->columnTypes[$col['Field']] = $col['Type'];
        }
    }
    
    /**
     * Embed related data into the document
     * 
     * @param array $document Current document
     * @param string $key Key to store embedded data
     * @param string $table Related table
     * @param string $foreignKey Foreign key column
     * @param mixed $foreignValue Foreign key value
     * @return array Document with embedded data
     */
    protected function embedRelated(
        array $document,
        string $key,
        string $table,
        string $foreignKey,
        $foreignValue
    ): array {
        if ($foreignValue === null) {
            $document[$key] = null;
            return $document;
        }
        
        $sql = "SELECT * FROM `{$table}` WHERE `{$foreignKey}` = :fk";
        $related = $this->db->createCommand($sql)->queryRow(true, [':fk' => $foreignValue]);
        
        if ($related) {
            // Get column types for related table
            $relatedTypes = $this->getColumnTypesForTable($table);
            $document[$key] = TypeTransformer::transformRow($related, $relatedTypes);
        } else {
            $document[$key] = null;
        }
        
        return $document;
    }
    
    /**
     * Embed multiple related records
     * 
     * @param array $document Current document
     * @param string $key Key to store embedded array
     * @param string $table Related table
     * @param string $foreignKey Foreign key column
     * @param mixed $foreignValue Foreign key value
     * @return array Document with embedded array
     */
    protected function embedRelatedMany(
        array $document,
        string $key,
        string $table,
        string $foreignKey,
        $foreignValue
    ): array {
        if ($foreignValue === null) {
            $document[$key] = [];
            return $document;
        }
        
        $sql = "SELECT * FROM `{$table}` WHERE `{$foreignKey}` = :fk";
        $rows = $this->db->createCommand($sql)->queryAll(true, [':fk' => $foreignValue]);
        
        $relatedTypes = $this->getColumnTypesForTable($table);
        $document[$key] = array_map(
            fn($row) => TypeTransformer::transformRow($row, $relatedTypes),
            $rows
        );
        
        return $document;
    }
    
    /**
     * Get column types for any table
     * @param string $table Table name
     * @return array Column types
     */
    protected function getColumnTypesForTable(string $table): array
    {
        static $cache = [];
        
        if (!isset($cache[$table])) {
            $columns = $this->db->createCommand("DESCRIBE `{$table}`")->queryAll();
            $cache[$table] = [];
            foreach ($columns as $col) {
                $cache[$table][$col['Field']] = $col['Type'];
            }
        }
        
        return $cache[$table];
    }
    
    /**
     * Transform document back to MySQL row format
     * @param array $document Couchbase document
     * @return array MySQL row
     */
    public function reverseTransform(array $document): array
    {
        $row = [];
        
        // Remove document metadata
        $data = array_filter($document, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
        
        foreach ($data as $column => $value) {
            if (isset($this->columnTypes[$column])) {
                $row[$column] = TypeTransformer::toMysql($value, $this->columnTypes[$column], $column);
            }
        }
        
        // Restore ID from metadata
        if (isset($document['_mysql_id'])) {
            $row['id'] = $document['_mysql_id'];
        }
        
        return $row;
    }
}
```

---

### Task 3.3: Patient Document Transformer

**File**: `/protected/models/couchbase/transformers/PatientTransformer.php`

```php
<?php
/**
 * Transforms patient MySQL records to Couchbase documents
 */

namespace OE\Couchbase\Transformers;

class PatientTransformer extends DocumentTransformer
{
    public function getTableName(): string
    {
        return 'patient';
    }
    
    public function getDocumentType(): string
    {
        return 'patient';
    }
    
    public function getScope(): string
    {
        return 'core';
    }
    
    public function getCollection(): string
    {
        return 'patient';
    }
    
    /**
     * Apply patient-specific transformations
     */
    protected function customTransform(array $transformed, array $originalRow): array
    {
        // Embed contact information
        if (!empty($originalRow['contact_id'])) {
            $transformed = $this->embedContact($transformed, $originalRow['contact_id']);
        }
        
        // Embed patient identifiers
        $transformed = $this->embedIdentifiers($transformed, $originalRow['id']);
        
        // Compute is_deceased flag
        $transformed['is_deceased'] = !empty($transformed['date_of_death']);
        
        // Remove the contact_id since we're embedding
        unset($transformed['contact_id']);
        
        return $transformed;
    }
    
    /**
     * Embed contact with addresses
     */
    private function embedContact(array $document, int $contactId): array
    {
        $contactSql = "SELECT * FROM contact WHERE id = :id";
        $contact = $this->db->createCommand($contactSql)->queryRow(true, [':id' => $contactId]);
        
        if ($contact) {
            $contactTypes = $this->getColumnTypesForTable('contact');
            $document['contact'] = TypeTransformer::transformRow($contact, $contactTypes);
            
            // Remove redundant id
            unset($document['contact']['id']);
            
            // Embed addresses
            $addressSql = "SELECT a.*, at.name as address_type, c.name as country 
                          FROM address a 
                          LEFT JOIN address_type at ON a.address_type_id = at.id
                          LEFT JOIN country c ON a.country_id = c.id
                          WHERE a.contact_id = :contact_id
                          ORDER BY a.date_start DESC";
            $addresses = $this->db->createCommand($addressSql)->queryAll(true, [':contact_id' => $contactId]);
            
            $addressTypes = $this->getColumnTypesForTable('address');
            $document['addresses'] = [];
            
            foreach ($addresses as $i => $addr) {
                $transformedAddr = TypeTransformer::transformRow($addr, $addressTypes);
                $transformedAddr['is_primary'] = ($i === 0);
                unset($transformedAddr['id'], $transformedAddr['contact_id']);
                $document['addresses'][] = $transformedAddr;
            }
        } else {
            $document['contact'] = null;
            $document['addresses'] = [];
        }
        
        return $document;
    }
    
    /**
     * Embed patient identifiers
     */
    private function embedIdentifiers(array $document, int $patientId): array
    {
        $sql = "SELECT pi.*, pit.short_title as type
                FROM patient_identifier pi
                JOIN patient_identifier_type pit ON pi.patient_identifier_type_id = pit.id
                WHERE pi.patient_id = :patient_id
                AND pi.deleted = 0";
        $identifiers = $this->db->createCommand($sql)->queryAll(true, [':patient_id' => $patientId]);
        
        $document['identifiers'] = [];
        foreach ($identifiers as $ident) {
            $document['identifiers'][] = [
                'type' => $ident['type'],
                'type_id' => (int)$ident['patient_identifier_type_id'],
                'value' => $ident['value'],
                'institution_id' => $ident['institution_id'] ? (int)$ident['institution_id'] : null,
            ];
        }
        
        return $document;
    }
    
    /**
     * Transform a patient with all related data for migration
     * @param int $patientId Patient ID
     * @return array|null Complete patient document or null if not found
     */
    public function transformById(int $patientId): ?array
    {
        $sql = "SELECT * FROM patient WHERE id = :id";
        $row = $this->db->createCommand($sql)->queryRow(true, [':id' => $patientId]);
        
        if (!$row) {
            return null;
        }
        
        return $this->transform($row);
    }
}
```

**Acceptance Criteria**:
- [ ] Patient transformer produces valid documents matching schema
- [ ] Contact information is properly embedded
- [ ] Addresses are embedded as array with primary flag
- [ ] Identifiers are embedded from patient_identifier table
- [ ] is_deceased computed correctly
- [ ] Round-trip transformation preserves data

---

## Section 4: Collection Mapping Configuration (Tasks 12-13)

### Task 4.1: Create Collection Mapping Configuration

**File**: `/protected/config/couchbase-collections.php`

```php
<?php
/**
 * Maps MySQL tables to Couchbase scopes and collections
 * 
 * This configuration defines how MySQL data is organized in Couchbase,
 * including embedding decisions and key patterns.
 */

return [
    // =========================================================================
    // CORE SCOPE - Primary entities
    // =========================================================================
    'core' => [
        'patient' => [
            'source_tables' => ['patient', 'contact', 'address', 'patient_identifier'],
            'document_type' => 'patient',
            'key_pattern' => 'patient::{id}',
            'transformer' => 'OE\\Couchbase\\Transformers\\PatientTransformer',
            'embedded' => ['contact', 'addresses', 'identifiers'],
            'indexes' => [
                'idx_patient_hos_num' => ['hos_num'],
                'idx_patient_nhs_num' => ['nhs_num'],
                'idx_patient_name' => ['contact.last_name', 'contact.first_name'],
                'idx_patient_dob' => ['dob'],
                'idx_patient_institution' => ['primary_institution_id'],
            ],
        ],
        
        'user' => [
            'source_tables' => ['user', 'contact'],
            'document_type' => 'user',
            'key_pattern' => 'user::{id}',
            'transformer' => 'OE\\Couchbase\\Transformers\\UserTransformer',
            'embedded' => ['contact'],
            'indexes' => [
                'idx_user_username' => ['username'],
                'idx_user_active' => ['active'],
            ],
        ],
        
        'episode' => [
            'source_tables' => ['episode'],
            'document_type' => 'episode',
            'key_pattern' => 'episode::{id}',
            'transformer' => 'OE\\Couchbase\\Transformers\\EpisodeTransformer',
            'embedded' => [],
            'indexes' => [
                'idx_episode_patient' => ['patient_id'],
                'idx_episode_firm' => ['firm_id'],
                'idx_episode_status' => ['episode_status_id', 'start_date'],
            ],
        ],
        
        'event' => [
            'source_tables' => ['event'],
            'document_type' => 'event',
            'key_pattern' => 'event::{id}',
            'transformer' => 'OE\\Couchbase\\Transformers\\EventTransformer',
            'embedded' => ['small_elements'],
            'indexes' => [
                'idx_event_episode' => ['episode_id'],
                'idx_event_type' => ['event_type_id'],
                'idx_event_date' => ['event_date DESC'],
                'idx_event_institution' => ['institution_id', 'site_id'],
            ],
        ],
        
        'firm' => [
            'source_tables' => ['firm'],
            'document_type' => 'firm',
            'key_pattern' => 'firm::{id}',
            'embedded' => [],
        ],
        
        'site' => [
            'source_tables' => ['site'],
            'document_type' => 'site',
            'key_pattern' => 'site::{id}',
            'embedded' => [],
        ],
        
        'institution' => [
            'source_tables' => ['institution'],
            'document_type' => 'institution',
            'key_pattern' => 'institution::{id}',
            'embedded' => ['contact', 'address'],
        ],
    ],
    
    // =========================================================================
    // CLINICAL SCOPE - Examination and clinical data
    // =========================================================================
    'clinical' => [
        'examination' => [
            'source_tables' => ['et_ophciexamination_*'],
            'document_type' => 'examination',
            'key_pattern' => 'examination::{event_id}',
            'embedded' => ['small_elements'],
            'element_size_threshold' => 10240, // 10KB - elements larger than this are referenced
        ],
        
        'diagnosis' => [
            'source_tables' => ['disorder', 'secondary_diagnosis'],
            'document_type' => 'diagnosis',
            'key_pattern' => 'diagnosis::{id}',
            'embedded' => [],
        ],
        
        'medication' => [
            'source_tables' => ['medication', 'medication_drug'],
            'document_type' => 'medication',
            'key_pattern' => 'medication::{id}',
            'embedded' => [],
        ],
        
        'allergy' => [
            'source_tables' => ['allergy', 'patient_allergy_assignment'],
            'document_type' => 'allergy',
            'key_pattern' => 'allergy::{id}',
            'embedded' => [],
        ],
        
        // Large examination elements stored separately
        'fundus_drawing' => [
            'source_tables' => ['et_ophciexamination_fundus'],
            'document_type' => 'fundus_drawing',
            'key_pattern' => 'fundus_drawing::{id}',
            'embedded' => [],
        ],
        
        'anterior_segment' => [
            'source_tables' => ['et_ophciexamination_anteriorsegment'],
            'document_type' => 'anterior_segment',
            'key_pattern' => 'anterior_segment::{id}',
            'embedded' => [],
        ],
    ],
    
    // =========================================================================
    // CORRESPONDENCE SCOPE - Letters, messages, documents
    // =========================================================================
    'correspondence' => [
        'letter' => [
            'source_tables' => ['et_ophcocorrespondence_letter'],
            'document_type' => 'letter',
            'key_pattern' => 'letter::{id}',
            'embedded' => [],
        ],
        
        'message' => [
            'source_tables' => ['ophcomessaging_message'],
            'document_type' => 'message',
            'key_pattern' => 'message::{id}',
            'embedded' => [],
        ],
        
        'document' => [
            'source_tables' => ['et_ophcodocument_document', 'protected_file'],
            'document_type' => 'document',
            'key_pattern' => 'document::{id}',
            'embedded' => ['file_metadata'],
        ],
    ],
    
    // =========================================================================
    // BOOKING SCOPE - Operations and scheduling
    // =========================================================================
    'booking' => [
        'operation' => [
            'source_tables' => [
                'et_ophtroperationbooking_operation',
                'ophtroperationbooking_operation_booking',
            ],
            'document_type' => 'operation',
            'key_pattern' => 'operation::{id}',
            'embedded' => ['procedures'],
        ],
        
        'session' => [
            'source_tables' => ['ophtroperationbooking_operation_session'],
            'document_type' => 'session',
            'key_pattern' => 'session::{id}',
            'embedded' => [],
        ],
        
        'theatre' => [
            'source_tables' => ['ophtroperationbooking_operation_theatre'],
            'document_type' => 'theatre',
            'key_pattern' => 'theatre::{id}',
            'embedded' => [],
        ],
    ],
    
    // =========================================================================
    // ADMIN SCOPE - Audit and system data
    // =========================================================================
    'admin' => [
        'audit' => [
            'source_tables' => ['audit'],
            'document_type' => 'audit',
            'key_pattern' => 'audit::{id}',
            'embedded' => [],
            'ttl' => 31536000, // 1 year TTL for audit logs
        ],
        
        'setting' => [
            'source_tables' => ['setting_metadata', 'setting_installation'],
            'document_type' => 'setting',
            'key_pattern' => 'setting::{key}',
            'embedded' => [],
        ],
    ],
    
    // =========================================================================
    // REFERENCE SCOPE - Lookup tables
    // =========================================================================
    'reference' => [
        'event_type' => [
            'source_tables' => ['event_type'],
            'document_type' => 'event_type',
            'key_pattern' => 'event_type::{id}',
            'cache' => true, // Cache in memory
        ],
        
        'element_type' => [
            'source_tables' => ['element_type'],
            'document_type' => 'element_type',
            'key_pattern' => 'element_type::{id}',
            'cache' => true,
        ],
        
        'specialty' => [
            'source_tables' => ['specialty'],
            'document_type' => 'specialty',
            'key_pattern' => 'specialty::{id}',
            'cache' => true,
        ],
        
        'subspecialty' => [
            'source_tables' => ['subspecialty'],
            'document_type' => 'subspecialty',
            'key_pattern' => 'subspecialty::{id}',
            'cache' => true,
        ],
        
        'disorder' => [
            'source_tables' => ['disorder'],
            'document_type' => 'disorder',
            'key_pattern' => 'disorder::{id}',
        ],
        
        'ethnic_group' => [
            'source_tables' => ['ethnic_group'],
            'document_type' => 'ethnic_group',
            'key_pattern' => 'ethnic_group::{id}',
            'cache' => true,
        ],
        
        'gender' => [
            'source_tables' => ['gender'],
            'document_type' => 'gender',
            'key_pattern' => 'gender::{id}',
            'cache' => true,
        ],
        
        'country' => [
            'source_tables' => ['country'],
            'document_type' => 'country',
            'key_pattern' => 'country::{id}',
            'cache' => true,
        ],
    ],
];
```

---

## Section 5: Index Definitions (Tasks 14-15)

### Task 5.1: Create Comprehensive Index Definitions

**File**: `/protected/scripts/couchbase/indexes/phase3-indexes.n1ql`

```sql
-- ============================================================================
-- Phase 3: Core Document Indexes
-- Run after collections are created
-- ============================================================================

-- ======================
-- CORE SCOPE INDEXES
-- ======================

-- Patient indexes
CREATE INDEX idx_patient_type ON `openeyes`.`core`.`patient`(_type) 
    WHERE _type = "patient" USING GSI;

CREATE INDEX idx_patient_hos_num ON `openeyes`.`core`.`patient`(hos_num) 
    WHERE _type = "patient" AND hos_num IS NOT NULL USING GSI;

CREATE INDEX idx_patient_nhs_num ON `openeyes`.`core`.`patient`(nhs_num) 
    WHERE _type = "patient" AND nhs_num IS NOT NULL USING GSI;

CREATE INDEX idx_patient_name ON `openeyes`.`core`.`patient`(
    contact.last_name, 
    contact.first_name
) WHERE _type = "patient" USING GSI;

CREATE INDEX idx_patient_dob ON `openeyes`.`core`.`patient`(dob) 
    WHERE _type = "patient" USING GSI;

CREATE INDEX idx_patient_dob_name ON `openeyes`.`core`.`patient`(
    dob, 
    contact.last_name, 
    contact.first_name
) WHERE _type = "patient" USING GSI;

CREATE INDEX idx_patient_institution ON `openeyes`.`core`.`patient`(primary_institution_id) 
    WHERE _type = "patient" USING GSI;

CREATE INDEX idx_patient_gp ON `openeyes`.`core`.`patient`(gp_id) 
    WHERE _type = "patient" AND gp_id IS NOT NULL USING GSI;

CREATE INDEX idx_patient_deleted ON `openeyes`.`core`.`patient`(deleted) 
    WHERE _type = "patient" USING GSI;

-- Episode indexes
CREATE INDEX idx_episode_patient ON `openeyes`.`core`.`episode`(patient_id) 
    WHERE _type = "episode" USING GSI;

CREATE INDEX idx_episode_firm ON `openeyes`.`core`.`episode`(firm_id) 
    WHERE _type = "episode" USING GSI;

CREATE INDEX idx_episode_subspecialty ON `openeyes`.`core`.`episode`(subspecialty_id) 
    WHERE _type = "episode" USING GSI;

CREATE INDEX idx_episode_status_date ON `openeyes`.`core`.`episode`(
    episode_status_id, 
    start_date DESC
) WHERE _type = "episode" USING GSI;

CREATE INDEX idx_episode_patient_firm ON `openeyes`.`core`.`episode`(
    patient_id, 
    firm_id
) WHERE _type = "episode" USING GSI;

-- Event indexes
CREATE INDEX idx_event_episode ON `openeyes`.`core`.`event`(episode_id) 
    WHERE _type = "event" USING GSI;

CREATE INDEX idx_event_type ON `openeyes`.`core`.`event`(event_type_id) 
    WHERE _type = "event" USING GSI;

CREATE INDEX idx_event_date ON `openeyes`.`core`.`event`(event_date DESC) 
    WHERE _type = "event" USING GSI;

CREATE INDEX idx_event_deleted ON `openeyes`.`core`.`event`(deleted, delete_pending) 
    WHERE _type = "event" USING GSI;

CREATE INDEX idx_event_institution_site ON `openeyes`.`core`.`event`(
    institution_id, 
    site_id
) WHERE _type = "event" USING GSI;

CREATE INDEX idx_event_episode_date ON `openeyes`.`core`.`event`(
    episode_id, 
    event_date DESC
) WHERE _type = "event" USING GSI;

-- User indexes
CREATE INDEX idx_user_username ON `openeyes`.`core`.`user`(username) 
    WHERE _type = "user" USING GSI;

CREATE INDEX idx_user_active ON `openeyes`.`core`.`user`(active) 
    WHERE _type = "user" AND active = true USING GSI;

CREATE INDEX idx_user_institution ON `openeyes`.`core`.`user`(primary_institution_id) 
    WHERE _type = "user" USING GSI;

-- Firm indexes
CREATE INDEX idx_firm_active ON `openeyes`.`core`.`firm`(active) 
    WHERE _type = "firm" USING GSI;

CREATE INDEX idx_firm_institution ON `openeyes`.`core`.`firm`(institution_id) 
    WHERE _type = "firm" USING GSI;

-- ======================
-- CLINICAL SCOPE INDEXES
-- ======================

CREATE INDEX idx_examination_event ON `openeyes`.`clinical`.`examination`(event_id) 
    WHERE _type = "examination" USING GSI;

CREATE INDEX idx_examination_date ON `openeyes`.`clinical`.`examination`(_modified DESC) 
    WHERE _type = "examination" USING GSI;

CREATE INDEX idx_diagnosis_disorder ON `openeyes`.`clinical`.`diagnosis`(disorder_id) 
    WHERE _type = "diagnosis" USING GSI;

CREATE INDEX idx_diagnosis_patient ON `openeyes`.`clinical`.`diagnosis`(patient_id) 
    WHERE _type = "diagnosis" USING GSI;

CREATE INDEX idx_medication_patient ON `openeyes`.`clinical`.`medication`(patient_id) 
    WHERE _type = "medication" USING GSI;

CREATE INDEX idx_allergy_patient ON `openeyes`.`clinical`.`allergy`(patient_id) 
    WHERE _type = "allergy" USING GSI;

-- ======================
-- REFERENCE SCOPE INDEXES
-- ======================

CREATE INDEX idx_disorder_term ON `openeyes`.`reference`.`disorder`(term) 
    WHERE _type = "disorder" USING GSI;

CREATE INDEX idx_disorder_snomed ON `openeyes`.`reference`.`disorder`(
    DISTINCT ARRAY s FOR s IN snomed_codes END
) WHERE _type = "disorder" USING GSI;

-- ======================
-- ADMIN SCOPE INDEXES
-- ======================

CREATE INDEX idx_audit_date ON `openeyes`.`admin`.`audit`(created_date DESC) 
    WHERE _type = "audit" USING GSI;

CREATE INDEX idx_audit_patient ON `openeyes`.`admin`.`audit`(patient_id) 
    WHERE _type = "audit" AND patient_id IS NOT NULL USING GSI;

CREATE INDEX idx_audit_user ON `openeyes`.`admin`.`audit`(user_id) 
    WHERE _type = "audit" USING GSI;

CREATE INDEX idx_audit_action ON `openeyes`.`admin`.`audit`(action, target) 
    WHERE _type = "audit" USING GSI;

-- ======================
-- COVERING INDEXES (for common queries)
-- ======================

-- Patient search covering index
CREATE INDEX idx_patient_search_covering ON `openeyes`.`core`.`patient`(
    hos_num,
    nhs_num,
    contact.last_name,
    contact.first_name,
    dob,
    gender
) WHERE _type = "patient" AND deleted = false USING GSI;

-- Episode list covering index
CREATE INDEX idx_episode_list_covering ON `openeyes`.`core`.`episode`(
    patient_id,
    start_date DESC,
    episode_status_id,
    subspecialty_id
) WHERE _type = "episode" USING GSI;

-- Event list covering index
CREATE INDEX idx_event_list_covering ON `openeyes`.`core`.`event`(
    episode_id,
    event_date DESC,
    event_type_id,
    deleted
) WHERE _type = "event" USING GSI;
```

---

## Section 6: CouchbaseActiveRecord Base Model (Tasks 16-18)

### Task 6.1: Create CouchbaseActiveRecord Base Class

**File**: `/protected/models/CouchbaseActiveRecord.php`

```php
<?php
/**
 * Base class for Couchbase document models
 * 
 * Provides ActiveRecord-like interface for Couchbase documents,
 * maintaining compatibility with existing OpenEyes patterns.
 */

use OE\Couchbase\Transformers\TypeTransformer;

abstract class CouchbaseActiveRecord extends CModel
{
    /** @var array Document attributes */
    protected $_attributes = [];
    
    /** @var array Original attributes for dirty checking */
    protected $_originalAttributes = [];
    
    /** @var bool Whether this is a new record */
    protected $_isNewRecord = true;
    
    /** @var mixed Primary key value */
    protected $_pk;
    
    /** @var CouchbaseConnection */
    protected $_connection;
    
    /** @var array Validation errors */
    private $_errors = [];
    
    /**
     * Get the document type identifier
     * @return string
     */
    abstract public function documentType(): string;
    
    /**
     * Get the Couchbase scope name
     * @return string
     */
    abstract public function scope(): string;
    
    /**
     * Get the Couchbase collection name
     * @return string
     */
    abstract public function collectionName(): string;
    
    /**
     * Define validation rules
     * @return array
     */
    public function rules(): array
    {
        return [];
    }
    
    /**
     * Define attribute labels
     * @return array
     */
    public function attributeLabels(): array
    {
        return [];
    }
    
    /**
     * Get attribute names
     * @return array
     */
    public function attributeNames(): array
    {
        return array_keys($this->_attributes);
    }
    
    /**
     * Get the document key
     * @return string
     */
    public function getDocumentKey(): string
    {
        return $this->collectionName() . '::' . $this->getPrimaryKey();
    }
    
    /**
     * Get primary key
     * @return mixed
     */
    public function getPrimaryKey()
    {
        return $this->_pk;
    }
    
    /**
     * Set primary key
     * @param mixed $pk
     */
    public function setPrimaryKey($pk): void
    {
        $this->_pk = $pk;
    }
    
    /**
     * Check if new record
     * @return bool
     */
    public function getIsNewRecord(): bool
    {
        return $this->_isNewRecord;
    }
    
    /**
     * Set new record flag
     * @param bool $value
     */
    public function setIsNewRecord(bool $value): void
    {
        $this->_isNewRecord = $value;
    }
    
    /**
     * Get the Couchbase connection
     * @return CouchbaseConnection
     */
    public function getConnection(): CouchbaseConnection
    {
        if ($this->_connection === null) {
            $this->_connection = Yii::app()->couchbase;
        }
        return $this->_connection;
    }
    
    /**
     * Get the Couchbase collection object
     * @return \Couchbase\Collection
     */
    protected function getCollection()
    {
        return $this->getConnection()->getCollection(
            $this->scope(),
            $this->collectionName()
        );
    }
    
    /**
     * Get a single attribute
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name)
    {
        return $this->_attributes[$name] ?? null;
    }
    
    /**
     * Set a single attribute
     * @param string $name
     * @param mixed $value
     */
    public function setAttribute(string $name, $value): void
    {
        $this->_attributes[$name] = $value;
    }
    
    /**
     * Get all attributes
     * @return array
     */
    public function getAttributes(?array $names = null): array
    {
        if ($names === null) {
            return $this->_attributes;
        }
        
        return array_intersect_key($this->_attributes, array_flip($names));
    }
    
    /**
     * Set multiple attributes
     * @param array $values
     * @param bool $safeOnly Only set safe attributes
     */
    public function setAttributes(array $values, bool $safeOnly = true): void
    {
        foreach ($values as $name => $value) {
            $this->_attributes[$name] = $value;
        }
    }
    
    /**
     * Check if attribute is dirty
     * @param string $name
     * @return bool
     */
    public function isAttributeDirty(string $name): bool
    {
        if (!array_key_exists($name, $this->_originalAttributes)) {
            return true;
        }
        return $this->_attributes[$name] !== $this->_originalAttributes[$name];
    }
    
    /**
     * Get dirty attributes
     * @return array
     */
    public function getDirtyAttributes(): array
    {
        $dirty = [];
        foreach ($this->_attributes as $name => $value) {
            if ($this->isAttributeDirty($name)) {
                $dirty[$name] = $value;
            }
        }
        return $dirty;
    }
    
    /**
     * Static model factory (matches Yii pattern)
     * @param string|null $className
     * @return static
     */
    public static function model($className = null)
    {
        $className = $className ?: get_called_class();
        return new $className();
    }
    
    /**
     * Find document by primary key
     * @param mixed $pk
     * @return static|null
     */
    public static function findByPk($pk): ?self
    {
        $model = static::model();
        
        try {
            $result = $model->getCollection()->get($model->collectionName() . '::' . $pk);
            
            $data = $result->content();
            if (is_object($data)) {
                $data = (array)$data;
            }
            
            $model->_attributes = $data;
            $model->_originalAttributes = $data;
            $model->_pk = $pk;
            $model->_isNewRecord = false;
            
            return $model;
        } catch (\Couchbase\Exception\DocumentNotFoundException $e) {
            return null;
        } catch (\Exception $e) {
            Yii::log(
                "Couchbase findByPk error: " . $e->getMessage(),
                CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return null;
        }
    }
    
    /**
     * Find documents by attributes using N1QL
     * @param array $attributes
     * @param array $options
     * @return array Array of models
     */
    public static function findAllByAttributes(array $attributes, array $options = []): array
    {
        $model = static::model();
        $conn = $model->getConnection();
        
        $bucket = $conn->config['bucket'];
        $scope = $model->scope();
        $collection = $model->collectionName();
        
        // Build N1QL query
        $query = "SELECT META().id as _key, * FROM `{$bucket}`.`{$scope}`.`{$collection}` WHERE _type = \$type";
        $params = ['type' => $model->documentType()];
        
        $paramIndex = 0;
        foreach ($attributes as $key => $value) {
            $paramName = "p{$paramIndex}";
            if ($value === null) {
                $query .= " AND `{$key}` IS NULL";
            } else {
                $query .= " AND `{$key}` = \${$paramName}";
                $params[$paramName] = $value;
            }
            $paramIndex++;
        }
        
        // Apply options
        if (!empty($options['order'])) {
            $query .= " ORDER BY " . $options['order'];
        }
        if (!empty($options['limit'])) {
            $query .= " LIMIT " . (int)$options['limit'];
        }
        if (!empty($options['offset'])) {
            $query .= " OFFSET " . (int)$options['offset'];
        }
        
        try {
            $results = $conn->query($query, $params);
            $models = [];
            
            foreach ($results as $row) {
                $newModel = static::model();
                
                // Extract data (structure depends on query)
                $data = isset($row[$collection]) ? (array)$row[$collection] : (array)$row;
                unset($data['_key']);
                
                $newModel->_attributes = $data;
                $newModel->_originalAttributes = $data;
                $newModel->_pk = isset($row['_key']) ? $model->extractPk($row['_key']) : ($data['_mysql_id'] ?? null);
                $newModel->_isNewRecord = false;
                
                $models[] = $newModel;
            }
            
            return $models;
        } catch (\Exception $e) {
            Yii::log(
                "Couchbase findAllByAttributes error: " . $e->getMessage(),
                CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return [];
        }
    }
    
    /**
     * Extract primary key from document key
     * @param string $key
     * @return string
     */
    protected function extractPk(string $key): string
    {
        $parts = explode('::', $key);
        return end($parts);
    }
    
    /**
     * Save the document
     * @param bool $runValidation
     * @return bool
     */
    public function save(bool $runValidation = true): bool
    {
        if ($runValidation && !$this->validate()) {
            return false;
        }
        
        // Prepare document data
        $data = $this->_attributes;
        $data['_type'] = $this->documentType();
        $data['_modified'] = date('c');
        
        $userId = $this->getChangeUserId();
        $data['last_modified_user_id'] = $userId;
        $data['last_modified_date'] = date('Y-m-d H:i:s');
        
        try {
            if ($this->_isNewRecord) {
                // Generate ID if not set
                if (!$this->_pk) {
                    $this->_pk = $this->generatePrimaryKey();
                }
                
                $data['_created'] = date('c');
                $data['_mysql_id'] = is_numeric($this->_pk) ? (int)$this->_pk : null;
                $data['_version'] = 1;
                $data['created_user_id'] = $userId;
                $data['created_date'] = date('Y-m-d H:i:s');
                
                $this->beforeSave();
                $this->getCollection()->insert($this->getDocumentKey(), $data);
                $this->_isNewRecord = false;
            } else {
                // Increment version
                $data['_version'] = ($data['_version'] ?? 0) + 1;
                
                $this->beforeSave();
                $this->getCollection()->replace($this->getDocumentKey(), $data);
            }
            
            $this->_attributes = $data;
            $this->_originalAttributes = $data;
            $this->afterSave();
            
            return true;
        } catch (\Exception $e) {
            Yii::log(
                "Couchbase save error: " . $e->getMessage(),
                CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            $this->addError('_save', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete the document
     * @return bool
     */
    public function delete(): bool
    {
        if ($this->_isNewRecord) {
            return false;
        }
        
        try {
            $this->beforeDelete();
            $this->getCollection()->remove($this->getDocumentKey());
            $this->afterDelete();
            return true;
        } catch (\Exception $e) {
            Yii::log(
                "Couchbase delete error: " . $e->getMessage(),
                CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return false;
        }
    }
    
    /**
     * Soft delete (set deleted flag)
     * @param string|null $reason
     * @return bool
     */
    public function softDelete(?string $reason = null): bool
    {
        $this->_attributes['deleted'] = true;
        if ($reason) {
            $this->_attributes['delete_reason'] = $reason;
        }
        return $this->save(false);
    }
    
    /**
     * Validate the model
     * @param array|null $attributes
     * @return bool
     */
    public function validate(?array $attributes = null): bool
    {
        $this->_errors = [];
        
        foreach ($this->rules() as $rule) {
            $ruleAttributes = is_array($rule[0]) ? $rule[0] : [$rule[0]];
            $validator = $rule[1];
            $params = array_slice($rule, 2);
            
            foreach ($ruleAttributes as $attr) {
                if ($attributes !== null && !in_array($attr, $attributes)) {
                    continue;
                }
                
                $value = $this->_attributes[$attr] ?? null;
                
                if ($validator === 'required' && ($value === null || $value === '')) {
                    $this->addError($attr, "{$attr} is required");
                }
                // Add more validators as needed
            }
        }
        
        return empty($this->_errors);
    }
    
    /**
     * Add validation error
     * @param string $attribute
     * @param string $error
     */
    public function addError(string $attribute, string $error): void
    {
        $this->_errors[$attribute][] = $error;
    }
    
    /**
     * Get validation errors
     * @param string|null $attribute
     * @return array
     */
    public function getErrors(?string $attribute = null): array
    {
        if ($attribute === null) {
            return $this->_errors;
        }
        return $this->_errors[$attribute] ?? [];
    }
    
    /**
     * Check if has errors
     * @param string|null $attribute
     * @return bool
     */
    public function hasErrors(?string $attribute = null): bool
    {
        if ($attribute === null) {
            return !empty($this->_errors);
        }
        return !empty($this->_errors[$attribute]);
    }
    
    /**
     * Generate a new primary key
     * @return string
     */
    protected function generatePrimaryKey(): string
    {
        return uniqid('', true);
    }
    
    /**
     * Get the current user ID for auditing
     * @return int
     */
    protected function getChangeUserId(): int
    {
        $user = Yii::app()->user ?? null;
        return ($user && $user->id) ? $user->id : 1;
    }
    
    /**
     * Called before save - override in subclasses
     */
    protected function beforeSave(): void
    {
    }
    
    /**
     * Called after save - override in subclasses
     */
    protected function afterSave(): void
    {
    }
    
    /**
     * Called before delete - override in subclasses
     */
    protected function beforeDelete(): void
    {
    }
    
    /**
     * Called after delete - override in subclasses
     */
    protected function afterDelete(): void
    {
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
    
    /**
     * Magic unset
     */
    public function __unset($name)
    {
        unset($this->_attributes[$name]);
    }
    
    /**
     * Convert to array
     * @return array
     */
    public function toArray(): array
    {
        return $this->_attributes;
    }
}
```

---

### Task 6.2: Create Unit Tests

**File**: `/protected/tests/unit/models/couchbase/TypeTransformerTest.php`

```php
<?php
/**
 * Unit tests for TypeTransformer
 */

use OE\Couchbase\Transformers\TypeTransformer;

class TypeTransformerTest extends CTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }
    
    /**
     * @test
     */
    public function testIntegerTransformation(): void
    {
        $this->assertSame(123, TypeTransformer::toJson('123', 'int(10)'));
        $this->assertSame(0, TypeTransformer::toJson('0', 'int(10)'));
        $this->assertSame(-5, TypeTransformer::toJson('-5', 'int(10)'));
        $this->assertNull(TypeTransformer::toJson(null, 'int(10)'));
    }
    
    /**
     * @test
     */
    public function testBooleanTransformation(): void
    {
        // Boolean fields by name
        $this->assertTrue(TypeTransformer::toJson('1', 'tinyint(1)', 'active'));
        $this->assertFalse(TypeTransformer::toJson('0', 'tinyint(1)', 'active'));
        $this->assertTrue(TypeTransformer::toJson('1', 'tinyint(1)', 'is_doctor'));
        $this->assertFalse(TypeTransformer::toJson('0', 'tinyint(1)', 'deleted'));
        
        // Non-boolean tinyint
        $this->assertSame(2, TypeTransformer::toJson('2', 'tinyint(1)', 'status_code'));
    }
    
    /**
     * @test
     */
    public function testDateTransformation(): void
    {
        $this->assertSame('2023-12-19', TypeTransformer::toJson('2023-12-19', 'date'));
        $this->assertNull(TypeTransformer::toJson('0000-00-00', 'date'));
        $this->assertNull(TypeTransformer::toJson(null, 'date'));
    }
    
    /**
     * @test
     */
    public function testDatetimeTransformation(): void
    {
        $result = TypeTransformer::toJson('2023-12-19 14:30:00', 'datetime');
        $this->assertStringContainsString('2023-12-19', $result);
        $this->assertStringContainsString('14:30:00', $result);
        
        $this->assertNull(TypeTransformer::toJson('0000-00-00 00:00:00', 'datetime'));
    }
    
    /**
     * @test
     */
    public function testDecimalTransformation(): void
    {
        $this->assertSame(123.45, TypeTransformer::toJson('123.45', 'decimal(10,2)'));
        $this->assertSame(0.0, TypeTransformer::toJson('0.00', 'decimal(10,2)'));
    }
    
    /**
     * @test
     */
    public function testBlobTransformation(): void
    {
        $data = 'binary data';
        $encoded = TypeTransformer::toJson($data, 'blob');
        $this->assertSame(base64_encode($data), $encoded);
    }
    
    /**
     * @test
     */
    public function testJsonTransformation(): void
    {
        $jsonString = '{"key": "value", "num": 123}';
        $result = TypeTransformer::toJson($jsonString, 'json');
        $this->assertIsArray($result);
        $this->assertSame('value', $result['key']);
        $this->assertSame(123, $result['num']);
    }
    
    /**
     * @test
     */
    public function testSetTransformation(): void
    {
        $this->assertSame(['a', 'b', 'c'], TypeTransformer::toJson('a,b,c', 'set'));
        $this->assertSame([], TypeTransformer::toJson('', 'set'));
    }
    
    /**
     * @test
     */
    public function testRoundTripInteger(): void
    {
        $original = '42';
        $toJson = TypeTransformer::toJson($original, 'int(10)');
        $backToMysql = TypeTransformer::toMysql($toJson, 'int(10)');
        $this->assertSame(42, $backToMysql);
    }
    
    /**
     * @test
     */
    public function testRoundTripDatetime(): void
    {
        $original = '2023-12-19 14:30:00';
        $toJson = TypeTransformer::toJson($original, 'datetime');
        $backToMysql = TypeTransformer::toMysql($toJson, 'datetime');
        $this->assertSame($original, $backToMysql);
    }
    
    /**
     * @test
     */
    public function testRoundTripBoolean(): void
    {
        $this->assertSame(1, TypeTransformer::toMysql(true, 'tinyint(1)', 'active'));
        $this->assertSame(0, TypeTransformer::toMysql(false, 'tinyint(1)', 'deleted'));
    }
    
    /**
     * @test
     */
    public function testTransformRow(): void
    {
        $row = [
            'id' => '123',
            'name' => 'Test',
            'active' => '1',
            'created_date' => '2023-12-19 10:00:00',
        ];
        
        $types = [
            'id' => 'int(10) unsigned',
            'name' => 'varchar(100)',
            'active' => 'tinyint(1)',
            'created_date' => 'datetime',
        ];
        
        $result = TypeTransformer::transformRow($row, $types);
        
        $this->assertSame(123, $result['id']);
        $this->assertSame('Test', $result['name']);
        $this->assertTrue($result['active']);
        $this->assertStringContainsString('2023-12-19', $result['created_date']);
    }
}
```

**File**: `/protected/tests/unit/models/couchbase/CouchbaseActiveRecordTest.php`

```php
<?php
/**
 * Unit tests for CouchbaseActiveRecord
 */

class CouchbaseActiveRecordTest extends CTestCase
{
    private $skipTests = false;
    
    public function setUp(): void
    {
        parent::setUp();
        
        // Skip if Couchbase not available
        if (!extension_loaded('couchbase')) {
            $this->skipTests = true;
        }
    }
    
    /**
     * @test
     */
    public function testAttributeAccess(): void
    {
        $model = new TestCouchbaseModel();
        
        // Set via property
        $model->name = 'Test';
        $this->assertSame('Test', $model->name);
        
        // Set via setAttribute
        $model->setAttribute('email', 'test@example.com');
        $this->assertSame('test@example.com', $model->getAttribute('email'));
        
        // Set via setAttributes
        $model->setAttributes(['age' => 30, 'active' => true]);
        $this->assertSame(30, $model->age);
        $this->assertTrue($model->active);
    }
    
    /**
     * @test
     */
    public function testDirtyTracking(): void
    {
        $model = new TestCouchbaseModel();
        $model->_originalAttributes = ['name' => 'Original'];
        $model->_attributes = ['name' => 'Original'];
        
        $this->assertFalse($model->isAttributeDirty('name'));
        
        $model->name = 'Changed';
        $this->assertTrue($model->isAttributeDirty('name'));
        
        $dirty = $model->getDirtyAttributes();
        $this->assertArrayHasKey('name', $dirty);
    }
    
    /**
     * @test
     */
    public function testIsNewRecord(): void
    {
        $model = new TestCouchbaseModel();
        $this->assertTrue($model->getIsNewRecord());
        
        $model->setIsNewRecord(false);
        $this->assertFalse($model->getIsNewRecord());
    }
    
    /**
     * @test
     */
    public function testDocumentKey(): void
    {
        $model = new TestCouchbaseModel();
        $model->setPrimaryKey(123);
        
        $this->assertSame('test::123', $model->getDocumentKey());
    }
    
    /**
     * @test
     */
    public function testValidation(): void
    {
        $model = new TestCouchbaseModelWithRules();
        
        // Missing required field
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('name'));
        
        // With required field
        $model->name = 'Test';
        $model->clearErrors();
        $this->assertTrue($model->validate());
    }
    
    /**
     * @test
     */
    public function testToArray(): void
    {
        $model = new TestCouchbaseModel();
        $model->name = 'Test';
        $model->active = true;
        
        $array = $model->toArray();
        $this->assertIsArray($array);
        $this->assertSame('Test', $array['name']);
        $this->assertTrue($array['active']);
    }
}

/**
 * Test model for unit testing
 */
class TestCouchbaseModel extends CouchbaseActiveRecord
{
    public function documentType(): string
    {
        return 'test';
    }
    
    public function scope(): string
    {
        return 'core';
    }
    
    public function collectionName(): string
    {
        return 'test';
    }
    
    public function clearErrors(): void
    {
        $this->_errors = [];
    }
}

class TestCouchbaseModelWithRules extends TestCouchbaseModel
{
    public function rules(): array
    {
        return [
            ['name', 'required'],
        ];
    }
}
```

---

## Acceptance Criteria Summary

### Section 1: Schema Analysis
- [ ] RelationshipAnalyzer script executes successfully
- [ ] Outputs valid JSON with all 500+ tables
- [ ] Embedding decisions are logical and documented
- [ ] core-tables.md documents all primary tables

### Section 2: Document Schemas
- [ ] All JSON schemas validate against Draft-07
- [ ] patient.schema.json has all required fields
- [ ] episode.schema.json correctly references patient
- [ ] event.schema.json supports element embedding
- [ ] user.schema.json includes contact embedding

### Section 3: Type Transformation
- [ ] TypeTransformer handles all MySQL types
- [ ] Boolean detection works for common patterns
- [ ] Round-trip transformation preserves data
- [ ] DocumentTransformer base class is functional
- [ ] PatientTransformer produces valid documents

### Section 4: Collection Mapping
- [ ] couchbase-collections.php covers all scopes
- [ ] Key patterns are consistent
- [ ] Transformer references are correct
- [ ] Index definitions match schema

### Section 5: Index Definitions
- [ ] All primary indexes defined
- [ ] Secondary indexes support common queries
- [ ] Covering indexes for frequent operations
- [ ] Index syntax is valid N1QL

### Section 6: CouchbaseActiveRecord
- [ ] CRUD operations work
- [ ] Attribute access matches Yii patterns
- [ ] Dirty tracking works
- [ ] Validation framework functional
- [ ] Unit tests pass

---

## Testing Commands

```bash
# 1. Create directory structure
docker compose -f .devcontainer/docker-compose.yml exec web bash -c "mkdir -p /var/www/openeyes/protected/models/couchbase/schemas /var/www/openeyes/protected/models/couchbase/transformers /var/www/openeyes/protected/scripts/couchbase/analyzers /var/www/openeyes/protected/tests/unit/models/couchbase"

# 2. Run relationship analyzer
docker compose -f .devcontainer/docker-compose.yml exec web php /var/www/openeyes/protected/yiic.php analyzerelationships

# 3. Verify JSON schema validity
docker compose -f .devcontainer/docker-compose.yml exec web php -r "
\$schema = json_decode(file_get_contents('/var/www/openeyes/protected/models/couchbase/schemas/patient.schema.json'));
echo json_last_error() === JSON_ERROR_NONE ? 'Patient schema valid' : 'Patient schema invalid';
"

# 4. Run unit tests
docker compose -f .devcontainer/docker-compose.yml exec web php /var/www/openeyes/bin/phpunit --configuration /var/www/openeyes/protected/tests/phpunit.xml /var/www/openeyes/protected/tests/unit/models/couchbase/

# 5. Test type transformer
docker compose -f .devcontainer/docker-compose.yml exec web php -r "
require_once '/var/www/openeyes/protected/models/couchbase/transformers/TypeTransformer.php';
use OE\Couchbase\Transformers\TypeTransformer;
var_dump(TypeTransformer::toJson('1', 'tinyint(1)', 'active'));
var_dump(TypeTransformer::toJson('2023-12-19', 'date', 'dob'));
"

# 6. Test patient transformer
docker compose -f .devcontainer/docker-compose.yml exec web php -r "
require_once '/var/www/openeyes/protected/yii.php';
require_once '/var/www/openeyes/protected/models/couchbase/transformers/TypeTransformer.php';
require_once '/var/www/openeyes/protected/models/couchbase/transformers/DocumentTransformer.php';
require_once '/var/www/openeyes/protected/models/couchbase/transformers/PatientTransformer.php';
\$transformer = new OE\Couchbase\Transformers\PatientTransformer();
\$doc = \$transformer->transformById(1);
print_r(\$doc);
"
```

---

## Rollback Instructions

If rollback is needed:

```bash
# Remove Phase 3 files
rm -rf protected/models/couchbase/
rm -rf protected/scripts/couchbase/analyzers/
rm -rf protected/tests/unit/models/couchbase/
rm -f protected/commands/AnalyzerelationshipsCommand.php
rm -f protected/config/couchbase-collections.php
rm -f protected/scripts/couchbase/indexes/phase3-indexes.n1ql
rm -rf docs/migration-mariadb-to-couchbase/schema-analysis/

# Restore from git if needed
git checkout -- protected/
```

---

## Definition of Done

- [ ] All 18 tasks completed
- [ ] All acceptance criteria met
- [ ] Unit tests pass
- [ ] Documentation complete
- [ ] No breaking changes to existing code
- [ ] Code reviewed

---

**Specification Author**: AI Agent (Droid)  
**Date**: December 19, 2025  
**Ready for Implementation**: YES

---

*This specification provides complete, step-by-step instructions for implementing Phase 3. Each task includes file paths, complete code, acceptance criteria, and testing commands.*
