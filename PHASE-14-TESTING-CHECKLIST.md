# Phase 14: Testing Checklist

**Status:** Ready for Testing  
**Date:** December 24, 2025  
**Phase:** 14 of 16  

---

## Pre-Testing Setup

### ✅ Infrastructure Verification

- [ ] **Quick Test Script**
  ```bash
  cd protected/scripts/couchbase
  ./quick-test.sh
  ```
  Expected: All critical tests pass

- [ ] **Pre-Flight Checks**
  ```bash
  ./pre-migration-check.sh
  ```
  Expected: No errors, all systems ready

- [ ] **Database Connections**
  - [ ] MariaDB accessible
  - [ ] Couchbase accessible
  - [ ] Both respond to ping/select

- [ ] **File Permissions**
  - [ ] Runtime directory writable
  - [ ] Scripts executable
  - [ ] Log directory accessible

---

## Unit Testing

### ✅ FullDataMigrationCommandTest (28 tests)

```bash
cd protected/tests
phpunit unit/commands/FullDataMigrationCommandTest.php
```

**Test Coverage:**
- [ ] Command structure and naming
- [ ] Help text generation
- [ ] Stage configuration (5 stages)
- [ ] Table assignments per stage
- [ ] Batch size configuration
- [ ] Scope assignments
- [ ] Model class validation
- [ ] Document creation methods
- [ ] Method existence checks
- [ ] Dependency ordering

**Expected:** 28 tests, 0 failures, 0 errors

### ✅ DataValidationCommandTest (if exists)

```bash
phpunit unit/commands/DataValidationCommandTest.php
```

**Expected:** All validation methods work correctly

---

## Integration Testing

### ✅ FullMigrationIntegrationTest (25 tests)

```bash
cd protected/tests
phpunit integration/FullMigrationIntegrationTest.php
```

**Test Coverage:**
- [ ] Command instantiation
- [ ] Couchbase availability
- [ ] Test data creation
- [ ] Patient data integrity
- [ ] Episode relationships
- [ ] Event hierarchy
- [ ] Batch processing
- [ ] Embedded relations
- [ ] NULL handling
- [ ] Count accuracy
- [ ] Document keys
- [ ] Timestamp preservation
- [ ] Special characters
- [ ] Data types
- [ ] Performance metrics
- [ ] Memory usage
- [ ] Rollback procedures

**Expected:** 25 tests pass (some may skip if Couchbase unavailable)

---

## Functional Testing

### 1. Dry Run Test

```bash
# Test migration without writing data
php protected/yiic fulldatamigration run --dryRun --verbose
```

**Verify:**
- [ ] All stages execute
- [ ] Record counts displayed
- [ ] No data written to Couchbase
- [ ] No errors in log
- [ ] Memory usage acceptable

**Duration:** ~5-10 minutes

### 2. Stage-by-Stage Test

**Stage 1: Reference Data**
```bash
php protected/yiic fulldatamigration stage --stage=1 --verbose
php protected/yiic datavalidation counts
```

**Verify:**
- [ ] Event types migrated
- [ ] Sites migrated
- [ ] Institutions migrated
- [ ] Counts match
- [ ] No errors

**Stage 2: Clinical Reference**
```bash
php protected/yiic fulldatamigration stage --stage=2 --verbose
php protected/yiic datavalidation counts
```

**Verify:**
- [ ] Disorders migrated (with SNOMED codes)
- [ ] Procedures migrated (with OPCS codes)
- [ ] Medications migrated (with dm+d codes)
- [ ] Counts match
- [ ] Codes preserved

**Stage 3: Core Clinical** (Small batch for testing)
```bash
php protected/yiic fulldatamigration stage --stage=3 --batch=10 --verbose
php protected/yiic datavalidation samples --table=patient --sample=10
```

**Verify:**
- [ ] 10 patients migrated
- [ ] Contact embeddings present
- [ ] Episodes migrated
- [ ] Events migrated
- [ ] Relationships intact
- [ ] Sample validation passes

**Stage 4: Module Elements** (Test mode)
```bash
php protected/yiic fulldatamigration stage --stage=4 --verbose
php protected/yiic moduledata status
```

**Verify:**
- [ ] Delegates to ModuleMigrationCommand
- [ ] Module elements migrated
- [ ] Element embeddings present

**Stage 5: Administrative**
```bash
php protected/yiic fulldatamigration stage --stage=5 --batch=10 --verbose
php protected/yiic datavalidation counts
```

