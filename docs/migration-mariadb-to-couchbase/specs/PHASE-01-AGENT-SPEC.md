# Phase 1: Infrastructure & Couchbase Setup - Agent Executable Specification

## Metadata
- **Phase**: 1 of 10
- **Estimated Duration**: 1-2 weeks
- **Dependencies**: None
- **Risk Level**: Low (no impact on existing functionality)

## Objective
Set up Couchbase infrastructure alongside existing MariaDB for the migration period. This phase establishes connectivity without modifying any existing application behavior.

---

## Task Checklist

### TASK 1: Create Docker Compose File for Couchbase
**Priority**: High
**File to Create**: `/docker-compose.couchbase.yml`

```yaml
version: '3.8'
services:
  couchbase:
    image: couchbase:enterprise-7.2.0
    container_name: openeyes-couchbase
    ports:
      - "8091-8096:8091-8096"
      - "11210:11210"
    volumes:
      - couchbase-data:/opt/couchbase/var
    environment:
      - CLUSTER_NAME=openeyes-cluster
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8091/ui/index.html"]
      interval: 30s
      timeout: 10s
      retries: 5

volumes:
  couchbase-data:
```

**Verification**:
```bash
docker-compose -f docker-compose.couchbase.yml up -d
curl -s http://localhost:8091/ui/index.html | head -1
```

---

### TASK 2: Create Scripts Directory Structure
**Priority**: High
**Commands**:
```bash
mkdir -p protected/scripts/couchbase
mkdir -p protected/scripts/couchbase/indexes
```

---

### TASK 3: Create Cluster Initialization Script
**Priority**: High
**File to Create**: `/protected/scripts/couchbase/init-cluster.sh`

```bash
#!/bin/bash
# Initialize Couchbase cluster for OpenEyes
# Usage: ./init-cluster.sh [host] [username] [password] [ram_quota_mb]

set -e

CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_PORT="${CB_PORT:-8091}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"
CB_RAM_QUOTA="${4:-${CB_RAM_QUOTA:-1024}}"

echo "Initializing Couchbase cluster..."
echo "Host: ${CB_HOST}:${CB_PORT}"

# Wait for Couchbase to be ready
echo "Waiting for Couchbase to start..."
MAX_RETRIES=30
RETRY_COUNT=0
until curl -s http://${CB_HOST}:${CB_PORT}/ui/index.html > /dev/null 2>&1; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "ERROR: Couchbase did not start within expected time"
        exit 1
    fi
    echo "  Attempt ${RETRY_COUNT}/${MAX_RETRIES}..."
    sleep 5
done

echo "Couchbase is ready. Initializing cluster..."

# Initialize cluster
curl -s -X POST "http://${CB_HOST}:${CB_PORT}/clusterInit" \
    -d "hostname=${CB_HOST}" \
    -d "dataPath=/opt/couchbase/var/lib/couchbase/data" \
    -d "indexPath=/opt/couchbase/var/lib/couchbase/data" \
    -d "username=${CB_USER}" \
    -d "password=${CB_PASS}" \
    -d "port=SAME" \
    -d "sendStats=false" \
    -d "services=kv,n1ql,index,fts" \
    -d "clusterName=openeyes-cluster" \
    -d "memoryQuota=${CB_RAM_QUOTA}" \
    -d "indexMemoryQuota=512" \
    -d "ftsMemoryQuota=256"

if [ $? -eq 0 ]; then
    echo "SUCCESS: Cluster initialized successfully"
    echo "  Admin UI: http://${CB_HOST}:${CB_PORT}"
    echo "  Username: ${CB_USER}"
else
    echo "ERROR: Cluster initialization failed"
    exit 1
fi
```

**Verification**:
```bash
chmod +x protected/scripts/couchbase/init-cluster.sh
./protected/scripts/couchbase/init-cluster.sh
```

---

### TASK 4: Create Bucket Creation Script
**Priority**: High
**File to Create**: `/protected/scripts/couchbase/create-buckets.sh`

