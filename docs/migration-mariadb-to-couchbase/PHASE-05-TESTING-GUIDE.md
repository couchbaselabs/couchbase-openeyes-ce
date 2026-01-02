# Phase 5: Module Model Migration - Testing Guide

**Created**: December 22, 2025  
**Status**: READY FOR TESTING  
**Prerequisites**: Phase 5 core models implemented

---

## Overview

This guide provides step-by-step instructions for testing the Phase 5 module migration implementation. Follow these steps in order to validate the implementation before enabling dual-write in production.

---

## Prerequisites Checklist

Before testing, ensure:

- [x] Phase 4 completed (Core models migrated)
- [x] Phase 5 code implemented (all document models created)
- [x] CouchbaseConnection component available
- [x] Docker installed and running
- [ ] Couchbase container running
- [ ] Collections created
- [ ] Indexes created

---

## Step 1: Start Couchbase Server

### Option A: Using Docker Compose (Recommended)

```bash
# Navigate to project root
cd /Users/asahu/Desktop/OpenEyes/openeyes

# Start Couchbase container
docker compose -f docker-compose.couchbase.yml up -d

# Wait for Couchbase to be healthy (may take 2-3 minutes)
docker compose -f docker-compose.couchbase.yml ps

# Check logs
docker compose -f docker-compose.couchbase.yml logs couchbase
```

### Option B: Check if Already Running

```bash
# Check running containers
docker ps | grep couchbase

# Should see: openeyes-couchbase container running on ports 8091-8096
```

### Verify Couchbase is Accessible

```bash
# Test web UI access
curl -s http://localhost:8091/pools/default

# Should return JSON response (not error)
```

---

## Step 2: Initialize Couchbase Cluster (First Time Only)

If this is the first time starting Couchbase, you need to initialize the cluster.

### Access Couchbase Web Console

1. Open browser: http://localhost:8091
2. Click "Setup New Cluster"
3. Configure:
   - **Cluster Name**: openeyes-cluster
   - **Admin Username**: Administrator
   - **Admin Password**: password (or set secure password)
4. Accept default settings and finish setup
5. Click "Configure" → "Buckets" → "ADD BUCKET"
6. Create bucket:
   - **Name**: openeyes
   - **Memory Quota**: 512 MB (minimum) or more
   - **Bucket Type**: Couchbase
   - **Replicas**: 0 (for dev environment)
7. Click "Add Bucket"

---

## Step 3: Create Scopes

Before creating collections, you need scopes:

```bash
# Access Couchbase container
docker exec -it openeyes-couchbase bash

# Inside container, create scopes using couchbase-cli
couchbase-cli bucket-edit \
  -c localhost:8091 \
  -u Administrator \
  -p password \
  --bucket openeyes \
  --enable-flush 0

# Create scopes via curl
curl -X POST http://localhost:8091/pools/default/buckets/openeyes/scopes \
  -u Administrator:password \
  -d name=core

curl -X POST http://localhost:8091/pools/default/buckets/openeyes/scopes \
  -u Administrator:password \
  -d name=clinical

curl -X POST http://localhost:8091/pools/default/buckets/openeyes/scopes \
  -u Administrator:password \
  -d name=booking

curl -X POST http://localhost:8091/pools/default/buckets/openeyes/scopes \
  -u Administrator:password \
  -d name=correspondence

curl -X POST http://localhost:8091/pools/default/buckets/openeyes/scopes \
  -u Administrator:password \
  -d name=admin

curl -X POST http://localhost:8091/pools/default/buckets/openeyes/scopes \
  -u Administrator:password \
  -d name=reference

# Exit container
exit
```

---

## Step 4: Create Collections

Run the collection creation script:

```bash
# Set environment variables (if not using defaults)
export CB_HOST=localhost
export CB_USER=Administrator
export CB_PASS=password
export CB_BUCKET=openeyes

# Execute collection creation script
./protected/scripts/couchbase/create-module-collections.sh
```

**Expected Output**:
```
Creating module collections in Couchbase...
Creating clinical.examination collection...
Creating booking collections...
Creating correspondence collections...
Module collections created successfully
```

### Verify Collections Created

Via Web Console:
1. Open http://localhost:8091
2. Click "Buckets" → "openeyes" → "Scopes & Collections"
3. Verify collections exist:
   - `clinical.examination`
   - `booking.operation`, `booking.session`, `booking.whiteboard`
   - `correspondence.letter`, `correspondence.message`, `correspondence.document`

Via Command Line:
```bash
# List all collections
curl -s http://localhost:8091/pools/default/buckets/openeyes/scopes \
  -u Administrator:password | python3 -m json.tool
```

---

## Step 5: Create N1QL Indexes

### Option A: Via Web Console (Recommended)

1. Open http://localhost:8091
2. Click "Query" tab
3. Copy contents of `protected/scripts/couchbase/indexes/module-indexes.n1ql`
4. Paste into Query Editor
5. Click "Execute"
6. Verify all 32 indexes created successfully

