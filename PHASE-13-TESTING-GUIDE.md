# Phase 13: Clinical Module Elements - Testing Guide

**Version:** 1.0  
**Last Updated:** December 24, 2025  
**Status:** Ready for Testing

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Testing Environment Setup](#testing-environment-setup)
3. [Module-by-Module Testing](#module-by-module-testing)
4. [Automated Testing](#automated-testing)
5. [Data Validation](#data-validation)
6. [Performance Testing](#performance-testing)
7. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Tools
- ✅ OpenEyes application installed
- ✅ MariaDB database running
- ✅ Couchbase Server 7.x+ running
- ✅ PHP 7.4+ with Couchbase extension
- ✅ Access to Couchbase Query Workbench
- ✅ PHPUnit for unit tests

### Configuration Check
```bash
# Verify Couchbase connection
php protected/yiic checkconfig couchbase

# Check dual-write configuration
./verify-dual-write-config.sh

# Verify all 22 models have CouchbaseElementBridge
grep -r "use.*CouchbaseElementBridge" protected/modules/*/models/Element_*.php
```

---

## Testing Environment Setup

### 1. Create Couchbase Indexes

```bash
# Navigate to Couchbase Query Workbench or use cbq CLI
cbq -f protected/scripts/couchbase/phase13-module-indexes.n1ql

# Verify indexes created
cbq -e "SELECT * FROM system:indexes WHERE keyspace_id = 'openeyes' AND name LIKE 'idx_%';"
```

### 2. Set Up Test Database

```sql
-- Create test data or use existing test patient
-- Ensure you have:
-- - At least 1 test patient
-- - At least 1 test user with clinical access
-- - Necessary reference data (procedures, medications, etc.)
```

### 3. Enable Verbose Logging (Optional)

```php
// In protected/config/local/common.php
'components' => [
    'log' => [
        'routes' => [
            'couchbase' => [
                'class' => 'CFileLogRoute',
                'levels' => 'trace, info, error, warning',
                'categories' => 'application.couchbase.*',
                'logFile' => 'couchbase-dual-write.log',
            ],
        ],
    ],
],
```

---

## Module-by-Module Testing

### 1. Operation Notes Module Testing

#### Test 1.1: Cataract Procedure
**Objective:** Verify dual-write for cataract surgery records

**Steps:**
1. Log into OpenEyes as a surgeon
2. Navigate to existing patient
3. Create new Operation Note event
4. Add Cataract element with:
   - IOL type
   - Incision details
   - Complications (if any)
   - Operative devices
5. Save the event

**Verification:**
```sql
-- Check MariaDB
SELECT * FROM et_ophtroperationnote_cataract ORDER BY id DESC LIMIT 1;
```

```n1ql
-- Check Couchbase
SELECT * FROM openeyes._default.Element_OphTrOperationnote_Cataract 
WHERE type = 'Element_OphTrOperationnote_Cataract' 
ORDER BY created_date DESC 
LIMIT 1;
```

**Expected Results:**
- ✅ Record exists in MariaDB
- ✅ Record exists in Couchbase with same ID
- ✅ IOL type embedded with id and name
- ✅ Complications array properly formatted
- ✅ All IDs are integers

#### Test 1.2: Procedure List with SNOMED Codes
**Objective:** Verify procedures array with SNOMED codes

**Steps:**
1. In same Operation Note event
2. Add Procedure List element
3. Select multiple procedures (e.g., "Phacoemulsification", "IOL insertion")
4. Save

**Verification:**
```n1ql
SELECT procedures, eye 
FROM openeyes._default.Element_OphTrOperationnote_ProcedureList 
WHERE type = 'Element_OphTrOperationnote_ProcedureList' 
ORDER BY created_date DESC 
LIMIT 1;
```

**Expected Results:**
- ✅ Procedures array contains all selected procedures
- ✅ Each procedure has: id, term, short_format, snomed_code
- ✅ SNOMED codes are present and valid
- ✅ Eye embedded with id and name

#### Test 1.3: Surgeon Details
**Steps:**
1. Add Surgeon element
2. Select surgeon, assistant, supervising surgeon
3. Save

**Expected Results:**
- ✅ All surgeon roles embedded with user details
- ✅ Full names included

#### Test 1.4-1.6: Anaesthetic, Comments, Generic Procedure
**Repeat similar testing patterns for remaining elements**

---

### 2. Laser Treatment Module Testing

#### Test 2.1: Laser Treatment
**Steps:**
1. Create new Laser Treatment event
2. Add Treatment element
3. Select laser type, site, procedures
4. Add operator details
5. Save

**Verification:**
```n1ql
SELECT laser, site, eye, procedures, operator 
FROM openeyes._default.Element_OphTrLaser_Treatment 
WHERE type = 'Element_OphTrLaser_Treatment' 
ORDER BY created_date DESC 
LIMIT 1;
```

**Expected Results:**
- ✅ Laser embedded with id, name, type
- ✅ Site embedded correctly
- ✅ Procedures array with SNOMED codes
- ✅ Operator details embedded

---

### 3. Biometry Module Testing

#### Test 3.1: Measurements
**Steps:**
1. Create Biometry event
2. Add Measurement element
3. Enter K readings, axial length for both eyes
4. Save

**Verification:**
```n1ql
SELECT eye, axial_length_left, axial_length_right, k_readings 
FROM openeyes._default.Element_OphInBiometry_Measurement 
WHERE type = 'Element_OphInBiometry_Measurement' 
ORDER BY created_date DESC 
LIMIT 1;
```

**Expected Results:**
- ✅ All measurements saved
- ✅ Eye embedded
- ✅ Numeric values properly formatted

#### Test 3.2: IOL Calculations
**Steps:**
1. Add Calculation element
2. Select IOL formula
3. View calculated IOL powers
4. Save

**Expected Results:**
- ✅ Calculations array with formula details
- ✅ IOL powers properly embedded

#### Test 3.3: IOL Selection
**Steps:**
1. Add Selection element
2. Select recommended IOL
3. Save

**Expected Results:**
- ✅ IOL type embedded with full details

---

### 4. Prescription Module Testing

#### Test 4.1: Create Prescription
**Steps:**
1. Create Prescription event
2. Add prescription items (multiple medications)
3. Select routes, frequencies, durations
4. Save

**Verification:**
```n1ql
SELECT prescription_items, is_print_pending, is_authorization_pending 
FROM openeyes._default.Element_OphDrPrescription_Details 
WHERE type = 'Element_OphDrPrescription_Details' 
ORDER BY created_date DESC 
LIMIT 1;
```

**Expected Results:**
- ✅ Prescription items array complete
- ✅ Each item has medication, route, frequency, duration
- ✅ Status flags properly set (print_pending, authorization_pending)

---

### 5. Correspondence Module Testing

#### Test 5.1: Generate Letter
**Steps:**
1. Create Correspondence event
2. Select letter type
3. Add content, enclosures
4. Create internal referral (optional)
5. Save

**Verification:**
```n1ql
SELECT letter_type, site, enclosures, internal_referral, is_signed_off 
FROM openeyes._default.ElementLetter 
WHERE type = 'ElementLetter' 
ORDER BY created_date DESC 
LIMIT 1;
```

**Expected Results:**
- ✅ Letter type embedded
- ✅ Site embedded
- ✅ Enclosures array present
- ✅ Internal referral details (if applicable)
- ✅ Status flags correct

---

### 6. Operation Booking Module Testing

#### Test 6.1: Create Operation Booking
**Steps:**
1. Create Operation Booking event
2. Add Operation element with:
   - Eye
   - Procedures
   - Anaesthetic types
   - Priority
   - Site
3. Save

**Verification:**
```n1ql
SELECT eye, procedures, anaesthetic_types, priority, status, booking 
FROM openeyes._default.Element_OphTrOperationbooking_Operation 
WHERE type = 'Element_OphTrOperationbooking_Operation' 
ORDER BY created_date DESC 
LIMIT 1;
```

**Expected Results:**
- ✅ Eye embedded
- ✅ Procedures array with SNOMED codes
- ✅ Anaesthetic types array
- ✅ Priority embedded
- ✅ Status embedded
- ✅ Booking details (if scheduled)
- ✅ Complexity with value and caption

#### Test 6.2: Add Diagnosis
**Steps:**
1. Add Diagnosis element
2. Select eye and disorder
3. Save

**Expected Results:**
- ✅ Eye embedded
- ✅ Disorder embedded with term and fully_specified_name

#### Test 6.3: Schedule Operation
**Steps:**
1. Add Schedule Operation element
2. Select schedule options
3. Add patient unavailable periods
4. Save

**Expected Results:**
- ✅ Schedule options embedded
- ✅ Patient unavailables array with dates and reasons

---

### 7. CVI Module Testing

#### Test 7.1: CVI Event Info
**Steps:**
1. Create CVI event
2. Add Event Info element
3. Select site, consultant
4. Set delivery options (GP, LA, RCO)
5. Save

**Verification:**
```n1ql
SELECT site, consultant_in_charge, is_draft, gp_delivery, la_delivery, rco_delivery 
FROM openeyes._default.Element_OphCoCvi_EventInfo 
WHERE type = 'Element_OphCoCvi_EventInfo' 
ORDER BY created_date DESC 
LIMIT 1;
```

**Expected Results:**
- ✅ Site embedded
- ✅ Consultant embedded with full name
- ✅ Draft status boolean
- ✅ Delivery statuses with enabled flag and status

#### Test 7.2: Clinical Info
**Steps:**
1. Add Clinical Info element
2. Enter examination date
3. Set blind status
4. Select consultant
5. Save

**Expected Results:**
- ✅ Consultant embedded
- ✅ is_considered_blind boolean
- ✅ examination_date present

#### Test 7.3: Clerical Info
**Steps:**
1. Add Clerical Info element
2. Enter employment status
3. Select preferred language
4. Set contact urgency
5. Save

**Expected Results:**
- ✅ Employment status embedded
- ✅ Preferred language embedded
- ✅ Contact urgency embedded

---

## Automated Testing

### Unit Tests

Create unit tests for all 22 models:

```bash
# Run all Phase 13 unit tests
./protected/vendor/bin/phpunit --testsuite Phase13

# Run specific module tests
./protected/vendor/bin/phpunit protected/tests/unit/modules/OphTrOperationnote/
./protected/vendor/bin/phpunit protected/tests/unit/modules/OphTrLaser/
./protected/vendor/bin/phpunit protected/tests/unit/modules/OphInBiometry/
./protected/vendor/bin/phpunit protected/tests/unit/modules/OphDrPrescription/
./protected/vendor/bin/phpunit protected/tests/unit/modules/OphCoCorrespondence/
./protected/vendor/bin/phpunit protected/tests/unit/modules/OphTrOperationbooking/
./protected/vendor/bin/phpunit protected/tests/unit/modules/OphCoCvi/
```

### Migration Tests

```bash
# Test migration command
php protected/yiic moduledata status

# Dry run migration for one module
php protected/yiic moduledata migrate --module=operationnote --dryRun

# Migrate small batch
php protected/yiic moduledata migrate --module=operationnote --batch=10
```

---

## Data Validation

### Validation Script

```bash
# Verify data consistency
php protected/yiic moduledata verify --module=all --sample=10

# Count records
php protected/yiic moduledata count --module=all
```

### Manual Validation Queries

```n1ql
-- Verify all 22 element types exist
SELECT type, COUNT(*) as count 
FROM openeyes._default.clinical 
WHERE type IN [
    'Element_OphTrOperationnote_Cataract',
    'Element_OphTrOperationnote_ProcedureList',
    'Element_OphTrOperationnote_Surgeon',
    'Element_OphTrOperationnote_Anaesthetic',
    'Element_OphTrOperationnote_Comments',
    'Element_OphTrOperationnote_GenericProcedure',
    'Element_OphTrLaser_Treatment',
    'Element_OphTrLaser_Site',
    'Element_OphTrLaser_AnteriorSegment',
    'Element_OphTrLaser_PosteriorPole',
    'Element_OphInBiometry_Measurement',
    'Element_OphInBiometry_Calculation',
    'Element_OphInBiometry_Selection',
    'Element_OphDrPrescription_Details',
    'ElementLetter',
    'Element_OphTrOperationbooking_Operation',
    'Element_OphTrOperationbooking_Diagnosis',
    'Element_OphTrOperationbooking_ScheduleOperation',
    'Element_OphCoCvi_EventInfo',
    'Element_OphCoCvi_ClinicalInfo',
    'Element_OphCoCvi_ClericalInfo'
]
GROUP BY type
ORDER BY type;

-- Check for records with missing embedded relations
SELECT id, type, event_id 
FROM openeyes._default.clinical 
WHERE type = 'Element_OphTrOperationnote_Cataract' 
AND iol_type IS NULL 
LIMIT 10;

-- Verify SNOMED codes present
SELECT id, procedures 
FROM openeyes._default.Element_OphTrOperationnote_ProcedureList 
WHERE type = 'Element_OphTrOperationnote_ProcedureList' 
AND ARRAY_LENGTH(procedures) > 0
LIMIT 5;
```

---

## Performance Testing

### Benchmark Dual-Write Performance

```bash
# Create performance test script
cat > test-phase13-performance.sh << 'EOF'
#!/bin/bash

echo "Phase 13 Dual-Write Performance Test"
echo "====================================="

# Test cataract record creation
start=$(date +%s%N)
for i in {1..100}; do
    # Create cataract record via API or CLI
    php protected/yiic testdualwrite createCataract
done
end=$(date +%s%N)
duration=$((($end-$start)/1000000))
echo "100 cataract records: ${duration}ms (avg: $((duration/100))ms each)"

# Test laser treatment creation
start=$(date +%s%N)
for i in {1..100}; do
    php protected/yiic testdualwrite createLaser
done
end=$(date +%s%N)
duration=$((($end-$start)/1000000))
echo "100 laser records: ${duration}ms (avg: $((duration/100))ms each)"

EOF

chmod +x test-phase13-performance.sh
./test-phase13-performance.sh
```

### Expected Performance
- Single record dual-write: < 100ms
- Batch migration (100 records): < 10 seconds
- No significant impact on UI responsiveness

---

## Troubleshooting

### Common Issues

#### 1. Record Not Appearing in Couchbase
**Symptoms:** Record saved to MariaDB but not in Couchbase

**Checks:**
```bash
# Check Couchbase connection
php protected/yiic checkconfig couchbase

# Check logs
tail -f protected/runtime/application.log | grep -i couchbase

# Verify trait is loaded
grep "CouchbaseElementBridge" protected/modules/OphTrOperationnote/models/Element_*.php
```

**Solutions:**
- Verify Couchbase server is running
- Check network connectivity
- Ensure PHP Couchbase extension loaded: `php -m | grep couchbase`
- Review error logs for exceptions

#### 2. Missing Embedded Relations
**Symptoms:** Record exists but embedded data is null

**Checks:**
```n1ql
-- Check specific record
SELECT * FROM openeyes._default.Element_OphTrOperationnote_Cataract 
WHERE id = 'RECORD_ID';
```

**Solutions:**
- Verify relations loaded before save
- Check getEmbeddedRelations() method implementation
- Ensure foreign key data exists in MariaDB

#### 3. Type Mismatch Errors
**Symptoms:** IDs stored as strings instead of integers

**Solution:**
- All IDs should be cast: `(int)$this->id`
- Review getEmbeddedRelations() implementation
- Re-save affected records

#### 4. Slow Performance
**Symptoms:** UI becomes slow after Phase 13 implementation

**Checks:**
```bash
# Check Couchbase query performance
cbq -e "EXPLAIN SELECT * FROM openeyes._default.Element_OphTrOperationnote_Cataract WHERE event_id = 'EVENT_ID';"
```

**Solutions:**
- Verify indexes created (see Setup section)
- Check Couchbase server resources
- Consider async write queue for non-critical writes

---

## Testing Checklist

### Pre-Testing
- [ ] Couchbase server running
- [ ] Indexes created
- [ ] Configuration verified
- [ ] Test data prepared

### Per-Module Testing
- [ ] Create new record
- [ ] Verify MariaDB write
- [ ] Verify Couchbase write
- [ ] Check embedded relations
- [ ] Verify SNOMED codes (if applicable)
- [ ] Check status flags
- [ ] Verify all IDs are integers

### Post-Testing
- [ ] Run automated tests
- [ ] Verify data counts match
- [ ] Check performance metrics
- [ ] Review error logs
- [ ] Document any issues

---

## Next Steps After Testing

1. **If all tests pass:**
   - Proceed with historical data migration
   - Schedule production deployment
   - Create rollback plan

2. **If issues found:**
   - Document issues in PHASE-13-ISSUES.md
   - Fix and re-test
   - Update this guide with lessons learned

3. **Production Deployment:**
   - See PHASE-13-READY-FOR-PRODUCTION.md
   - Follow staged rollout plan
   - Monitor closely for first 48 hours

---

**Document Version:** 1.0  
**Last Updated:** December 24, 2025  
**Maintained By:** OpenEyes Development Team