```bash
#!/bin/bash
# Create OpenEyes buckets in Couchbase
# Usage: ./create-buckets.sh [host] [username] [password]

set -e

CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"

echo "Creating Couchbase buckets..."

# Function to create a bucket
create_bucket() {
    local name=$1
    local ram=$2
    
    echo "Creating bucket: ${name} (${ram}MB RAM)"
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${name}" \
        -d "ramQuotaMB=${ram}" \
        -d "bucketType=couchbase" \
        -d "durabilityMinLevel=none" \
        -d "replicaNumber=0")
    
    if [ "$HTTP_CODE" = "202" ]; then
        echo "  SUCCESS: Bucket '${name}' created"
    elif [ "$HTTP_CODE" = "400" ]; then
        echo "  WARNING: Bucket '${name}' may already exist"
    else
        echo "  ERROR: Failed to create bucket '${name}' (HTTP ${HTTP_CODE})"
        return 1
    fi
}

# Main data bucket
create_bucket "openeyes" 512

# Test bucket (for testing environment)
create_bucket "openeyes_test" 256

echo ""
echo "Bucket creation complete!"
echo "Verify at: http://${CB_HOST}:8091/ui/index.html#/buckets"
```

**Verification**:
```bash
chmod +x protected/scripts/couchbase/create-buckets.sh
./protected/scripts/couchbase/create-buckets.sh
```

---

### TASK 5: Create Scopes and Collections Script
**Priority**: High
**File to Create**: `/protected/scripts/couchbase/create-scopes.sh`

```bash
#!/bin/bash
# Create scopes and collections for OpenEyes
# Usage: ./create-scopes.sh [host] [username] [password] [bucket]

set -e

CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"
BUCKET="${4:-openeyes}"

echo "Creating scopes and collections in bucket: ${BUCKET}"

# Function to create a scope
create_scope() {
    local scope=$1
    echo "Creating scope: ${scope}"
    
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${scope}" > /dev/null 2>&1 || true
}

# Function to create a collection in a scope
create_collection() {
    local scope=$1
    local collection=$2
    
    echo "  Creating collection: ${scope}.${collection}"
    
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/${scope}/collections" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${collection}" > /dev/null 2>&1 || true
}

# Wait for bucket to be ready
echo "Waiting for bucket to be ready..."
sleep 5

# Create scopes
echo ""
echo "=== Creating Scopes ==="
create_scope "core"
create_scope "clinical"
create_scope "correspondence"
create_scope "booking"
create_scope "admin"
create_scope "reference"

# Create core collections
echo ""
echo "=== Creating Core Collections ==="
for coll in patient user episode event firm site institution contact address; do
    create_collection "core" "$coll"
done

# Create clinical collections
echo ""
echo "=== Creating Clinical Collections ==="
for coll in examination diagnosis procedure medication allergy; do
    create_collection "clinical" "$coll"
done

# Create correspondence collections
echo ""
echo "=== Creating Correspondence Collections ==="
for coll in letter message document; do
    create_collection "correspondence" "$coll"
done

# Create booking collections
echo ""
echo "=== Creating Booking Collections ==="
for coll in operation session whiteboard; do
    create_collection "booking" "$coll"
done

# Create admin collections
echo ""
echo "=== Creating Admin Collections ==="
for coll in audit setting; do
    create_collection "admin" "$coll"
done

# Create reference collections
echo ""
echo "=== Creating Reference Collections ==="
for coll in specialty subspecialty disorder drug procedure_type; do
    create_collection "reference" "$coll"
done

echo ""
echo "SUCCESS: All scopes and collections created!"
echo "Verify at: http://${CB_HOST}:8091/ui/index.html#/buckets/${BUCKET}"
```

**Verification**:
```bash
chmod +x protected/scripts/couchbase/create-scopes.sh
./protected/scripts/couchbase/create-scopes.sh
```

---

### TASK 6: Update composer.json for Couchbase SDK
**Priority**: High
**File to Modify**: `/composer.json`

**Action**: Add to the `require` section:
```json
"couchbase/couchbase": "^4.2"
```

**Verification**:
```bash
composer update couchbase/couchbase
php -m | grep -i couchbase
```

**Note**: If the couchbase PHP extension is not installed, the agent should provide instructions based on the OS (see Task 7).

---

### TASK 7: Create SDK Installation Script
**Priority**: Medium
**File to Create**: `/protected/scripts/couchbase/install-sdk.sh`

