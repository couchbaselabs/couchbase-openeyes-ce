# Cypress E2E Test Report

**Date:** December 31, 2025  
**Environment:** localhost:7777 (Docker)  
**Cypress Version:** 12.17.4  
**Browser:** Electron 106 (headless)  
**Total Duration:** 32 minutes 35 seconds

---

## Executive Summary

| Metric | Value | Percentage |
|--------|-------|------------|
| **Total Specs** | 89 | 100% |
| **Passing Specs** | 2 | 2.2% |
| **Failing Specs** | 87 | 97.8% |
| **Total Tests** | 214 | 100% |
| **Passing Tests** | 8 | 3.7% |
| **Failing Tests** | 118 | 55.1% |
| **Skipped Tests** | 88 | 41.1% |

**Overall Status:** ❌ **MOSTLY FAILING** - Couchbase migration has broken test infrastructure

---

## Passing Specs (2)

| Spec | Tests | Duration | Notes |
|------|-------|----------|-------|
| ✅ `admin/common-ophthalmic-disorders.cy.js` | 2/2 | 20s | No seeders required |
| ✅ `admin/medication-routes.cy.js` | 3/3 | 25s | No seeders required |

**Partial Passing:**
| Spec | Passing | Total |
|------|---------|-------|
| `modules/cvi/admin/clinical-disorders.cy.js` | 1 | 4 |
| `admin/common-systemic-disorders.cy.js` | 1 | 2 |

---

## Failure Analysis by Category

### Category 1: Seeder Failures (Most Common)
**Root Cause:** Seeders use factory methods that execute complex SQL queries. The Couchbase migration lacks MariaDB fallback for these queries.

**Error Pattern:**
```
CypressError: `cy.request()` failed on:
http://localhost:7777/CypressHelper/Default/runSeeder
Status: 500 Internal Server Error
```

**Affected Specs:** ~60 specs

| Module | Failing Specs |
|--------|--------------|
| admin | 10 |
| modules/examination | 22 |
| modules/messaging | 7 |
| modules/consent | 3 |
| modules/cvi | 4 |
| modules/correspondence | 4 |
| worklist | 6 |
| patient | 2 |

### Category 2: HTTP 500 Errors (Page Load Failures)
**Root Cause:** Some admin pages throw errors due to missing database connections or queries.

**Affected Specs:**
- `admin/clinical-pathway-presets.cy.js`
- `admin/queue-sets.cy.js`
- `admin/setting-field-types.cy.js`

### Category 3: Missing Data/Collections
**Root Cause:** Some tests expect data that doesn't exist in Couchbase or collections not yet created.

---

## Detailed Failure Breakdown

### Admin Module (12 specs)
| Spec | Status | Failure Reason |
|------|--------|----------------|
| clinical-pathway-presets | ❌ | 500 error on page load |
| common-ophthalmic-disorders | ✅ | **PASS** |
| common-systemic-disorders | ⚠️ | 1/2 pass, seeder fails |
| dispense_locations | ❌ | Seeder fails |
| display_order | ❌ | Seeder fails |
| generic-event-subtypes | ❌ | Seeder fails |
| medication-routes | ✅ | **PASS** |
| post-op-complications | ❌ | Seeder fails |
| post-op-complication-assignments | ❌ | Seeder fails |
| queue-sets | ❌ | Seeder fails |
| setting-field-types | ❌ | Seeder fails |
| signature_import_log | ❌ | Seeder fails |

### Worklist Module (6 specs)
| Spec | Status | Failure Reason |
|------|--------|----------------|
| filtering | ❌ | Seeder fails |
| patient_summary_pathway | ❌ | Seeder fails |
| patient_pathway | ❌ | Seeder fails |
| 13839-verify-prescribe-behaviour | ❌ | Seeder fails |
| 14295-recent_filters | ❌ | Seeder fails |
| favourites | ❌ | Seeder fails |

### Examination Module (22 specs)
| Spec | Status | Tests |
|------|--------|-------|
| save_and_discard | ❌ | 0/4 |
| list_view_controls | ❌ | 0/2 |
| adderdialog/adder_dialog | ❌ | 0/1 |
| elements/visual_acuity | ❌ | 0/5 |
| elements/history | ❌ | 0/1 |
| elements/risks | ❌ | 0/2 |
| elements/pain_element | ❌ | 0/5 |
| elements/freehand_drawing | ❌ | 0/6 |
| + 14 more... | ❌ | All failing |