### Option B: Via cbq CLI

```bash
# Access container
docker exec -it openeyes-couchbase bash

# Run cbq (Couchbase Query shell)
cbq -u Administrator -p password -e http://localhost:8091

# Copy/paste index definitions from module-indexes.n1ql
# Or execute file:
\source /path/to/module-indexes.n1ql

# Exit
\exit
exit
```

### Verify Indexes Created

```sql
-- In Query Workbench, run:
SELECT idx.name, idx.state 
FROM system:indexes idx 
WHERE idx.bucket_id = 'openeyes'
ORDER BY idx.name;
```

**Expected**: 32 indexes in "online" state

---

## Step 6: Test Sync Command (Dry Run)

Before syncing real data, test the command structure:

```bash
# Navigate to project
cd /Users/asahu/Desktop/OpenEyes/openeyes

# View help
php protected/yiic.php couchbasemodulesync --help

# Verify command is recognized
php protected/yiic.php couchbasemodulesync
```

**Expected Output**: Should show available actions (sync, verify)

---

## Step 7: Sync Small Test Batch

Start with a very small batch to validate functionality:

```bash
# Sync first 5 examination events
php protected/yiic.php couchbasemodulesync sync \
  --module=OphCiExamination \
  --from=1 \
  --to=5 \
  --verbose

# Expected output:
# ======================================================================
# Syncing module: OphCiExamination
# ======================================================================
# 
# Found X examination events to sync
#   Progress: 5/X (...)
# 
# Module OphCiExamination: 5 synced, 0 errors
```

---

## Step 8: Verify Data Integrity

### Check Couchbase Count

```bash
# Verify sync count
php protected/yiic.php couchbasemodulesync verify --module=OphCiExamination

# Expected output:
# ======================================================================
# SYNC VERIFICATION
# ======================================================================
# 
# Verifying OphCiExamination...
#   MySQL: X, Couchbase: 5 ✓
```

### Query Synced Documents

Via Query Workbench:
```sql
-- Count examination documents
SELECT COUNT(*) as count 
FROM `openeyes`.`clinical`.`examination`;

-- View sample document
SELECT META().id, * 
FROM `openeyes`.`clinical`.`examination` 
LIMIT 1;

-- Check element embedding
SELECT META().id, 
       event_id, 
       patient_id, 
       OBJECT_NAMES(elements) as element_types
FROM `openeyes`.`clinical`.`examination`
LIMIT 5;
```

---

## Step 9: Validate Document Structure

### Check Visual Acuity Element

```sql
SELECT META().id,
       event_id,
       elements.VisualAcuity.left_readings,
       elements.VisualAcuity.right_readings,
       elements.VisualAcuity._sided
FROM `openeyes`.`clinical`.`examination`
WHERE elements.VisualAcuity IS NOT NULL
LIMIT 1;
```

**Verify**:
- Readings are embedded as arrays
- Method names are resolved (not just IDs)
- Unit names are resolved
- Source names are resolved
- `_sided` metadata includes `has_left` and `has_right` flags

### Check Diagnoses Element

```sql
SELECT META().id,
       event_id,
       elements.Diagnoses.diagnoses
FROM `openeyes`.`clinical`.`examination`
WHERE elements.Diagnoses IS NOT NULL
LIMIT 1;
```

**Verify**:
- Diagnoses are embedded as array
- Disorder terms are resolved (not just IDs)
- Eye names are resolved
- Principal flag is present

---

## Step 10: Test Query Methods

### Test via PHP Console (if available)

```php
// Test ExaminationDocument query
$docs = OEModule\OphCiExamination\models\ExaminationDocument::findByPatientId('1', 5);
echo "Found " . count($docs) . " examinations\n";

// Test with element filter
$vaExams = OEModule\OphCiExamination\models\ExaminationDocument::findByPatientWithElement('1', 'VisualAcuity', 5);
echo "Found " . count($vaExams) . " examinations with VA\n";

// Test getters
if (!empty($docs)) {
    $exam = $docs[0];
    echo "Elements: " . implode(', ', $exam->getElementNames()) . "\n";
    
    if ($exam->hasElement('VisualAcuity')) {
        $va = $exam->getVisualAcuity();
        echo "VA left readings: " . count($va['left_readings']) . "\n";
    }
}
```

---

## Step 11: Sync Larger Batch

Once small batch validates successfully:

```bash
# Sync 100 examination events
php protected/yiic.php couchbasemodulesync sync \
  --module=OphCiExamination \
  --from=1 \
  --to=100 \
  --batch=50 \
  --verbose

# Sync all examination events (careful with large datasets!)
php protected/yiic.php couchbasemodulesync sync \
  --module=OphCiExamination \
  --batch=500 \
  --verbose
```

---

## Step 12: Test All Modules

