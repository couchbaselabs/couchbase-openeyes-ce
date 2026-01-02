# Phase 12: Administrative & Settings Migration

## Overview

This phase migrates administrative tables including audit logs, system settings, user authentication, and authorization data. These tables support system operations and compliance requirements.

**Duration**: 1-2 weeks  
**Priority**: MEDIUM  
**Complexity**: Medium

## Prerequisites

- Phase 10 completed (Core Lookup Tables)
- Phase 11 completed (Clinical Reference Data)
- Couchbase `admin` scope created

## Dependencies

- Phase 4: User model migrated
- Phase 10: Institution, Site tables migrated

---

## Section 1: Audit Table Migration

### 1.1 Create Audit Couchbase Support

**File**: `protected/models/Audit.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class Audit extends BaseActiveRecord
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'admin';
    }

    public function couchbaseCollection()
    {
        return 'audit';
    }

    /**
     * Get embedded relations for Couchbase document
     * Audit documents are denormalized for fast querying
     * @return array
     */
    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed user info for quick display
        if ($this->user) {
            $data['user'] = [
                'id' => (int)$this->user->id,
                'username' => $this->user->username,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
            ];
        }
        
        // Embed patient info if available
        if ($this->patient) {
            $data['patient'] = [
                'id' => (int)$this->patient->id,
                'hos_num' => $this->patient->hos_num,
            ];
        }
        
        // Embed site info
        if ($this->site) {
            $data['site'] = [
                'id' => (int)$this->site->id,
                'name' => $this->site->name,
            ];
        }
        
        // Embed event type if available
        if ($this->event_type_id && $this->eventType) {
            $data['event_type'] = [
                'id' => (int)$this->eventType->id,
                'name' => $this->eventType->name,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~60

---

### 1.2 Create AuditDocument Model

**File**: `protected/models/couchbase/AuditDocument.php`

```php
<?php
/**
 * Couchbase document model for Audit
 * Optimized for time-series queries and compliance reporting
 */

class AuditDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'audit';
    protected $scope = 'admin';
    protected $collection = 'audit';

    /**
     * Create document from Audit model
     * @param Audit $audit
     * @return array
     */
    public static function createFromModel($audit)
    {
        $doc = [
            '_type' => 'audit',
            'id' => (int)$audit->id,
            'action_id' => $audit->action_id ? (int)$audit->action_id : null,
            'type_id' => $audit->type_id ? (int)$audit->type_id : null,
            'patient_id' => $audit->patient_id ? (int)$audit->patient_id : null,
            'episode_id' => $audit->episode_id ? (int)$audit->episode_id : null,
            'event_id' => $audit->event_id ? (int)$audit->event_id : null,
            'user_id' => $audit->user_id ? (int)$audit->user_id : null,
            'site_id' => $audit->site_id ? (int)$audit->site_id : null,
            'firm_id' => $audit->firm_id ? (int)$audit->firm_id : null,
            'event_type_id' => $audit->event_type_id ? (int)$audit->event_type_id : null,
            'data' => $audit->data,
            'remote_addr' => $audit->remote_addr,
            'http_user_agent' => $audit->http_user_agent,
            'server_name' => $audit->server_name,
            'request_uri' => $audit->request_uri,
            'created_date' => $audit->created_date,
            
            // Computed fields for time-series queries
            'created_timestamp' => strtotime($audit->created_date),
            'created_date_only' => substr($audit->created_date, 0, 10),
            'created_hour' => (int)date('H', strtotime($audit->created_date)),
        ];
        
        // Embed action name
        if ($audit->action) {
            $doc['action'] = [
                'id' => (int)$audit->action->id,
                'name' => $audit->action->name,
            ];
        }
        
        // Embed type name
        if ($audit->type) {
            $doc['type'] = [
                'id' => (int)$audit->type->id,
                'name' => $audit->type->name,
            ];
        }
        
        // Embed user info
        if ($audit->user) {
            $doc['user'] = [
                'id' => (int)$audit->user->id,
                'username' => $audit->user->username,
                'first_name' => $audit->user->first_name,
                'last_name' => $audit->user->last_name,
            ];
        }
        
        // Embed patient info
        if ($audit->patient) {
            $doc['patient'] = [
                'id' => (int)$audit->patient->id,
                'hos_num' => $audit->patient->hos_num,
            ];
        }
        
        // Embed site info
        if ($audit->site) {
            $doc['site'] = [
                'id' => (int)$audit->site->id,
                'name' => $audit->site->name,
            ];
        }
        
        return $doc;
    }

    /**
     * Find audits by date range
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return array
     */
    public static function findByDateRange($startDate, $endDate, $limit = 1000)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.created_date_only >= \$startDate 
                  AND a.created_date_only <= \$endDate 
                  ORDER BY a.created_date DESC 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'limit' => $limit
        ]);
    }

    /**
     * Find audits by user
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function findByUser($userId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.user_id = \$userId 
                  ORDER BY a.created_date DESC 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'userId' => $userId,
            'limit' => $limit
        ]);
    }

    /**
     * Find audits by patient
     * @param int $patientId
     * @param int $limit
     * @return array
     */
    public static function findByPatient($patientId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.patient_id = \$patientId 
                  ORDER BY a.created_date DESC 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'patientId' => $patientId,
            'limit' => $limit
        ]);
    }

    /**
     * Count audits by action type for date range
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function countByAction($startDate, $endDate)
    {
        $query = "SELECT a.action.name AS action_name, COUNT(*) AS count 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.created_date_only >= \$startDate 
                  AND a.created_date_only <= \$endDate 
                  GROUP BY a.action.name 
                  ORDER BY count DESC";
        
        return self::executeQuery($query, [
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }
}
```

**Lines**: ~170

---

## Section 2: Settings Tables Migration

### 2.1 Create SettingMetadata Couchbase Support

**File**: `protected/models/SettingMetadata.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class SettingMetadata extends BaseActiveRecord
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'admin';
    }

    public function couchbaseCollection()
    {
        return 'setting_metadata';
    }

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed group info
        if ($this->group) {
            $data['group'] = [
                'id' => (int)$this->group->id,
                'name' => $this->group->name,
            ];
        }
        
        // Embed field type
        if ($this->fieldType) {
            $data['field_type'] = [
                'id' => (int)$this->fieldType->id,
                'name' => $this->fieldType->name,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~40

---

### 2.2 Create SettingsDocument Model

**File**: `protected/models/couchbase/SettingsDocument.php`

```php
<?php
/**
 * Couchbase document model for Settings
 * Consolidates all setting levels into single queryable documents
 */

class SettingsDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'settings';
    protected $scope = 'admin';
    protected $collection = 'settings';

    /**
     * Create consolidated settings document
     * Combines metadata with values at all levels
     * @param SettingMetadata $metadata
     * @return array
     */
    public static function createFromMetadata($metadata)
    {
        $doc = [
            '_type' => 'settings',
            'id' => (int)$metadata->id,
            'key' => $metadata->key,
            'name' => $metadata->name,
            'data' => $metadata->data,
            'default_value' => $metadata->default_value,
            'field_type_id' => $metadata->field_type_id ? (int)$metadata->field_type_id : null,
            'group_id' => $metadata->group_id ? (int)$metadata->group_id : null,
            'lowest_setting_level' => $metadata->lowest_setting_level,
            'description' => $metadata->description,
            
            // Embed field type
            'field_type' => $metadata->fieldType ? [
                'id' => (int)$metadata->fieldType->id,
                'name' => $metadata->fieldType->name,
            ] : null,
            
            // Embed group
            'group' => $metadata->group ? [
                'id' => (int)$metadata->group->id,
                'name' => $metadata->group->name,
            ] : null,
            
            // Values at each level
            'installation_value' => self::getSettingValue('SettingInstallation', $metadata->key),
            'institution_values' => self::getInstitutionValues($metadata->key),
            'site_values' => self::getSiteValues($metadata->key),
            'firm_values' => self::getFirmValues($metadata->key),
            'user_values' => self::getUserValues($metadata->key),
        ];
        
        return $doc;
    }

    /**
     * Get installation-level setting value
     */
    protected static function getSettingValue($model, $key)
    {
        $setting = $model::model()->find('`key` = ?', [$key]);
        return $setting ? $setting->value : null;
    }

    /**
     * Get institution-level values
     */
    protected static function getInstitutionValues($key)
    {
        $values = [];
        $settings = SettingInstitution::model()->findAll('`key` = ?', [$key]);
        foreach ($settings as $setting) {
            $values[$setting->institution_id] = $setting->value;
        }
        return $values;
    }

    /**
     * Get site-level values
     */
    protected static function getSiteValues($key)
    {
        $values = [];
        $settings = SettingSite::model()->findAll('`key` = ?', [$key]);
        foreach ($settings as $setting) {
            $values[$setting->site_id] = $setting->value;
        }
        return $values;
    }

    /**
     * Get firm-level values
     */
    protected static function getFirmValues($key)
    {
        $values = [];
        $settings = SettingFirm::model()->findAll('`key` = ?', [$key]);
        foreach ($settings as $setting) {
            $values[$setting->firm_id] = $setting->value;
        }
        return $values;
    }

    /**
     * Get user-level values (limited to avoid large documents)
     */
    protected static function getUserValues($key)
    {
        // Only include count to avoid bloating document
        $count = SettingUser::model()->count('`key` = ?', [$key]);
        return ['count' => $count];
    }

    /**
     * Find setting by key
     * @param string $key
     * @return array|null
     */
    public static function findByKey($key)
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`admin`.`settings` s 
                  WHERE s.key = \$key";
        
        $result = self::executeQuery($query, ['key' => $key]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find settings by group
     * @param int $groupId
     * @return array
     */
    public static function findByGroup($groupId)
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`admin`.`settings` s 
                  WHERE s.group_id = \$groupId 
                  ORDER BY s.name";
        
        return self::executeQuery($query, ['groupId' => $groupId]);
    }
}
```

**Lines**: ~140

---

## Section 3: User Authentication Tables

### 3.1 Create UserAuthentication Couchbase Support

**File**: `protected/models/UserAuthentication.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class UserAuthentication extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'admin';
    }

    public function couchbaseCollection()
    {
        return 'user_authentication';
    }

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed institution
        if ($this->institution) {
            $data['institution'] = [
                'id' => (int)$this->institution->id,
                'name' => $this->institution->name,
            ];
        }
        
        // Embed user basic info
        if ($this->user) {
            $data['user'] = [
                'id' => (int)$this->user->id,
                'username' => $this->user->username,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~40

---

## Section 4: Authorization Tables

### 4.1 Create AuthItem Couchbase Support

**File**: `protected/models/AuthItem.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class AuthItem extends CActiveRecord
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'admin';
    }

    public function couchbaseCollection()
    {
        return 'auth_item';
    }
}
```

**Lines to add**: ~15

---

### 4.2 Update AuthAssignment Couchbase Support

**File**: `protected/models/AuthAssignment.php`

```php
<?php
use OE\Models\Traits\CouchbaseModelBridge;

class AuthAssignment extends CActiveRecord
{
    use CouchbaseModelBridge;

    public function couchbaseScope()
    {
        return 'admin';
    }

    public function couchbaseCollection()
    {
        return 'auth_assignment';
    }

    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed user info
        $user = User::model()->findByPk($this->userid);
        if ($user) {
            $data['user'] = [
                'id' => (int)$user->id,
                'username' => $user->username,
            ];
        }
        
        return $data;
    }
}
```

**Lines to add**: ~35

---

## Section 5: N1QL Indexes

### 5.1 Create Admin Indexes

**File**: `protected/scripts/couchbase/indexes/admin-indexes.n1ql`

```sql
-- =====================================================
-- Phase 12: Administrative & Settings Indexes
-- =====================================================

-- Audit Indexes (optimized for time-series queries)
CREATE INDEX idx_audit_date 
ON `openeyes`.`admin`.`audit`(created_date_only DESC, created_timestamp DESC);

CREATE INDEX idx_audit_user 
ON `openeyes`.`admin`.`audit`(user_id, created_date DESC);

CREATE INDEX idx_audit_patient 
ON `openeyes`.`admin`.`audit`(patient_id, created_date DESC) 
WHERE patient_id IS NOT NULL;

CREATE INDEX idx_audit_action 
ON `openeyes`.`admin`.`audit`(action.name, created_date_only);

CREATE INDEX idx_audit_type 
ON `openeyes`.`admin`.`audit`(type.name, created_date_only);

CREATE INDEX idx_audit_event 
ON `openeyes`.`admin`.`audit`(event_id) 
WHERE event_id IS NOT NULL;

CREATE INDEX idx_audit_site 
ON `openeyes`.`admin`.`audit`(site_id, created_date_only);

-- Settings Indexes
CREATE INDEX idx_settings_key 
ON `openeyes`.`admin`.`settings`(`key`);

CREATE INDEX idx_settings_group 
ON `openeyes`.`admin`.`settings`(group_id);

CREATE INDEX idx_setting_metadata_key 
ON `openeyes`.`admin`.`setting_metadata`(`key`);

-- User Authentication Indexes
CREATE INDEX idx_user_auth_user 
ON `openeyes`.`admin`.`user_authentication`(user_id);

CREATE INDEX idx_user_auth_institution 
ON `openeyes`.`admin`.`user_authentication`(institution_id);

CREATE INDEX idx_user_auth_username 
ON `openeyes`.`admin`.`user_authentication`(username);

-- Authorization Indexes
CREATE PRIMARY INDEX idx_auth_item_primary 
ON `openeyes`.`admin`.`auth_item`;

CREATE INDEX idx_auth_item_type 
ON `openeyes`.`admin`.`auth_item`(type);

CREATE INDEX idx_auth_assignment_user 
ON `openeyes`.`admin`.`auth_assignment`(userid);

CREATE INDEX idx_auth_assignment_item 
ON `openeyes`.`admin`.`auth_assignment`(itemname);
```

**Lines**: ~60

---

## Section 6: Migration Command

### 6.1 Create Admin Migration Command

**File**: `protected/commands/AdminMigrationCommand.php`

```php
<?php
/**
 * Command to migrate administrative tables to Couchbase
 */

class AdminMigrationCommand extends CConsoleCommand
{
    protected $tables = [
        // Tier 1: Small tables first
        'setting_group' => ['model' => 'SettingGroup', 'scope' => 'admin'],
        'setting_field_type' => ['model' => 'SettingFieldType', 'scope' => 'admin'],
        'audit_action' => ['model' => 'AuditAction', 'scope' => 'admin'],
        'audit_type' => ['model' => 'AuditType', 'scope' => 'admin'],
        'auth_item' => ['model' => 'AuthItem', 'scope' => 'admin'],
        
        // Tier 2: Medium tables
        'setting_metadata' => ['model' => 'SettingMetadata', 'scope' => 'admin'],
        'setting_installation' => ['model' => 'SettingInstallation', 'scope' => 'admin'],
        'setting_institution' => ['model' => 'SettingInstitution', 'scope' => 'admin'],
        'setting_site' => ['model' => 'SettingSite', 'scope' => 'admin'],
        'setting_firm' => ['model' => 'SettingFirm', 'scope' => 'admin'],
        'setting_user' => ['model' => 'SettingUser', 'scope' => 'admin'],
        'user_authentication' => ['model' => 'UserAuthentication', 'scope' => 'admin'],
        'auth_assignment' => ['model' => 'AuthAssignment', 'scope' => 'admin'],
        
        // Tier 3: Large tables (audit)
        'audit' => ['model' => 'Audit', 'scope' => 'admin', 'large' => true],
    ];

    public function actionMigrate($table = null, $batch = 1000, $verbose = false, $dryRun = false)
    {
        echo "===========================================\n";
        echo "Phase 12: Administrative Tables Migration\n";
        echo "===========================================\n\n";

        $tables = $table ? [$table => $this->tables[$table]] : $this->tables;
        $totalMigrated = 0;
        $totalErrors = 0;

        foreach ($tables as $tableName => $config) {
            echo "Migrating table: {$tableName}\n";
            
            $tableBatch = isset($config['large']) && $config['large'] ? 500 : $batch;
            $result = $this->migrateTable($tableName, $config, $tableBatch, $verbose, $dryRun);
            
            $totalMigrated += $result['migrated'];
            $totalErrors += $result['errors'];
            
            echo "  Migrated: {$result['migrated']}, Errors: {$result['errors']}\n\n";
        }

        echo "===========================================\n";
        echo "Total Migrated: {$totalMigrated}, Errors: {$totalErrors}\n";
        echo "===========================================\n";

        return $totalErrors === 0 ? 0 : 1;
    }

    protected function migrateTable($tableName, $config, $batch, $verbose, $dryRun)
    {
        $modelClass = $config['model'];
        $scope = $config['scope'];
        $migrated = 0;
        $errors = 0;

        $adapter = Yii::app()->couchbase;
        $total = $modelClass::model()->count();
        echo "  Total records: {$total}\n";

        if ($dryRun) {
            echo "  [DRY RUN] Would migrate {$total} records\n";
            return ['migrated' => 0, 'errors' => 0];
        }

        $offset = 0;
        while ($offset < $total) {
            $records = $modelClass::model()->findAll([
                'limit' => $batch,
                'offset' => $offset,
            ]);

            foreach ($records as $record) {
                try {
                    $doc = $record->toCouchbaseDocument();
                    $key = $tableName . '::' . $record->id;
                    $adapter->upsert($scope, $tableName, $key, $doc);
                    $migrated++;
                } catch (Exception $e) {
                    $errors++;
                    if ($verbose) {
                        echo "    ERROR: {$e->getMessage()}\n";
                    }
                }
            }

            $offset += $batch;
            if ($verbose && $migrated % 1000 === 0) {
                echo "    Progress: {$migrated}/{$total}\n";
            }
        }

        return ['migrated' => $migrated, 'errors' => $errors];
    }

    public function actionStatus()
    {
        echo "Administrative Tables Migration Status\n";
        echo "======================================\n\n";

        $adapter = Yii::app()->couchbase;

        foreach ($this->tables as $tableName => $config) {
            $modelClass = $config['model'];
            $mysqlCount = $modelClass::model()->count();
            
            try {
                $cbCount = $adapter->count($config['scope'], $tableName);
            } catch (Exception $e) {
                $cbCount = 0;
            }
            
            $status = $mysqlCount === $cbCount ? '✓' : '✗';
            printf("%-30s MySQL: %8d  CB: %8d  [%s]\n",
                $tableName, $mysqlCount, $cbCount, $status
            );
        }
    }
}
```

**Lines**: ~130

---

## Section 7: Unit Tests

### 7.1 AuditDocument Test

**File**: `protected/tests/unit/models/couchbase/AuditDocumentTest.php`

```php
<?php
class AuditDocumentTest extends CDbTestCase
{
    public function testCreateFromModel()
    {
        $audit = Audit::model()->find();
        if (!$audit) {
            $this->markTestSkipped('No audit record found');
        }
        
        $doc = AuditDocument::createFromModel($audit);
        
        $this->assertArrayHasKey('_type', $doc);
        $this->assertEquals('audit', $doc['_type']);
        $this->assertEquals($audit->id, $doc['id']);
    }

    public function testTimestampComputed()
    {
        $audit = Audit::model()->find();
        if (!$audit) {
            $this->markTestSkipped('No audit record found');
        }
        
        $doc = AuditDocument::createFromModel($audit);
        
        $this->assertArrayHasKey('created_timestamp', $doc);
        $this->assertArrayHasKey('created_date_only', $doc);
        $this->assertArrayHasKey('created_hour', $doc);
    }

    public function testUserEmbedded()
    {
        $audit = Audit::model()->find('user_id IS NOT NULL');
        if (!$audit) {
            $this->markTestSkipped('No audit with user found');
        }
        
        $doc = AuditDocument::createFromModel($audit);
        
        $this->assertArrayHasKey('user', $doc);
        $this->assertArrayHasKey('username', $doc['user']);
    }
}
```

**Lines**: ~55

---

## Section 8: Validation & Success Criteria

### 8.1 Validation Checklist

```markdown
## Phase 12 Validation Checklist

### Audit Data
- [ ] All audit records migrated
- [ ] Timestamps correctly computed
- [ ] User/Patient/Site embedded
- [ ] Date range queries work

### Settings Data
- [ ] All setting metadata migrated
- [ ] Values at all levels captured
- [ ] Setting inheritance works
- [ ] Key lookup works

### Authorization Data
- [ ] All auth items migrated
- [ ] All auth assignments migrated
- [ ] User-role queries work

### Performance
- [ ] Audit date range query < 100ms
- [ ] Setting lookup < 50ms
- [ ] Auth check < 10ms
```

---

## Summary

### Files to Create
| File | Lines | Purpose |
|------|-------|---------|
| AuditDocument.php | 170 | Audit document model |
| SettingsDocument.php | 140 | Settings document model |
| admin-indexes.n1ql | 60 | N1QL indexes |
| AdminMigrationCommand.php | 130 | Migration command |
| AuditDocumentTest.php | 55 | Unit tests |

### Files to Modify
| File | Changes |
|------|---------|
| Audit.php | Add CouchbaseModelBridge (~60 lines) |
| SettingMetadata.php | Add CouchbaseModelBridge (~40 lines) |
| UserAuthentication.php | Add CouchbaseModelBridge (~40 lines) |
| AuthItem.php | Add CouchbaseModelBridge (~15 lines) |
| AuthAssignment.php | Add CouchbaseModelBridge (~35 lines) |

### Total Effort
- **New Files**: 5 files (~555 lines)
- **Modified Files**: 5 files (~190 lines)
- **Total Code**: ~745 lines
- **Estimated Duration**: 1-2 weeks

---

**Phase 12 Status**: SPECIFICATION COMPLETE  
**Ready for Implementation**: YES
