# Phase 12: Password Storage Guide

## 🔐 Where Passwords Are Stored

### 1. MySQL Database (Primary Storage)

**Table**: `user_authentication`

**Key Columns**:
```sql
CREATE TABLE user_authentication (
    id INT PRIMARY KEY,
    user_id INT,
    username VARCHAR(40),
    password_hash VARCHAR(255),           -- Bcrypt hash (NOT plaintext!)
    password_salt VARCHAR(10),            -- Legacy salt (deprecated)
    password_status VARCHAR(20),          -- 'current', 'expired', etc.
    password_last_changed_date DATETIME,
    password_failed_tries INT,
    password_softlocked_until DATETIME,
    institution_authentication_id INT,
    active BOOLEAN,
    ...
);
```

**Example Record**:
```
id: 1
username: admin
password_hash: $2y$10$abcd1234efgh5678ijkl9012mnop3456qrst7890uvwx1234yz567890
password_salt: NULL (not used in new hashes)
password_status: current
active: 1
```

---

### 2. Couchbase Storage (After Phase 12)

**Location**: `openeyes.admin.user_authentication`

**Document Structure**:
```json
{
  "_type": "user_authentication",
  "_mysql_id": 1,
  "_modified": "2024-12-24T05:42:26+00:00",
  "_version": 1,
  
  "id": 1,
  "user_id": 100,
  "username": "admin",
  "password_hash": "$2y$10$abcd1234efgh5678...",  ← Same hash from MySQL
  "password_salt": null,
  "password_status": "current",
  "password_last_changed_date": "2024-12-24 05:30:00",
  "password_failed_tries": 0,
  "password_softlocked_until": null,
  "institution_authentication_id": 1,
  "active": true,
  "last_modified_date": "2024-12-24 05:42:26",
  
  "user": {
    "id": 100,
    "username": "admin",
    "first_name": "Admin",
    "last_name": "User",
    "active": true
  },
  "institution": {
    "id": 1,
    "name": "Default Institution"
  },
  "authentication_method": {
    "code": "Local",
    "description": "Local authentication"
  }
}
```

---

## 🔒 Password Security Features

### Hashing Algorithm

**Current (Recommended)**: PHP `password_hash()` with bcrypt
```php
$hash = password_hash($password, PASSWORD_DEFAULT);
// Result: $2y$10$...  (bcrypt with cost factor 10)
```

**Legacy (Deprecated)**: Custom salt + hash
```php
$hash = md5($password . $salt);  // Old method, being phased out
```

### Security Properties

| Feature | Status | Description |
|---------|--------|-------------|
| **Plaintext Storage** | ❌ Never | Passwords never stored in plaintext |
| **One-Way Hash** | ✅ Yes | Cannot be reversed to get original password |
| **Salt** | ✅ Automatic | Bcrypt includes random salt in each hash |
| **Cost Factor** | ✅ 10 | 2^10 iterations (1024 rounds) |
| **Password Verification** | ✅ Secure | Uses `password_verify()` - timing-safe |
| **Automatic Rehashing** | ✅ Yes | Legacy hashes upgraded on next login |

---

## 🔄 How Password Hashing Works

### When Creating/Updating Password:

```php
// In UserAuthentication model:
public function setPasswordHash()
{
    if ($this->isLocalAuth() && !empty($this->password)) {
        $this->password_salt = null;  // Not needed with bcrypt
        $this->password_hash = PasswordUtils::hashPassword($this->password, null);
        // Result: $2y$10$abcd1234efgh5678ijkl...
    }
}
```

### When Verifying Password (Login):

```php
// In UserAuthentication model:
public function verifyPassword($password)
{
    // Modern method (bcrypt)
    if (!$this->password_salt) {
        if (password_verify($password, $this->password_hash)) {
            $this->password_failed_tries = 0;
            return true;
        }
        return false;
    }
    
    // Legacy method (auto-upgrade to bcrypt)
    if (PasswordUtils::hashPassword($password, $this->password_salt) === $this->password_hash) {
        // Upgrade to bcrypt
        $this->password_salt = null;
        $this->password_hash = PasswordUtils::hashPassword($password, null);
        $this->save();
        return true;
    }
    return false;
}
```

