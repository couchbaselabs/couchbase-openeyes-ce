# Phase 6: Query Migration - Quick Start Guide

**Status**: IMPLEMENTED ✅  
**Date**: December 22, 2025

---

## Quick Reference

### 1. Analyze Existing SQL Queries

```bash
# Inside Docker container
docker compose exec web php protected/yiic.php queryanalysis run

# Or run script directly
docker compose exec web php protected/scripts/couchbase/analyze-queries.php
```

**Output**: Reports generated in `docs/migration-mariadb-to-couchbase/`

---

### 2. Build N1QL Queries with Query Builder

```php
use OE\Database\N1qlQueryBuilder;

// Basic query
$builder = new N1qlQueryBuilder('openeyes');
$patients = $builder
    ->from('core', 'patient')
    ->where('hos_num = $hosNum', ['hosNum' => '12345'])
    ->execute();

// Complex query with JOIN
$events = $builder
    ->from('core', 'event', 'e')
    ->join('core', 'episode', 'e.episode_id = META(ep).id', 'ep')
    ->select('e.*, ep.patient_id')
    ->where('ep.patient_id = $patientId', ['patientId' => 123])
    ->orderBy('e.event_date', 'DESC')
    ->limit(50)
    ->execute();

// Array query
$exams = $builder
    ->from('clinical', 'examination')
    ->whereAny('elements.VisualAcuity.left_readings', 'r', 'r.value < $threshold', ['threshold' => 0.5])
    ->execute();
```

---

### 3. Search Patients with Couchbase

```php
use OE\Reports\CouchbasePatientSearch;

$search = new CouchbasePatientSearch();

// By hospital number
$patient = $search->findByHosNum('12345');

// By name
$patients = $search->searchByName('John Smith', 50);

// Complex search
$results = $search->search([
    'last_name' => 'Smith',
    'gender' => 'M',
    'exclude_deceased' => true,
    'limit' => 20
]);
```

---

### 4. Generate Reports

```php
use OE\Reports\CouchbaseEpisodeReport;
use OE\Reports\CouchbaseEventReport;

// Episode statistics
$episodeReport = new CouchbaseEpisodeReport();
$stats = $episodeReport->countBySubspecialty('2024-01-01', '2024-12-31');

// Patient history
$history = $episodeReport->patientHistory(123);

// Event reports
$eventReport = new CouchbaseEventReport();
$events = $eventReport->findByPatientId(123, 100);
$typeCounts = $eventReport->countByEventType('2024-01-01', '2024-12-31');
```

---

### 5. Convert SQL to N1QL

```php
use OE\Database\QueryMigrationHelper;

$helper = new QueryMigrationHelper();

// Get keyspace for table
$keyspace = $helper->getKeyspace('patient');
// Returns: `openeyes`.`core`.`patient`

// Convert SQL
$n1ql = $helper->convertSimpleSelect('SELECT * FROM patient WHERE id = 123');

// Convert Yii criteria
$criteria = new CDbCriteria();
$criteria->condition = 'hos_num = :hosNum';
$criteria->params = [':hosNum' => '12345'];
$builder = $helper->criteriaToBuilder($criteria, 'patient');
$results = $builder->execute();
```

---

## Enable/Disable Couchbase Queries

### Enable

```bash
export ENABLE_COUCHBASE_QUERIES=true
```

### Disable (Fallback to MariaDB)

```bash
export ENABLE_COUCHBASE_QUERIES=false
# or unset
unset ENABLE_COUCHBASE_QUERIES
```

---

## Common Query Patterns

### Simple Search

```php
$builder = new N1qlQueryBuilder();
$results = $builder
    ->from('core', 'patient')
    ->whereILike('contact.last_name', 'smi%', 'name')
    ->orderBy('contact.last_name')
    ->limit(20)
    ->execute();
```

### Date Range Query

```php
$builder = new N1qlQueryBuilder();
$results = $builder
    ->from('core', 'episode')
    ->whereBetween('start_date', '2024-01-01', '2024-12-31')
    ->execute();
```

### IN Clause

```php
$builder = new N1qlQueryBuilder();
$results = $builder
    ->from('core', 'patient')
    ->whereIn('gender', ['M', 'F'])
    ->execute();
```

### Aggregation

```php
$builder = new N1qlQueryBuilder();
$results = $builder
    ->from('core', 'episode')
    ->select('firm_id, COUNT(*) as count')
    ->groupBy('firm_id')
    ->having('COUNT(*) > $min', ['min' => 10])
    ->execute();
```

### Count

```php
$builder = new N1qlQueryBuilder();
$count = $builder
    ->from('core', 'patient')
    ->where('active = $active', ['active' => true])
    ->count();
```

---

## File Locations

| File | Purpose |
|------|---------|
| `protected/components/database/N1qlQueryBuilder.php` | Query builder |
| `protected/components/database/QueryMigrationHelper.php` | Migration helpers |
| `protected/components/reports/CouchbasePatientSearch.php` | Patient search |
| `protected/components/reports/CouchbaseEpisodeReport.php` | Episode reports |
| `protected/components/reports/CouchbaseEventReport.php` | Event reports |
| `protected/commands/QueryAnalysisCommand.php` | Analysis command |
| `protected/scripts/couchbase/analyze-queries.php` | Analysis script |
| `protected/config/couchbase-collection-map.php` | Table mapping |
| `docs/migration-mariadb-to-couchbase/sql-to-n1ql-guide.md` | Translation guide |

---

## Troubleshooting

### Query Not Found

Ensure Couchbase is running:
```bash
docker compose -f docker-compose.couchbase.yml ps
```

### Syntax Errors

Check PHP syntax:
```bash
docker compose exec web php -l protected/components/database/N1qlQueryBuilder.php
```

### Query Returns Empty

1. Check if data exists in Couchbase
2. Verify collection mapping
3. Check document key format
4. Review query with `->build()` method:

```php
$builder = new N1qlQueryBuilder();
$builder->from('core', 'patient')->where('hos_num = $hosNum', ['hosNum' => '12345']);
echo $builder->build(); // Print query for debugging
```

---

## Next Steps

1. **Run Analysis**: `docker compose exec web php protected/yiic.php queryanalysis run`
2. **Review Translation Guide**: `docs/migration-mariadb-to-couchbase/sql-to-n1ql-guide.md`
3. **Test Queries**: Use the examples above
4. **Create Unit Tests**: See `PHASE-06-AGENT-SPEC.md` for test templates
5. **Gradual Rollout**: Enable for one collection at a time

---

## Support

For detailed information, see:
- **Full Spec**: `PHASE-06-AGENT-SPEC.md`
- **Implementation Summary**: `PHASE-06-IMPLEMENTATION-SUMMARY.md`
- **SQL to N1QL Guide**: `sql-to-n1ql-guide.md`
- **Master Plan**: `00-MASTER-MIGRATION-PLAN.md`