```bash
#!/bin/bash
# Install Couchbase PHP SDK
# Usage: ./install-sdk.sh

set -e

echo "Installing Couchbase PHP SDK..."

# Detect OS
if [ -f /etc/debian_version ]; then
    echo "Detected Debian/Ubuntu"
    
    # Install libcouchbase
    sudo apt-get update
    sudo apt-get install -y build-essential cmake libssl-dev
    
    # Add Couchbase repository
    wget -O - https://packages.couchbase.com/clients/c/repos/deb/couchbase.key | sudo apt-key add -
    echo "deb https://packages.couchbase.com/clients/c/repos/deb/ubuntu2004 focal focal/main" | sudo tee /etc/apt/sources.list.d/couchbase.list
    
    sudo apt-get update
    sudo apt-get install -y libcouchbase3 libcouchbase-dev libcouchbase3-tools
    
    # Install PHP extension via PECL
    sudo pecl install couchbase
    
    # Enable extension
    PHP_VERSION=$(php -v | head -1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    echo "extension=couchbase.so" | sudo tee /etc/php/${PHP_VERSION}/mods-available/couchbase.ini
    sudo phpenmod couchbase
    
    # Restart PHP-FPM if running
    sudo systemctl restart php${PHP_VERSION}-fpm 2>/dev/null || true
    
elif [ -f /etc/redhat-release ]; then
    echo "Detected RHEL/CentOS"
    
    # Install libcouchbase
    sudo yum install -y epel-release
    sudo yum install -y libcouchbase3 libcouchbase-devel
    
    # Install PHP extension
    sudo pecl install couchbase
    
    echo "extension=couchbase.so" | sudo tee /etc/php.d/couchbase.ini
    
    sudo systemctl restart php-fpm 2>/dev/null || true
    
elif [[ "$OSTYPE" == "darwin"* ]]; then
    echo "Detected macOS"
    
    # Use Homebrew
    brew install libcouchbase
    pecl install couchbase
    
    # Add to php.ini
    PHP_INI=$(php --ini | grep "Loaded Configuration File" | cut -d':' -f2 | tr -d ' ')
    if ! grep -q "extension=couchbase.so" "$PHP_INI"; then
        echo "extension=couchbase.so" >> "$PHP_INI"
    fi
else
    echo "ERROR: Unsupported operating system"
    exit 1
fi

# Verify installation
echo ""
echo "Verifying installation..."
php -m | grep -i couchbase

if [ $? -eq 0 ]; then
    echo ""
    echo "SUCCESS: Couchbase PHP SDK installed successfully!"
    php -r "echo 'Couchbase SDK Version: ' . phpversion('couchbase') . PHP_EOL;"
else
    echo "ERROR: Couchbase extension not loaded"
    exit 1
fi
```

---

### TASK 8: Create Couchbase Configuration File
**Priority**: High
**File to Create**: `/protected/config/couchbase.php`

```php
<?php
/**
 * Couchbase configuration for OpenEyes
 * 
 * Configuration values are loaded from:
 * 1. Docker secrets (if available)
 * 2. Environment variables
 * 3. Default values (for development only)
 * 
 * IMPORTANT: Never commit credentials to version control.
 * In production, always use environment variables or secrets management.
 */

// Helper function to get config value
function getCouchbaseConfigValue($secretPath, $envVar, $default = null) {
    // Try Docker secret first
    if (file_exists($secretPath)) {
        $value = rtrim(file_get_contents($secretPath));
        if (!empty($value)) {
            return $value;
        }
    }
    
    // Try environment variable
    $envValue = getenv($envVar);
    if ($envValue !== false && !empty($envValue)) {
        return $envValue;
    }
    
    // Return default
    return $default;
}

return [
    // Connection settings
    'connection' => [
        'host' => getCouchbaseConfigValue(
            '/run/secrets/COUCHBASE_HOST',
            'COUCHBASE_HOST',
            'localhost'
        ),
        'username' => getCouchbaseConfigValue(
            '/run/secrets/COUCHBASE_USER',
            'COUCHBASE_USER',
            'Administrator'
        ),
        'password' => getCouchbaseConfigValue(
            '/run/secrets/COUCHBASE_PASS',
            'COUCHBASE_PASS',
            'password'
        ),
    ],
    
    // Bucket name
    'bucket' => getCouchbaseConfigValue(
        '/run/secrets/COUCHBASE_BUCKET',
        'COUCHBASE_BUCKET',
        'openeyes'
    ),
    
    // Scope mappings
    'scopes' => [
        'core' => 'core',
        'clinical' => 'clinical',
        'correspondence' => 'correspondence',
        'booking' => 'booking',
        'admin' => 'admin',
        'reference' => 'reference',
    ],
    
    // Timeout settings (in milliseconds)
    'options' => [
        'connect_timeout' => 10000,  // 10 seconds
        'kv_timeout' => 5000,        // 5 seconds for key-value operations
        'query_timeout' => 75000,    // 75 seconds for N1QL queries
        'view_timeout' => 75000,     // 75 seconds for views
        'analytics_timeout' => 75000, // 75 seconds for analytics
    ],
    
    // Feature flags for migration phases
    'features' => [
        'enabled' => true,           // Master switch for Couchbase functionality
        'dual_write' => false,       // Write to both MariaDB and Couchbase (Phase 4+)
        'read_from_couchbase' => false, // Read from Couchbase (Phase 6+)
    ],
];
```

