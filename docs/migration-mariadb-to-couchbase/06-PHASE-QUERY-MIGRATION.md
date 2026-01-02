# Phase 6: Query Migration (SQL to N1QL)

## Overview
This phase focuses on converting existing SQL queries to Couchbase N1QL (SQL++). This includes report queries, search queries, and complex data retrieval patterns.

## Prerequisites
- Phase 5 completed (Module models migrated)
- Data synced to Couchbase
- Indexes created

## Dependencies
- Phase 5: Module Model Migration

## Tasks

### 6.1 Query Inventory

#### 6.1.1 Query Analyzer Script
**File**: `/protected/scripts/couchbase/analyze-queries.php`

```php
<?php
/**
 * Analyze SQL queries in codebase for migration
 */

require_once(dirname(__FILE__) . '/../../yiic.php');

class QueryAnalyzer
{
    private $patterns = [
        'createCommand' => '/createCommand\s*\(\s*[\'"]([^"\']+)[\'"]/ms',
        'rawSql' => '/->(?:query|execute|queryAll|queryRow|queryColumn|queryScalar)\s*\(\s*[\'"]([^"\']+)[\'"]/ms',
        'findBySql' => '/findBySql\s*\(\s*[\'"]([^"\']+)[\'"]/ms',
        'findAllBySql' => '/findAllBySql\s*\(\s*[\'"]([^"\']+)[\'"]/ms',
    ];
    
    private $queries = [];
    
    public function analyze($directory)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $this->analyzeFile($file->getPathname());
            }
        }
        
        return $this->queries;
    }
    
    private function analyzeFile($filepath)
    {
        $content = file_get_contents($filepath);
        
        foreach ($this->patterns as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $sql) {
                    $this->queries[] = [
                        'file' => $filepath,
                        'type' => $type,
                        'sql' => trim($sql),
                        'complexity' => $this->assessComplexity($sql),
                    ];
                }
            }
        }
    }
    
    private function assessComplexity($sql)
    {
        $score = 0;
        
        // JOINs add complexity
        $score += substr_count(strtoupper($sql), ' JOIN ') * 2;
        
        // Subqueries
        $score += substr_count(strtoupper($sql), 'SELECT', 1) * 3;
        
        // Aggregations
        $patterns = ['GROUP BY', 'HAVING', 'UNION', 'DISTINCT'];
        foreach ($patterns as $p) {
            $score += substr_count(strtoupper($sql), $p);
        }
        
        // Complex functions
        $functions = ['COALESCE', 'IFNULL', 'CASE', 'CONCAT'];
        foreach ($functions as $f) {
            $score += substr_count(strtoupper($sql), $f);
        }
        
        if ($score <= 2) return 'simple';
        if ($score <= 5) return 'moderate';
        return 'complex';
    }
    
    public function exportReport($filename)
    {
        $grouped = [
            'simple' => [],
            'moderate' => [],
            'complex' => [],
        ];
        
        foreach ($this->queries as $query) {
            $grouped[$query['complexity']][] = $query;
        }
        
        $report = "# SQL Query Migration Report\n\n";
        $report .= "Total Queries: " . count($this->queries) . "\n";
        $report .= "- Simple: " . count($grouped['simple']) . "\n";
        $report .= "- Moderate: " . count($grouped['moderate']) . "\n";
        $report .= "- Complex: " . count($grouped['complex']) . "\n\n";
        
        foreach ($grouped as $complexity => $queries) {
            $report .= "## {$complexity} Queries\n\n";
            foreach ($queries as $q) {
                $report .= "### File: {$q['file']}\n";
                $report .= "```sql\n{$q['sql']}\n```\n\n";
            }
        }
        
        file_put_contents($filename, $report);
    }
}

$analyzer = new QueryAnalyzer();
$analyzer->analyze(dirname(__FILE__) . '/../../');
$analyzer->exportReport(dirname(__FILE__) . '/query-migration-report.md');
echo "Report generated\n";
```

**Acceptance Criteria**:
- [ ] All queries identified and categorized
- [ ] Complexity assessment accurate
- [ ] Report generated

