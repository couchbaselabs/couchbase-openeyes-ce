# Phase 13: Setup Complete - Collections Created & Dual-Write Enabled ✅

**Date:** December 24, 2025  
**Status:** ✅ READY FOR TESTING

---

## What Was Completed

### 1. ✅ All 21 Collections Created in Couchbase

Successfully created **21 collections** in the `clinical` scope:

#### Operation Notes Module (6 collections)
- ✓ `operationnote_cataract`
- ✓ `operationnote_procedurelist`
- ✓ `operationnote_surgeon`
- ✓ `operationnote_anaesthetic`
- ✓ `operationnote_comments`
- ✓ `operationnote_generic`

#### Laser Treatment Module (4 collections)
- ✓ `laser_treatment`
- ✓ `laser_site`
- ✓ `laser_anteriorsegment`
- ✓ `laser_posteriorpole`

#### Biometry Module (3 collections)
- ✓ `biometry_measurement`
- ✓ `biometry_calculation`
- ✓ `biometry_selection`

#### Prescription Module (1 collection)
- ✓ `prescription_details`

#### Correspondence Module (1 collection)
- ✓ `element_letter`

#### Operation Booking Module (3 collections)
- ✓ `opbooking_operation`
- ✓ `opbooking_diagnosis`
- ✓ `opbooking_schedule`

#### CVI Module (3 collections)
- ✓ `cvi_eventinfo`
- ✓ `cvi_clinicalinfo`
- ✓ `cvi_clericalinfo`

### 2. ✅ Dual-Write Enabled

Updated `protected/config/couchbase.php`:

```php
'features' => [
    'enabled' => true,
    'dual_write' => true,  // ✅ NOW ENABLED
    'read_from_couchbase' => false,
],
```

### 3. ✅ Collection Creation Script Available

Created: `protected/scripts/couchbase/create-phase13-collections.sh`

This script can be re-run anytime to ensure collections exist:

```bash
bash protected/scripts/couchbase/create-phase13-collections.sh
```

---

## Verification

### View Collections in Couchbase Web UI

1. Open browser to: http://localhost:8091
2. Log in (Administrator / password)
3. Navigate to: **Buckets** → **openeyes** → **Scopes & Collections**
4. Click on **clinical** scope
5. You should see all 21 collections listed

### Via Command Line

```bash
# List all clinical collections
curl -s -u Administrator:password \
    "http://localhost:8091/pools/default/buckets/openeyes/scopes/clinical/collections" \
    | python3 -m json.tool

# Count collections
curl -s -u Administrator:password \
    "http://localhost:8091/pools/default/buckets/openeyes/scopes/clinical/collections" \
    | grep -o '"name"' | wc -l
```

Expected output: 21+ collections (including any existing ones)

---

## Testing the Dual-Write

### Test URLs by Module

Replace `{PATIENT_ID}` with an actual patient ID from your database:

```bash
# Get a patient ID for testing
mysql -e "SELECT id, hos_num FROM patient LIMIT 1;" openeyes
```

#### 1. Test Prescription (Simplest to test)
```
http://localhost/OphDrPrescription/default/create?patient_id={PATIENT_ID}
```

**Steps:**
1. Navigate to URL above
2. Add a medication
3. Click Save
4. Record should be in both MariaDB and Couchbase

**Verify in Couchbase:**
```bash
curl -u Administrator:password \
    "http://localhost:8091/query/service" \
    -d "statement=SELECT * FROM openeyes.clinical.prescription_details LIMIT 1"
```

#### 2. Test Operation Note
```
http://localhost/OphTrOperationnote/default/create?patient_id={PATIENT_ID}
```

#### 3. Test Laser Treatment
```
http://localhost/OphTrLaser/default/create?patient_id={PATIENT_ID}
```

#### 4. Test Biometry
```
http://localhost/OphInBiometry/default/create?patient_id={PATIENT_ID}
```

#### 5. Test Correspondence
```
http://localhost/OphCoCorrespondence/default/create?patient_id={PATIENT_ID}
```

#### 6. Test Operation Booking
```
http://localhost/OphTrOperationbooking/default/create?patient_id={PATIENT_ID}
```

#### 7. Test CVI
```
http://localhost/OphCoCvi/default/create?patient_id={PATIENT_ID}
```

---

## Troubleshooting

### Collections Not Showing Up

If collections don't appear:

```bash
# Re-run creation script
bash protected/scripts/couchbase/create-phase13-collections.sh

# Check Couchbase is running
curl -u Administrator:password http://localhost:8091/pools/default

# Verify clinical scope exists
curl -s -u Administrator:password \
    "http://localhost:8091/pools/default/buckets/openeyes/scopes" | grep clinical
```

