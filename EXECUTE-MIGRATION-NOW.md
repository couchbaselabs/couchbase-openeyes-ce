# Execute Phase 14 Migration - Ready to Run

**Environment:** Development ✅  
**Safety:** Hiccups OK, can recover ✅  
**Status:** Ready to execute 🚀  

---

## ⚡ Quick Start - Run Migration Now

### Option 1: If Running in Docker/DevContainer

**Check if containers are running:**
```bash
docker ps
```

**Execute migration inside the web container:**
```bash
# Enter the container
docker exec -it openeyes-web bash

# Inside container, run migration
cd /var/www/openeyes
php protected/yiic fulldatamigration run --verbose

# Or run specific stages
php protected/yiic fulldatamigration stage --stage=1 --verbose
```

---

### Option 2: If PHP is Installed Locally

**Find PHP:**
```bash
# Try these
which php
which php8.1
which php8.0

# Or use specific path
/usr/bin/php --version
/usr/local/bin/php --version
```

**Run migration:**
```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes

# Use whatever PHP you found above
php protected/yiic fulldatamigration run --verbose

# Or with full path
/usr/local/bin/php protected/yiic fulldatamigration run --verbose
```

---

### Option 3: Stage-by-Stage Execution (RECOMMENDED for Dev Testing)

This is the safest way to test - migrate one stage at a time and verify.

#### Stage 1: Reference Data (~5-10 minutes)

**What it migrates:**
- event_type, element_type
- site, institution, firm  
- specialty, subspecialty
- eye, gender, ethnic_group

**Execute:**
```bash
php protected/yiic fulldatamigration stage --stage=1 --verbose
```

**Validate:**
```bash
php protected/yiic datavalidation counts
```

**Expected output:**
```
Stage 1: Reference Data
---------------------------------------------------------------------------
  event_type           MySQL:   100  CB:   100  [✓ 100%]
  site                 MySQL:    45  CB:    45  [✓ 100%]
  ...all tables match...
```

**Check Couchbase:**
```bash
# If cbq is available
cbq -e "SELECT type, COUNT(*) FROM openeyes._default.reference GROUP BY type"
```

---

#### Stage 2: Clinical Reference (~30-60 minutes)

**What it migrates:**
- disorder (SNOMED codes)
- procedure (OPCS codes)
- medication (dm+d codes)
- allergy, drug, benefit, complication

**Execute:**
```bash
php protected/yiic fulldatamigration stage --stage=2 --verbose
```

**Validate:**
```bash
php protected/yiic datavalidation counts

# Check SNOMED codes preserved
cbq -e "SELECT COUNT(*) as total, 
        COUNT(CASE WHEN snomed_code IS NOT NULL THEN 1 END) as with_snomed
        FROM openeyes._default.reference WHERE type = 'disorder'"
```

---

#### Stage 3: Core Clinical Data (~1-4 hours depending on data volume)

**What it migrates:**
- patient (with embedded contact)
- episode (with embedded firm, disorder)
- event (with embedded event_type, user)
- contact, user

**Execute (with smaller batch for dev testing):**
```bash
php protected/yiic fulldatamigration stage --stage=3 --batch=100 --verbose
```

**Monitor in another terminal:**
```bash
tail -f protected/runtime/migration_*.log
```

**Validate:**
```bash
php protected/yiic datavalidation counts
php protected/yiic datavalidation samples --table=patient --sample=50
php protected/yiic datavalidation integrity
```

**Check embedded relations:**
```bash
cbq -e "SELECT COUNT(*) as total, 
        COUNT(CASE WHEN contact IS NOT NULL THEN 1 END) as with_contact
        FROM openeyes._default.clinical WHERE type = 'patient'"
```

---

#### Stage 4: Module Elements (~2-6 hours)

**What it migrates:**
- All 22 module element types
- Operation notes, Laser, Biometry, etc.

**Execute:**
```bash
php protected/yiic fulldatamigration stage --stage=4 --verbose
```

**This delegates to ModuleMigrationCommand, so you'll see:**
```
Delegating to command: yiic moduledata migrate --module=all
```

**Validate:**
```bash
php protected/yiic moduledata status
```

---

#### Stage 5: Administrative Data (~1-3 hours)

