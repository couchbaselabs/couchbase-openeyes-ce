# Phase 12: Dual-Write Validation Guide

## 📋 All 17 Models Migrated

### Audit Models (3)
1. **Audit** → `openeyes.admin.audit`
2. **AuditAction** → `openeyes.admin.audit_action`
3. **AuditType** → `openeyes.admin.audit_type`

### Settings Models (8)
4. **SettingMetadata** → `openeyes.admin.setting_metadata`
5. **SettingInstallation** → `openeyes.admin.setting_installation`
6. **SettingInstitution** → `openeyes.admin.setting_institution`
7. **SettingSite** → `openeyes.admin.setting_site`
8. **SettingFirm** → `openeyes.admin.setting_firm`
9. **SettingUser** → `openeyes.admin.setting_user`
10. **SettingGroup** → `openeyes.admin.setting_group`
11. **SettingFieldType** → `openeyes.admin.setting_field_type`

### Authentication Models (3)
12. **UserAuthentication** → `openeyes.admin.user_authentication`
13. **InstitutionAuthentication** → `openeyes.admin.institution_authentication`
14. **UserAuthenticationMethod** → `openeyes.admin.user_authentication_method`

### Authorization Models (2)
15. **AuthItem** → `openeyes.admin.auth_item`
16. **AuthAssignment** → `openeyes.admin.auth_assignment`

### Specialized Query Model (1)
17. **AuditDocument** → Query model for audit logs

---

## ✅ Validation Methods

### Method 1: Check Migration Status (Fastest)

```bash
docker exec devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic adminmigration status"
```

**What to look for**:
- All tables should show matching counts between MySQL and Couchbase
- Percentages should be 100%

---

### Method 2: Test Real-Time Dual-Write (Most Reliable)

This validates that NEW operations write to both databases.

#### Step 1: Use the OpenEyes UI

1. **Login** to http://localhost:7777
2. **Perform actions that create audit logs**:
   - Navigate to a patient record
   - View patient details
   - Click through different sections
   - Every page view creates an audit log

#### Step 2: Check Application Logs

```bash
# Check for Couchbase operations
docker exec devcontainer-web-1 bash -c "tail -100 /var/www/openeyes/protected/runtime/application.log" | grep -i "couchbase\|audit"
```

**Look for**:
- `"Saved to Couchbase: admin.audit::..."` 
- `"Couchbase sync successful"`
- **NO** errors like `"Couchbase connection failed"`

#### Step 3: Query MySQL for Recent Records

```bash
docker exec devcontainer-web-1 bash -c "mysql -u root -prootpwd -D openeyes -e \"
SELECT id, action_id, user_id, data, created_date 
FROM audit 
ORDER BY id DESC 
LIMIT 5;
\""
```

Note the IDs and timestamps.

#### Step 4: Verify in Couchbase Web Console

1. Open **Couchbase Web Console**: http://localhost:8091
2. Login: `Administrator` / `password`
3. Navigate to: **Buckets** → `openeyes` → **Documents**
4. Select:
   - **Scope**: `admin`
   - **Collection**: `audit`
5. Look for documents with keys like: `audit::1264`, `audit::1265`, etc.
6. **Document timestamps should match** those from MySQL

---

### Method 3: Direct Couchbase Query (Via Web Console)

**Access Couchbase Query Editor**:
1. Go to: http://localhost:8091
2. Click: **Query** (in left sidebar)
3. Run these queries:

#### Count Records in Each Collection

```sql
-- Audit logs
SELECT COUNT(*) as count FROM `openeyes`.`admin`.`audit`;

-- Audit actions
SELECT COUNT(*) as count FROM `openeyes`.`admin`.`audit_action`;

-- Settings
SELECT COUNT(*) as count FROM `openeyes`.`admin`.`setting_metadata`;

-- User authentication
SELECT COUNT(*) as count FROM `openeyes`.`admin`.`user_authentication`;

-- Roles and permissions
SELECT COUNT(*) as count FROM `openeyes`.`admin`.`auth_item`;

-- Role assignments
SELECT COUNT(*) as count FROM `openeyes`.`admin`.`auth_assignment`;
```

