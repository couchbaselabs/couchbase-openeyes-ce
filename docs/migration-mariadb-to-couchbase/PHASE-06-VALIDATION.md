# Phase 6: Query Migration - Validation Guide

This guide walks you through validating that Phase 6 is correctly implemented and functional.

---

## Quick Validation (5 minutes)

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes

# 1. Run validation script
bash docs/migration-mariadb-to-couchbase/validate-phase6.sh
```

---

## Manual Validation Steps

### Step 1: Verify All Files Exist ✅

```bash
# Core components
ls -l protected/components/database/N1qlQueryBuilder.php
ls -l protected/components/database/QueryMigrationHelper.php
ls -l protected/components/reports/CouchbasePatientSearch.php
ls -l protected/components/reports/CouchbaseEpisodeReport.php
ls -l protected/components/reports/CouchbaseEventReport.php

# Module components
ls -l protected/modules/OphCiExamination/components/CouchbaseExaminationSearch.php
ls -l protected/modules/OphTrOperationbooking/components/CouchbaseWaitingList.php
ls -l protected/modules/OphTrOperationbooking/components/CouchbaseTheatreSchedule.php
ls -l protected/modules/OphCoCorrespondence/components/CouchbaseLetterSearch.php

# Commands
ls -l protected/commands/QueryAnalysisCommand.php
ls -l protected/commands/QueryBenchmarkCommand.php
ls -l protected/commands/QueryMigrationVerifyCommand.php

# Tests
ls -l protected/tests/unit/components/database/N1qlQueryBuilderTest.php
ls -l protected/tests/integration/CouchbaseQueryIntegrationTest.php
```

**Expected**: All files should exist with no "No such file" errors.

---

### Step 2: Check PHP Syntax ✅

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes

# Check all core files
docker compose -f .devcontainer/docker-compose.yml exec -T web php -l protected/components/database/N1qlQueryBuilder.php
docker compose -f .devcontainer/docker-compose.yml exec -T web php -l protected/components/database/QueryMigrationHelper.php
docker compose -f .devcontainer/docker-compose.yml exec -T web php -l protected/components/reports/CouchbasePatientSearch.php

# Check module files
docker compose -f .devcontainer/docker-compose.yml exec -T web php -l protected/modules/OphCiExamination/components/CouchbaseExaminationSearch.php
docker compose -f .devcontainer/docker-compose.yml exec -T web php -l protected/modules/OphTrOperationbooking/components/CouchbaseWaitingList.php

# Check commands
docker compose -f .devcontainer/docker-compose.yml exec -T web php -l protected/commands/QueryAnalysisCommand.php
docker compose -f .devcontainer/docker-compose.yml exec -T web php -l protected/commands/QueryBenchmarkCommand.php
```

**Expected**: All files should show "No syntax errors detected".

---

### Step 3: Test N1QL Query Builder ✅

```bash
# Test basic query building
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$builder = new \OE\Database\N1qlQueryBuilder('openeyes');
\$query = \$builder
    ->from('core', 'patient')
    ->where('hos_num = \$hosNum', ['hosNum' => '12345'])
    ->limit(10)
    ->build();
echo \"Query Built Successfully:\\n\";
echo \$query . \"\\n\\n\";
echo \"Parameters: \" . json_encode(\$builder->getParams()) . \"\\n\";
"
```

**Expected Output**:
```
Query Built Successfully:
SELECT META().id AS _id, *
FROM `openeyes`.`core`.`patient`
WHERE hos_num = $hosNum
LIMIT 10

Parameters: {"hosNum":"12345"}
```

---

### Step 4: Run Query Analysis ✅

```bash
# Analyze existing SQL queries
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php queryanalysis summary
```

**Expected Output**:
```
Query Analysis Summary
========================================
Complexity      Count     
-------------------------
Simple          XXX       
Moderate        XXX       
Complex         XXX       
-------------------------
Total           XXX
```

---

### Step 5: Test Query Migration Helper ✅