**What it migrates:**
- audit (time-series, potentially large)
- audit_type, audit_action
- settings (metadata, installation, institution, site, user)

**Execute:**
```bash
php protected/yiic fulldatamigration stage --stage=5 --batch=200 --verbose
```

**Validate:**
```bash
php protected/yiic datavalidation counts
```

---

### Option 4: Full Automated Migration

**If you want to run everything at once:**

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes/protected/scripts/couchbase

# Full migration with automation
./run-full-migration.sh --verbose

# Or if you want no confirmation prompts
./run-full-migration.sh --no-confirm --verbose
```

**This will:**
1. Run pre-flight checks
2. Create checkpoint
3. Execute all 5 stages
4. Validate after each stage
5. Generate summary report

---

## 📊 During Migration - What to Watch

### Terminal 1: Run Migration
```bash
php protected/yiic fulldatamigration stage --stage=1 --verbose
```

### Terminal 2: Monitor Log
```bash
tail -f protected/runtime/migration_*.log
```

### Terminal 3: Watch Couchbase
```bash
# Update every 30 seconds
watch -n 30 'cbq -e "SELECT type, COUNT(*) FROM openeyes._default.clinical GROUP BY type LIMIT 10"'
```

---

## ✅ Post-Migration Validation

### Quick Validation
```bash
php protected/yiic datavalidation counts
```

### Comprehensive Validation
```bash
php protected/yiic datavalidation all --sample=500 --verbose
```

**What it checks:**
- ✓ Count matching (MariaDB vs Couchbase)
- ✓ Sample records (500 random samples)
- ✓ Referential integrity (no orphans)
- ✓ Embedded relations (contact, firm, etc.)
- ✓ Index coverage

### Expected Output
```
COMPREHENSIVE DATA VALIDATION
==============================================================================

--- Count Validation ---
patient                  MySQL: 12450  CB: 12450  [✓ 100%]
episode                  MySQL: 34567  CB: 34567  [✓ 100%]
event                    MySQL: 89123  CB: 89123  [✓ 100%]
...

--- Sample Validation ---
  patient: 500/500 (100.0%)
  episode: 500/500 (100.0%)
  event: 500/500 (100.0%)

--- Referential Integrity ---
  ✓ Episode → Patient: No orphans
  ✓ Event → Episode: No orphans
  ✓ Event → EventType: No orphans

--- Embedded Relations ---
  Patient contact embeddings: 500/500 (100.0%)
  Episode firm embeddings: 500/500 (100.0%)

==============================================================================
OVERALL: 3514/3514 (100.0%)
==============================================================================
```

---

## 🔍 Check Specific Data

### Patient Data
```bash
# Count
cbq -e "SELECT COUNT(*) FROM openeyes._default.clinical WHERE type = 'patient'"

# Sample record
cbq -e "SELECT * FROM openeyes._default.clinical WHERE type = 'patient' LIMIT 1"

# Check embedded contact
cbq -e "SELECT contact.first_name, contact.last_name 
        FROM openeyes._default.clinical 
        WHERE type = 'patient' 
        AND contact IS NOT NULL 
        LIMIT 5"
```

### Episode Data
```bash
# Count
cbq -e "SELECT COUNT(*) FROM openeyes._default.clinical WHERE type = 'episode'"

# Check embedded firm
cbq -e "SELECT firm.name, disorder.term 
        FROM openeyes._default.clinical 
        WHERE type = 'episode' 
        AND firm IS NOT NULL 
        LIMIT 5"
```

### Reference Data
```bash
# All reference types
cbq -e "SELECT type, COUNT(*) as count 
        FROM openeyes._default.reference 
        GROUP BY type 
        ORDER BY type"

# Disorders with SNOMED
cbq -e "SELECT term, snomed_code 
        FROM openeyes._default.reference 
        WHERE type = 'disorder' 
        AND snomed_code IS NOT NULL 
        LIMIT 10"
```

---

## 🐛 If Something Goes Wrong

### Error: "Couchbase not accessible"
```bash
# Check if Couchbase is running
docker ps | grep couchbase
# Or
curl http://localhost:8091/pools/default
```

### Error: "Model class not found"
```bash
# Check if you're in the right directory
pwd
# Should be: /Users/asahu/Desktop/OpenEyes/openeyes

