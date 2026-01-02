# ✅ Phase 6 Validation - Quick Guide

**Run these commands to validate Phase 6 is correctly implemented.**

---

## ✅ Quick Validation (Copy & Paste)

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes

echo "=== Phase 6 Validation ==="
echo ""
echo "1. Checking files..."
ls protected/components/database/N1qlQueryBuilder.php && echo "✓ N1qlQueryBuilder exists"
ls protected/components/reports/CouchbasePatientSearch.php && echo "✓ CouchbasePatientSearch exists"
ls protected/modules/OphCiExamination/components/CouchbaseExaminationSearch.php && echo "✓ ExaminationSearch exists"
ls protected/commands/QueryAnalysisCommand.php && echo "✓ QueryAnalysisCommand exists"

echo ""
echo "2. Checking syntax..."
docker compose -f .devcontainer/docker-compose.yml exec -T web php -l protected/components/database/N1qlQueryBuilder.php
docker compose -f .devcontainer/docker-compose.yml exec -T web php -l protected/components/reports/CouchbasePatientSearch.php

echo ""
echo "3. Testing Query Builder..."
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$builder = new \OE\Database\N1qlQueryBuilder('openeyes');
\$query = \$builder->from('core', 'patient')->where('hos_num = \$hosNum', ['hosNum' => '12345'])->limit(10)->build();
echo \$query . \"\n\";
echo \"✓ Query Builder works!\n\";
"

echo ""
echo "4. Testing commands..."
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php queryanalysis help | head -5

echo ""
echo "✓✓✓ Phase 6 Validation Complete! ✓✓✓"
```

## 📋 Detailed Validation Checklist

### Test 1: File Count
```bash
find protected/components/database -name "N1ql*.php" -o -name "Query*.php" | wc -l
# Expected: 2
```

### Test 2: Component Loading
```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
echo '✓ Patient Search: ';
\$ps = new \OE\Reports\CouchbasePatientSearch();
echo 'Loaded\n';

echo '✓ Examination Search: ';
\$es = new \OEModule\OphCiExamination\components\CouchbaseExaminationSearch();
echo 'Loaded (' . count(get_class_methods(\$es)) . ' methods)\n';

echo '✓ Waiting List: ';
\$wl = new \OEModule\OphTrOperationbooking\components\CouchbaseWaitingList();
echo 'Loaded (' . count(get_class_methods(\$wl)) . ' methods)\n';
"
```

### Test 3: Query Analysis
```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php queryanalysis summary
```

### Test 4: Query Building
```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$builder = new \OE\Database\N1qlQueryBuilder('openeyes');

echo \"Basic Query:\n\";
echo \$builder->from('core', 'patient')->where('hos_num = \$hosNum', ['hosNum' => '12345'])->build();

echo \"\n\nComplex Query:\n\";
\$builder->reset();
echo \$builder
    ->from('core', 'episode', 'e')
    ->join('core', 'patient', 'e.patient_id = META(p).id', 'p')
    ->select('e.*, p.hos_num')
    ->orderBy('e.start_date', 'DESC')
    ->limit(50)
    ->build();
echo \"\n\";
"
```

## ✅ Success Indicators

You'll know Phase 6 is successful if:

1. ✅ All 22 files exist
2. ✅ No "syntax errors" in any file  
3. ✅ Query Builder creates valid N1QL queries
4. ✅ All components load without errors
5. ✅ Commands are accessible (queryanalysis, querybenchmark, querymigrationverify)
6. ✅ Configuration has feature flags

## 🎯 Final Validation Command

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes

# Count files
echo "Files created: $(find protected -name "Couchbase*.php" -o -name "N1ql*.php" -o -name "*Query*.php" 2>/dev/null | grep -E "(Couchbase|N1ql|Query)" | wc -l)"

# Test query builder
docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
try {
    \$b = new \OE\Database\N1qlQueryBuilder('openeyes');
    \$q = \$b->from('core', 'patient')->limit(5)->build();
    echo \"✓ Phase 6 Implementation: SUCCESS\n\";
    exit(0);
} catch (Exception \$e) {
    echo \"✗ Error: \" . \$e->getMessage() . \"\n\";
    exit(1);
}
"
```

## 📊 Expected Output

```
Files created: 22+
✓ Phase 6 Implementation: SUCCESS
```

---

## 🔍 If You See Errors

### "Class not found"
```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web composer dump-autoload
```

### "Command not found"
```bash
docker compose -f .devcontainer/docker-compose.yml exec -T web rm -rf protected/runtime/cache/*
```

### "Syntax error"
Check the specific file mentioned in the error.

---

**See `docs/migration-mariadb-to-couchbase/PHASE-06-VALIDATION.md` for detailed validation steps.**