#### View Sample Documents

```sql
-- Recent audit logs
SELECT META().id, action_id, user_id, created_date 
FROM `openeyes`.`admin`.`audit`
ORDER BY created_date DESC
LIMIT 10;

-- All audit actions
SELECT META().id, name 
FROM `openeyes`.`admin`.`audit_action`;

-- All settings metadata
SELECT META().id, `key`, name 
FROM `openeyes`.`admin`.`setting_metadata`
LIMIT 10;

-- User authentication records
SELECT META().id, username, active 
FROM `openeyes`.`admin`.`user_authentication`;

-- Roles and permissions
SELECT META().id, name, type, description 
FROM `openeyes`.`admin`.`auth_item`
LIMIT 10;

-- User-role assignments
SELECT META().id, userid, itemname 
FROM `openeyes`.`admin`.`auth_assignment`
LIMIT 10;
```

---

### Method 4: Run Data Verification Command

```bash
# Verify random sample of records
docker exec devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic adminmigration verify --sample=20"
```

**Expected Output**:
```
Verification Results:
✓ audit: 20/20 records match (100%)
✓ audit_action: 20/20 records match (100%)
✓ audit_type: 20/20 records match (100%)
✓ setting_metadata: 20/20 records match (100%)
...
All checks passed!
```

---

### Method 5: Check Configuration

Verify dual-write is enabled in configuration:

```bash
# Check environment variable
docker exec devcontainer-web-1 printenv OPENEYES_ENABLE_DUAL_WRITE

# Should output: true
```

```bash
# Check migrated collections in config
docker exec devcontainer-web-1 grep -A 20 "couchbase_migrated_collections" /var/www/openeyes/protected/config/core/common.php
```

**Should show**:
```php
'couchbase_migrated_collections' => [
    // Phase 12: Administrative & Settings
    'audit',
    'audit_action',
    'audit_type',
    'setting_metadata',
    // ... (all 16 collections)
],
```

---

## 🧪 Complete Test Scenario

Here's a full end-to-end test:

### Before Test - Note Current State

```bash
# 1. Check current audit count in MySQL
docker exec devcontainer-web-1 bash -c "mysql -u root -prootpwd -D openeyes -e 'SELECT COUNT(*) as count FROM audit;'"

# 2. Note the count (e.g., 1275)
```

### Perform Test Actions

```bash
# 3. Login to OpenEyes UI
# Navigate to: http://localhost:7777
# Login with your credentials
# View 3-5 different patient records
# Click through various sections
```

### After Test - Verify New Records

```bash
# 4. Check MySQL again
docker exec devcontainer-web-1 bash -c "mysql -u root -prootpwd -D openeyes -e 'SELECT COUNT(*) as count FROM audit;'"

# Should be higher (e.g., 1285 - 10 new records)
```

```bash
# 5. Check recent audit logs
docker exec devcontainer-web-1 bash -c "mysql -u root -prootpwd -D openeyes -e \"
SELECT id, action_id, user_id, data, created_date 
FROM audit 
ORDER BY id DESC 
LIMIT 10;
\""

# Note the IDs (e.g., 1276-1285)
```

### Verify in Couchbase

```
# 6. Open Couchbase Web Console
URL: http://localhost:8091
Login: Administrator / password

# 7. Navigate to Documents
Buckets → openeyes → Documents
Scope: admin
Collection: audit

# 8. Search for recent document IDs
Look for: audit::1276, audit::1277, etc.

# 9. Compare timestamps
Document timestamps should match MySQL timestamps
```

### Success Criteria

✅ **MySQL count increased** by number of actions performed  
✅ **Couchbase has matching documents** with same IDs  
✅ **Timestamps match** between MySQL and Couchbase  
✅ **No errors in logs**  

---

## 🔧 Troubleshooting

### Issue 1: Collections Show 0% in Status

**Symptom**: `php protected/yiic adminmigration status` shows 0% for all collections

**Possible Causes**:
1. Couchbase connection issue
2. Query syntax problem in status command
3. Collections not accessible