### Messaging Module (7 specs)
| Spec | Status | Tests |
|------|--------|-------|
| create | ❌ | 0/1 |
| dashboard | ❌ | 0/1 |
| edit | ❌ | 0/3 |
| mailbox-filtering-refactor | ❌ | 0/3 |
| personal-mailbox | ❌ | 0/1 |
| search-box-visibility | ❌ | 0/2 |
| shared-mailbox-functionality | ❌ | 0/4 |

---

## Root Cause Summary

### Primary Issue: No MariaDB Connection
The Couchbase migration removed MariaDB but tests require it for:
1. **Factory/Seeder operations** - Creating test data via complex JOINs
2. **CDbCriteria queries** - Many models use SQL features not in N1QL
3. **AuthAssignment queries** - Team model requires JOINs

### Secondary Issues
1. **Collection scope mismatches** - Some collections mapped incorrectly (fixed)
2. **RAND() function** - MySQL RAND() not converted to N1QL RANDOM() (fixed)
3. **UserAuthenticationMethod PK** - Non-standard primary key (fixed)

---

## Fixes Applied This Session

1. ✅ **CouchbaseDbCommand query builder** - Added select/from/join/where methods
2. ✅ **UserAuthenticationMethod.primaryKey()** - Returns 'code' instead of 'id'
3. ✅ **BaseActiveRecordVersioned** - Check hasAttribute('id') before setting
4. ✅ **RAND() conversion** - Both CouchbaseDbConnection and CouchbaseModelBridge
5. ✅ **Scope mappings** - Added 15+ admin collections to CouchbaseAdapter

---

## Recommendations

### Short Term (To Get Tests Passing)
1. **Re-enable MariaDB** for test environment
   - Add MariaDB service to docker-compose
   - Configure connection in test.php
   
2. **Create test data fixtures** in Couchbase
   - Pre-seed required data instead of using factories
   
### Long Term
1. **Refactor seeders** to not require JOINs
2. **Complete N1QL conversion** for all query patterns
3. **Create Couchbase-native factories**

---

## Test Results by Module

| Module | Specs | Pass | Fail | Pass Rate |
|--------|-------|------|------|-----------|
| admin | 12 | 2 | 10 | 16.7% |
| worklist | 6 | 0 | 6 | 0% |
| event | 1 | 0 | 1 | 0% |
| patient | 2 | 0 | 2 | 0% |
| homepage | 1 | 0 | 1 | 0% |
| advancedsearch | 1 | 0 | 1 | 0% |
| multitenancy | 1 | 0 | 1 | 0% |
| user/profile | 1 | 0 | 1 | 0% |
| couchbase | 3 | 0 | 3 | 0% |
| modules/examination | 22 | 0 | 22 | 0% |
| modules/messaging | 7 | 0 | 7 | 0% |
| modules/correspondence | 4 | 0 | 4 | 0% |
| modules/consent | 3 | 0 | 3 | 0% |
| modules/cvi | 5 | 1* | 4 | 20% |
| modules/operationnote | 2 | 0 | 2 | 0% |
| modules/operationbooking | 2 | 0 | 2 | 0% |
| modules/prescription | 2 | 0 | 2 | 0% |
| modules/psdpgd | 2 | 0 | 2 | 0% |
| modules/patientticketing | 2 | 0 | 2 | 0% |
| modules/phasing | 1 | 0 | 1 | 0% |
| modules/generic | 1 | 0 | 1 | 0% |
| modules/hotlist | 1 | 0 | 1 | 0% |
| modules/admin | 2 | 0 | 2 | 0% |

*Partial pass (1 of 4 tests)

---

## Conclusion

The E2E test suite is **not production-ready** due to the Couchbase migration breaking the test infrastructure. 

**Key Statistics:**
- **97.8% of specs failing** (87/89)
- **96.3% of tests failing or skipped** (206/214)
- **Only 5 tests passing** out of 214

**Primary Blocker:** MariaDB is required for test seeders but has been removed from the environment.

---

**Report Generated:** December 31, 2025  
**Test Duration:** 32m 35s  
**Environment:** Docker (Couchbase-only)
