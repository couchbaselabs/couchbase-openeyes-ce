# Phase 17: Data Migration Execution Guide

**Status:** Ready to execute  
**Date:** 2025-12-25

---

## Prerequisites ✅

- [x] Models migrated (1016/1071 - 94% complete)
- [x] Dual-write enabled and validated
- [x] Couchbase read enabled for 46 collections + 30 modules
- [x] Configuration validated (no duplicates)
- [x] Docker containers running
  - `devcontainer-web-1` - Up 37 hours
  - `openeyes-couchbase` - Up 2 days (healthy)

---

## Migration Overview

The migration runs in **5 stages** with increasing complexity:

### Stage 1: Reference Data (Foundation)
**Collections:** 11 tables  
**Time estimate:** ~2-5 minutes  
**Data volume:** Low (100-1000 records each)

- event_type, element_type
- specialty, subspecialty
- site, institution, firm
- eye, gender, ethnic_group, event_group

### Stage 2: Clinical Reference (Medical Codes)
**Collections:** 7 tables  
**Time estimate:** ~10-30 minutes  
**Data volume:** High (10,000-100,000 records)

- disorder (SNOMED codes)
- procedure (OPCS/SNOMED codes)
- medication (dm+d codes)
- allergy, drug, benefit, complication

### Stage 3: Core Clinical (Patient Data)
**Collections:** 5 tables  
**Time estimate:** ~20-60 minutes  
**Data volume:** Very High (depends on patients)

- patient (batch size: 200)
- episode (batch size: 500)
- event (batch size: 500)
- user (batch size: 500)
- contact (batch size: 500)

### Stage 4: Module Elements (Clinical Details)
**Collections:** 30 modules with 300+ element tables  
**Time estimate:** ~30-120 minutes  
**Data volume:** Very High

Delegates to ModuleMigrationCommand for:
- OphCiExamination elements
- OphTrOperationnote elements
- All other module-specific data

### Stage 5: Administrative (Audit & Settings)
**Collections:** 8 tables  
**Time estimate:** ~10-30 minutes  
**Data volume:** Medium-High

- audit (batch size: 200)
- audit_type, audit_action
- setting_* (metadata, installation, institution, site, user)

---

## Execution Options

### Option 1: Run All Stages (Recommended for First Migration)

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes
./run-full-migration.sh all
```

**What it does:**
1. Tests dual-write functionality
2. Runs all 5 migration stages
3. Validates migrated data
4. Shows final status

**Total time estimate:** 1-4 hours (depends on data volume)

### Option 2: Run Stages Individually

```bash
# Stage 1: Reference Data
./run-full-migration.sh migrate
# Then select stage 1 when prompted

# Stage 2: Clinical Reference
# (Continue with stage 2)

# ... etc
```

**Use when:**
- Testing each stage separately
- Recovering from a failed stage
- Running specific stages only

### Option 3: Validate Only (After Migration)

```bash
./run-full-migration.sh validate
```

**What it validates:**
- Count validation (MariaDB vs Couchbase record counts)
- Sample validation (compare 100 random records)
- Integrity validation (check foreign keys, embedded data)

### Option 4: Check Status

```bash
./run-full-migration.sh status
```

**Shows:**
- Migration progress per stage
- Record counts per collection
- Last migration timestamp
- Success/failure status

---

## Detailed Command Reference

### Using Docker Directly

```bash
# Test dual-write
docker exec -it devcontainer-web-1 php protected/yiic testdualwrite

# Run full migration
docker exec -it devcontainer-web-1 php protected/yiic fulldatamigration run

# Run specific stage
docker exec -it devcontainer-web-1 php protected/yiic fulldatamigration stage --stage=1
docker exec -it devcontainer-web-1 php protected/yiic fulldatamigration stage --stage=2
docker exec -it devcontainer-web-1 php protected/yiic fulldatamigration stage --stage=3
docker exec -it devcontainer-web-1 php protected/yiic fulldatamigration stage --stage=4
docker exec -it devcontainer-web-1 php protected/yiic fulldatamigration stage --stage=5