### 6.2 Query Builder for Couchbase

#### 6.2.1 N1QL Query Builder
**File**: `/protected/components/database/N1qlQueryBuilder.php`

```php
<?php
/**
 * N1QL Query Builder for Couchbase
 */

namespace OE\Database;

class N1qlQueryBuilder
{
    private $bucket;
    private $scope;
    private $collection;
    private $select = ['*'];
    private $joins = [];
    private $where = [];
    private $params = [];
    private $orderBy = [];
    private $groupBy = [];
    private $having = [];
    private $limit;
    private $offset;
    
    public function __construct(string $bucket = 'openeyes')
    {
        $this->bucket = $bucket;
    }
    
    public function from(string $scope, string $collection): self
    {
        $this->scope = $scope;
        $this->collection = $collection;
        return $this;
    }
    
    public function select($columns): self
    {
        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }
        $this->select = array_map('trim', $columns);
        return $this;
    }
    
    public function where(string $condition, array $params = []): self
    {
        $this->where[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    public function andWhere(string $condition, array $params = []): self
    {
        return $this->where($condition, $params);
    }
    
    public function orWhere(string $condition, array $params = []): self
    {
        if (!empty($this->where)) {
            $last = array_pop($this->where);
            $this->where[] = "({$last}) OR ({$condition})";
        } else {
            $this->where[] = $condition;
        }
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    public function join(string $scope, string $collection, string $on): self
    {
        $this->joins[] = [
            'type' => 'JOIN',
            'scope' => $scope,
            'collection' => $collection,
            'on' => $on,
        ];
        return $this;
    }
    
    public function leftJoin(string $scope, string $collection, string $on): self
    {
        $this->joins[] = [
            'type' => 'LEFT JOIN',
            'scope' => $scope,
            'collection' => $collection,
            'on' => $on,
        ];
        return $this;
    }
    
    public function nest(string $scope, string $collection, string $as, string $on): self
    {
        $this->joins[] = [
            'type' => 'NEST',
            'scope' => $scope,
            'collection' => $collection,
            'as' => $as,
            'on' => $on,
        ];
        return $this;
    }
    
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }
    
    public function groupBy($columns): self
    {
        if (is_string($columns)) {
            $columns = explode(',', $columns);
        }
        $this->groupBy = array_merge($this->groupBy, array_map('trim', $columns));
        return $this;
    }
    
    public function having(string $condition, array $params = []): self
    {
        $this->having[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }
    
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
    
    public function build(): string
    {
        $parts = [];
        
        // SELECT
        $selectStr = implode(', ', $this->select);
        if (strpos($selectStr, 'META()') === false && $selectStr !== '*') {
            $selectStr = "META().id, {$selectStr}";
        }
        $parts[] = "SELECT {$selectStr}";
        
        // FROM
        $parts[] = "FROM `{$this->bucket}`.`{$this->scope}`.`{$this->collection}`";
        
        // JOINs
        foreach ($this->joins as $join) {
            $target = "`{$this->bucket}`.`{$join['scope']}`.`{$join['collection']}`";
            if (isset($join['as'])) {
                $target .= " AS {$join['as']}";
            }
            $parts[] = "{$join['type']} {$target} ON {$join['on']}";
        }
        
        // WHERE
        if (!empty($this->where)) {
            $parts[] = "WHERE " . implode(' AND ', $this->where);
        }
        
        // GROUP BY
        if (!empty($this->groupBy)) {
            $parts[] = "GROUP BY " . implode(', ', $this->groupBy);
        }
        
        // HAVING
        if (!empty($this->having)) {
            $parts[] = "HAVING " . implode(' AND ', $this->having);
        }
        
        // ORDER BY
        if (!empty($this->orderBy)) {
            $parts[] = "ORDER BY " . implode(', ', $this->orderBy);
        }
        
        // LIMIT/OFFSET
        if ($this->limit !== null) {
            $parts[] = "LIMIT {$this->limit}";
        }
        if ($this->offset !== null) {
            $parts[] = "OFFSET {$this->offset}";
        }
        
        return implode("\n", $parts);
    }
    
    public function getParams(): array
    {
        return $this->params;
    }
    
    public function execute(): array
    {
        $connection = \Yii::app()->couchbase;
        $result = $connection->query($this->build(), $this->params);
        return $result->rows();
    }
    
    /**
     * Reset builder for reuse
     */
    public function reset(): self
    {
        $this->select = ['*'];
        $this->scope = null;
        $this->collection = null;
        $this->joins = [];
        $this->where = [];
        $this->params = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->having = [];
        $this->limit = null;
        $this->offset = null;
        return $this;
    }
}
```