# Check if Yii is loading
php -r "include 'protected/yii.php'; echo 'Yii loaded';"
```

### Error: "Out of memory"
```bash
# Reduce batch size
php protected/yiic fulldatamigration stage --stage=3 --batch=50
```

### Data Mismatch
```bash
# Run sample validation to see specifics
php protected/yiic datavalidation samples --table=patient --sample=100 --verbose

# Check for missing embedded relations
cbq -e "SELECT id, hos_num 
        FROM openeyes._default.clinical 
        WHERE type = 'patient' 
        AND contact_id IS NOT NULL 
        AND contact IS MISSING 
        LIMIT 10"
```

### Need to Re-migrate
```bash
# Clear Couchbase data for a scope
cbq -e "DELETE FROM openeyes._default.clinical"

# Then re-run the stage
php protected/yiic fulldatamigration stage --stage=3
```

---

## 📝 Logging and Monitoring

### Log Files
```bash
# Main migration log
ls -lt protected/runtime/migration_*.log | head -1

# View log
tail -f protected/runtime/migration_*.log

# Search for errors
grep -i error protected/runtime/migration_*.log
```

### Checkpoint File
```bash
# View current progress
cat protected/runtime/migration-checkpoint.json

# Pretty print with jq (if installed)
cat protected/runtime/migration-checkpoint.json | jq
```

### Migration Status
```bash
# Check overall status
php protected/yiic fulldatamigration status

# Shows counts for all tables:
# Stage 1: Reference Data
#   event_type    MySQL: 100  CB: 100  [✓ 100%]
#   ...
```

---

## 🎯 Recommended Execution for Dev

**Since this is dev, I recommend stage-by-stage:**

```bash
# 1. Stage 1 (quick test - 5-10 min)
php protected/yiic fulldatamigration stage --stage=1 --verbose
php protected/yiic datavalidation counts

# 2. If Stage 1 OK, continue with Stage 2 (30-60 min)
php protected/yiic fulldatamigration stage --stage=2 --verbose
php protected/yiic datavalidation counts

# 3. Stage 3 with small batch (1-4 hours)
php protected/yiic fulldatamigration stage --stage=3 --batch=100 --verbose
php protected/yiic datavalidation samples --table=patient --sample=50

# 4. Stage 4 (2-6 hours)
php protected/yiic fulldatamigration stage --stage=4 --verbose
php protected/yiic moduledata status

# 5. Stage 5 (1-3 hours)
php protected/yiic fulldatamigration stage --stage=5 --verbose

# 6. Final validation
php protected/yiic datavalidation all --sample=500
```

**Total time:** 4-14 hours depending on data volume

---

## 🚀 ONE-LINER - Quick Start

**For the brave (runs everything):**
```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes && \
php protected/yiic fulldatamigration run --verbose && \
php protected/yiic datavalidation all --sample=500
```

---

## 📋 Quick Checklist

Before starting:
- [ ] Confirmed this is dev environment ✅
- [ ] OK with potential issues ✅
- [ ] Couchbase is running
- [ ] MariaDB is accessible
- [ ] PHP command works

Execute:
- [ ] Run Stage 1
- [ ] Validate Stage 1
- [ ] Run Stage 2
- [ ] Validate Stage 2
- [ ] Run Stage 3 (small batch)
- [ ] Validate Stage 3
- [ ] Run Stage 4
- [ ] Validate Stage 4
- [ ] Run Stage 5
- [ ] Final comprehensive validation

Success criteria:
- [ ] All counts match (100%)
- [ ] Sample validation >99%
- [ ] No orphaned records
- [ ] Embedded relations present
- [ ] SNOMED/OPCS codes preserved

---

## 🎉 When Complete

You'll have:
- ✅ All data migrated to Couchbase
- ✅ Data validated and verified
- ✅ Dual-write capability tested
- ✅ Performance baseline established
- ✅ Framework validated in your environment
- ✅ Ready to move to staging/production

**Next Steps:**
- Document any issues found
- Review performance metrics
- Adjust configurations if needed
- Plan staging deployment
- Move to Phase 15 (Performance Optimization)

---

**Ready to execute?** Pick your option above and let's go! 🚀

**Document Version:** 1.0  
**Created:** December 24, 2025  
**Environment:** Development (safe to experiment!)
