# Prescription Testing Guide - Fix Allergy & Element Errors

**Quick Fix Guide for:** "Patient allergy status is not known" and "Cannot create an event without at least one element"

---

## Problem Summary

You're seeing two errors when creating a prescription:

1. **"Patient allergy status is not known"** - Safety feature requiring allergy status before prescribing
2. **"Cannot create an event without at least one element"** - Must add at least one medication

---

## Solution 1: Set Patient Allergy Status

### Option A: Using Helper Script (Recommended for Testing)

```bash
# Navigate to OpenEyes directory
cd /Users/asahu/Desktop/OpenEyes/openeyes

# List available patients
php protected/scripts/set-patient-no-allergies.php

# Set no allergies for a patient (replace 1 with actual patient ID)
php protected/scripts/set-patient-no-allergies.php 1
```

### Option B: Using MySQL Direct (Quick)

```bash
# Access MySQL in Docker container
docker exec -it openeyes_db mysql -u root -p openeyes

# Or if running locally:
mysql -u root -p openeyes
```

Then run:

```sql
-- Find patients without allergy status
SELECT 
    p.id,
    p.hos_num,
    CONCAT(c.first_name, ' ', c.last_name) as name,
    p.no_allergies_date,
    CASE 
        WHEN p.no_allergies_date IS NOT NULL THEN 'No Allergies Set'
        WHEN (SELECT COUNT(*) FROM patient_allergy_assignment WHERE patient_id = p.id) > 0 THEN 'Has Allergies'
        ELSE 'Status Unknown'
    END as allergy_status
FROM patient p
JOIN contact c ON p.contact_id = c.id
LIMIT 10;

-- Set no allergies for a specific patient (replace 1 with patient ID)
UPDATE patient 
SET no_allergies_date = NOW() 
WHERE id = 1;

-- Verify it worked
SELECT id, hos_num, no_allergies_date 
FROM patient 
WHERE id = 1;
```

### Option C: Using OpenEyes UI (Production Method)

1. Navigate to patient record
2. Create or open an Examination event
3. Add "Allergies" element
4. Check "Patient has no allergies" box
5. Save the event

---

## Solution 2: Create Prescription Properly

### Step-by-Step Instructions

After setting allergy status:

#### 1. Open Prescription Creation

```
http://localhost/OphDrPrescription/default/create?patient_id=1
```
(Replace `1` with your patient ID)

#### 2. Add Medication