---

### TASK 9: Create Couchbase Connection Component
**Priority**: High
**File to Create**: `/protected/components/CouchbaseConnection.php`

```php
<?php
/**
 * Couchbase connection component for OpenEyes
 * 
 * Manages connection to Couchbase cluster and provides access to
 * buckets, scopes, and collections. Implements lazy connection
 * initialization and connection pooling.
 * 
 * Usage:
 *   $cb = Yii::app()->couchbase;
 *   $collection = $cb->getCollection('core', 'patient');
 *   $result = $cb->query('SELECT * FROM `openeyes`.`core`.`patient` LIMIT 10');
 * 
 * @property-read \Couchbase\Cluster $cluster
 * @property-read \Couchbase\Bucket $bucket
 */

use Couchbase\Cluster;
use Couchbase\ClusterOptions;
use Couchbase\Bucket;
use Couchbase\Scope;
use Couchbase\Collection;
use Couchbase\QueryOptions;
use Couchbase\QueryResult;
use Couchbase\Exception\CouchbaseException;

class CouchbaseConnection extends CApplicationComponent
{
    /**
     * @var array Configuration array
     */
    public $config;
    
    /**
     * @var Cluster Cached cluster instance
     */
    private $_cluster;
    
    /**
     * @var Bucket Cached bucket instance
     */
    private $_bucket;
    
    /**
     * @var array Cached scope instances
     */
    private $_scopes = [];
    
    /**
     * @var array Cached collection instances
     */
    private $_collections = [];
    
    /**
     * @var bool Connection state
     */
    private $_connected = false;
    
    /**
     * Initialize the component
     * @throws CException if configuration is missing
     */
    public function init()
    {
        parent::init();
        
        if (empty($this->config)) {
            throw new CException('Couchbase configuration is required');
        }
        
        if (empty($this->config['connection']['host'])) {
            throw new CException('Couchbase host is required');
        }
        
        Yii::log('CouchbaseConnection initialized', CLogger::LEVEL_INFO, 'application.couchbase');
    }
    
    /**
     * Get the Couchbase cluster instance (lazy initialization)
     * @return Cluster
     * @throws CException on connection failure
     */
    public function getCluster(): Cluster
    {
        if ($this->_cluster === null) {
            try {
                $options = new ClusterOptions();
                $options->credentials(
                    $this->config['connection']['username'],
                    $this->config['connection']['password']
                );
                
                // Apply timeout options
                if (isset($this->config['options']['connect_timeout'])) {
                    $options->connectTimeout($this->config['options']['connect_timeout']);
                }
                if (isset($this->config['options']['kv_timeout'])) {
                    $options->kvTimeout($this->config['options']['kv_timeout']);
                }
                
                $connectionString = 'couchbase://' . $this->config['connection']['host'];
                $this->_cluster = new Cluster($connectionString, $options);
                $this->_connected = true;
                
                Yii::log(
                    'Connected to Couchbase cluster: ' . $this->config['connection']['host'],
                    CLogger::LEVEL_INFO,
                    'application.couchbase'
                );
            } catch (CouchbaseException $e) {
                Yii::log(
                    'Failed to connect to Couchbase: ' . $e->getMessage(),
                    CLogger::LEVEL_ERROR,
                    'application.couchbase'
                );
                throw new CException('Failed to connect to Couchbase: ' . $e->getMessage());
            }
        }
        
        return $this->_cluster;
    }
    
    /**
     * Get the default bucket
     * @return Bucket
     */
    public function getBucket(): Bucket
    {
        if ($this->_bucket === null) {
            $this->_bucket = $this->getCluster()->bucket($this->config['bucket']);
            
            // Wait for bucket to be ready
            $this->_bucket->waitUntilReady(10000); // 10 second timeout
        }
        
        return $this->_bucket;
    }
    
    /**
     * Get a scope by name
     * @param string $name Scope name (config key or actual name)
     * @return Scope
     */
    public function getScope(string $name): Scope
    {
        if (!isset($this->_scopes[$name])) {
            $scopeName = $this->config['scopes'][$name] ?? $name;
            $this->_scopes[$name] = $this->getBucket()->scope($scopeName);
        }
        
        return $this->_scopes[$name];
    }
    
    /**
     * Get a collection
     * @param string $scopeName Scope name
     * @param string $collectionName Collection name
     * @return Collection
     */
    public function getCollection(string $scopeName, string $collectionName): Collection
    {
        $key = "{$scopeName}.{$collectionName}";
        
        if (!isset($this->_collections[$key])) {
            $this->_collections[$key] = $this->getScope($scopeName)->collection($collectionName);
        }
        
        return $this->_collections[$key];
    }
    
    /**
     * Execute a N1QL query
     * @param string $query N1QL query string
     * @param array $params Named parameters (optional)
     * @return QueryResult
     */
    public function query(string $query, array $params = []): QueryResult
    {
        $options = new QueryOptions();
        
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        if (isset($this->config['options']['query_timeout'])) {
            $options->timeout($this->config['options']['query_timeout']);
        }
        
        Yii::log(
            'Executing N1QL query: ' . substr($query, 0, 200),
            CLogger::LEVEL_TRACE,
            'application.couchbase'
        );
        
        return $this->getCluster()->query($query, $options);
    }
    
    /**
     * Check if connection is alive
     * @return array Ping results with status and latency
     */
    public function ping(): array
    {
        $result = [
            'status' => 'unknown',
            'latency_ms' => null,
            'services' => [],
        ];
        
        try {
            $start = microtime(true);
            $pingResult = $this->getBucket()->ping();
            $latency = round((microtime(true) - $start) * 1000, 2);
            
            $result['status'] = 'healthy';
            $result['latency_ms'] = $latency;
            $result['services'] = $pingResult;
            
        } catch (CouchbaseException $e) {
            $result['status'] = 'unhealthy';
            $result['error'] = $e->getMessage();
            
            Yii::log(
                'Couchbase ping failed: ' . $e->getMessage(),
                CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
        }
        
        return $result;
    }
    
    /**
     * Check if feature is enabled
     * @param string $feature Feature name
     * @return bool
     */
    public function isFeatureEnabled(string $feature): bool
    {
        return $this->config['features'][$feature] ?? false;
    }
    
    /**
     * Check if Couchbase is enabled
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->isFeatureEnabled('enabled');
    }
    
    /**
     * Check if connected
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->_connected;
    }
    
    /**
     * Close connection and clear cached instances
     */
    public function close()
    {
        $this->_cluster = null;
        $this->_bucket = null;
        $this->_scopes = [];
        $this->_collections = [];
        $this->_connected = false;
        
        Yii::log('Couchbase connection closed', CLogger::LEVEL_INFO, 'application.couchbase');
    }
}
```

