# Phase 14: Verification & Status Guide

**Quick Reference:** How to verify Phase 14 completion and check data migration status

---

## ⚡ Quick Status Check

```bash
# 1. Verify Phase 14 files exist
ls -la protected/commands/FullDataMigrationCommand.php
ls -la protected/commands/DataValidationCommand.php
ls -la protected/config/migration-config.php
ls -la protected/scripts/couchbase/{run-full-migration,pre-migration-check,quick-test}.sh
ls -la protected/scripts/couchbase/phase14-indexes.n1ql

# 2. Quick test (basic verification)
cd protected/scripts/couchbase
./quick-test.sh

# 3. Check migration status
php protected/yiic fulldatamigration status

# 4. Check Couchbase data (if running)
cbq -e "SELECT type, COUNT(*) FROM openeyes._default.clinical GROUP BY type LIMIT 10"
```

---

## 📋 Understanding Phase 14

**Important:** Phase 14 created the **MIGRATION FRAMEWORK**, not the actual data migration.

- ✅ **Phase 14 Status:** Implementation COMPLETE (framework ready)
- ⏳ **Data Migration:** Not yet executed (awaiting deployment)

**What Phase 14 Delivers:**
- Migration orchestration commands
- Validation framework (6 validation types)
- Automation scripts (3 scripts)
- Database indexes (80+ indexes)
- Test suites (53 test methods)
- Documentation (5 guides)

---

## 🗂️ Models Updated (From Earlier Phases)

### Core Models (~40 models) - Phases 10-12

**All have `CouchbaseModelBridge` trait:**

| Model | Scope | Collection | Purpose |
|-------|-------|------------|---------|
| Patient | clinical | patient | Patient demographics + embedded contact |
| Episode | clinical | episode | Care episodes + embedded firm/disorder |
| Event | clinical | event | Clinical events + embedded event_type |
| Contact | clinical | contact | Person contact details |
| User | admin | user | System users |
| EventType | reference | event_type | Event type definitions |
| ElementType | reference | element_type | Element type definitions |
| Site | reference | site | Hospital sites |
| Institution | reference | institution | Healthcare institutions |
| Firm | reference | firm | Clinical firms/teams |
| Specialty | reference | specialty | Medical specialties |
| Subspecialty | reference | subspecialty | Medical subspecialties |
| Eye | reference | eye | Eye identifiers (left/right/both) |
| Gender | reference | gender | Gender options |
| EthnicGroup | reference | ethnic_group | Ethnicity classifications |
| Disorder | reference | disorder | Diagnoses with SNOMED codes |
| Procedure | reference | procedure | Procedures with OPCS codes |
| Medication | reference | medication | Medications with dm+d codes |
| MedicationSet | reference | medication_set | Medication sets |
| MedicationSetItem | reference | medication_set_item | Items in medication sets |
| Allergy | reference | allergy | Allergy types |
| Drug | reference | drug | Drug formulations |
| Benefit | reference | benefit | Benefits lookup |
| Complication | reference | complication | Complications lookup |
| Audit | admin | audit | Audit trail (time-series) |
| AuditType | admin | audit_type | Audit event types |
| AuditAction | admin | audit_action | Audit actions |
| SettingMetadata | admin | setting_metadata | Setting definitions |
| SettingInstallation | admin | setting_installation | Installation settings |
| SettingInstitution | admin | setting_institution | Institution settings |
| SettingSite | admin | setting_site | Site settings |
| SettingUser | admin | setting_user | User settings |
| OPCSCode | reference | opcs_code | OPCS procedure codes |
| Address | clinical | address | Address records |
| + more... | | | |

**Verify:**
```bash
grep -l "CouchbaseModelBridge" protected/models/*.php | wc -l
# Expected: ~40 files
```

### Module Element Models (22 models) - Phase 13

**All have `CouchbaseElementBridge` trait:**

**Operation Notes (6):**
- Element_OphTrOperationnote_Cataract
- Element_OphTrOperationnote_ProcedureList
- Element_OphTrOperationnote_Surgeon
- Element_OphTrOperationnote_Anaesthetic
- Element_OphTrOperationnote_Comments
- Element_OphTrOperationnote_GenericProcedure

**Laser Treatment (4):**
- Element_OphTrLaser_Treatment
- Element_OphTrLaser_Site
- Element_OphTrLaser_AnteriorSegment
- Element_OphTrLaser_PosteriorPole