After OphCiExamination validates successfully:

```bash
# Sync operation booking
php protected/yiic.php couchbasemodulesync sync \
  --module=OphTrOperationbooking \
  --from=1 \
  --to=10 \
  --verbose

# Sync correspondence
php protected/yiic.php couchbasemodulesync sync \
  --module=OphCoCorrespondence \
  --from=1 \
  --to=10 \
  --verbose

# Verify all modules
php protected/yiic.php couchbasemodulesync verify
```

---

## Step 13: Performance Benchmarking

### Measure Sync Performance

```bash
# Time large batch sync
time php protected/yiic.php couchbasemodulesync sync \
  --module=OphCiExamination \
  --from=1 \
  --to=1000 \
  --batch=100

# Calculate throughput
# Target: >100 records/second for simple elements
```

### Measure Query Performance

Via Query Workbench:
```sql
-- Test patient examination query (should be <100ms)
SELECT META().id, event_date, OBJECT_NAMES(elements) as elements
FROM `openeyes`.`clinical`.`examination`
WHERE patient_id = '1'
ORDER BY event_date DESC
LIMIT 10;

-- Test element-specific query (should use index)
SELECT META().id, event_date, elements.VisualAcuity
FROM `openeyes`.`clinical`.`examination`
USE INDEX (idx_exam_va)
WHERE patient_id = '1'
  AND elements.VisualAcuity IS NOT NULL
ORDER BY event_date DESC;
```

---

## Troubleshooting

### Issue: "Couchbase connection failed"

**Solution**:
```bash
# Check if Couchbase is running
docker ps | grep couchbase

# Check Couchbase logs
docker logs openeyes-couchbase

# Restart Couchbase
docker compose -f docker-compose.couchbase.yml restart
```

### Issue: "Collection not found"

**Solution**:
```bash
# Re-run collection creation script
./protected/scripts/couchbase/create-module-collections.sh

# Verify collections exist
curl -s http://localhost:8091/pools/default/buckets/openeyes/scopes \
  -u Administrator:password | grep -o '"name":"[^"]*"'
```

### Issue: "Event type OphCiExamination not found"

**Solution**:
- Ensure you're running command from project root
- Check that event types exist in MySQL:
  ```bash
  docker compose -f .devcontainer/docker-compose.yml exec db \
    mysql -u openeyes -popeneyes openeyes \
    -e "SELECT id, name, class_name FROM event_type WHERE class_name LIKE '%Examination%';"
  ```

### Issue: "Sync errors with element embedding"

**Solution**:
1. Check specific error in verbose output
2. Verify element has `toCouchbaseEmbedded()` method
3. Check for missing trait: `use CouchbaseElementBridge;`
4. Verify relations exist in element model

### Issue: "Index not found" errors in queries

**Solution**:
```sql
-- Check index status
SELECT idx.name, idx.state, idx.bucket_id
FROM system:indexes idx
WHERE idx.bucket_id = 'openeyes'
  AND idx.state != 'online';

-- If indexes are building, wait for completion
-- If indexes missing, re-run index creation script
```

---

## Success Criteria

Phase 5 testing is successful when:

- [x] Couchbase server running and accessible
- [ ] All scopes created (6 scopes)
- [ ] All collections created (9 collections)
- [ ] All indexes created (32 indexes) and online
- [ ] Sync command executes without errors
- [ ] Document count matches between MySQL and Couchbase
- [ ] Documents have correct structure (metadata, embedded elements)
- [ ] Lookup values resolved correctly (not just IDs)
- [ ] Query methods return correct results
- [ ] Query performance <100ms for single patient
- [ ] Sync throughput >100 records/second

---

## Next Steps After Testing

Once testing validates successfully:

1. **Document Results**
   - Record sync statistics
   - Document any issues encountered
   - Note performance metrics

2. **Enable Dual-Write (Optional)**
   - Edit `protected/config/core/common.php`
   - Uncomment desired modules in `couchbase_migrated_modules`
   - Test with real traffic

3. **Monitor & Validate**
   - Watch error logs
   - Monitor sync performance
   - Verify data consistency

4. **Proceed to Phase 6**
   - Once Phase 5 stable
   - Begin query migration (SQL to N1QL)

---

## Quick Reference Commands

```bash
# Start Couchbase
docker compose -f docker-compose.couchbase.yml up -d

# Create collections
./protected/scripts/couchbase/create-module-collections.sh

# Sync small batch
php protected/yiic.php couchbasemodulesync sync --module=OphCiExamination --from=1 --to=10 --verbose

# Verify sync
php protected/yiic.php couchbasemodulesync verify

# View Couchbase logs
docker logs openeyes-couchbase --tail 100 -f

# Stop Couchbase
docker compose -f docker-compose.couchbase.yml down
```

---

**Last Updated**: December 22, 2025  
**Version**: 1.0  
**Status**: READY FOR USE