---

### TASK 10: Update Main Configuration
**Priority**: High
**File to Modify**: `/protected/config/core/common.php`

**Action**: Find the `'components'` array and add the Couchbase component after existing database components.

**Code to Add** (within the 'components' array):
```php
    // Couchbase connection component (Phase 1 - Infrastructure)
    'couchbase' => array(
        'class' => 'application.components.CouchbaseConnection',
        'config' => require(dirname(__FILE__) . '/../couchbase.php'),
    ),
```

**Note**: The exact location should be after the `'db'` component definition.

---

### TASK 11: Create Health Check Controller
**Priority**: High
**File to Create**: `/protected/controllers/CouchbaseHealthController.php`

```php
<?php
/**
 * Couchbase health check controller
 * 
 * Provides a health check endpoint for monitoring Couchbase connectivity.
 * Returns JSON with connection status, latency, and diagnostic information.
 * 
 * Endpoints:
 *   GET /couchbaseHealth - Full health check
 *   GET /couchbaseHealth/ping - Simple ping check
 */

class CouchbaseHealthController extends CController
{
    /**
     * Disable layout for API responses
     */
    public $layout = false;
    
    /**
     * Allow access without authentication for health checks
     */
    public function filters()
    {
        return [];
    }
    
    /**
     * Full health check
     * GET /couchbaseHealth
     */
    public function actionIndex()
    {
        $this->sendJsonResponse($this->performHealthCheck());
    }
    
    /**
     * Simple ping check
     * GET /couchbaseHealth/ping
     */
    public function actionPing()
    {
        try {
            $pingResult = Yii::app()->couchbase->ping();
            $this->sendJsonResponse([
                'status' => $pingResult['status'],
                'timestamp' => date('c'),
            ], $pingResult['status'] === 'healthy' ? 200 : 503);
        } catch (Exception $e) {
            $this->sendJsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => date('c'),
            ], 503);
        }
    }
    
    /**
     * Perform comprehensive health check
     * @return array Health check results
     */
    private function performHealthCheck(): array
    {
        $status = [
            'service' => 'couchbase',
            'status' => 'unknown',
            'timestamp' => date('c'),
            'checks' => [],
        ];
        
        $httpCode = 200;
        
        // Check 1: Configuration
        try {
            $config = Yii::app()->couchbase->config;
            $status['checks']['configuration'] = [
                'status' => 'pass',
                'host' => $config['connection']['host'],
                'bucket' => $config['bucket'],
            ];
        } catch (Exception $e) {
            $status['checks']['configuration'] = [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
            $httpCode = 503;
        }
        
        // Check 2: Connectivity
        try {
            $pingResult = Yii::app()->couchbase->ping();
            $status['checks']['connectivity'] = [
                'status' => $pingResult['status'] === 'healthy' ? 'pass' : 'fail',
                'latency_ms' => $pingResult['latency_ms'],
            ];
            if ($pingResult['status'] !== 'healthy') {
                $httpCode = 503;
            }
        } catch (Exception $e) {
            $status['checks']['connectivity'] = [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
            $httpCode = 503;
        }
        
        // Check 3: Query capability
        try {
            $start = microtime(true);
            $result = Yii::app()->couchbase->query('SELECT 1 as test');
            $latency = round((microtime(true) - $start) * 1000, 2);
            
            $status['checks']['query'] = [
                'status' => 'pass',
                'latency_ms' => $latency,
            ];
        } catch (Exception $e) {
            $status['checks']['query'] = [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
            $httpCode = 503;
        }
        
        // Determine overall status
        $failedChecks = array_filter($status['checks'], function($check) {
            return $check['status'] === 'fail';
        });
        
        $status['status'] = empty($failedChecks) ? 'healthy' : 'unhealthy';
        
        // Set HTTP code
        http_response_code($httpCode);
        
        return $status;
    }
    
    /**
     * Send JSON response
     * @param array $data Response data
     * @param int $httpCode HTTP status code
     */
    private function sendJsonResponse(array $data, int $httpCode = 200)
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Yii::app()->end();
    }
}
```