# Validate data
docker exec -it devcontainer-web-1 php protected/yiic datavalidation counts
docker exec -it devcontainer-web-1 php protected/yiic datavalidation samples --sample=100
docker exec -it devcontainer-web-1 php protected/yiic datavalidation integrity

# Check status
docker exec -it devcontainer-web-1 php protected/yiic fulldatamigration status
```

---

## Migration Process Flow

```
┌─────────────────────────────────────────────────┐
│  Pre-Migration Checks                           │
│  ✓ Docker containers running                    │
│  ✓ Couchbase cluster healthy                    │
│  ✓ Database connectivity                        │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│  Stage 1: Reference Data (5 min)                │
│  → event_type, element_type, specialty, etc.    │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│  Stage 2: Clinical Reference (30 min)           │
│  → disorder, procedure, medication, etc.        │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│  Stage 3: Core Clinical (60 min)                │
│  → patient, episode, event, user, contact       │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│  Stage 4: Module Elements (120 min)             │
│  → All OphCiExamination, OphTr* elements        │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│  Stage 5: Administrative (30 min)               │
│  → audit, settings, auth                        │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│  Post-Migration Validation                      │
│  ✓ Count validation                             │
│  ✓ Sample validation                            │
│  ✓ Integrity validation                         │
└─────────────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────────────┐
│  Migration Complete!                            │
│  → All data synced MariaDB → Couchbase          │
│  → Dual-write continues for new data            │
│  → Application reads from Couchbase             │
└─────────────────────────────────────────────────┘
```

---

## What Happens During Migration

### For Each Collection:

1. **Count Records**
   - Query MariaDB: `SELECT COUNT(*) FROM table`
   - Store count for validation

2. **Batch Processing**
   - Fetch records in batches (200-1000 per batch)
   - Prevents memory exhaustion
   - Shows progress bar

3. **Transform Data**
   - Load ActiveRecord model
   - Call `toCouchbaseDocument()` method
   - Handle type conversions (dates, JSON, blobs)
   - Embed related data if configured

4. **Write to Couchbase**
   - Generate document key: `collection::id`
   - Upsert document (insert or update)
   - Add metadata: `_type`, `_mysql_id`, `_created`, `_version`

5. **Validate**
   - Compare record count
   - Sample random records for content comparison
   - Check embedded relations

6. **Log Progress**
   - Write to migration log file
   - Show progress in console
   - Report errors/warnings

---

## Monitoring During Migration

### Watch Logs

```bash
# Follow migration progress
docker logs -f devcontainer-web-1

# Watch for errors
docker logs -f devcontainer-web-1 | grep -i error

# Watch Couchbase writes
docker logs -f devcontainer-web-1 | grep -i couchbase
```

### Check Couchbase Status

```bash
# Query record counts
docker exec -it openeyes-couchbase cbq -u Administrator -p password123 -s \
  "SELECT _type, COUNT(*) as count FROM openeyes.clinical GROUP BY _type ORDER BY _type"

# Check specific collection
docker exec -it openeyes-couchbase cbq -u Administrator -p password123 -s \
  "SELECT COUNT(*) FROM openeyes.clinical WHERE _type='patient'"

# Check sample patient
docker exec -it openeyes-couchbase cbq -u Administrator -p password123 -s \
  "SELECT * FROM openeyes.clinical WHERE _type='patient' LIMIT 1"
```

### Monitor Performance

```bash
# Check Couchbase bucket stats
open http://localhost:8091

