# Phase 14: Full Data Migration - Execution Guide

## Overview

This guide provides step-by-step instructions for executing the complete data migration from MariaDB to Couchbase. The migration is divided into 5 stages with comprehensive validation and rollback procedures.

**Estimated Duration:** 16-72 hours (depending on data volume)  
**Prerequisite:** Phase 13 complete (dual-write enabled for all models)  
**Risk Level:** Medium (mitigated with validation and rollback procedures)

---

## Table of Contents

1. [Pre-Migration Preparation](#pre-migration-preparation)
2. [Migration Stages](#migration-stages)
3. [Execution Methods](#execution-methods)
4. [Monitoring During Migration](#monitoring-during-migration)
5. [Post-Migration Validation](#post-migration-validation)
6. [Troubleshooting](#troubleshooting)
7. [Rollback Procedures](#rollback-procedures)

---

## Pre-Migration Preparation

### 1. Schedule Maintenance Window

**Recommended:**
- Weekend or off-peak hours
- 24-72 hour window (depending on data volume)
- Team availability for monitoring

**Communication:**
- Notify all stakeholders
- Update status page
- Prepare support team

### 2. Create Backups

```bash
# Backup MariaDB
mysqldump -u root -p openeyes_db > backup_$(date +%Y%m%d).sql

# Verify backup
ls -lh backup_*.sql

# Optional: Backup to remote location
scp backup_*.sql backup-server:/backups/
```

### 3. Run Pre-Flight Checks

```bash
cd protected/scripts/couchbase
./pre-migration-check.sh
```

**Expected Output:**
```
========================================
PRE-MIGRATION VERIFICATION
========================================

--- PHP Environment ---
Checking PHP available... ✓
Checking PHP version (>= 7.4)... ✓
  PHP 8.1.x
Checking PHP Couchbase extension... ✓
Checking Couchbase extension version... ✓
  v4.x

--- Couchbase Server ---
Checking Couchbase connection... ✓
  Connected

--- MariaDB/MySQL ---
Checking MariaDB connection... ✓
  Connected
Checking Patient records exist... ✓
  12450 patients

... (more checks)

========================================
✓✓✓ ALL CHECKS PASSED ✓✓✓
========================================
```

### 4. Verify Configuration

```bash
# Check migration configuration
cat protected/config/migration-config.php

# Verify batch sizes appropriate for your environment
# Adjust if needed based on available resources
```

### 5. Review Disk Space

```bash
# Check available disk space
df -h /var/lib/couchbase  # Couchbase data directory

# Recommended: 3x current MariaDB size
# Current DB size can be checked from pre-flight output
```

---

## Migration Stages

### Stage 1: Reference Data (30 minutes)

**Tables:**
- event_type, element_type
- specialty, subspecialty
- site, institution, firm
- eye, gender, ethnic_group

**Characteristics:**
- Small datasets (typically < 1000 records per table)
- Foundation data required by other stages
- Fast migration

### Stage 2: Clinical Reference (2 hours)

**Tables:**
- disorder (SNOMED codes - 100K+ records)
- procedure (OPCS codes - 50K+ records)
- medication (dm+d codes - 80K+ records)
- allergy, drug, benefit, complication

**Characteristics:**
- Large code sets
- Complex embedded relations
- Critical for clinical operations

### Stage 3: Core Clinical (4-8 hours)

**Tables:**
- patient (with embedded contact, demographics)
- episode (with embedded firm, disorder)
- event (with embedded event_type, user, episode)
- contact, user

**Characteristics:**
- High volume (10K-1M+ records)
- Complex embedded relations
- Most critical data
- Batch size: 200-500

### Stage 4: Module Elements (6-12 hours)

**Delegates to ModuleMigrationCommand:**
- Examination elements (18 types)
- Operation Note elements (6 types)
- Laser elements (4 types)
- Biometry elements (3 types)
- Other module elements

**Characteristics:**
- Highest volume
- Element-specific embedding logic
- SNOMED/OPCS code embedding

### Stage 5: Administrative (4-8 hours)

**Tables:**
- audit (time-series, high volume)
- audit_type, audit_action
- setting_* (hierarchical configuration)

**Characteristics:**
- Audit logs can be very large
- Settings have hierarchical structure
- Lower priority (can be run separately if time-constrained)

---

## Execution Methods

### Method 1: Automated Script (Recommended)

**Full migration with all safety checks:**

```bash
cd protected/scripts/couchbase
./run-full-migration.sh
```

**Options:**

```bash
# Automated (no confirmation prompts)
./run-full-migration.sh --no-confirm

# Custom batch size
./run-full-migration.sh --batch=500

# Verbose output
./run-full-migration.sh --verbose

# Skip post-validation (run separately)
./run-full-migration.sh --skip-validation
```

**What the script does:**
1. Runs pre-flight checks
2. Creates checkpoint file
3. Executes all 5 stages sequentially
4. Updates checkpoint after each stage
5. Runs post-migration validation
6. Generates summary report

### Method 2: Manual Stage-by-Stage (More Control)

**Execute each stage individually:**

```bash
# Stage 1: Reference Data
php protected/yiic fulldatamigration stage --stage=1 --verbose

# Verify stage 1
php protected/yiic datavalidation counts

# Stage 2: Clinical Reference
php protected/yiic fulldatamigration stage --stage=2 --verbose

# Verify stage 2
php protected/yiic datavalidation counts

# Stage 3: Core Clinical
php protected/yiic fulldatamigration stage --stage=3 --verbose --batch=200

# Verify stage 3
php protected/yiic datavalidation counts
php protected/yiic datavalidation samples --table=patient --sample=100

# Stage 4: Module Elements
php protected/yiic fulldatamigration stage --stage=4 --verbose

# Verify stage 4
php protected/yiic moduledata status

# Stage 5: Administrative
php protected/yiic fulldatamigration stage --stage=5 --verbose

# Final validation
php protected/yiic datavalidation all --sample=500
```

### Method 3: Dry Run (Testing)

**Test migration without writing data:**

```bash
php protected/yiic fulldatamigration run --dryRun --verbose
```

**Benefits:**
- Verify migration logic
- Estimate duration
- Identify potential issues
- No data written to Couchbase

---

## Monitoring During Migration

### Real-Time Progress

**In the migration terminal:**
- Watch for progress messages every 100-1000 records
- Monitor batch completion rates
- Track stage completion percentages

**Example output:**
```
Table: patient (Model: Patient, Scope: clinical)
  Total Records: 12450
  Progress: 2000/12450 (16.1%)
  Progress: 4000/12450 (32.1%)
  Progress: 6000/12450 (48.2%)
  ...
  ✓ Migrated: 12450/12450 (100.0%), Errors: 0
```

### Log Files

**Primary log:**
```bash
tail -f protected/runtime/migration_YYYY-MM-DD_HH-MM-SS.log
```

**Checkpoint file:**
```bash
# View current progress
cat protected/runtime/migration-checkpoint.json | jq

# Example output:
{
  "migration_start": "2025-12-24T10:00:00Z",
  "batch_size": 1000,
  "stages": {
    "1": {"name": "Reference Data", "status": "completed", "duration": 15},
    "2": {"name": "Clinical Reference", "status": "completed", "duration": 120},
    "3": {"name": "Core Clinical", "status": "in_progress"},
    "4": {"name": "Module Elements", "status": "pending"},
    "5": {"name": "Administrative", "status": "pending"}
  }
}
```

### System Resources

**Monitor Couchbase server:**

```bash
# Couchbase cluster status
couchbase-cli server-info -c localhost:8091 -u Admin -p password

# Watch resource usage
watch -n 30 'couchbase-cli server-stats -c localhost:8091 -u Admin -p password'

# Disk usage
watch -n 60 df -h /var/lib/couchbase
```

**Monitor PHP memory:**

```bash
# Watch PHP processes
watch -n 10 'ps aux | grep yiic | grep -v grep'

# If memory issues, adjust batch size in migration-config.php
```

### Database Queries

**Check Couchbase record counts:**

```bash
# Using cbq (Couchbase Query)
cbq -e "SELECT COUNT(*) FROM openeyes._default.clinical WHERE type = 'patient'"

cbq -e "SELECT scope, type, COUNT(*) as count 
        FROM openeyes._default._default 
        GROUP BY scope, type"
```

**Check MariaDB for comparison:**

```bash
mysql -u root -p openeyes_db -e "
  SELECT 'patient' as table_name, COUNT(*) as count FROM patient
  UNION ALL
  SELECT 'episode', COUNT(*) FROM episode
  UNION ALL
  SELECT 'event', COUNT(*) FROM event
"
```

---

## Post-Migration Validation

### Comprehensive Validation

```bash
# Run all validation types
php protected/yiic datavalidation all --sample=500 --verbose
```

**Validation includes:**
- **Count Validation:** MariaDB vs Couchbase record counts
- **Sample Validation:** Random sample of 500 records
- **Integrity Validation:** Referential integrity checks
- **Embedding Validation:** Embedded relations verification

**Expected output:**
```
================================================================================
COMPREHENSIVE DATA VALIDATION
================================================================================

--- Count Validation ---
Table                     MariaDB    Couchbase   Status
...
patient                      12450        12450       ✓ [100%]
episode                      34567        34567       ✓ [100%]
event                        89123        89123       ✓ [100%]

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

================================================================================
VALIDATION SUMMARY
================================================================================
  Count          : 10/10 passed (100.0%) [✓]
  Sample         : 1500/1500 passed (100.0%) [✓]
  Integrity      : 3/3 passed (100.0%) [✓]
  Embeddings     : 1000/1000 passed (100.0%) [✓]

  OVERALL: 3513/3513 (100.0%)
================================================================================
```

### Individual Validation Commands

**Count validation only:**
```bash
php protected/yiic datavalidation counts
```

**Sample validation for specific table:**
```bash
php protected/yiic datavalidation samples --table=patient --sample=100
```

**Referential integrity:**
```bash
php protected/yiic datavalidation integrity
```

**Embedding verification:**
```bash
php protected/yiic datavalidation embeddings --sample=100
```

---

## Troubleshooting

### Common Issues

#### 1. Connection Timeout

**Symptoms:**
```
ERROR: Couchbase connection timeout
```

**Solutions:**
- Check Couchbase server status
- Verify network connectivity
- Increase connection timeout in migration-config.php
- Restart Couchbase service

#### 2. Memory Exhaustion

**Symptoms:**
```
PHP Fatal error: Allowed memory size exhausted
```

**Solutions:**
- Reduce batch size in migration-config.php
- Increase PHP memory_limit
- Run migration in smaller stages

```php
// In migration-config.php
'batchSizes' => [
    'default' => 500,  // Reduce from 1000
    'patient' => 100,  // Reduce from 200
],
```

#### 3. Slow Migration

**Symptoms:**
- Migration taking longer than estimated
- < 50 records/second throughput

**Solutions:**
- Check Couchbase server resources (CPU, memory, disk I/O)
- Verify network latency
- Increase batch size (if resources available)
- Run during off-peak hours
- Consider parallel migration (advanced)

#### 4. Data Mismatch

**Symptoms:**
```
MISMATCH: Patient 12345
  hos_num: MySQL=ABC123, CB=ABC124
```

**Solutions:**
- Check for concurrent writes (disable application if needed)
- Verify toCouchbaseDocument() implementation
- Re-migrate specific records

```bash
# Re-migrate specific stage
php protected/yiic fulldatamigration stage --stage=3
```

#### 5. Partial Stage Failure

**Symptoms:**
```
❌ Stage 3 failed (exit code: 1)
```

**Solutions:**
1. Check error messages in log file
2. Fix underlying issue (disk space, connection, etc.)
3. Resume from failed stage

```bash
# Resume from stage 3
php protected/yiic fulldatamigration stage --stage=3 --continueOnError
```

---

## Rollback Procedures

### Level 1: Stop Migration

**If migration is in progress and issues arise:**

```bash
# Press Ctrl+C in terminal
# Migration will stop at current batch
```

**Check checkpoint:**
```bash
cat protected/runtime/migration-checkpoint.json
```

**Resume later:**
```bash
# Continue from last stage
./run-full-migration.sh
```

### Level 2: Disable Couchbase Reads

**Keep dual-write, but read only from MariaDB:**

```php
// protected/config/local/common.php
'couchbase' => [
    'enableDualWrite' => true,   // Keep writing
    'enableReads' => false,       // Disable reads
],
```

```bash
# Clear application cache
php protected/yiic cache flush
```

### Level 3: Clear Couchbase Data

**If data needs to be re-migrated:**

```bash
# Using command
php protected/yiic fulldatamigration rollback --confirm=true

# Or manually using N1QL
cbq -e "DELETE FROM openeyes._default.clinical"
cbq -e "DELETE FROM openeyes._default.reference"
cbq -e "DELETE FROM openeyes._default.admin"
```

**Then re-run migration:**
```bash
./run-full-migration.sh
```

### Level 4: Full Rollback with MariaDB Restore

**In case of catastrophic failure (unlikely with dual-write):**

```bash
# 1. Disable Couchbase
# Edit config: enableDualWrite = false, enableReads = false

# 2. Clear cache
php protected/yiic cache flush

# 3. Restore MariaDB from backup (if needed)
mysql -u root -p openeyes_db < backup_YYYYMMDD.sql

# 4. Application continues on MariaDB only
```

---

## Post-Migration Checklist

- [ ] All 5 stages completed successfully
- [ ] Validation shows 100% count match
- [ ] Sample validation passes (>99%)
- [ ] Referential integrity verified
- [ ] Embedded relations present
- [ ] Application smoke tests pass
- [ ] Performance acceptable
- [ ] Team trained on new system
- [ ] Documentation updated
- [ ] Monitoring configured

---

## Next Steps

After successful migration:

1. **Monitor for 24-48 hours**
   - Watch for application errors
   - Monitor Couchbase performance
   - Check data consistency

2. **Optimize Indexes** (Phase 15)
   - Analyze query patterns
   - Create additional indexes as needed
   - Remove unused indexes

3. **Performance Tuning** (Phase 15)
   - Query optimization
   - Caching strategies
   - Load testing

4. **Production Cutover** (Phase 16)
   - Switch reads to Couchbase
   - Gradual traffic migration
   - Monitor closely

---

## Support Contacts

**During Migration:**
- Technical Lead: [Contact Info]
- DBA: [Contact Info]
- On-Call Engineer: [Contact Info]

**Escalation:**
- Critical issues: [Contact Info]
- After-hours: [Contact Info]

---

**Document Version:** 1.0  
**Last Updated:** December 24, 2025  
**Maintained By:** OpenEyes Development Team