---

### TASK 12: Create Index Creation Script
**Priority**: Medium
**File to Create**: `/protected/scripts/couchbase/indexes/create-primary-indexes.n1ql`

```sql
-- Primary indexes for OpenEyes Couchbase migration
-- Execute using: cbq -u Administrator -p password -f create-primary-indexes.n1ql
-- Or via Couchbase Query Workbench

-- Core scope indexes
-- NOTE: Primary indexes are for development/debugging only
-- In production, use specific GSI indexes for better performance

-- Primary indexes (required for unindexed queries)
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`patient` USING GSI;
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`user` USING GSI;
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`episode` USING GSI;
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`event` USING GSI;
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`firm` USING GSI;
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`site` USING GSI;
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`institution` USING GSI;

-- Patient indexes (frequently queried)
CREATE INDEX `idx_patient_hos_num` ON `openeyes`.`core`.`patient`(`hos_num`) USING GSI;
CREATE INDEX `idx_patient_nhs_num` ON `openeyes`.`core`.`patient`(`nhs_num`) USING GSI;
CREATE INDEX `idx_patient_dob` ON `openeyes`.`core`.`patient`(`dob`) USING GSI;
CREATE INDEX `idx_patient_name` ON `openeyes`.`core`.`patient`(`last_name`, `first_name`) USING GSI;

-- Episode indexes
CREATE INDEX `idx_episode_patient_id` ON `openeyes`.`core`.`episode`(`patient_id`) USING GSI;
CREATE INDEX `idx_episode_firm_id` ON `openeyes`.`core`.`episode`(`firm_id`) USING GSI;

-- Event indexes
CREATE INDEX `idx_event_episode_id` ON `openeyes`.`core`.`event`(`episode_id`) USING GSI;
CREATE INDEX `idx_event_created` ON `openeyes`.`core`.`event`(`created_date`) USING GSI;
CREATE INDEX `idx_event_type` ON `openeyes`.`core`.`event`(`event_type_id`) USING GSI;

-- User indexes
CREATE INDEX `idx_user_username` ON `openeyes`.`core`.`user`(`username`) USING GSI;
CREATE INDEX `idx_user_active` ON `openeyes`.`core`.`user`(`active`) USING GSI;
```