```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$helper = new \OE\Database\QueryMigrationHelper();

// Test keyspace generation
\$keyspace = \$helper->getKeyspace('patient');
echo \"Keyspace for 'patient': \$keyspace\\n\";

// Test scope detection
\$scope = \$helper->getScopeForTable('examination');
echo \"Scope for 'examination': \$scope\\n\";

// Test SQL conversion
\$sql = 'SELECT * FROM patient WHERE id = 123';
\$n1ql = \$helper->convertSimpleSelect(\$sql);
echo \"\\nConverted SQL:\\n\$n1ql\\n\";
"
```

**Expected Output**:
```
Keyspace for 'patient': `openeyes`.`core`.`patient`
Scope for 'examination': clinical

Converted SQL:
SELECT META().id AS _id, * FROM `openeyes`.`core`.`patient` WHERE id = 123
```

---

### Step 6: Test Patient Search (If Couchbase Available) ⚠️

```bash
# This will only work if Couchbase is running and data is synced
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$search = new \OE\Reports\CouchbasePatientSearch();

// Test search method exists
if (method_exists(\$search, 'search')) {
    echo \"✓ CouchbasePatientSearch class loaded successfully\\n\";
    echo \"✓ search() method exists\\n\";
}

if (method_exists(\$search, 'findByHosNum')) {
    echo \"✓ findByHosNum() method exists\\n\";
}

if (method_exists(\$search, 'searchByName')) {
    echo \"✓ searchByName() method exists\\n\";
}

echo \"\\nCouchbasePatientSearch is ready (requires Couchbase connection for actual queries)\\n\";
"
```

**Expected Output**:
```
✓ CouchbasePatientSearch class loaded successfully
✓ search() method exists
✓ findByHosNum() method exists
✓ searchByName() method exists

CouchbasePatientSearch is ready (requires Couchbase connection for actual queries)
```

---

### Step 7: Verify Configuration Changes ✅

```bash
# Check if feature flags were added
grep -A 3 "Phase 6: Query Migration Feature Flags" protected/config/core/common.php
```

**Expected Output**:
```php
// Phase 6: Query Migration Feature Flags
// These flags control the gradual rollout of Couchbase N1QL queries
*/
$config["params"]["enable_couchbase_queries"] = strtolower(getenv('ENABLE_COUCHBASE_QUERIES') ?: 'false') === 'true';
$config["params"]["couchbase_query_collections"] = []; // Collections using Couchbase queries
```

---

### Step 8: Test Commands Exist ✅

```bash
# Test QueryAnalysisCommand
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php queryanalysis help

# Test QueryBenchmarkCommand
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php querybenchmark help

# Test QueryMigrationVerifyCommand
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php querymigrationverify help
```

**Expected**: Each command should display its help text without errors.

---

### Step 9: Run Unit Tests ✅

```bash
# Test N1QL Query Builder
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/vendor/bin/phpunit \
  protected/tests/unit/components/database/N1qlQueryBuilderTest.php \
  --testdox

# Test Patient Search
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/vendor/bin/phpunit \
  protected/tests/unit/components/reports/CouchbasePatientSearchTest.php \
  --testdox
```

**Expected**: All tests should pass with green checkmarks.

---

### Step 10: Test Complex Query Building ✅

```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$builder = new \OE\Database\N1qlQueryBuilder('openeyes');

// Test complex query with JOIN
\$query = \$builder
    ->from('core', 'episode', 'e')
    ->join('core', 'patient', 'e.patient_id = META(p).id', 'p')
    ->select('e.*, p.hos_num')
    ->where('e.start_date >= \$startDate', ['startDate' => '2024-01-01'])
    ->orderBy('e.start_date', 'DESC')
    ->limit(50)
    ->build();

echo \"Complex Query Built Successfully:\\n\";
echo \$query . \"\\n\";
"
```

**Expected**: Should output a valid N1QL query with JOIN.

---

### Step 11: Verify Module Components ✅

