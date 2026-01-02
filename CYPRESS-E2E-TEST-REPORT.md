# Cypress E2E Test Report

**Date:** December 31, 2025  
**Environment:** localhost:7777 (Docker)  
**Cypress Version:** 12.17.4  
**Browser:** Electron 106 (headless)  

---

## Executive Summary

| Metric | Value |
|--------|-------|
| **Total Specs Found** | 89 |
| **Specs Executed** | 63 (before timeout) |
| **Specs Remaining** | 26 |
| **Execution Time** | 40 minutes (timed out) |

**Overall Status:** ⚠️ **PARTIAL - Many Failures Due to Application Errors**

---

## Root Cause Analysis

Most test failures are **NOT due to test issues** but due to **application/backend errors** related to the Couchbase migration:

### Primary Failure Causes

1. **`CouchbaseDbCommand::select()` not defined** (Critical)
   - Many tests fail because the Couchbase database adapter is missing the `select()` method
   - Affects: Team model, seeders, and factory methods

2. **`UserAuthenticationMethod.id` property not defined** (Critical)
   - The model factory cannot save UserAuthenticationMethod records
   - Affects: All tests using seeders that create institutions/users

3. **500 Internal Server Errors**
   - Various admin pages returning HTTP 500
   - Related to Couchbase integration issues

4. **Seeder Failures**
   - Most seeders fail when trying to create test data
   - The `CypressHelper/Default/runSeeder` endpoint returns 500 errors

---

## Detailed Results by Category

### ✅ Passing Tests (Confirmed)

| Spec File | Tests | Status |
|-----------|-------|--------|
| `admin/common-ophthalmic-disorders.cy.js` | 2 | ✅ PASS |
| `admin/medication-routes.cy.js` | 3 | ✅ PASS |
| `modules/messaging/create.cy.js` | 1 | ✅ PASS |
| `modules/messaging/dashboard.cy.js` | 3 | ✅ PASS |
| `modules/messaging/edit.cy.js` | 3 | ✅ PASS |
| `modules/messaging/mailbox-filtering-refactor.cy.js` | 4 | ✅ PASS |
| `modules/messaging/personal-mailbox.cy.js` | 1 | ✅ PASS |
| `modules/messaging/search-box-visibility.cy.js` | 2 | ✅ PASS |
| `modules/messaging/shared-mailbox-functionality.cy.js` | 5 | ✅ PASS |
| `modules/hotlist/14048-prompt-redirect-confirmation.cy.js` | 2 | ✅ PASS |
| `modules/examination/list_view_controls.cy.js` | 1 | ✅ PASS |
| `modules/examination/save_and_discard.cy.js` | 5 | ✅ PASS |
| `modules/examination/adderdialog/adder_dialog.cy.js` | 1 | ✅ PASS |

**Estimated Passing:** ~35+ tests

---

### ❌ Failing Tests

#### Admin Module Failures

| Spec File | Failure Reason |
|-----------|----------------|
| `admin/clinical-pathway-presets.cy.js` | 500 Error on `/Admin/worklist/presetPathways` |
| `admin/common-systemic-disorders.cy.js` | Seeder fails: `UserAuthenticationMethod.id` not defined |
| `admin/dispense_locations.cy.js` | Seeder fails: `UserAuthenticationMethod.id` not defined |
| `admin/display_order.cy.js` | Seeder fails |
| `admin/generic-event-subtypes.cy.js` | Seeder fails |
| `admin/post-op-complications.cy.js` | Seeder fails |
| `admin/post-op-complication-assignments.cy.js` | Seeder fails |
| `admin/queue-sets.cy.js` | Seeder fails |
| `admin/setting-field-types.cy.js` | Seeder fails |
| `admin/signature_import_log.cy.js` | Seeder fails |

#### Patient Module Failures

| Spec File | Failure Reason |
|-----------|----------------|
| `patient/summary.cy.js` | Seeder fails |
| `patient/add-patient-patient-identifiers.cy.js` | Seeder fails |

#### Other Module Failures

| Spec File | Failure Reason |
|-----------|----------------|
| `advancedsearch/save-search.cy.js` | Seeder fails |
| `homepage/13336-show-search-examples-popup.cy.js` | Page error |
| `multitenancy/test_assignments.cy.js` | Seeder fails |
| `modules/psdpgd/inactive_team.cy.js` | `CouchbaseDbCommand::select()` not defined |
| `modules/psdpgd/pending_deletion.cy.js` | Seeder fails |

---

## Error Details

### Error 1: CouchbaseDbCommand::select() Missing

```
Call to undefined method CouchbaseDbCommand::select()
File: /var/www/openeyes/protected/models/Team.php, line 279
```

**Impact:** Any test involving Team model operations fails.

### Error 2: UserAuthenticationMethod Property Missing

```
Property "UserAuthenticationMethod.id" is not defined
File: /var/www/openeyes/vendor/yiisoft/yii/framework/db/ar/CActiveRecord.php
```

**Impact:** All factory/seeder operations creating institutions fail.

### Error 3: HTTP 500 on Admin Pages

```
http://localhost:7777/Admin/worklist/presetPathways
Response: 500 Internal Server Error
```

**Impact:** Tests cannot load admin pages.

---

## Screenshots Captured (Failures)

The following failure screenshots were captured in `cypress/screenshots/`:

- 48+ failure screenshots across various tests
- Most show application error pages
- Located in: `/cypress/screenshots/{module}/{test}.cy.js/`

---

## Specs Not Executed (Due to Timeout)

The following 26 specs did not run due to the 40-minute timeout:

1. `modules/psdpgd/pending_deletion.cy.js` (partial)
2. `modules/phasing/save.cy.js`
3. `modules/cvi/admin/clinical-disorders.cy.js`
4. `modules/examination/eyedraw/*.cy.js` (2 specs)
5. `modules/examination/elements/*.cy.js` (~20 specs)
6. `modules/examination/esign/*.cy.js`
7. `user/profile/sites.cy.js`

---

## Recommendations

### Immediate Actions Required

1. **Fix `CouchbaseDbCommand::select()` method**
   - Add the missing `select()` method to `CouchbaseDbCommand` class
   - Or ensure MariaDB is used for these operations during tests

2. **Fix `UserAuthenticationMethod` model**
   - Ensure the model has proper `id` property defined
   - Check if Couchbase migration broke the model schema

3. **Disable Couchbase for test environment**
   - Consider using MariaDB-only mode for Cypress tests
   - Or fix all Couchbase adapter methods

### Test Infrastructure

4. **Run tests in parallel** to reduce execution time
5. **Configure test-specific database** that doesn't have Couchbase issues
6. **Add retry logic** for flaky network/server errors

---

## Summary Statistics

| Category | Count |
|----------|-------|
| Total Specs | 89 |
| Executed | 63 |
| Estimated Passing | ~35 |
| Estimated Failing | ~28 |
| Not Executed | 26 |
| **Pass Rate (of executed)** | **~55%** |

---

## Conclusion

The E2E test suite is **significantly impacted by Couchbase migration issues**. The core application functionality appears broken for:
- Factory/seeder operations
- Team model operations  
- Admin page routing

**Before meaningful E2E testing can occur, the Couchbase integration bugs must be fixed.**

---

**Report Generated:** December 31, 2025  
**Test Runner:** Cypress 12.17.4  
**Timeout:** 40 minutes (2400 seconds)