**Verify:**
- [ ] Audit records migrated
- [ ] Settings migrated
- [ ] Counts match

### 3. Validation Testing

**Count Validation**
```bash
php protected/yiic datavalidation counts
```

**Verify:**
- [ ] All tables show counts
- [ ] MariaDB vs Couchbase match
- [ ] No missing tables

**Sample Validation**
```bash
php protected/yiic datavalidation samples --table=patient --sample=100
```

**Verify:**
- [ ] 100 samples checked
- [ ] >99% match rate
- [ ] Mismatches explained (if any)

**Integrity Validation**
```bash
php protected/yiic datavalidation integrity
```

**Verify:**
- [ ] No orphaned episodes
- [ ] No orphaned events
- [ ] All relationships valid

**Embedding Validation**
```bash
php protected/yiic datavalidation embeddings --sample=100
```

**Verify:**
- [ ] Patient contacts embedded
- [ ] Episode firms embedded
- [ ] Event types embedded
- [ ] All required embeddings present

**Comprehensive Validation**
```bash
php protected/yiic datavalidation all --sample=500
```

**Verify:**
- [ ] All validation types pass
- [ ] Overall >99% success rate
- [ ] Summary shows green status

---

## Performance Testing

### 1. Batch Processing Performance

**Test with varying batch sizes:**
```bash
# Small batch
time php protected/yiic fulldatamigration stage --stage=1 --batch=100

# Medium batch
time php protected/yiic fulldatamigration stage --stage=1 --batch=500

# Large batch
time php protected/yiic fulldatamigration stage --stage=1 --batch=1000
```

**Measure:**
- [ ] Records per second
- [ ] Memory usage
- [ ] CPU usage
- [ ] Optimal batch size identified

### 2. Large Dataset Test

**Create test dataset:**
```bash
# If test data generation available
php protected/yiic generatetestdata --patients=1000 --episodes=5000
```

**Migrate:**
```bash
time php protected/yiic fulldatamigration run --verbose
```

**Verify:**
- [ ] Migration completes successfully
- [ ] Throughput >100 records/sec
- [ ] Memory stays < 512MB
- [ ] No memory leaks
- [ ] All data validated

### 3. Stress Test

**Test with production-like volume:**
```bash
# Estimate duration
php protected/yiic fulldatamigration status

# Run migration
time php protected/yiic fulldatamigration run --batch=500
```

**Monitor:**
- [ ] System resources during migration
- [ ] Couchbase server health
- [ ] MariaDB performance
- [ ] Network bandwidth
- [ ] Disk I/O

---

## Error Handling Testing

### 1. Connection Failure Test

**Simulate Couchbase failure:**
```bash
# Stop Couchbase service
sudo systemctl stop couchbase-server

# Try migration
php protected/yiic fulldatamigration stage --stage=1
```

**Verify:**
- [ ] Error detected
- [ ] Appropriate error message
- [ ] No data corruption
- [ ] Can resume after fix

### 2. Partial Failure Test

**Test continueOnError flag:**
```bash
php protected/yiic fulldatamigration run --continueOnError
```

**Verify:**
- [ ] Migration continues on errors
- [ ] Errors logged
- [ ] Summary shows error count
- [ ] Failed records identifiable

### 3. Rollback Test

**Test rollback procedures:**

**Level 1: Stop migration**
```bash
# Start migration
php protected/yiic fulldatamigration run &
PID=$!

# Wait a bit, then stop
sleep 10
kill $PID

# Check checkpoint
cat protected/runtime/migration-checkpoint.json
```

**Verify:**
- [ ] Migration stops cleanly
- [ ] Checkpoint saved
- [ ] Can resume from checkpoint

**Level 2: Clear Couchbase**
```bash
php protected/yiic fulldatamigration rollback --confirm=true
```

**Verify:**
- [ ] Confirmation required
- [ ] Instructions displayed
- [ ] Data can be cleared

---

## Monitoring Testing

### 1. Log File Testing

```bash
# Start migration with logging
php protected/yiic fulldatamigration run --verbose

# In another terminal, monitor logs
tail -f protected/runtime/migration_*.log
```

**Verify:**
- [ ] Log file created
- [ ] Timestamps present
- [ ] Progress tracked
- [ ] Errors logged
- [ ] Summary at end

### 2. Checkpoint Testing

```bash
# Start migration
php protected/yiic fulldatamigration run &

# Monitor checkpoint updates
watch -n 5 cat protected/runtime/migration-checkpoint.json
```

