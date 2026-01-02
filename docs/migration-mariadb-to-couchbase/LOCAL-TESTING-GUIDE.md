# Local Testing Guide - Phase 1 & 2 Verification

This guide will help you run the OpenEyes server locally to verify that Phase 1 and Phase 2 changes are working correctly.

## Prerequisites

✅ Docker: v24.0.6 (installed)
✅ Docker Compose: v2.35.1 (installed)

---

## Quick Start - Testing MariaDB Only (Default)

Our Phase 1 & 2 changes are backward-compatible and work with MariaDB only by default. Couchbase is optional.

### Step 1: Navigate to Project Directory

```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes
```

### Step 2: Start the Development Environment

```bash
cd .devcontainer
docker compose up -d
```

This will start:
- **Database (db)**: MariaDB with sample data on port 3306
- **Web Server (web)**: PHP application on port 7777

### Step 3: Wait for Services to Initialize

```bash
# Check if services are running
docker compose ps

# Watch logs (Ctrl+C to exit)
docker compose logs -f web
```

Wait until you see logs indicating the web server has started (usually "apache started" or similar).

### Step 4: Access the Application

Open your browser and navigate to:

**Main URL**: http://localhost:7777

**Expected Result**: You should see the OpenEyes login page.

### Step 5: Verify No Errors

Check that the application loaded without PHP errors. Our Phase 1 & 2 changes should not cause any issues since:
- ✅ Couchbase component is optional (disabled by default)
- ✅ Database adapter defaults to MariaDB
- ✅ All feature flags are disabled by default

### Step 6: Check Application Logs

```bash
# View recent logs
docker compose logs --tail=100 web

# Look for errors
docker compose logs web | grep -i error
docker compose logs web | grep -i warning
```

**Expected**: No PHP fatal errors or warnings related to our new code.

### Step 7: Stop the Environment

```bash
# Stop services
docker compose down

# Or stop and remove volumes (clean slate)
docker compose down -v
```

---

## Advanced Testing - With Couchbase (Optional)

If you want to test the Couchbase integration:

### Step 1: Start Both Environments

```bash
# Terminal 1: Start main OpenEyes
cd /Users/asahu/Desktop/OpenEyes/openeyes/.devcontainer
docker compose up -d

# Terminal 2: Start Couchbase
cd /Users/asahu/Desktop/OpenEyes/openeyes
docker compose -f docker-compose.couchbase.yml up -d
```

### Step 2: Initialize Couchbase

```bash
# Wait for Couchbase to start (about 30 seconds)
sleep 30

# Run setup scripts
docker compose -f docker-compose.couchbase.yml exec couchbase /var/www/openeyes/protected/scripts/couchbase/setup-all.sh
```

### Step 3: Enable Couchbase in OpenEyes

Add these environment variables to `.devcontainer/.env`:

```bash
# Couchbase Configuration
COUCHBASE_HOST=host.docker.internal
COUCHBASE_USER=Administrator
COUCHBASE_PASSWORD=password
COUCHBASE_BUCKET=openeyes
COUCHBASE_ENABLED=TRUE
```

### Step 4: Restart OpenEyes Web Server

```bash
cd .devcontainer
docker compose restart web
```

### Step 5: Verify Couchbase Health

**Health Check URL**: http://localhost:7777/CouchbaseHealth/index

**Expected JSON Response**:
```json
{
  "status": "healthy",
  "couchbase": {
    "enabled": true,
    "connected": true,
    "host": "host.docker.internal",
    "bucket": "openeyes"
  }
}
```

### Step 6: Check Couchbase Ping

**Ping URL**: http://localhost:7777/CouchbaseHealth/ping

**Expected JSON Response**:
```json
{
  "status": "success",
  "message": "Couchbase connection successful",
  "timestamp": "2025-12-19T..."
}
```

---

## Testing Phase 2 Database Adapters

### Test 1: Verify Adapter Factory

Create a test script: `test-adapter.php`

```php
<?php
// Bootstrap Yii
require_once(__DIR__ . '/protected/yiic.php');

echo "=== Testing Database Adapter Factory ===\n\n";

// Test 1: Get default adapter
$adapter = \OE\Database\DatabaseAdapterFactory::getAdapter();
echo "Default adapter: " . get_class($adapter) . "\n";

// Test 2: Check configuration
echo "Database adapter setting: " . Yii::app()->params['database_adapter'] . "\n";
echo "Dual-write enabled: " . (Yii::app()->params['enable_dual_write'] ? 'true' : 'false') . "\n";
echo "Couchbase read enabled: " . (Yii::app()->params['enable_couchbase_read'] ? 'true' : 'false') . "\n";

// Test 3: Count users
$count = $adapter->count('user');
echo "Total users in database: " . $count . "\n";

// Test 4: Find a user
$user = $adapter->findByPk('user', 1);
if ($user) {
    echo "Found user ID 1: " . $user['username'] . "\n";
}

echo "\n=== Tests Complete ===\n";
```

Run the test:

```bash
docker compose exec web php /var/www/openeyes/test-adapter.php
```