```bash
# Test Examination Search
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$search = new \OEModule\OphCiExamination\components\CouchbaseExaminationSearch();
echo \"✓ CouchbaseExaminationSearch loaded\\n\";
echo \"  Methods: \" . count(get_class_methods(\$search)) . \"\\n\";
"

# Test Waiting List
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$wl = new \OEModule\OphTrOperationbooking\components\CouchbaseWaitingList();
echo \"✓ CouchbaseWaitingList loaded\\n\";
echo \"  Methods: \" . count(get_class_methods(\$wl)) . \"\\n\";
"

# Test Letter Search
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$ls = new \OEModule\OphCoCorrespondence\components\CouchbaseLetterSearch();
echo \"✓ CouchbaseLetterSearch loaded\\n\";
echo \"  Methods: \" . count(get_class_methods(\$ls)) . \"\\n\";
"
```

**Expected**: All components should load successfully with method counts.

---

## Full Integration Test (With Couchbase)

If you have Couchbase running with synced data:

```bash
# Run integration tests
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/vendor/bin/phpunit \
  protected/tests/integration/CouchbaseQueryIntegrationTest.php \
  --testdox

# Run query verification
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php querymigrationverify all

# Run performance benchmark
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php querybenchmark run --iterations=10
```

---

## Validation Checklist

Use this checklist to track validation progress:

- [ ] All 23 files exist (22 created + 1 modified)
- [ ] No PHP syntax errors in any file
- [ ] N1qlQueryBuilder can build basic queries
- [ ] N1qlQueryBuilder can build complex queries (JOINs, WHERE, ORDER BY)
- [ ] Query Analysis command works
- [ ] Query Migration Helper converts SQL to N1QL
- [ ] Patient Search component loads
- [ ] Episode/Event Report components load
- [ ] Examination Search component loads (18 methods)
- [ ] Waiting List component loads (8 methods)
- [ ] Theatre Schedule component loads (5 methods)
- [ ] Letter Search component loads (10 methods)
- [ ] Configuration changes present (feature flags)
- [ ] QueryBenchmarkCommand exists and shows help
- [ ] QueryMigrationVerifyCommand exists and shows help
- [ ] Unit tests exist and can be located
- [ ] Integration tests exist
- [ ] Documentation files exist (4 files)

---

## Expected Results Summary

| Check | Expected Result | Status |
|-------|----------------|--------|
| Files Created | 22 files | ✅ |
| Files Modified | 1 file | ✅ |
| PHP Syntax | No errors | ✅ |
| Query Builder | Builds queries | ✅ |
| Commands | 4 commands work | ✅ |
| Unit Tests | Can run | ✅ |
| Documentation | 4 files exist | ✅ |
| Module Components | 4 components load | ✅ |

---

## Troubleshooting

### Issue: "Class not found" errors

**Solution**: Make sure autoloading is working:
```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web composer dump-autoload
```

### Issue: "Couchbase unavailable" in tests

**Solution**: This is expected if Couchbase isn't running. Tests will skip Couchbase-dependent checks.

### Issue: Command not found

**Solution**: Clear Yii cache:
```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web rm -rf protected/runtime/cache/*
```

### Issue: phpunit not found

**Solution**: Install dev dependencies:
```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web composer install
```

---

## Quick Validation Result

If all checks pass, you should see:

✅ **Phase 6 is successfully implemented!**

- All 22 files created
- All files have valid PHP syntax
- Query Builder works
- All commands accessible
- All components load
- Tests can run
- Documentation complete

**Ready for production testing!** 🎉

---

## Next Steps After Validation

1. **Sync data to Couchbase** (if not already done):
   ```bash
   docker compose exec web php protected/yiic.php couchbasemodulesync sync --module=OphCiExamination
   ```

2. **Run full integration tests** with real data

3. **Benchmark performance** against MariaDB

4. **Enable Couchbase queries** for one collection at a time

5. **Proceed to Phase 7**: Services Layer Migration
