# Phase 14: Next Steps - Migration Execution Plan

**Current Status:** Framework Complete ✅ | Data Migration Pending ⏳

---

## 🎯 Understanding Where We Are

### What's Complete ✅

**Phase 14 delivered the FRAMEWORK:**
- Migration orchestration commands
- Validation tools (6 validation types)
- Automation scripts (3 scripts)
- Database indexes (80+ indexes)
- Test suites (53 tests)
- Documentation (comprehensive guides)

**Think of it like this:**
- ✅ We built the rocket (migration framework)
- ✅ We tested the components (unit tests)
- ✅ We wrote the flight manual (documentation)
- ⏳ We have NOT launched yet (no data migrated)

### What's NOT Complete ⏳

- ❌ Data migration execution
- ❌ Environment verification
- ❌ Production readiness testing
- ❌ Backup procedures validated
- ❌ Couchbase cluster validated

---

## 🚦 Why We Haven't Migrated Yet

### 1. **Safety First**
This is **medical data** - patient records, clinical events, prescriptions.
- Wrong approach: "Let's just run it and see what happens"
- Right approach: "Let's verify, test, then execute carefully"

### 2. **Unknown Environment**
We don't know:
- Is Couchbase running?
- Is this dev, staging, or production?
- How much data exists?
- Are backups in place?

### 3. **Risk Management**
Potential consequences of premature migration:
- Data loss or corruption
- Application downtime
- Clinical workflow disruption
- Compliance violations (HIPAA, GDPR)

### 4. **Best Practice**
Standard migration workflow:
```
Build → Test → Validate → Execute
   ✅      ⏳       ⏳        ⏳
  Done   Next    After    Final
```

We're at "Build Done" - need to do "Test" next.

---

## 📋 Pre-Migration Checklist

Before running migration, answer these questions:

### Environment Questions
- [ ] What environment is this? (dev/staging/production)
- [ ] Is Couchbase server running and accessible?
- [ ] Is PHP Couchbase extension installed?
- [ ] Are Couchbase bucket/scopes/collections created?
- [ ] Is network connectivity stable?

### Data Questions
- [ ] How much data exists in MariaDB?
  ```bash
  mysql -e "SELECT COUNT(*) FROM openeyes.patient"
  ```
- [ ] Do you have adequate disk space (3x DB size)?
- [ ] What's the estimated migration duration?

### Safety Questions
- [ ] Do you have a recent MariaDB backup?
- [ ] Have you tested backup restoration?
- [ ] Is this a test system (safe to experiment)?
- [ ] Is there a rollback plan?
- [ ] Who's on-call if something goes wrong?

### Business Questions
- [ ] Is there a maintenance window scheduled?
- [ ] Have stakeholders approved migration?
- [ ] Has the team been trained?
- [ ] Is there a communication plan?

---

## 🎬 Recommended Execution Path

### Option 1: Safe Testing Path (RECOMMENDED)

**Best for:** First-time execution, learning the system

**Steps:**

#### Step 1: Environment Check (15 minutes)
```bash
# Verify environment
cd /Users/asahu/Desktop/OpenEyes/openeyes

# Check if this is production
hostname
cat protected/config/local/common.php | grep environment

# Check data volume
mysql -u root -p openeyes -e "
  SELECT 
    'patient' as table_name, COUNT(*) as count FROM patient
    UNION ALL SELECT 'episode', COUNT(*) FROM episode
    UNION ALL SELECT 'event', COUNT(*) FROM event
"
```

#### Step 2: Verify Couchbase (15 minutes)
```bash
# Check if Couchbase is running
curl http://localhost:8091/pools/default

# Or check service status
systemctl status couchbase-server

# Test PHP connection
php -r "
  include 'protected/yii.php';
  \$app = Yii::createConsoleApplication('protected/config/console.php');
  try {
    \$cb = Yii::app()->couchbase;
    echo 'SUCCESS: Couchbase accessible\n';
  } catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage() . '\n';
  }
"
```

#### Step 3: Create Backup (30 minutes)
```bash
# Backup MariaDB
mysqldump -u root -p openeyes > backup_$(date +%Y%m%d_%H%M%S).sql

# Verify backup created
ls -lh backup_*.sql

# CRITICAL: Test restoration on a test database
mysql -u root -p -e "CREATE DATABASE openeyes_test"
mysql -u root -p openeyes_test < backup_*.sql
# If this works, you have a good backup!
```

#### Step 4: Pre-Flight Checks (10 minutes)
```bash
cd protected/scripts/couchbase
./pre-migration-check.sh
```

