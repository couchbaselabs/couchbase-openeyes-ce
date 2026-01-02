# Phase 5: Quick Start Guide

**For**: Testing Phase 5 implementation  
**Time Required**: 30 minutes  
**Prerequisites**: Phase 5 code complete (✅)

---

## 🚀 Quick Start (5 Commands)

```bash
# 1. Check if Couchbase is running
docker ps | grep couchbase

# 2. If not running, start it
docker compose -f docker-compose.couchbase.yml up -d

# 3. Initialize Couchbase (first time only - see below)
# Open: http://localhost:8091

# 4. Create collections
./protected/scripts/couchbase/create-module-collections.sh

# 5. Test sync
php protected/yiic.php couchbasemodulesync sync --module=OphCiExamination --from=1 --to=5 --verbose
```

---

## ⚙️ First-Time Couchbase Setup (10 minutes)

### Step 1: Access Web Console
- Open: **http://localhost:8091**
- Click "Setup New Cluster"

### Step 2: Configure Cluster
```
Cluster Name: openeyes-cluster
Admin Username: Administrator
Admin Password: password
```

### Step 3: Create Bucket
```
Name: openeyes
Memory: 512 MB (minimum)
Type: Couchbase
Replicas: 0 (dev only)
```

### Step 4: Create Scopes (via terminal)

```bash
# Create all 6 scopes
for scope in core clinical booking correspondence admin reference; do
  curl -X POST http://localhost:8091/pools/default/buckets/openeyes/scopes \
    -u Administrator:password \
    -d name=$scope
done
```

### Step 5: Create Collections

```bash
./protected/scripts/couchbase/create-module-collections.sh
```

### Step 6: Create Indexes

1. Open http://localhost:8091 → "Query" tab
2. Copy from: `protected/scripts/couchbase/indexes/module-indexes.n1ql`
3. Paste and Execute
4. Wait for indexes to go "online" (~1-2 minutes)

---

## ✅ Verify Setup

```bash
# Check collections exist
curl -s http://localhost:8091/pools/default/buckets/openeyes/scopes \
  -u Administrator:password | grep -o '"name":"[^"]*"'

# Expected output:
# "name":"_default"
# "name":"core"
# "name":"clinical"
# "name":"booking"
# "name":"correspondence"
# "name":"admin"
# "name":"reference"
```

---

## 🧪 Test Sync (Small Batch)

```bash
# Sync first 5 examination events
php protected/yiic.php couchbasemodulesync sync \
  --module=OphCiExamination \
  --from=1 \
  --to=5 \
  --verbose

# Verify sync
php protected/yiic.php couchbasemodulesync verify --module=OphCiExamination
```

**Expected Output**:
```
======================================================================
Syncing module: OphCiExamination
======================================================================

Found X examination events to sync
  Progress: 5/X (...)

Module OphCiExamination: 5 synced, 0 errors
```

---

## 🔍 Validate Data

Via Query Workbench (http://localhost:8091 → Query):

```sql
-- Count documents
SELECT COUNT(*) as count 
FROM `openeyes`.`clinical`.`examination`;

-- View sample document
SELECT META().id, event_id, patient_id, event_date, OBJECT_NAMES(elements)
FROM `openeyes`.`clinical`.`examination`
LIMIT 1;

-- Check Visual Acuity embedding
SELECT elements.VisualAcuity
FROM `openeyes`.`clinical`.`examination`
WHERE elements.VisualAcuity IS NOT NULL
LIMIT 1;
```

---

## 📊 Sync All Modules

Once OphCiExamination validates successfully:

```bash
# Sync operation booking (small batch)
php protected/yiic.php couchbasemodulesync sync \
  --module=OphTrOperationbooking \
  --from=1 \
  --to=10 \
  --verbose

# Sync correspondence (small batch)
php protected/yiic.php couchbasemodulesync sync \
  --module=OphCoCorrespondence \
  --from=1 \
  --to=10 \
  --verbose

# Verify all
php protected/yiic.php couchbasemodulesync verify
```

---

## 🚨 Troubleshooting

### Couchbase not accessible

```bash
# Check container status
docker ps | grep couchbase

# View logs
docker logs openeyes-couchbase --tail 50

# Restart
docker compose -f docker-compose.couchbase.yml restart
```

### Collection not found

```bash
# Re-run script
./protected/scripts/couchbase/create-module-collections.sh

# Or create manually via curl (see First-Time Setup)
```

### Sync errors

```bash
# Run with verbose flag
php protected/yiic.php couchbasemodulesync sync \
  --module=OphCiExamination \
  --from=1 \
  --to=1 \
  --verbose

# Check specific event ID
php protected/yiic.php couchbasemodulesync sync \
  --module=OphCiExamination \
  --eventId=123 \
  --verbose
```

---

## 📚 Full Documentation

For comprehensive instructions:
- **PHASE-05-TESTING-GUIDE.md** - Complete testing procedures
- **PHASE-05-SESSION-3-SUMMARY.md** - Session summary
- **PHASE-05-IMPLEMENTATION-PROGRESS.md** - Overall progress

---

## ✨ Success Criteria

Phase 5 testing successful when:
- ✅ Couchbase running
- ✅ Collections created (9 collections)
- ✅ Indexes created (32 indexes)
- ✅ Sync completes without errors
- ✅ Document count matches MySQL
- ✅ Elements embedded correctly
- ✅ Lookups resolved (names not IDs)

---

## 🎯 What You Can Do After Success

1. **Sync larger batches**:
   ```bash
   php protected/yiic.php couchbasemodulesync sync \
     --module=OphCiExamination \
     --batch=500
   ```

2. **Enable dual-write** (optional):
   - Edit `protected/config/core/common.php`
   - Uncomment modules in `couchbase_migrated_modules`

3. **Query Couchbase**:
   - Use N1QL via Query Workbench
   - Test PHP document models
   - Benchmark performance

4. **Proceed to Phase 6**:
   - Query migration (SQL to N1QL)
   - Repository pattern implementation

---

**Quick Start Complete!** For detailed info, see PHASE-05-TESTING-GUIDE.md