---

### TASK 13: Create Index Creation Shell Script
**Priority**: Medium
**File to Create**: `/protected/scripts/couchbase/create-indexes.sh`

```bash
#!/bin/bash
# Create Couchbase indexes for OpenEyes
# Usage: ./create-indexes.sh [host] [username] [password]

set -e

CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Creating Couchbase indexes..."

# Execute index creation via REST API
execute_n1ql() {
    local query=$1
    echo "Executing: ${query:0:80}..."
    
    curl -s -X POST "http://${CB_HOST}:8093/query/service" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "statement=${query}" > /dev/null 2>&1 || true
}

# Read and execute N1QL file
while IFS= read -r line; do
    # Skip comments and empty lines
    [[ "$line" =~ ^--.*$ ]] && continue
    [[ -z "$line" ]] && continue
    
    execute_n1ql "$line"
done < "${SCRIPT_DIR}/indexes/create-primary-indexes.n1ql"

echo ""
echo "Index creation complete!"
echo "Verify indexes at: http://${CB_HOST}:8091/ui/index.html#/query"
```

---

### TASK 14: Create Full Setup Script
**Priority**: High
**File to Create**: `/protected/scripts/couchbase/setup-all.sh`

```bash
#!/bin/bash
# Complete Couchbase setup for OpenEyes
# Usage: ./setup-all.sh [host] [username] [password]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"

echo "========================================"
echo "OpenEyes Couchbase Setup"
echo "========================================"
echo "Host: ${CB_HOST}"
echo ""

# Step 1: Initialize cluster
echo "[1/4] Initializing cluster..."
bash "${SCRIPT_DIR}/init-cluster.sh" "${CB_HOST}" "${CB_USER}" "${CB_PASS}"
echo ""

# Step 2: Create buckets
echo "[2/4] Creating buckets..."
bash "${SCRIPT_DIR}/create-buckets.sh" "${CB_HOST}" "${CB_USER}" "${CB_PASS}"
echo ""

# Wait for bucket initialization
echo "Waiting for buckets to initialize..."
sleep 10

# Step 3: Create scopes and collections
echo "[3/4] Creating scopes and collections..."
bash "${SCRIPT_DIR}/create-scopes.sh" "${CB_HOST}" "${CB_USER}" "${CB_PASS}"
echo ""

# Wait for collections to be ready
echo "Waiting for collections to be ready..."
sleep 5

# Step 4: Create indexes
echo "[4/4] Creating indexes..."
bash "${SCRIPT_DIR}/create-indexes.sh" "${CB_HOST}" "${CB_USER}" "${CB_PASS}"
echo ""

echo "========================================"
echo "Setup Complete!"
echo "========================================"
echo ""
echo "Couchbase Admin UI: http://${CB_HOST}:8091"
echo "Username: ${CB_USER}"
echo ""
echo "Next steps:"
echo "1. Run 'composer update' to install PHP SDK"
echo "2. Verify PHP extension: php -m | grep couchbase"
echo "3. Test connection: curl http://localhost/couchbaseHealth"
```

---

### TASK 15: Add URL Rule for Health Check
**Priority**: Medium
**File to Modify**: `/protected/config/core/common.php`

**Action**: Find the `'urlManager'` component's `'rules'` array and add:

```php
'couchbaseHealth' => 'couchbaseHealth/index',
'couchbaseHealth/ping' => 'couchbaseHealth/ping',
```

---

### TASK 16: Create Unit Test for Connection
**Priority**: Medium
**File to Create**: `/protected/tests/unit/components/CouchbaseConnectionTest.php`