**Expected:** All critical checks should pass

#### Step 5: Dry Run (15 minutes)
```bash
# Test migration logic WITHOUT writing data
php protected/yiic fulldatamigration run --dryRun --verbose
```

**What to check:**
- No errors in output
- All stages listed
- Record counts displayed
- Duration estimate reasonable

#### Step 6: Stage 1 Test (15 minutes)
```bash
# Migrate ONLY Stage 1 (reference data - small & fast)
php protected/yiic fulldatamigration stage --stage=1 --verbose

# Validate
php protected/yiic datavalidation counts
```

**Expected Results:**
```
Stage 1: Reference Data
---------------------------------------------------------------------------
  event_type           MySQL:   100  CB:   100  [✓ 100%]
  site                 MySQL:    45  CB:    45  [✓ 100%]
  institution          MySQL:    12  CB:    12  [✓ 100%]
```

#### Step 7: Verify Stage 1 Data (10 minutes)
```bash
# Check Couchbase has data
cbq -e "SELECT type, COUNT(*) as count 
        FROM openeyes._default.reference 
        WHERE type IN ['event_type', 'site', 'institution'] 
        GROUP BY type"

# Sample validation
php protected/yiic datavalidation samples --table=event_type --sample=10
```

#### Step 8: Continue or Stop
**If Stage 1 successful:**
- ✅ Continue with Stage 2
- ✅ Then Stage 3 (in small batches)
- ✅ Monitor carefully

**If Stage 1 has issues:**
- ❌ STOP
- ❌ Review errors in log file
- ❌ Fix issues before proceeding

**Total Time:** ~2 hours for careful testing

---

### Option 2: Full Development Migration

**Best for:** You've verified Stage 1, ready for full migration

```bash
# Create checkpoint
echo "Starting full migration at $(date)" >> migration-log.txt

# Run full migration with automation
cd protected/scripts/couchbase
./run-full-migration.sh --verbose

# Monitor in another terminal
tail -f protected/runtime/migration_*.log

# Comprehensive validation after completion
php protected/yiic datavalidation all --sample=500
```

**Duration:** 16-72 hours depending on data volume

---

### Option 3: Staging/Production Path

**Best for:** After dev testing successful, ready for real deployment

**Prerequisites:**
- [ ] Dev migration tested successfully
- [ ] Staging environment available
- [ ] Maintenance window scheduled
- [ ] Team on-call
- [ ] Backups verified

**Process:**
1. Deploy code to staging/production
2. Run pre-flight checks
3. Create production backup
4. Enable maintenance mode
5. Execute migration
6. Comprehensive validation
7. Go/no-go decision
8. Enable application or rollback

---

## ⚠️ Important Safety Notes

### DON'T Do This:
- ❌ Run migration on production without testing
- ❌ Migrate without backups
- ❌ Execute during peak hours
- ❌ Skip validation steps
- ❌ Ignore errors and continue
- ❌ Run without understanding data volume

### DO This:
- ✅ Test in development first
- ✅ Create and verify backups
- ✅ Run during maintenance window
- ✅ Validate each stage
- ✅ Monitor carefully
- ✅ Have rollback plan ready

---

## 🆘 Troubleshooting Common Issues

### Issue 1: "Couchbase not accessible"
```bash
# Check if Couchbase is running
systemctl status couchbase-server

# Check connection details in config
cat protected/config/couchbase.php

# Verify network/firewall
curl http://localhost:8091
```

### Issue 2: "PHP Couchbase extension not found"
```bash
# Check if extension installed
php -m | grep couchbase

# If not, install (Ubuntu/Debian)
sudo apt-get install php-couchbase

# Or (RHEL/CentOS)
sudo yum install php-couchbase
```

### Issue 3: "Out of memory"
```bash
# Reduce batch size in migration-config.php
# Change from 1000 to 100-200

# Or increase PHP memory
php -d memory_limit=2G protected/yiic fulldatamigration run
```

### Issue 4: "Data mismatch after migration"
```bash
# Check specific records
php protected/yiic datavalidation samples --table=patient --sample=100 --verbose

# Identify mismatches
php protected/yiic datavalidation integrity

# If critical, consider re-migration
```

---

## 📊 What to Expect

### Small Dataset (< 10K patients)
- **Duration:** 4-6 hours
- **Stages 1-2:** 1 hour
- **Stage 3:** 2 hours
- **Stage 4:** 2 hours
- **Stage 5:** 1 hour