**Biometry (3):**
- Element_OphInBiometry_Measurement
- Element_OphInBiometry_Calculation
- Element_OphInBiometry_Selection

**Prescription (1):**
- Element_OphDrPrescription_Details

**Correspondence (1):**
- ElementLetter

**Operation Booking (3):**
- Element_OphTrOperationbooking_Operation
- Element_OphTrOperationbooking_Diagnosis
- Element_OphTrOperationbooking_ScheduleOperation

**CVI (3):**
- Element_OphCoCvi_EventInfo
- Element_OphCoCvi_ClinicalInfo
- Element_OphCoCvi_ClericalInfo

**Verify:**
```bash
grep -r "CouchbaseElementBridge" protected/modules/*/models/ | wc -l
# Expected: ~22 matches
```

---

## 🗄️ Couchbase Collections Structure

```
openeyes (bucket)
├── reference (scope) - Lookup/reference data
│   ├── event_type
│   ├── element_type
│   ├── site
│   ├── institution
│   ├── firm
│   ├── specialty, subspecialty
│   ├── eye, gender, ethnic_group
│   ├── disorder (SNOMED codes)
│   ├── procedure (OPCS codes)
│   ├── medication (dm+d codes)
│   ├── medication_set, medication_set_item
│   ├── allergy, drug
│   └── benefit, complication
│
├── clinical (scope) - Patient/clinical data
│   ├── patient (with embedded contact)
│   ├── episode (with embedded firm, disorder)
│   ├── event (with embedded event_type, user)
│   ├── contact
│   ├── address
│   └── [22 element types]
│       ├── Element_OphTrOperationnote_Cataract
│       ├── Element_OphTrLaser_Treatment
│       └── ... (20 more)
│
└── admin (scope) - System/admin data
    ├── user
    ├── audit (time-series)
    ├── audit_type, audit_action
    ├── setting_metadata
    ├── setting_installation
    ├── setting_institution
    ├── setting_site
    └── setting_user
```

---

## ✅ Phase 14 Implementation Verification

### Check All Files Exist

```bash
# Commands (2)
ls -la protected/commands/FullDataMigrationCommand.php
ls -la protected/commands/DataValidationCommand.php

# Config (1)
ls -la protected/config/migration-config.php

# Scripts (3)
ls -la protected/scripts/couchbase/run-full-migration.sh
ls -la protected/scripts/couchbase/pre-migration-check.sh
ls -la protected/scripts/couchbase/quick-test.sh

# Indexes (1)
ls -la protected/scripts/couchbase/phase14-indexes.n1ql

# Tests (2)
ls -la protected/tests/unit/commands/FullDataMigrationCommandTest.php
ls -la protected/tests/integration/FullMigrationIntegrationTest.php

# Documentation (6)
ls -la docs/migration-mariadb-to-couchbase/PHASE-14-*.md
ls -la PHASE-14-*.md
```

**Expected:** All 15 files should exist

### Check Models Have Traits

```bash
# Core models
grep -l "use.*CouchbaseModelBridge" protected/models/*.php | head -10

# Module elements
grep -r "use.*CouchbaseElementBridge" protected/modules/*/models/ | head -10

# Total count
grep -r "CouchbaseModelBridge\|CouchbaseElementBridge" \
    protected/models/ protected/modules/*/models/ | wc -l
# Expected: ~62+ matches
```

---

## 🔍 Migration Status Verification

### Before Migration (Current State)

**Check MariaDB counts:**
```bash
php protected/yiic fulldatamigration status
```

**Expected output:**
```
FULL DATA MIGRATION STATUS
===========================================================================

Stage 1: Reference Data
---------------------------------------------------------------------------
  event_type                MySQL:      100  CB:        0  [⚠   0%]
  site                      MySQL:       45  CB:        0  [⚠   0%]
  institution               MySQL:       12  CB:        0  [⚠   0%]
  ...

Stage 2: Clinical Reference
---------------------------------------------------------------------------
  disorder                  MySQL:   125000  CB:        0  [⚠   0%]
  procedure                 MySQL:    58000  CB:        0  [⚠   0%]
  medication                MySQL:    92000  CB:        0  [⚠   0%]
  ...

Stage 3: Core Clinical
---------------------------------------------------------------------------
  patient                   MySQL:    12450  CB:        0  [⚠   0%]
  episode                   MySQL:    34567  CB:        0  [⚠   0%]
  event                     MySQL:    89123  CB:        0  [⚠   0%]
  ...
```