**Verify:**
- [ ] Checkpoint file created
- [ ] Updates after each stage
- [ ] Contains stage status
- [ ] Contains duration info

### 3. N1QL Query Testing

**Test monitoring queries:**
```bash
# Record counts
cbq -e "SELECT type, COUNT(*) FROM openeyes._default.clinical GROUP BY type"

# Recent writes
cbq -e "SELECT type, COUNT(*) FROM openeyes._default.clinical 
        WHERE created_date >= DATE_ADD_STR(NOW_STR(), -1, 'hour') GROUP BY type"
```

**Verify:**
- [ ] Queries execute
- [ ] Counts accurate
- [ ] Performance acceptable

---

## Automation Testing

### 1. Pre-Migration Script

```bash
cd protected/scripts/couchbase
./pre-migration-check.sh
```

**Verify:**
- [ ] All checks execute
- [ ] Clear pass/fail indicators
- [ ] Helpful error messages
- [ ] Exit code correct

### 2. Full Migration Script

```bash
./run-full-migration.sh --no-confirm --batch=100
```

**Verify:**
- [ ] Stages execute in order
- [ ] Progress displayed
- [ ] Checkpoints saved
- [ ] Validation runs
- [ ] Summary generated

### 3. Quick Test Script

```bash
./quick-test.sh
```

**Verify:**
- [ ] All tests execute
- [ ] Results color-coded
- [ ] Summary displayed
- [ ] Exit code correct

---

## Documentation Testing

### 1. Execution Guide

- [ ] Instructions clear and accurate
- [ ] Commands execute as documented
- [ ] Examples work correctly
- [ ] Troubleshooting helps resolve issues

### 2. Monitoring Guide

- [ ] N1QL queries execute
- [ ] Query results useful
- [ ] Scripts work as documented
- [ ] Dashboards helpful

---

## Regression Testing

### 1. Existing Functionality

**Verify existing features still work:**
- [ ] Patient search
- [ ] Episode creation
- [ ] Event creation
- [ ] Dual-write (if enabled)
- [ ] Application UI

### 2. Data Consistency

**Verify MariaDB unchanged:**
```bash
# Compare checksums before/after
mysql -u root -p openeyes -e "CHECKSUM TABLE patient, episode, event"
```

**Verify:**
- [ ] Source data unchanged
- [ ] No data loss
- [ ] Relationships intact

---

## Sign-Off Checklist

### Development Team
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Code reviewed
- [ ] Documentation reviewed
- [ ] Known issues documented

### QA Team
- [ ] Functional tests pass
- [ ] Performance tests pass
- [ ] Error handling verified
- [ ] User acceptance criteria met
- [ ] Test report completed

### Operations Team
- [ ] Infrastructure ready
- [ ] Monitoring configured
- [ ] Backup procedures tested
- [ ] Rollback procedures tested
- [ ] Team trained

### Product Owner
- [ ] Requirements met
- [ ] Risks understood
- [ ] Timeline acceptable
- [ ] Business sign-off

---

## Test Results Template

```markdown
# Phase 14 Test Results

**Date:** YYYY-MM-DD
**Tester:** Name
**Environment:** Development/Staging/Production

## Unit Tests
- Tests Run: X
- Tests Passed: X
- Tests Failed: X
- Pass Rate: X%

## Integration Tests
- Tests Run: X
- Tests Passed: X
- Tests Failed: X
- Pass Rate: X%

## Functional Tests
- Dry Run: PASS/FAIL
- Stage 1: PASS/FAIL
- Stage 2: PASS/FAIL
- Stage 3: PASS/FAIL
- Stage 4: PASS/FAIL
- Stage 5: PASS/FAIL

## Performance
- Throughput: X records/sec
- Memory Usage: X MB
- Duration: X hours

## Validation
- Count Validation: PASS/FAIL
- Sample Validation: X% match
- Integrity: PASS/FAIL
- Embeddings: PASS/FAIL

## Issues Found
1. Issue description
2. Issue description

## Recommendations
1. Recommendation
2. Recommendation

## Overall Assessment
[ ] Ready for next phase
[ ] Needs fixes
[ ] Blocked
```

---

## Next Phase

After all tests pass:
- [ ] Deploy to staging
- [ ] Execute staging migration
- [ ] Monitor for 24-48 hours
- [ ] Fix any issues
- [ ] Schedule production window
- [ ] Proceed to Phase 15 (Performance Optimization)

---

**Document Version:** 1.0  
**Last Updated:** December 24, 2025  
**Maintained By:** OpenEyes Development Team
