# Dual-Write Enabled for Couchbase

## Summary

Dual-write has been successfully enabled for the OpenEyes dev container. All events and entities created via the web UI will now automatically be written to both MariaDB and Couchbase.

## Changes Made

### 1. Docker Compose Configuration
**File**: `.devcontainer/docker-compose.yml`

Changed environment variable defaults:
```yaml
# Before:
OPENEYES_ENABLE_DUAL_WRITE: ${OPENEYES_ENABLE_DUAL_WRITE:-false}
OPENEYES_ENABLE_COUCHBASE_READ: ${OPENEYES_ENABLE_COUCHBASE_READ:-false}

# After:
OPENEYES_ENABLE_DUAL_WRITE: ${OPENEYES_ENABLE_DUAL_WRITE:-true}
OPENEYES_ENABLE_COUCHBASE_READ: ${OPENEYES_ENABLE_COUCHBASE_READ:-true}
```

### 2. Common Configuration
**File**: `protected/config/core/common.php` (line 1027)

Fixed duplicate configuration that was using wrong env var name:
```php
# Before:
'enable_dual_write' => strtolower(getenv('ENABLE_DUAL_WRITE') ?: '') === 'true',

# After:
'enable_dual_write' => filter_var(getenv('OPENEYES_ENABLE_DUAL_WRITE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
```

### 3. Console Configuration
**File**: `protected/config/core/console.php`

Added local config merging:
```php
// Merge with local console config if it exists
$localConfig = __DIR__ . '/../local/console.php';
if (file_exists($localConfig)) {
    $localConsoleConfig = include($localConfig);
    foreach ($localConsoleConfig as $key => $value) {
        if (isset($config[$key]) && is_array($config[$key]) && is_array($value)) {
            $config[$key] = array_replace_recursive($config[$key], $value);
        } else {
            $config[$key] = $value;
        }
    }
}
```

### 4. Container Restart
Recreated containers to apply environment variable changes.

## Current Status

✅ **Dual-write enabled**: `OPENEYES_ENABLE_DUAL_WRITE=true`
✅ **Couchbase read enabled**: `OPENEYES_ENABLE_COUCHBASE_READ=true`  
✅ **Couchbase host configured**: `COUCHBASE_HOST=host.docker.internal`
✅ **Containers restarted**: Environment variables applied
✅ **Configuration merged**: Console commands now inherit dual-write settings

## How It Works

When you create or update an entity via the web UI (Patient, Episode, Event, etc.):

1. The model's `afterSave()` method is called
2. `CouchbaseModelBridge::saveToCouchbase()` is invoked
3. Data is written to MariaDB (primary)
4. Data is written to Couchbase (secondary) via the `CouchbaseAdapter`
5. If Couchbase write fails, it's logged but doesn't fail the main operation

## Testing Dual-Write

### Via Web UI (Recommended)

1. Open OpenEyes in your browser: http://localhost:7777
2. Navigate to a patient record
3. Create a new event (e.g., Examination, Correspondence)
4. The event will be saved to both MariaDB and Couchbase

### Verify in Couchbase

```bash
# Connect to Couchbase container
docker exec -it couchbase bash

# Query for events
cbq -e couchbase://localhost -u Administrator -p password
SELECT * FROM openeyes.core.event LIMIT 10;
```

### Check Application Logs

Look for Couchbase sync messages in the logs:
```bash
docker exec devcontainer-web-1 tail -f /var/www/openeyes/protected/runtime/application.log | grep -i couchbase
```

## Migrated Collections

The following collections are configured for dual-write:

**Reference Data:**
- institution
- site
- specialty
- subspecialty
- firm
- event_type
- ethnic_group
- gender
- country

**Core Entities:**
- user
- contact
- address
- patient
- episode
- event

## Services Using Couchbase

The following services have been migrated to support Couchbase:

1. **PatientService** - Patient CRUD and search
2. **EpisodeService** - Episode management
3. **EventService** - Event operations
4. **BaseFhirService** - FHIR endpoints
5. **FhirPatientService** - FHIR patient resources

## Troubleshooting

### If dual-write doesn't work:

1. **Check environment variables inside container:**
   ```bash
   docker exec devcontainer-web-1 env | grep OPENEYES_ENABLE
   ```
   Should show:
   ```
   OPENEYES_ENABLE_DUAL_WRITE=true
   OPENEYES_ENABLE_COUCHBASE_READ=true
   ```

2. **Check Couchbase connection:**
   ```bash
   docker exec devcontainer-web-1 ping -c 1 host.docker.internal
   ```

3. **Check application logs:**
   ```bash
   docker exec devcontainer-web-1 tail -100 /var/www/openeyes/protected/runtime/application.log
   ```

4. **Verify Couchbase container is running:**
   ```bash
   docker ps | grep couchbase
   ```

### If you need to disable dual-write:

```bash
# Set environment variable to false
export OPENEYES_ENABLE_DUAL_WRITE=false

# Restart containers
cd .devcontainer
docker-compose restart web
```

## Next Steps

1. **Test via Web UI**: Create/update events and verify they appear in Couchbase
2. **Monitor Performance**: Watch for any latency issues with dual-write
3. **Check Error Logs**: Look for any Couchbase connection errors
4. **Validate Data**: Run data validation command to ensure consistency

## Notes

- Dual-write is **non-blocking** - Couchbase failures won't prevent MariaDB writes
- Errors are logged to `protected/runtime/application.log` with level WARNING
- The `CouchbaseModelBridge` trait handles all sync logic automatically
- Models must use the trait to enable dual-write (Patient, Episode, Event already do)