# Navigate to:
# - Buckets → openeyes → Statistics
# - Watch: ops/sec, disk usage, memory usage
```

---

## Troubleshooting

### Migration Fails on Stage X

**Solution:** Run that stage individually with more verbose output

```bash
docker exec -it devcontainer-web-1 php protected/yiic fulldatamigration stage --stage=X --verbose=1
```

### Couchbase Connection Timeout

**Check:**
1. Is Couchbase container healthy?
   ```bash
   docker ps | grep couchbase
   ```

2. Can web container reach Couchbase?
   ```bash
   docker exec -it devcontainer-web-1 ping -c 3 openeyes-couchbase
   ```

3. Are Couchbase ports accessible?
   ```bash
   curl http://localhost:8091/
   ```

### Out of Memory Error

**Solution:** Reduce batch size

Edit the stage configuration in `protected/commands/FullDataMigrationCommand.php`:
```php
'patient' => ['model' => 'Patient', 'batch' => 100], // was 200
```

### Validation Errors (Count Mismatch)

**Possible causes:**
1. Migration still in progress
2. New records created during migration (dual-write working!)
3. Soft-deleted records in MariaDB but not in Couchbase

**Solution:** Re-run validation after migration completes

```bash
./run-full-migration.sh validate
```

---

## Post-Migration Checklist

After migration completes, verify:

- [ ] All stages completed successfully
- [ ] Validation shows matching counts
- [ ] Sample data comparison passes
- [ ] Application still works (create/read/update/delete)
- [ ] New records write to both databases
- [ ] Couchbase queries return correct data
- [ ] No error logs in application
- [ ] Couchbase bucket size is reasonable

---

## Expected Results

### Stage 1: Reference Data
```
✓ event_type: 30 records migrated
✓ element_type: 150 records migrated
✓ specialty: 15 records migrated
✓ subspecialty: 45 records migrated
✓ site: 5 records migrated
✓ institution: 3 records migrated
✓ firm: 50 records migrated
✓ eye: 3 records migrated
✓ gender: 3 records migrated
✓ ethnic_group: 20 records migrated
✓ event_group: 10 records migrated

Total: ~334 records in ~2 minutes
```

### Stage 2: Clinical Reference
```
✓ disorder: 50,000+ records migrated
✓ procedure: 20,000+ records migrated
✓ medication: 30,000+ records migrated
✓ allergy: 500+ records migrated
✓ drug: 10,000+ records migrated
✓ benefit: 100+ records migrated
✓ complication: 200+ records migrated

Total: ~110,700 records in ~25 minutes
```

### Stage 3: Core Clinical
```
✓ patient: [varies] records migrated
✓ episode: [varies] records migrated
✓ event: [varies] records migrated
✓ user: [varies] records migrated
✓ contact: [varies] records migrated

Total: Depends on your data volume (30-90 minutes)
```

### Stage 4: Module Elements
```
✓ OphCiExamination: [varies] elements
✓ OphTrOperationnote: [varies] elements
✓ [All other modules]

Total: Depends on your data volume (60-120 minutes)
```

### Stage 5: Administrative
```
✓ audit: [varies] records migrated
✓ audit_type: ~50 records migrated
✓ audit_action: ~100 records migrated
✓ setting_metadata: ~200 records migrated
✓ setting_installation: ~50 records migrated
✓ setting_institution: ~100 records migrated
✓ setting_site: ~150 records migrated
✓ setting_user: [varies] records migrated

Total: Depends on usage (20-40 minutes)
```

---

## Ready to Execute?

### Quick Start (Recommended)

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes

# Run full migration with all stages
./run-full-migration.sh all
```

### Conservative Approach (Stage by Stage)

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes

# Stage 1: Reference data
docker exec -it devcontainer-web-1 php protected/yiic fulldatamigration stage --stage=1

# Verify stage 1
./run-full-migration.sh validate

# Continue with remaining stages...
```

---

## Next Steps After Migration

1. **Test Application** - Verify all features work with Couchbase
2. **Performance Testing** - Benchmark query performance
3. **Monitor Production** - Watch for errors, slow queries
4. **Optimize Indexes** - Add N1QL indexes for slow queries
5. **Plan Cutover** - Prepare to disable MariaDB reads (Phase 18)

---

**The migration is ready to run. All prerequisites are met!** 🚀
