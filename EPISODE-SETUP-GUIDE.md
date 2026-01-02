# Episode Setup Guide - Fix "episode is null" Error

## Problem

You're seeing this error:
```
PHP warning: Attempt to read property "id" on null
$this->event->episode_id = $this->episode->id;
```

**Root Cause:** Patient 13 doesn't have an **episode** for the specialty.

In OpenEyes:
- Every clinical event (Biometry, Prescription, etc.) needs an **Episode**
- Episodes link patients to a specialty/subspecialty via a **Firm** (consultant practice)
- Without an episode, you can't create clinical events

---

## Solution: Create Episode for Patient 13

### **Method 1: Using SQL Script (Fastest)**

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes

# Docker:
docker exec -it openeyes_db mysql -u root -p openeyes < create-episode-for-patient-13.sql

# Local MySQL:
mysql -u root -p openeyes < create-episode-for-patient-13.sql
```

### **Method 2: Manual SQL**

```sql
-- Connect to database
docker exec -it openeyes_db mysql -u root -p openeyes

-- Then run:

-- Check if patient has episodes
SELECT * FROM episode WHERE patient_id = 13;

-- If no episodes exist, create one:
INSERT INTO episode (
    patient_id,
    firm_id,
    start_date,
    episode_status_id,
    last_modified_user_id,
    created_user_id,
    last_modified_date,
    created_date
)
SELECT 
    13,
    (SELECT id FROM firm WHERE active = 1 LIMIT 1),
    CURDATE(),
    1,
    1,
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (SELECT 1 FROM episode WHERE patient_id = 13);

-- Verify:
SELECT 
    e.id,
    e.patient_id,
    e.firm_id,
    f.name as firm_name
FROM episode e
LEFT JOIN firm f ON e.firm_id = f.id
WHERE e.patient_id = 13;
```

### **Method 3: Using OpenEyes UI (Production)**

1. Navigate to patient 13's record
2. The system should automatically create an episode when you:
   - Select a firm/consultant from the dropdown
   - Create your first clinical event
3. Or manually create an episode via the Episodes section

---

## Complete Setup for Patient 13

Here's the full checklist:

### **Step 1: Set Allergy Status** ✅ (Already done)
```sql
UPDATE patient SET no_allergies_date = NOW() WHERE id = 13;
```

### **Step 2: Create Episode** ⏳ (Do this now)
```sql
INSERT INTO episode (patient_id, firm_id, start_date, episode_status_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
SELECT 13, (SELECT id FROM firm WHERE active = 1 LIMIT 1), CURDATE(), 1, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM episode WHERE patient_id = 13);
```

### **Step 3: Verify Setup**
```sql
-- Check patient has both allergy status and episode
SELECT 
    p.id,
    p.hos_num,
    p.no_allergies_date,
    (SELECT COUNT(*) FROM episode WHERE patient_id = p.id) as episode_count,
    CASE 
        WHEN p.no_allergies_date IS NULL THEN '❌ Missing allergy status'
        WHEN (SELECT COUNT(*) FROM episode WHERE patient_id = p.id) = 0 THEN '❌ Missing episode'
        ELSE '✓ Ready for events'
    END as status
FROM patient p
WHERE p.id = 13;
```

Expected output:
```
id | hos_num | no_allergies_date        | episode_count | status
13 | ...     | 2025-12-24 07:30:00      | 1             | ✓ Ready for events
```

---

## Testing After Setup

Once both allergy status AND episode are set:

### **Test 1: Biometry**
```
http://localhost:7777/OphInBiometry/default/create?patient_id=13
```

Should now work without errors!

1. Add "Measurement" element
2. Fill in K1: 43.0, K2: 44.0, Axial Length: 23.5
3. Click "Save"

### **Test 2: Prescription**
```
http://localhost:7777/OphDrPrescription/default/create?patient_id=13
```

1. Click "+ Add medication"
2. Select drug
3. Fill details
4. Click "Save"

### **Test 3: Correspondence**
```
http://localhost:7777/OphCoCorrespondence/default/create?patient_id=13
```

1. Select letter type
2. Enter content
3. Click "Save"

---

## Verify Dual-Write in Couchbase

After successfully creating a record:

```bash
# Check Biometry
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT * FROM openeyes.clinical.biometry_measurement WHERE event_id IN (SELECT id FROM openeyes.core.event WHERE episode_id IN (SELECT id FROM openeyes.core.episode WHERE patient_id = 13)) LIMIT 1"

# Or simpler - just check latest:
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT * FROM openeyes.clinical.biometry_measurement ORDER BY created_date DESC LIMIT 1"

# Check Prescription
curl -u Administrator:password "http://localhost:8091/query/service" \
    -d "statement=SELECT * FROM openeyes.clinical.prescription_details ORDER BY created_date DESC LIMIT 1"
```

---

## Troubleshooting

### Issue: "No firm available"

If you see errors about missing firms:

```sql
-- Check available firms
SELECT id, name, active FROM firm WHERE active = 1;

-- If none exist, create a default firm:
INSERT INTO firm (name, active, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES ('General Ophthalmology', 1, 1, 1, NOW(), NOW());
```

### Issue: Still getting episode error after creating episode

```sql
-- Verify episode exists and is valid
SELECT 
    e.*,
    f.name as firm_name,
    f.active as firm_active
FROM episode e
LEFT JOIN firm f ON e.firm_id = f.id
WHERE e.patient_id = 13;

-- If firm is inactive, update:
UPDATE episode 
SET firm_id = (SELECT id FROM firm WHERE active = 1 LIMIT 1)
WHERE patient_id = 13;
```

### Issue: Session/context errors

The system might need you to select a firm in the UI:
1. Go to patient 13's landing page: `http://localhost:7777/patient/view/13`
2. Select a firm/consultant from dropdown (usually in header)
3. Then try creating the event again

---

## Quick Reference

### Complete Setup Commands (One Block)

```sql
-- Set allergy status
UPDATE patient SET no_allergies_date = NOW() WHERE id = 13;

-- Create episode
INSERT INTO episode (patient_id, firm_id, start_date, episode_status_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
SELECT 13, (SELECT id FROM firm WHERE active = 1 LIMIT 1), CURDATE(), 1, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM episode WHERE patient_id = 13);

-- Verify setup
SELECT 
    p.id,
    p.no_allergies_date,
    (SELECT COUNT(*) FROM episode WHERE patient_id = p.id) as episodes,
    CASE 
        WHEN p.no_allergies_date IS NOT NULL AND (SELECT COUNT(*) FROM episode WHERE patient_id = p.id) > 0 
        THEN '✓ READY'
        ELSE '❌ NOT READY'
    END as status
FROM patient p
WHERE p.id = 13;
```

---

## Summary

**The error occurs because:**
- ✅ Patient exists
- ✅ Allergy status set (from earlier)
- ❌ **No episode exists** ← This is the problem

**The fix:**
1. Create an episode for patient 13
2. Link it to an active firm
3. Then clinical events can be created

**After fix:**
- All module URLs should work without errors
- Events will dual-write to Couchbase
- Patient is fully ready for testing

---

**Execute the SQL above, then retry the Biometry creation!**