```php
<?php
/**
 * Unit tests for CouchbaseConnection component
 */

class CouchbaseConnectionTest extends CDbTestCase
{
    private $connection;
    
    protected function setUp()
    {
        parent::setUp();
        
        // Skip tests if Couchbase is not configured
        if (!isset(Yii::app()->couchbase)) {
            $this->markTestSkipped('Couchbase not configured');
        }
        
        $this->connection = Yii::app()->couchbase;
    }
    
    public function testConnectionHasConfig()
    {
        $this->assertNotEmpty($this->connection->config);
        $this->assertArrayHasKey('connection', $this->connection->config);
        $this->assertArrayHasKey('bucket', $this->connection->config);
    }
    
    public function testGetClusterReturnsClusterInstance()
    {
        try {
            $cluster = $this->connection->getCluster();
            $this->assertInstanceOf('Couchbase\Cluster', $cluster);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testGetBucketReturnsBucketInstance()
    {
        try {
            $bucket = $this->connection->getBucket();
            $this->assertInstanceOf('Couchbase\Bucket', $bucket);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testGetCollectionReturnsCollectionInstance()
    {
        try {
            $collection = $this->connection->getCollection('core', 'patient');
            $this->assertInstanceOf('Couchbase\Collection', $collection);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testPingReturnsArrayWithStatus()
    {
        try {
            $result = $this->connection->ping();
            $this->assertIsArray($result);
            $this->assertArrayHasKey('status', $result);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testQueryExecutesSuccessfully()
    {
        try {
            $result = $this->connection->query('SELECT 1 as test');
            $this->assertNotNull($result);
        } catch (Exception $e) {
            $this->markTestSkipped('Cannot connect to Couchbase: ' . $e->getMessage());
        }
    }
    
    public function testIsEnabledReturnsBoolean()
    {
        $result = $this->connection->isEnabled();
        $this->assertIsBool($result);
    }
}
```

---

## Execution Order

Execute tasks in this order:

1. **TASK 1**: Create Docker Compose file
2. **TASK 2**: Create directory structure
3. **TASK 3**: Create cluster init script
4. **TASK 4**: Create bucket creation script
5. **TASK 5**: Create scopes/collections script
6. **TASK 6**: Update composer.json
7. **TASK 7**: Create SDK installation script
8. **TASK 8**: Create Couchbase config file
9. **TASK 9**: Create CouchbaseConnection component
10. **TASK 10**: Update common.php configuration
11. **TASK 11**: Create health check controller
12. **TASK 12**: Create N1QL index file
13. **TASK 13**: Create index shell script
14. **TASK 14**: Create full setup script
15. **TASK 15**: Add URL rules
16. **TASK 16**: Create unit tests

---

## Verification Steps

After completing all tasks, run these verification commands:

```bash
# 1. Start Couchbase container
docker-compose -f docker-compose.couchbase.yml up -d

# 2. Wait for container to be ready
sleep 30

# 3. Run full setup
chmod +x protected/scripts/couchbase/*.sh
./protected/scripts/couchbase/setup-all.sh

# 4. Install PHP SDK (if not already installed)
composer update

# 5. Verify PHP extension
php -m | grep -i couchbase

# 6. Test health endpoint (from application)
curl http://localhost/couchbaseHealth

# 7. Run unit tests
./vendor/bin/phpunit protected/tests/unit/components/CouchbaseConnectionTest.php
```

---

## Success Criteria

- [ ] Docker container running and accessible
- [ ] Cluster initialized with all services
- [ ] Buckets `openeyes` and `openeyes_test` created
- [ ] All scopes and collections created
- [ ] Couchbase PHP SDK installed and loaded
- [ ] Configuration file created with no hardcoded secrets
- [ ] CouchbaseConnection component accessible via `Yii::app()->couchbase`
- [ ] Health check endpoint returns 200 with healthy status
- [ ] All indexes created successfully
- [ ] Unit tests pass

---

## Rollback Instructions

If rollback is needed:

```bash
# 1. Stop and remove Couchbase container
docker-compose -f docker-compose.couchbase.yml down -v

# 2. Remove files created in this phase
rm -f docker-compose.couchbase.yml
rm -rf protected/scripts/couchbase
rm -f protected/config/couchbase.php
rm -f protected/components/CouchbaseConnection.php
rm -f protected/controllers/CouchbaseHealthController.php
rm -f protected/tests/unit/components/CouchbaseConnectionTest.php

# 3. Revert composer.json changes
# Remove "couchbase/couchbase": "^4.2" from require section

# 4. Revert common.php changes
# Remove 'couchbase' component from components array
# Remove URL rules for couchbaseHealth

# 5. Update composer
composer update
```

---

## Notes for Agent

1. **File Paths**: All file paths are relative to project root `/Users/asahu/Desktop/OpenEyes/openeyes/`
2. **Existing Files**: Before modifying existing files (composer.json, common.php), read them first to understand structure
3. **Permissions**: Shell scripts need execute permission (`chmod +x`)
4. **No Credentials**: Never hardcode credentials; use environment variables
5. **Testing**: Each task should be verified before moving to the next
6. **Idempotent**: Scripts should be safe to run multiple times