**Expected Output**:
```
=== Testing Database Adapter Factory ===

Default adapter: OE\Database\MariaDbAdapter
Database adapter setting: mariadb
Dual-write enabled: false
Couchbase read enabled: false
Total users in database: [some number]
Found user ID 1: [username]

=== Tests Complete ===
```

### Test 2: Verify No Breaking Changes

The most important test is that existing functionality still works:

1. ✅ Application loads without errors
2. ✅ Login page displays correctly
3. ✅ Database queries work normally
4. ✅ No PHP warnings or errors in logs

---

## Troubleshooting

### Issue: Port 7777 Already in Use

```bash
# Find what's using port 7777
lsof -i :7777

# Kill the process or change port in docker-compose.yml
```

### Issue: Database Connection Failed

```bash
# Check if database container is running
docker compose ps

# Check database logs
docker compose logs db

# Restart database
docker compose restart db
```

### Issue: Permission Denied on Volumes

```bash
# Fix permissions
sudo chown -R $USER:$USER /Users/asahu/Desktop/OpenEyes/openeyes/protected/runtime
sudo chmod -R 777 /Users/asahu/Desktop/OpenEyes/openeyes/protected/runtime
```

### Issue: Composer Dependencies Missing

```bash
# Install dependencies
docker compose exec web composer install
```

### Issue: Couchbase Connection Failed

```bash
# Check if Couchbase is running
docker compose -f docker-compose.couchbase.yml ps

# Check Couchbase logs
docker compose -f docker-compose.couchbase.yml logs couchbase

# Verify Couchbase is accessible
curl http://localhost:8091
```

### Issue: PHP Errors with New Code

```bash
# Check for syntax errors in our new files
docker compose exec web find /var/www/openeyes/protected/components/database -name "*.php" -exec php -l {} \;

# View detailed error logs
docker compose logs web | grep "Fatal error"
docker compose logs web | grep "Parse error"
```

---

## Verification Checklist

### Phase 1: Couchbase Infrastructure
- [ ] Couchbase container starts successfully (if enabled)
- [ ] Couchbase Web UI accessible at http://localhost:8091 (if enabled)
- [ ] CouchbaseConnection component loads without errors
- [ ] Health check endpoints respond correctly

### Phase 2: Abstract Database Layer
- [ ] Application starts without PHP errors
- [ ] DatabaseAdapterFactory returns MariaDbAdapter by default
- [ ] Existing database queries work normally
- [ ] Configuration parameters are set correctly
- [ ] No breaking changes to existing functionality

### General
- [ ] Login page loads correctly
- [ ] No PHP fatal errors in logs
- [ ] No warnings related to our new code
- [ ] Application behaves normally with default settings

---

## Expected Results Summary

### With Default Configuration (MariaDB Only)

| Test | Expected Result | Status |
|------|----------------|--------|
| Application starts | ✅ No errors | - |
| Login page loads | ✅ Displays correctly | - |
| Database queries work | ✅ Normal operation | - |
| No PHP errors | ✅ Clean logs | - |
| Adapter factory returns | MariaDbAdapter | - |
| Feature flags | All disabled | - |

### With Couchbase Enabled

| Test | Expected Result | Status |
|------|----------------|--------|
| Couchbase starts | ✅ Container running | - |
| Health check responds | ✅ JSON with status | - |
| Ping endpoint works | ✅ Connection successful | - |
| Buckets created | ✅ openeyes, openeyes_test | - |
| Scopes created | ✅ 6 scopes | - |
| Collections created | ✅ 30+ collections | - |

---

## Next Steps After Verification

Once you've verified the application runs correctly:

1. **Code Review**: Review the implemented code for any issues
2. **Run Unit Tests**: Execute the test suite (when test environment is set up)
3. **Performance Testing**: Check if there's any performance impact
4. **Documentation**: Update any internal documentation
5. **Plan Phase 3**: Begin Data Modeling & Schema Translation

---

## Useful Commands Reference

```bash
# Start environment
cd .devcontainer && docker compose up -d

# Stop environment
docker compose down

# View logs
docker compose logs -f web

# Execute command in web container
docker compose exec web [command]

# Restart web server
docker compose restart web

# Check running containers
docker compose ps

# Access web container shell
docker compose exec web bash

# Check PHP version
docker compose exec web php -v

# Run PHP file
docker compose exec web php /var/www/openeyes/[file.php]
```

---

## Important Notes

1. **Backward Compatibility**: All our changes default to MariaDB-only mode, ensuring no breaking changes.

2. **Optional Couchbase**: Couchbase is completely optional. The application works perfectly without it.

3. **Feature Flags**: All migration features are disabled by default and controlled via environment variables.

4. **Zero Impact**: With default settings, there should be absolutely no change in application behavior.

5. **Safe Testing**: You can test with confidence knowing that rollback is always available.

---

**Last Updated**: December 19, 2025  
**Tested On**: Docker v24.0.6, Docker Compose v2.35.1  
**Status**: Ready for local testing