---

## 📊 Password Lifecycle

### 1. User Creation
```
User enters password
    ↓
password_hash($password) → bcrypt hash
    ↓
Store in MySQL: user_authentication.password_hash
    ↓
Dual-write to Couchbase: admin.user_authentication
    ↓
Original password discarded (never stored)
```

### 2. Login Attempt
```
User enters password
    ↓
Retrieve user_authentication record
    ↓
password_verify($input, $stored_hash)
    ↓
If match: Login successful
    ↓
Reset password_failed_tries to 0
    ↓
Update last_successful_login_date
```

### 3. Password Change
```
User changes password
    ↓
Validate new password (strength requirements)
    ↓
Generate new bcrypt hash
    ↓
Update password_hash in MySQL
    ↓
Dual-write to Couchbase
    ↓
Update password_last_changed_date
    ↓
Reset password_failed_tries to 0
```

### 4. Failed Login
```
Wrong password entered
    ↓
password_verify() returns false
    ↓
Increment password_failed_tries
    ↓
If tries >= 5: Set password_softlocked_until
    ↓
Return login error
```

---

## 🎯 Key Points

### What's Stored:

✅ **password_hash** - Bcrypt hash (irreversible)
- Format: `$2y$10$...` (60 characters)
- Example: `$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy`

✅ **password_status** - Status flag
- Values: `'current'`, `'expired'`, `'softlocked'`, `'stale'`

✅ **password_failed_tries** - Failed login counter
- Increments on wrong password
- Resets on successful login

✅ **password_last_changed_date** - Timestamp
- Tracks when password was last updated
- Used for password expiry policies

❌ **NOT stored**: Original plaintext password

### Dual-Write Behavior:

When password is changed:
1. Hash generated in application code
2. **Same hash** written to both MySQL and Couchbase
3. No difference in security between the two databases
4. Both contain the same bcrypt hash

### Security Notes:

🔒 **Passwords are secure**:
- Bcrypt is industry-standard
- Salt is automatic and unique per hash
- Cost factor makes brute-force attacks impractical
- Even with database access, passwords cannot be recovered

⚠️ **Legacy passwords**:
- Old records may have `password_salt` populated
- These use weaker MD5-based hashing
- Automatically upgraded to bcrypt on next login
- No action required from users

---

## 🔍 View Password Data (Hashes Only)

### MySQL Query:
```sql
SELECT 
    id,
    username,
    password_hash,
    password_status,
    password_last_changed_date,
    password_failed_tries
FROM user_authentication
WHERE active = 1;
```

### Couchbase Query:
```sql
SELECT 
    META().id,
    username,
    password_hash,
    password_status,
    password_last_changed_date,
    password_failed_tries
FROM `openeyes`.`admin`.`user_authentication`
WHERE active = true;
```

**Note**: Both queries return hashes, not actual passwords. Hashes look like:
```
$2y$10$abcd1234efgh5678ijkl9012mnop3456qrst7890uvwx1234yz567890
```

---

## ✅ Summary

| Question | Answer |
|----------|--------|
| **Where are passwords stored?** | MySQL `user_authentication.password_hash` + Couchbase `admin.user_authentication` |
| **Are passwords encrypted?** | Yes, using bcrypt (one-way hash, not reversible) |
| **Are passwords in Couchbase secure?** | Yes, same bcrypt hash as MySQL |
| **Can passwords be recovered?** | No, hashes cannot be reversed |
| **What if database is compromised?** | Passwords still safe - bcrypt makes cracking impractical |
| **Does dual-write affect security?** | No, same secure hash in both databases |

**Password storage is secure in both MySQL and Couchbase! 🔒**

---

**Created**: December 24, 2024  
**Phase**: 12 - Authentication Migration  
**Status**: Secure and operational
