# Phase 11: Allergy Model Dual-Write Status

**Date**: December 23, 2025  
**Status**: ⚠️ PARTIALLY WORKING (Manual Sync Required)

## Problem

The `Allergy` model cannot use automatic dual-write because it's **based on a database VIEW**, not a real table.

From the model comments:
```php
/**
 * @deprecated 2.0.0
 * @deprecated This model remains to support backward compatibility, and is defined by a view
 * @deprecated it is not editable in its current form and should not be referenced going forward
 */
```

## What We Implemented

### 1. Created AllergyDocument.php ✅
**File**: `/protected/models/couchbase/AllergyDocument.php`

Features:
- `createFromModel()` - Convert Allergy to Couchbase document
- `search()` - Search allergies by term
- `findByName()` - Find by exact name
- `findAll()` - Get all active allergies

### 2. Updated Allergy.php ✅
**File**: `/protected/models/Allergy.php`

Added:
- `toCouchbaseDocument()` method
- Already had `CouchbaseModelBridge` trait
- Already had `afterSave()` and `afterDelete()` hooks

### 3. Created AllergyCommand ✅
**File**: `/protected/commands/AllergyCommand.php`

Commands:
- `allergy add --name="Name"` - Add new allergy
- `allergy list` - List all allergies (shows Couchbase status)
- `allergy sync` - Manually sync all allergies to Couchbase
- `allergy test` - Test dual-write (fails due to view limitation)

## Current Status

### Existing Allergies: 2 Total

| ID | Name | MariaDB | Couchbase |
|----|------|---------|-----------|
| 1  | Other | ✅ | ✅ |
| 2  | Penicillin | ✅ | ✅ |

### Manual Sync Works ✅
```bash
$ docker exec devcontainer-web-1 php protected/yiic allergy sync

Syncing allergies to Couchbase...
Syncing Allergy ID 1: Other
  ✓ Synced successfully
Syncing Allergy ID 2: Penicillin
  ✓ Synced successfully

Summary: 2 synced, 0 failed
```

### Automatic Dual-Write: ❌ NOT WORKING

Because the Allergy table is a view:
- `afterSave()` hook doesn't trigger properly
- ID is not returned after save
- Cannot determine document key for Couchbase
- Delete operations fail

## Recommended Approach

### For Now: Manual Sync
After adding/updating allergies, run:
```bash
docker exec devcontainer-web-1 php protected/yiic allergy sync
```

### Long-Term: Replace with Real Table

The Allergy model should be replaced with a proper table-backed model. According to the deprecation notice, this model "should not be referenced going forward."

**Options:**
1. **Create new table**: `allergy_new` with proper structure
2. **Migrate data**: From view to table
3. **Update references**: Change code to use new model
4. **Enable dual-write**: Will work automatically with real table

## How to Add Allergies

### Option 1: Via CLI Command
```bash
# Add new allergy
docker exec devcontainer-web-1 php protected/yiic allergy add --name="Sulfa"

# Manually sync to Couchbase
docker exec devcontainer-web-1 php protected/yiic allergy sync

# Verify
docker exec devcontainer-web-1 php protected/yiic allergy list
```

### Option 2: Via Direct Database Insert
```bash
# Insert into MariaDB
docker exec devcontainer-db-1 mysql -u openeyes -popeneyes openeyes -e "
INSERT INTO allergy (name, active) VALUES ('Latex', 1);
"

# Sync to Couchbase
docker exec devcontainer-web-1 php protected/yiic allergy sync
```

### Option 3: Via API (If Available)
The `AllergyController.php` provides an autocomplete endpoint:
```
GET /allergy/autocomplete?term=penicillin
```

But there's no create/update endpoint currently.

## Files Created/Modified

1. **Created**: `/protected/models/couchbase/AllergyDocument.php` (97 lines)
2. **Modified**: `/protected/models/Allergy.php` (+9 lines)
3. **Created**: `/protected/commands/AllergyCommand.php` (205 lines)

## Verification

### List Allergies with Couchbase Status
```bash
$ docker exec devcontainer-web-1 php protected/yiic allergy list

Allergies in MariaDB:
======================================
ID 1: Other [Active]
  Couchbase: ✓ YES
ID 2: Penicillin [Active]
  Couchbase: ✓ YES
```

### Query Couchbase Directly
```sql
SELECT a.* 
FROM `openeyes`.`reference`.`allergy` a
WHERE a.active = true
ORDER BY a.name;
```

## Summary

✅ **Allergy data CAN be synced to Couchbase** - Via manual sync command  
❌ **Automatic dual-write does NOT work** - Because table is a view  
✅ **Manual workflow functional** - Add → Sync → Verify  
⏳ **Needs long-term fix** - Replace view with real table  

## Phase 11 Complete Status Update

| Model | Dual-Write Status | Notes |
|-------|-------------------|-------|
| Disorder | ✅ Auto | Working |
| Medication | ✅ Auto | Working |
| Procedure | ✅ Auto | Working |
| Drug | ✅ Auto | Working |
| **Allergy** | ⚠️ **Manual** | **View-based, requires manual sync** |
| MedicationRoute | ✅ Auto | Working |
| MedicationForm | ✅ Auto | Working |
| MedicationFrequency | ✅ Auto | Working |
| MedicationDuration | ✅ Auto | Working |
| Benefit | ✅ Auto | Working |
| Complication | ✅ Auto | Working |
| OPCSCode | ✅ Auto | Working |

**10 of 11 models have automatic dual-write**  
**1 model (Allergy) requires manual sync due to database view limitation**