**Acceptance Criteria**:
- [ ] Builder generates valid N1QL
- [ ] All SQL clause types supported
- [ ] Parameters handled correctly

### 6.3 Common Query Translations

#### 6.3.1 SQL to N1QL Translation Guide
**File**: `/docs/migration-mariadb-to-couchbase/sql-to-n1ql-guide.md`

```markdown
# SQL to N1QL Translation Guide

## Basic SELECT

### MySQL
```sql
SELECT * FROM patient WHERE hos_num = '12345'
```

### N1QL
```sql
SELECT META().id, * FROM `openeyes`.`core`.`patient` 
WHERE hos_num = '12345'
```

## JOINs

### MySQL
```sql
SELECT p.*, e.start_date 
FROM patient p
JOIN episode e ON e.patient_id = p.id
WHERE p.id = 123
```

### N1QL (Using document references)
```sql
SELECT p.*, e.start_date
FROM `openeyes`.`core`.`patient` p
JOIN `openeyes`.`core`.`episode` e ON e.patient_id = META(p).id
WHERE META(p).id = 'patient::123'
```

### Alternative (Using NEST for embedded results)
```sql
SELECT p.*, ARRAY_AGG(e) as episodes
FROM `openeyes`.`core`.`patient` p
NEST `openeyes`.`core`.`episode` e ON e.patient_id = META(p).id
WHERE META(p).id = 'patient::123'
GROUP BY META(p).id, p
```

## Aggregations

### MySQL
```sql
SELECT firm_id, COUNT(*) as episode_count
FROM episode
WHERE start_date >= '2024-01-01'
GROUP BY firm_id
HAVING COUNT(*) > 10
ORDER BY episode_count DESC
```

### N1QL
```sql
SELECT firm_id, COUNT(*) as episode_count
FROM `openeyes`.`core`.`episode`
WHERE start_date >= '2024-01-01'
GROUP BY firm_id
HAVING COUNT(*) > 10
ORDER BY episode_count DESC
```

## Subqueries

### MySQL
```sql
SELECT * FROM patient
WHERE id IN (
    SELECT patient_id FROM episode 
    WHERE subspecialty_id = 1
)
```

### N1QL
```sql
SELECT META().id, * FROM `openeyes`.`core`.`patient` p
WHERE META(p).id IN (
    SELECT RAW e.patient_id 
    FROM `openeyes`.`core`.`episode` e
    WHERE e.subspecialty_id = '1'
)
```

## Date Functions

### MySQL
```sql
SELECT * FROM patient
WHERE DATE(dob) BETWEEN '1980-01-01' AND '1989-12-31'
```

### N1QL
```sql
SELECT META().id, * FROM `openeyes`.`core`.`patient`
WHERE dob BETWEEN '1980-01-01' AND '1989-12-31'
```

## LIKE Patterns

### MySQL
```sql
SELECT * FROM patient WHERE last_name LIKE 'Smi%'
```

### N1QL
```sql
SELECT META().id, * FROM `openeyes`.`core`.`patient`
WHERE last_name LIKE 'Smi%'
```

## COALESCE/IFNULL

### MySQL
```sql
SELECT COALESCE(nhs_num, hos_num) as identifier FROM patient
```

### N1QL
```sql
SELECT IFNULL(nhs_num, hos_num) as identifier 
FROM `openeyes`.`core`.`patient`
```

## Array Operations (Couchbase-specific)

### Access embedded array
```sql
SELECT META().id, elements.VisualAcuity
FROM `openeyes`.`clinical`.`examination`
WHERE ANY reading IN elements.VisualAcuity.left_readings 
      SATISFIES reading.value < 0.5 END