**Check Couchbase (should be empty):**
```bash
cbq -e "SELECT COUNT(*) FROM openeyes._default.clinical WHERE type = 'patient'"
# Expected: {"$1": 0} or error if collection doesn't exist
```

### After Migration (Successful State)

**Check counts match:**
```bash
php protected/yiic datavalidation counts
```

**Expected output:**
```
=== Record Counts ===

Table                     MariaDB    Couchbase   Status
------------------------------------------------------------------------
patient                      12450        12450       ✓ [100%]
episode                      34567        34567       ✓ [100%]
event                        89123        89123       ✓ [100%]
disorder                    125000       125000       ✓ [100%]
medication                   92000        92000       ✓ [100%]
...

Result: 10/10 tables match
```

**Comprehensive validation:**
```bash
php protected/yiic datavalidation all --sample=500
```

**Expected output:**
```
COMPREHENSIVE DATA VALIDATION
==============================================================================

--- Count Validation ---
...all tables match...

--- Sample Validation ---
  patient: 500/500 (100.0%)
  episode: 500/500 (100.0%)
  event: 500/500 (100.0%)

--- Referential Integrity ---
  ✓ Episode → Patient: No orphans
  ✓ Event → Episode: No orphans
  ✓ Event → EventType: No orphans
  ✓ Episode → Firm: No orphans

--- Embedded Relations ---
  Patient contact embeddings: 500/500 (100.0%)
  Episode firm embeddings: 500/500 (100.0%)

==============================================================================
VALIDATION SUMMARY
==============================================================================
  Count          : 10/10 passed (100.0%) [✓]
  Sample         : 1500/1500 passed (100.0%) [✓]
  Integrity      : 4/4 passed (100.0%) [✓]
  Embeddings     : 1000/1000 passed (100.0%) [✓]

  OVERALL: 3514/3514 (100.0%)
==============================================================================
```

---

## 🎯 Verification by Migration Stage

### Stage 1: Reference Data → reference scope

**Tables:** event_type, element_type, site, institution, firm, specialty, subspecialty, eye, gender, ethnic_group

**Verify:**
```bash
cbq -e "SELECT type, COUNT(*) as count 
        FROM openeyes._default.reference 
        WHERE type IN ['event_type', 'site', 'institution', 'firm', 'specialty'] 
        GROUP BY type 
        ORDER BY type"
```

### Stage 2: Clinical Reference → reference scope

**Tables:** disorder (SNOMED), procedure (OPCS), medication (dm+d), allergy, drug, benefit, complication

**Verify:**
```bash
cbq -e "SELECT type, COUNT(*) as count 
        FROM openeyes._default.reference 
        WHERE type IN ['disorder', 'procedure', 'medication', 'allergy', 'drug'] 
        GROUP BY type 
        ORDER BY type"

# Check SNOMED codes preserved
cbq -e "SELECT COUNT(*) as total,
        COUNT(CASE WHEN snomed_code IS NOT NULL THEN 1 END) as with_snomed
        FROM openeyes._default.reference 
        WHERE type = 'disorder'"
```

### Stage 3: Core Clinical → clinical + admin scopes

**Tables:** patient, episode, event, contact (clinical); user (admin)

**Verify:**
```bash
# Clinical scope
cbq -e "SELECT type, COUNT(*) as count 
        FROM openeyes._default.clinical 
        WHERE type IN ['patient', 'episode', 'event', 'contact'] 
        GROUP BY type 
        ORDER BY type"

# Admin scope
cbq -e "SELECT type, COUNT(*) as count 
        FROM openeyes._default.admin 
        WHERE type = 'user'"

# Check embedded relations
cbq -e "SELECT 
        COUNT(*) as total_patients,
        COUNT(CASE WHEN contact IS NOT NULL THEN 1 END) as with_contact
        FROM openeyes._default.clinical 
        WHERE type = 'patient'"
```

### Stage 4: Module Elements → clinical scope

**Tables:** All 22 element types (examination, operation notes, laser, etc.)

**Verify:**
```bash
php protected/yiic moduledata status

# Or via N1QL
cbq -e "SELECT type, COUNT(*) as count 
        FROM openeyes._default.clinical 
        WHERE type LIKE 'Element_%' 
        GROUP BY type 
        ORDER BY type"
```

### Stage 5: Administrative → admin scope

**Tables:** audit, audit_type, audit_action, setting_*

