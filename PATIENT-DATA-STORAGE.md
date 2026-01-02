# Patient Data Storage in OpenEyes

## Overview
When a new patient is created in OpenEyes, the data is stored in **multiple MariaDB tables** and **optionally in Couchbase** if dual-write is enabled.

## Storage Flow

### 1. User Creates Patient
**UI Endpoint**: `http://localhost:7777/patient/create` (POST)

**Controller**: `PatientController::actionCreate()` → `performPatientSave()` → `patientSaveInner()`

### 2. MariaDB Storage (Primary)

Patient data is saved to these MariaDB tables:

#### a) **`contact` table**
Stores personal contact information:
- `first_name`, `last_name`, `title`, `nick_name`
- `primary_phone`, `email`
- `qualifications`, `contact_label_id`
- `created_institution_id`

**Model**: `Contact` (`protected/models/Contact.php`)

#### b) **`patient` table**
Stores core patient data:
- `contact_id` (FK to contact table)
- `dob` (date of birth)
- `gender`
- `date_of_death`, `is_deceased`
- `ethnic_group_id`
- `gp_id`, `practice_id`
- `patient_source` (e.g., 'Referral', 'Self-register')
- `is_local` (local vs PAS-managed)
- `primary_institution_id`

**Model**: `Patient` (`protected/models/Patient.php`)

#### c) **`address` table**
Stores postal address:
- `contact_id` (FK to contact table)
- `address1`, `address2`
- `city`, `postcode`, `county`
- `country_id`

**Model**: `Address` (`protected/models/Address.php`)

#### d) **`patient_identifier` table**
Stores patient identifiers (NHS number, hospital number, etc.):
- `patient_id` (FK to patient table)
- `patient_identifier_type_id` (FK to patient_identifier_type)
- `value` (the actual identifier)
- `patient_identifier_status_id` (verified/unverified)

**Model**: `PatientIdentifier` (`protected/models/PatientIdentifier.php`)

Multiple identifiers per patient are supported.

#### e) **`patient_referral` table** (if applicable)
Stores referral information:
- `patient_id`
- `referral_type`
- Referral source details

**Model**: `PatientReferral`

#### f) **`patient_user_referral` table** (if applicable)
Stores referral to a specific doctor:
- `patient_id`
- `user_id` (doctor being referred to)

**Model**: `PatientUserReferral`

#### g) **`patient_contact_associate` table** (optional)
Associates patient with GP/practice:
- `patient_id`
- `gp_id`
- `practice_id`

**Model**: `PatientContactAssociate`

### 3. Couchbase Storage (Dual-Write)

If **dual-write is enabled** (`enable_dual_write = true` in config), patient data is **automatically synced to Couchbase** after MariaDB save.

#### Location in Couchbase:
- **Bucket**: `openeyes` (or configured bucket)
- **Scope**: `core`
- **Collection**: `patient`
- **Document Key**: `patient::<id>` (e.g., `patient::1001`)

#### How it Works:
The `Patient` model uses the `CouchbaseModelBridge` trait which hooks into `afterSave()`:

```php
// In Patient model (line 82):
use CouchbaseModelBridge;

// Automatic sync after save (line 2653):
protected function afterSave()
{
    parent::afterSave();
    $this->saveToCouchbase();  // Automatically syncs to Couchbase
}
```

#### Couchbase Document Structure:
```json
{
  "_type": "patient",
  "_mysql_id": 1001,
  "id": 1001,
  "dob": "1990-01-15",
  "gender": "M",
  "is_deceased": false,
  "date_of_death": null,
  "ethnic_group_id": 5,
  "gp_id": 10,
  "practice_id": 3,
  "patient_source": "Referral",
  "is_local": 1,
  "primary_institution_id": 1,
  "contact": {
    "id": 2001,
    "first_name": "John",
    "last_name": "Doe",
    "title": "Mr",
    "primary_phone": "01234567890",
    "email": "john.doe@example.com"
  },
  "full_name": "John Doe",
  "created_date": "2025-12-23T05:30:00Z",
  "last_modified_date": "2025-12-23T05:30:00Z"
}
```

**Note**: The contact information is **embedded** in the patient document (not a separate reference).

### 4. Related Documents Created

#### a) **OphCoDocument Event** (if referral documents uploaded)
If referral documents are attached during patient creation:
- **Table**: `event` - Creates a Document event
- **Table**: `et_ophcodocument_document` - Stores document element
- **Table**: `protected_file` - Stores the actual file
- **Table**: `episode` - Creates/links to an episode

**Note**: This only happens if OphCoDocument module is installed (as of the recent fix).

### 5. Audit Trail
Every patient creation is logged:
```php
Audit::add('Patient', 'add-patient', "Patient manually [id: $patient->id] added.");
```

## Configuration

### Dual-Write Settings
Located in `protected/config/core/common.php`:

```php
'enable_dual_write' => getenv('OPENEYES_ENABLE_DUAL_WRITE') === 'true',
'enable_couchbase_read' => getenv('OPENEYES_ENABLE_COUCHBASE_READ') === 'true',
```

### Environment Variables (docker-compose.yml)
```yaml
OPENEYES_ENABLE_DUAL_WRITE: "true"
OPENEYES_ENABLE_COUCHBASE_READ: "true"
```

## Save Transaction

All patient saves occur within a **database transaction**:

```php
$transaction = Yii::app()->db->beginTransaction();
try {
    // Save contact
    // Save patient
    // Save address
    // Save identifiers
    // Save referral
    // Save documents
    $transaction->commit();
} catch (Exception $e) {
    $transaction->rollback();
}
```

If any step fails, all changes are rolled back.

## Data Relationships

```
contact (1) ─────┬──── (1) address
                 │
                 └──── (1) patient ─────┬──── (*) patient_identifier
                                        │
                                        ├──── (1) patient_referral
                                        │
                                        ├──── (*) patient_user_referral
                                        │
                                        ├──── (*) patient_contact_associate
                                        │
                                        └──── (*) episode ──── (*) event
```

## Key Files

- **Controller**: `protected/controllers/PatientController.php`
- **Models**: 
  - `protected/models/Patient.php`
  - `protected/models/Contact.php`
  - `protected/models/Address.php`
  - `protected/models/PatientIdentifier.php`
- **Trait**: `protected/models/traits/CouchbaseModelBridge.php`
- **Views**: `protected/views/patient/crud/create.php`

## Summary

**Primary Storage**: MariaDB tables (`patient`, `contact`, `address`, `patient_identifier`, etc.)

**Secondary Storage**: Couchbase (if dual-write enabled) - `core.patient` collection

**Sync Method**: Automatic via `afterSave()` hook in Patient model

**Read Priority**: Can be configured to read from Couchbase first, fall back to MariaDB

---

Last Updated: December 23, 2025