```

### Unnest embedded array
```sql
SELECT META().id, r.*
FROM `openeyes`.`clinical`.`examination` e
UNNEST e.elements.VisualAcuity.left_readings r
WHERE r.value < 0.5
```

## Pagination

### MySQL
```sql
SELECT * FROM patient ORDER BY last_name LIMIT 20 OFFSET 40
```

### N1QL
```sql
SELECT META().id, * FROM `openeyes`.`core`.`patient`
ORDER BY last_name
LIMIT 20 OFFSET 40
```
```

**Acceptance Criteria**:
- [ ] All common patterns documented
- [ ] Examples are correct and tested

#### 6.3.2 Query Migration Helper
**File**: `/protected/components/database/QueryMigrationHelper.php`

```php
<?php
/**
 * Helper to convert common SQL patterns to N1QL
 */

namespace OE\Database;

class QueryMigrationHelper
{
    private $bucket = 'openeyes';
    
    /**
     * Table to scope/collection mapping
     */
    private $collectionMap = [];
    
    public function __construct()
    {
        $this->collectionMap = require(\Yii::getPathOfAlias('application.config') 
            . '/couchbase-collection-map.php');
    }
    
    /**
     * Get the keyspace for a table
     */
    public function getKeyspace(string $table): string
    {
        foreach ($this->collectionMap as $scope => $collections) {
            if (isset($collections[$table])) {
                return "`{$this->bucket}`.`{$scope}`.`{$table}`";
            }
        }
        
        // Default to core scope if not found
        return "`{$this->bucket}`.`core`.`{$table}`";
    }
    
    /**
     * Convert simple SELECT to N1QL
     */
    public function convertSelect(string $sql): string
    {
        // Extract table name
        if (preg_match('/FROM\s+[`]?(\w+)[`]?/i', $sql, $matches)) {
            $table = $matches[1];
            $keyspace = $this->getKeyspace($table);
            
            // Replace table reference
            $sql = preg_replace(
                '/FROM\s+[`]?\w+[`]?/i',
                "FROM {$keyspace}",
                $sql
            );
            
            // Add META().id to SELECT if selecting *
            if (preg_match('/SELECT\s+\*/i', $sql)) {
                $sql = preg_replace(
                    '/SELECT\s+\*/i',
                    'SELECT META().id, *',
                    $sql
                );
            }
            
            // Convert id references to document keys
            $sql = preg_replace(
                '/(\w+)\.id\s*=\s*(\d+)/i',
                "META($1).id = '$table::$2'",
                $sql
            );
        }
        
        return $sql;
    }
    
    /**
     * Convert a CDbCriteria to N1QL builder
     */
    public function criteriaToBuilder(
        \CDbCriteria $criteria,
        string $table
    ): N1qlQueryBuilder {
        $builder = new N1qlQueryBuilder($this->bucket);
        
        // Get scope and collection
        $scope = 'core';
        foreach ($this->collectionMap as $s => $collections) {
            if (isset($collections[$table])) {
                $scope = $s;
                break;
            }
        }
        
        $builder->from($scope, $table);
        
        // SELECT
        if ($criteria->select !== '*') {
            $builder->select($criteria->select);
        }
        
        // WHERE
        if ($criteria->condition) {
            $builder->where($criteria->condition, $criteria->params);
        }
        
        // ORDER
        if ($criteria->order) {
            foreach (explode(',', $criteria->order) as $order) {
                $parts = preg_split('/\s+/', trim($order));
                $column = $parts[0];
                $direction = $parts[1] ?? 'ASC';
                $builder->orderBy($column, $direction);
            }
        }
        
        // LIMIT/OFFSET
        if ($criteria->limit > 0) {
            $builder->limit($criteria->limit);
        }
        if ($criteria->offset > 0) {
            $builder->offset($criteria->offset);
        }
        
        return $builder;
    }
}
```

**Acceptance Criteria**:
- [ ] Simple queries convert correctly
- [ ] Criteria conversion works
- [ ] Table mapping is correct

### 6.4 Report Query Migration

#### 6.4.1 Patient Search Report
**File**: `/protected/components/reports/CouchbasePatientSearch.php`

```php
<?php
/**
 * Patient search using Couchbase
 */

namespace OE\Reports;

use OE\Database\N1qlQueryBuilder;

class CouchbasePatientSearch
{
    /**
     * Search patients by various criteria
     */
    public function search(array $criteria): array
    {
        $builder = new N1qlQueryBuilder();
        $builder->from('core', 'patient')
            ->select('META().id, hos_num, nhs_num, first_name, last_name, dob, gender');
        
        if (!empty($criteria['hos_num'])) {
            $builder->where('hos_num = $hosNum', ['hosNum' => $criteria['hos_num']]);
        }
        
        if (!empty($criteria['nhs_num'])) {
            $builder->where('nhs_num = $nhsNum', ['nhsNum' => $criteria['nhs_num']]);
        }
        
        if (!empty($criteria['last_name'])) {
            $builder->where('LOWER(last_name) LIKE $lastName', [
                'lastName' => strtolower($criteria['last_name']) . '%'
            ]);
        }
        
        if (!empty($criteria['first_name'])) {
            $builder->where('LOWER(first_name) LIKE $firstName', [
                'firstName' => strtolower($criteria['first_name']) . '%'
            ]);
        }
        
        if (!empty($criteria['dob'])) {
            $builder->where('dob = $dob', ['dob' => $criteria['dob']]);
        }
        
        $builder->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($criteria['limit'] ?? 50);
        
        if (!empty($criteria['offset'])) {
            $builder->offset($criteria['offset']);
        }
        
        return $builder->execute();
    }
    
    /**
     * Full-text search using FTS index
     */
    public function fullTextSearch(string $query, int $limit = 50): array
    {
        $cluster = \Yii::app()->couchbase->getCluster();
        
        $searchQuery = new \Couchbase\SearchQuery(
            'patient_search',
            new \Couchbase\MatchQuery($query)
        );
        $searchQuery->limit($limit);
        
        $result = $cluster->searchQuery('patient_search', $searchQuery);
        
        $patients = [];
        foreach ($result->rows() as $row) {
            // Fetch full document
            $patient = PatientDocument::findByPk(
                str_replace('patient::', '', $row->id())
            );
            if ($patient) {
                $patients[] = $patient;
            }
        }
        
        return $patients;
    }
}
```

#### 6.4.2 Episode Report
**File**: `/protected/components/reports/CouchbaseEpisodeReport.php`

```php
<?php
/**
 * Episode statistics report using Couchbase
 */

namespace OE\Reports;

use OE\Database\N1qlQueryBuilder;

class CouchbaseEpisodeReport
{
    /**
     * Get episode counts by subspecialty
     */
    public function countBySubspecialty(
        string $startDate,
        string $endDate,
        string $institutionId = null
    ): array {
        $query = "
            SELECT 
                e.subspecialty_id,
                COUNT(*) as episode_count,
                COUNT(DISTINCT e.patient_id) as patient_count
            FROM `openeyes`.`core`.`episode` e
            WHERE e.start_date BETWEEN \$startDate AND \$endDate
        ";
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
        
        if ($institutionId) {
            $query .= " AND e.institution_id = \$institutionId";
            $params['institutionId'] = $institutionId;
        }
        
        $query .= " GROUP BY e.subspecialty_id ORDER BY episode_count DESC";
        
        $connection = \Yii::app()->couchbase;
        return $connection->query($query, $params)->rows();
    }
    
    /**
     * Get patient episode history
     */
    public function patientHistory(string $patientId): array
    {
        $builder = new N1qlQueryBuilder();
        $builder->from('core', 'episode')
            ->select('META().id, subspecialty_id, firm_id, start_date, end_date, episode_status_id')
            ->where('patient_id = $patientId', ['patientId' => $patientId])
            ->orderBy('start_date', 'DESC');
        
        return $builder->execute();
    }
}
```

**Acceptance Criteria**:
- [ ] Reports return correct data
- [ ] Performance is acceptable
- [ ] Results match MariaDB queries

### 6.5 API Query Updates

#### 6.5.1 Patient API Couchbase Support
**File**: `/protected/services/PatientService.php` (update)

```php
<?php
// Add method for Couchbase-backed search

class PatientService extends InternalService
{
    // Existing methods...
    
    /**
     * Search using Couchbase if enabled
     */
    public function searchCouchbase(array $criteria): array
    {
        if (!\Yii::app()->params['enable_couchbase_read']) {
            return $this->search($criteria);
        }
        
        $searcher = new \OE\Reports\CouchbasePatientSearch();
        return $searcher->search($criteria);
    }
}
```

### 6.6 Complex Query Examples

#### 6.6.1 Waiting List Query
**File**: `/protected/modules/OphTrOperationbooking/components/CouchbaseWaitingList.php`

```php
<?php
/**
 * Waiting list queries using Couchbase
 */

namespace OEModule\OphTrOperationbooking\components;

class CouchbaseWaitingList
{
    /**
     * Get waiting list for a firm
     */
    public function getForFirm(
        string $firmId,
        string $statusId = null,
        int $limit = 100
    ): array {
        $query = "
            SELECT 
                o.*,
                META(o).id as operation_id
            FROM `openeyes`.`booking`.`operation` o
            WHERE o.booking IS NULL
        ";
        $params = [];
        
        // Operations belong to events which belong to episodes
        // In the embedded model, we'd have denormalized firm_id
        // Or we need to join through episode
        
        if ($firmId) {
            $query .= " AND o.firm_id = \$firmId";
            $params['firmId'] = $firmId;
        }
        
        if ($statusId) {
            $query .= " AND o.status_id = \$statusId";
            $params['statusId'] = $statusId;
        }
        
        $query .= " ORDER BY o.decision_date ASC LIMIT \$limit";
        $params['limit'] = $limit;
        
        $connection = \Yii::app()->couchbase;
        return $connection->query($query, $params)->rows();
    }
    
    /**
     * Get waiting list statistics
     */
    public function getStatistics(string $institutionId = null): array
    {
        $query = "
            SELECT 
                status_id,
                status_name,
                COUNT(*) as count,
                AVG(DATE_DIFF_STR(NOW_STR(), decision_date, 'day')) as avg_wait_days
            FROM `openeyes`.`booking`.`operation`
            WHERE booking IS NULL
        ";
        $params = [];
        
        if ($institutionId) {
            $query .= " AND institution_id = \$institutionId";
            $params['institutionId'] = $institutionId;
        }
        
        $query .= " GROUP BY status_id, status_name";
        
        $connection = \Yii::app()->couchbase;
        return $connection->query($query, $params)->rows();
    }
}
```

**Acceptance Criteria**:
- [ ] Waiting list queries work
- [ ] Statistics are accurate
- [ ] Performance is acceptable

## Testing Criteria

### Query Correctness
- [ ] All migrated queries return same results as SQL
- [ ] Edge cases handled
- [ ] NULL handling correct

### Performance Tests
- [ ] Simple queries < 50ms
- [ ] Complex queries < 500ms
- [ ] Search queries < 100ms

### Integration Tests
- [ ] API endpoints work with Couchbase queries
- [ ] Reports generate correctly
- [ ] Search functionality works

## Rollback Plan

1. Feature flag `enable_couchbase_read` to false
2. All queries fall back to MariaDB
3. No data changes required

## Definition of Done

- [ ] All critical queries translated
- [ ] Query builder functional
- [ ] Reports working with Couchbase
- [ ] Performance within acceptable limits
- [ ] Documentation complete

---

*Phase 6 Completion Sign-off:*
- [ ] Technical Lead
- [ ] QA

*Estimated Duration: 3-4 weeks*