### Dual-Write Not Working

If records aren't appearing in Couchbase:

1. **Check config:**
   ```bash
   grep "dual_write" protected/config/couchbase.php
   # Should show: 'dual_write' => true,
   ```

2. **Check logs:**
   ```bash
   tail -f protected/runtime/application.log | grep -i couchbase
   ```

3. **Verify PHP Couchbase extension:**
   ```bash
   php -m | grep couchbase
   ```

4. **Check model has trait:**
   ```bash
   grep -l "CouchbaseElementBridge" protected/modules/OphDrPrescription/models/*.php
   ```

### Verify a Specific Collection Exists

```bash
# Example: Check if prescription_details collection exists
curl -s -u Administrator:password \
    "http://localhost:8091/pools/default/buckets/openeyes/scopes/clinical/collections" \
    | grep "prescription_details"
```

---

## Next Steps

### Immediate Testing (Today)

1. ✅ **Create Test Record** - Try creating a prescription through UI
2. ✅ **Verify in MariaDB** - Check the record exists in MySQL
3. ✅ **Verify in Couchbase** - Check the record exists in Couchbase
4. ✅ **Check Embedded Relations** - Verify medication details are embedded

### Short-term (This Week)

1. **Test All Modules** - Create at least one record in each of the 7 modules
2. **Verify Embedded Data** - Check that SNOMED codes, relations are properly embedded
3. **Monitor Performance** - Ensure no slowdown from dual-write

### Medium-term (Next Week)

1. **Create Indexes** - Run the N1QL indexes script:
   ```bash
   # Via Couchbase Query Workbench or cbq CLI
   cbq -f protected/scripts/couchbase/phase13-module-indexes.n1ql
   ```

2. **Migration** - Migrate historical data:
   ```bash
   php protected/yiic moduledata migrate --module=prescription --batch=100
   php protected/yiic moduledata migrate --module=biometry --batch=100
   # ... continue for other modules
   ```

3. **Production Deployment** - Follow PHASE-13-READY-FOR-PRODUCTION.md

---

## Quick Reference

### All Testing URLs

```bash
# Replace {PATIENT_ID} with actual ID
PATIENT_ID=123

echo "Operation Notes:     http://localhost/OphTrOperationnote/default/create?patient_id=$PATIENT_ID"
echo "Laser Treatment:     http://localhost/OphTrLaser/default/create?patient_id=$PATIENT_ID"
echo "Biometry:           http://localhost/OphInBiometry/default/create?patient_id=$PATIENT_ID"
echo "Prescription:       http://localhost/OphDrPrescription/default/create?patient_id=$PATIENT_ID"
echo "Correspondence:     http://localhost/OphCoCorrespondence/default/create?patient_id=$PATIENT_ID"
echo "Operation Booking:  http://localhost/OphTrOperationbooking/default/create?patient_id=$PATIENT_ID"
echo "CVI:               http://localhost/OphCoCvi/default/create?patient_id=$PATIENT_ID"
```

### Verification Commands

```bash
# Count documents in a collection
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT COUNT(*) FROM openeyes.clinical.prescription_details"

# View recent documents
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT * FROM openeyes.clinical.prescription_details ORDER BY created_date DESC LIMIT 5"

# Check all Phase 13 collections
for coll in prescription_details laser_treatment biometry_measurement opbooking_operation cvi_eventinfo; do
    echo "Checking: $coll"
    curl -s -u Administrator:password "http://localhost:8091/query/service" \
        -d "statement=SELECT COUNT(*) FROM openeyes.clinical.$coll"
done
```

---

## Summary

✅ **All 21 Phase 13 collections created**  
✅ **Dual-write enabled**  
✅ **Configuration updated**  
✅ **Script available for re-running**  
✅ **Ready for UI testing**

**Status:** Phase 13 infrastructure is 100% complete and ready for testing!

---

## Files Created/Modified

### New Files
- `protected/scripts/couchbase/create-phase13-collections.sh` - Collection creation script

### Modified Files
- `protected/config/couchbase.php` - Enabled dual_write flag

### Previously Completed (Phase 13)
- 22 model files updated with CouchbaseElementBridge trait
- `protected/commands/ModuleMigrationCommand.php` created
- `protected/scripts/couchbase/phase13-module-indexes.n1ql` created
- `protected/config/couchbase-collections.php` updated

---

**Next Action:** Test creating a prescription record through the UI and verify it appears in Couchbase!

For detailed testing instructions, see: **PHASE-13-TESTING-GUIDE.md**

---

**Generated:** December 24, 2025  
**By:** OpenEyes Phase 13 Implementation