**Verify:**
```bash
cbq -e "SELECT type, COUNT(*) as count 
        FROM openeyes._default.admin 
        WHERE type IN ['audit', 'audit_type', 'audit_action', 'setting_metadata'] 
        GROUP BY type 
        ORDER BY type"
```

---

## 🚀 Running the Migration

### Pre-Flight Checks

```bash
cd protected/scripts/couchbase

# Run pre-flight verification
./pre-migration-check.sh
```

**Must pass:**
- ✓ Couchbase connection
- ✓ MariaDB connection
- ✓ PHP extensions loaded
- ✓ Required model classes exist
- ✓ Disk space adequate
- ✓ Runtime directory writable

### Execute Migration

**Option 1: Automated (Recommended)**
```bash
./run-full-migration.sh --verbose
```

**Option 2: Manual Stage-by-Stage**
```bash
php protected/yiic fulldatamigration stage --stage=1 --verbose
php protected/yiic datavalidation counts

php protected/yiic fulldatamigration stage --stage=2 --verbose
php protected/yiic datavalidation counts

php protected/yiic fulldatamigration stage --stage=3 --batch=200 --verbose
php protected/yiic datavalidation samples --table=patient --sample=100

php protected/yiic fulldatamigration stage --stage=4 --verbose
php protected/yiic fulldatamigration stage --stage=5 --verbose

php protected/yiic datavalidation all --sample=500
```

**Option 3: Dry Run (Test)**
```bash
php protected/yiic fulldatamigration run --dryRun --verbose
```

---

## 📊 Monitoring During Migration

### Real-time Progress

```bash
# Terminal 1: Watch log file
tail -f protected/runtime/migration_*.log

# Terminal 2: Monitor Couchbase counts
watch -n 30 'cbq -e "SELECT type, COUNT(*) FROM openeyes._default.clinical GROUP BY type"'

# Terminal 3: Monitor checkpoint
watch -n 30 'cat protected/runtime/migration-checkpoint.json | jq'
```

### Quick Status Checks

```bash
# Migration status
php protected/yiic fulldatamigration status

# Count comparison
php protected/yiic datavalidation counts

# Recent writes
cbq -e "SELECT type, COUNT(*) as recent_count 
        FROM openeyes._default.clinical 
        WHERE created_date >= DATE_ADD_STR(NOW_STR(), -1, 'hour') 
        GROUP BY type"
```

---

## 🎯 Success Criteria

**Phase 14 Implementation:** ✅ COMPLETE
- [ ] All 15 files exist
- [ ] Scripts are executable
- [ ] Commands load without errors
- [ ] Quick test passes basic checks

**Migration Execution:** ⏳ PENDING
- [ ] All 5 stages complete
- [ ] Count validation 100% match
- [ ] Sample validation >99% match
- [ ] Referential integrity maintained
- [ ] Embedded relations present
- [ ] No orphaned records
- [ ] SNOMED/OPCS codes preserved

---

## 📝 Summary

### Current Status

- ✅ **Phase 14 Framework:** COMPLETE (all 15 files created)
- ✅ **Models Updated:** ~62 models with Couchbase traits
- ⏳ **Data Migration:** NOT YET EXECUTED
- ⏳ **Collections:** Empty (awaiting migration)

### To Verify Implementation

```bash
# Quick verification
cd protected/scripts/couchbase
./quick-test.sh

# Check files
ls -la protected/commands/FullDataMigrationCommand.php
ls -la protected/scripts/couchbase/*.sh
```

### To Verify Migration Success (After Running)

```bash
# Comprehensive validation
php protected/yiic datavalidation all --sample=500

# Check specific collections
cbq -e "SELECT type, COUNT(*) FROM openeyes._default.clinical GROUP BY type"
cbq -e "SELECT type, COUNT(*) FROM openeyes._default.reference GROUP BY type"
cbq -e "SELECT type, COUNT(*) FROM openeyes._default.admin GROUP BY type"
```

### Next Steps

1. ✅ Verify Phase 14 files exist (this guide)
2. ⏳ Run tests in full environment
3. ⏳ Execute pre-migration checks
4. ⏳ Backup MariaDB
5. ⏳ Run migration
6. ⏳ Validate results
7. ⏳ Monitor for 24-48 hours

---

**Document Version:** 1.0  
**Last Updated:** December 24, 2025  
**For:** Phase 14 Full Data Migration  
**Maintained By:** OpenEyes Development Team