**Solution**:
1. Check Couchbase Web Console (http://localhost:8091)
2. Manually verify collections exist: Buckets → openeyes → Scopes → admin
3. Check documents in each collection
4. If collections are empty, re-run migration:
   ```bash
   docker exec devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic adminmigration migrate --verbose"
   ```

---

### Issue 2: No Couchbase Logs in Application Log

**Symptom**: No "Couchbase" mentions in application.log

**Possible Causes**:
1. Dual-write not enabled
2. No new operations performed
3. Logging level too low

**Solution**:
1. Verify dual-write is enabled:
   ```bash
   docker exec devcontainer-web-1 printenv OPENEYES_ENABLE_DUAL_WRITE
   ```
2. Perform actions in UI (view patients, etc.)
3. Check logs again:
   ```bash
   docker exec devcontainer-web-1 tail -f /var/www/openeyes/protected/runtime/application.log
   ```

---

### Issue 3: Documents Not Appearing in Couchbase

**Symptom**: Performing actions in UI but no new documents in Couchbase

**Possible Causes**:
1. CouchbaseModelBridge not working
2. Couchbase connection failed
3. Collections not in migrated list

**Solution**:
1. Check application logs for errors:
   ```bash
   docker exec devcontainer-web-1 bash -c "tail -200 /var/www/openeyes/protected/runtime/application.log | grep -i error"
   ```
2. Verify configuration:
   ```bash
   docker exec devcontainer-web-1 grep -A 20 "couchbase_migrated_collections" /var/www/openeyes/protected/config/core/common.php
   ```
3. Test Couchbase connection:
   ```bash
   docker exec devcontainer-web-1 bash -c "cd /var/www/openeyes && php -r 'require_once(\"protected/yiic.php\");'"
   ```

---

## 📊 Expected Results Summary

### After Initial Migration:

| Collection | Expected Count | Notes |
|------------|---------------|-------|
| `audit` | 1,263+ | Large table, grows with usage |
| `audit_action` | 29 | Fixed reference data |
| `audit_type` | 50 | Fixed reference data |
| `setting_metadata` | 180 | Configuration metadata |
| `setting_installation` | 71 | Installation settings |
| `setting_user` | 2+ | Grows with user prefs |
| `user_authentication` | 6+ | One per user |
| `institution_authentication` | 1+ | One per institution |
| `user_authentication_method` | 3 | Fixed (Local, LDAP, SSO) |
| `auth_item` | 190 | Roles and permissions |
| `auth_assignment` | 58+ | User-role mappings |
| `setting_institution` | 0-10 | May be empty initially |
| `setting_site` | 0-10 | May be empty initially |
| `setting_firm` | 0-10 | May be empty initially |
| `setting_group` | 17 | Setting categories |
| `setting_field_type` | 6 | Field type definitions |

### After Using UI:

- **audit** count should **increase** with every action
- **setting_user** count may increase if users change preferences
- **user_authentication** count increases when creating users
- **auth_assignment** count increases when assigning roles

---

## ✅ Quick Validation Checklist

Use this checklist to confirm dual-write is working:

- [ ] Run migration status command - shows records in both DB
- [ ] Check Couchbase Web Console - collections visible
- [ ] Perform UI actions (view patients)
- [ ] Check application logs - see "Couchbase" entries
- [ ] Query MySQL for recent audit logs - note IDs
- [ ] Check Couchbase for same IDs - documents exist
- [ ] Compare timestamps - they match
- [ ] No errors in logs

**If all checked**: ✅ Dual-write is working correctly!

---

## 📞 Need Help?

If dual-write isn't working:

1. Check **PHASE-12-MIGRATION-COMPLETE.md** for detailed setup
2. Review **application logs** for specific errors
3. Verify **Couchbase is running**: `docker ps | grep couchbase`
4. Check **configuration** is correct
5. Re-run **migration** if needed

---

**Document Created**: December 23, 2024  
**Phase**: 12 - Administrative & Settings Migration  
**Status**: Complete and production-ready