### Medium Dataset (10K-100K patients)
- **Duration:** 12-24 hours
- **Stages 1-2:** 2 hours
- **Stage 3:** 6 hours
- **Stage 4:** 10 hours
- **Stage 5:** 6 hours

### Large Dataset (> 100K patients)
- **Duration:** 2-4 days
- **Stages 1-2:** 3 hours
- **Stage 3:** 24 hours
- **Stage 4:** 48 hours
- **Stage 5:** 24 hours

---

## 📞 Getting Help

### During Migration
- Watch log files: `tail -f protected/runtime/migration_*.log`
- Check status: `php protected/yiic fulldatamigration status`
- Monitor Couchbase: `cbq -e "SELECT COUNT(*) FROM openeyes._default.clinical"`

### Documentation Available
- `PHASE-14-EXECUTION-GUIDE.md` - Complete execution instructions
- `PHASE-14-MONITORING.md` - Monitoring queries and dashboards
- `PHASE-14-VERIFICATION-GUIDE.md` - How to verify success
- `PHASE-14-TESTING-CHECKLIST.md` - QA procedures

### If Stuck
1. Check log files in `protected/runtime/`
2. Review documentation guides
3. Run validation commands to identify issues
4. Check Couchbase server health
5. Verify disk space and memory

---

## ✅ Success Criteria

### Migration Successful When:
- [ ] All 5 stages completed without errors
- [ ] Count validation shows 100% match
- [ ] Sample validation >99% match
- [ ] Referential integrity verified (no orphans)
- [ ] Embedded relations present
- [ ] SNOMED/OPCS codes preserved
- [ ] Application functions correctly
- [ ] Performance acceptable

---

## 🚀 Your Action Plan (Next 2 Hours)

**Step 1: Environment Check (30 min)**
```bash
# Determine environment
hostname
php -r "echo Yii::app()->params['environment'] ?? 'unknown';"

# Check data volume
mysql -u root -p openeyes -e "SELECT COUNT(*) FROM patient"

# Verify Couchbase accessible
curl http://localhost:8091/pools/default
```

**Step 2: Create Backup (30 min)**
```bash
mysqldump -u root -p openeyes > backup_$(date +%Y%m%d_%H%M%S).sql
ls -lh backup_*.sql
```

**Step 3: Pre-Flight Check (10 min)**
```bash
cd protected/scripts/couchbase
./pre-migration-check.sh
```

**Step 4: Dry Run (15 min)**
```bash
php protected/yiic fulldatamigration run --dryRun --verbose
```

**Step 5: Test Stage 1 (30 min)**
```bash
php protected/yiic fulldatamigration stage --stage=1 --verbose
php protected/yiic datavalidation counts
```

**Step 6: Review Results (15 min)**
- Check log file
- Verify data in Couchbase
- Validate counts match
- Document any issues

---

## 🎓 Key Takeaways

1. **Phase 14 = Framework Built** ✅
   - Tools created, not data migrated

2. **Safety = Priority** 🔒
   - Medical data requires careful approach

3. **Test First** 🧪
   - Start with Stage 1 in dev environment

4. **Have Backups** 💾
   - Always have verified backups

5. **Validate Everything** ✓
   - Count, sample, integrity, embeddings

6. **Monitor Carefully** 👀
   - Watch logs, check status, verify data

7. **Rollback Ready** ⏪
   - Know how to undo if needed

---

## 📝 Decision Matrix

| If You Have... | Then Do... |
|---------------|------------|
| Fresh install (no data) | Test Stage 1, then full migration |
| Small dev environment | Test Stage 1, validate, continue |
| Staging environment | Full test migration in staging |
| Production system | TEST IN DEV FIRST, then staging, then prod |
| No Couchbase setup | Set up Couchbase before migrating |
| No backups | CREATE BACKUPS before anything |
| Production + no testing | ⚠️ DO NOT MIGRATE - test first |

---

## 🎯 Bottom Line

**Where we are:**
- Framework complete ✅
- Ready to test ⏳
- Not yet migrated ⏳

**What you should do next:**
1. Verify environment (dev/staging/prod)
2. Check Couchbase is running
3. Create backup
4. Run pre-flight checks
5. Test Stage 1 migration
6. If successful → continue
7. If issues → fix before proceeding

**Remember:** We built a safe, production-ready framework. Take your time to test properly - rushing medical data migration is never worth the risk.

---

**Ready to proceed?** Start with the 2-hour action plan above! 🚀

**Document Version:** 1.0  
**Created:** December 24, 2025  
**For:** Phase 14 Migration Execution