- Click **"+ Add medication"** button (don't skip this!)
- Search for a drug, e.g., "Chloramphenicol"
- Select from dropdown

#### 3. Fill in Details

| Field | Example Value |
|-------|---------------|
| **Dose** | 1 drop |
| **Route** | Eye |
| **Frequency** | 4 times a day |
| **Duration** | 7 days |

#### 4. Save

Click **"Save"** button

---

## Verification: Check Dual-Write

### Via Couchbase Query Workbench

1. Open: http://localhost:8091
2. Go to **Query** tab
3. Run:

```sql
SELECT * 
FROM openeyes.clinical.prescription_details 
ORDER BY created_date DESC 
LIMIT 1;
```

### Via Command Line

```bash
# Check if document exists
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT id, event_id, prescription_items FROM openeyes.clinical.prescription_details ORDER BY created_date DESC LIMIT 1"

# Count documents
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT COUNT(*) FROM openeyes.clinical.prescription_details"
```

### Verify Embedded Data

Check that prescription items are properly embedded:

```sql
SELECT 
    id,
    event_id,
    is_print_pending,
    is_authorization_pending,
    prescription_items
FROM openeyes.clinical.prescription_details 
ORDER BY created_date DESC 
LIMIT 1;
```

You should see:
- ✅ Document exists with same ID as MariaDB
- ✅ `prescription_items` array contains medication details
- ✅ Each item has medication, route, frequency, duration

---

## Alternative: Test Simpler Modules First

If prescriptions are too complex, test with modules that don't require allergy status:

### Biometry (Easiest)

```
http://localhost/OphInBiometry/default/create?patient_id=1
```

1. Click "Measurement" element
2. Enter values:
   - K1: 43.0
   - K2: 44.0
   - Axial Length: 23.5
3. Save

**Verify:**
```bash
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT * FROM openeyes.clinical.biometry_measurement LIMIT 1"
```

### Correspondence (Simple)

```
http://localhost/OphCoCorrespondence/default/create?patient_id=1
```

1. Select letter type
2. Enter letter content
3. Save

**Verify:**
```bash
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT * FROM openeyes.clinical.element_letter LIMIT 1"
```

---

## Troubleshooting

### Error: "Patient allergy status is not known"

**Cause:** Patient's `no_allergies_date` is NULL and has no allergies assigned

**Fix:**
```sql
UPDATE patient SET no_allergies_date = NOW() WHERE id = YOUR_PATIENT_ID;
```

### Error: "Cannot create an event without at least one element"

**Cause:** Trying to save prescription without adding any medications

**Fix:** Click "+ Add medication" and add at least one drug before saving

### Dual-write not working

**Checks:**

1. **Verify dual-write is enabled:**
   ```bash
   grep "dual_write" protected/config/couchbase.php
   # Should show: 'dual_write' => true,
   ```

2. **Check collections exist:**
   ```bash
   curl -s -u Administrator:password \
       "http://localhost:8091/pools/default/buckets/openeyes/scopes/clinical/collections" \
       | grep "prescription_details"
   ```

3. **Check logs:**
   ```bash
   tail -f protected/runtime/application.log | grep -i couchbase
   ```

---

## Quick Commands Reference

### Set No Allergies
```bash
# Using script
php protected/scripts/set-patient-no-allergies.php 1

# Using SQL
mysql -e "UPDATE patient SET no_allergies_date = NOW() WHERE id = 1;" openeyes
```

### Check Allergy Status
```sql
SELECT 
    id, 
    hos_num,
    no_allergies_date,
    (SELECT COUNT(*) FROM patient_allergy_assignment WHERE patient_id = p.id) as allergies
FROM patient p 
WHERE id = 1;
```

### Test URLs
```bash
PATIENT_ID=1

echo "Prescription: http://localhost/OphDrPrescription/default/create?patient_id=$PATIENT_ID"
echo "Biometry:     http://localhost/OphInBiometry/default/create?patient_id=$PATIENT_ID"
echo "Letter:       http://localhost/OphCoCorrespondence/default/create?patient_id=$PATIENT_ID"
```

### Verify in Couchbase
```bash
# Prescription
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT COUNT(*) FROM openeyes.clinical.prescription_details"

# Biometry  
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT COUNT(*) FROM openeyes.clinical.biometry_measurement"

# Letter
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT COUNT(*) FROM openeyes.clinical.element_letter"
```

---

## Summary Checklist

For successful prescription testing:

- [ ] Set patient allergy status (no_allergies_date)
- [ ] Open prescription creation URL
- [ ] Click "+ Add medication" button
- [ ] Select a drug from dropdown
- [ ] Fill in dose, route, frequency, duration
- [ ] Click "Save"
- [ ] Verify in MariaDB (check et_ophdrprescription_details table)
- [ ] Verify in Couchbase (query prescription_details collection)
- [ ] Check embedded prescription_items array

---

## Next Steps

1. **Set allergy status** for a test patient
2. **Create prescription** with at least one medication
3. **Verify dual-write** in Couchbase
4. **Test other modules** (biometry, correspondence, laser, etc.)
5. **Create N1QL indexes** when ready (see phase13-module-indexes.n1ql)

---

**Helper Script Created:** `protected/scripts/set-patient-no-allergies.php`

This script makes it easy to set allergy status for testing. Just run it to see available patients and set their status.

**For Production:** Always use the OpenEyes UI to record allergy status properly (via Examination > Allergies element).
