# Phase 1: Infrastructure & Couchbase Setup

## Overview
This phase establishes the foundational infrastructure required for running Couchbase alongside the existing MariaDB installation during the migration period.

## Prerequisites
- Access to server infrastructure (Docker, VMs, or cloud instances)
- PHP 8.1+ installed or planned upgrade path
- Network access between application servers and database servers
- Sufficient storage for dual-database operation during migration

## Dependencies
- None (this is the first phase)

## Tasks

### 1.1 Couchbase Server Installation

#### 1.1.1 Development Environment Setup
**File**: `docker-compose.couchbase.yml` (create in project root)

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

**Acceptance Criteria**:
- [ ] Couchbase container starts successfully
- [ ] Web console accessible at http://localhost:8091
- [ ] Cluster can be initialized via REST API or console

#### 1.1.2 Cluster Configuration Script
**File**: `/protected/scripts/couchbase/init-cluster.sh`

```bash
#!/bin/bash
# Initialize Couchbase cluster for OpenEyes

CB_HOST="${CB_HOST:-localhost}"
CB_PORT="${CB_PORT:-8091}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"
CB_RAM_QUOTA="${CB_RAM_QUOTA:-1024}"

# Wait for Couchbase to be ready
until curl -s http://${CB_HOST}:${CB_PORT}/ui/index.html > /dev/null; do
    echo "Waiting for Couchbase..."
    sleep 5
done

# Initialize cluster
curl -X POST http://${CB_HOST}:${CB_PORT}/clusterInit \
    -d "hostname=${CB_HOST}" \
    -d "dataPath=/opt/couchbase/var/lib/couchbase/data" \
    -d "indexPath=/opt/couchbase/var/lib/couchbase/data" \
    -d "username=${CB_USER}" \
    -d "password=${CB_PASS}" \
    -d "port=SAME" \
    -d "services=kv,n1ql,index,fts"

echo "Cluster initialized successfully"
```

**Acceptance Criteria**:
- [ ] Script executes without errors
- [ ] Cluster services (KV, N1QL, Index, FTS) are enabled
- [ ] Admin credentials are set

### 1.2 Bucket and Scope Creation

#### 1.2.1 Bucket Structure Design
**File**: `/protected/scripts/couchbase/create-buckets.sh`

```bash
#!/bin/bash
# Create OpenEyes buckets

CB_HOST="${CB_HOST:-localhost}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"

# Main data bucket
curl -X POST http://${CB_HOST}:8091/pools/default/buckets \
    -u ${CB_USER}:${CB_PASS} \
    -d name=openeyes \
    -d ramQuotaMB=512 \
    -d bucketType=couchbase

# Test bucket (for testing environment)
curl -X POST http://${CB_HOST}:8091/pools/default/buckets \
    -u ${CB_USER}:${CB_PASS} \
    -d name=openeyes_test \
    -d ramQuotaMB=256 \
    -d bucketType=couchbase

echo "Buckets created successfully"
```

#### 1.2.2 Scope and Collection Structure
**File**: `/protected/scripts/couchbase/create-scopes.sh`

```bash
#!/bin/bash
# Create scopes and collections mapping to MySQL tables

CB_HOST="${CB_HOST:-localhost}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"
BUCKET="openeyes"

# Define scopes (logical groupings)
SCOPES=(
    "core"           # Core system entities (patient, user, episode, event)
    "clinical"       # Clinical data (examination, diagnosis, procedures)
    "correspondence" # Letters, messages, documents
    "booking"        # Operation booking, scheduling
    "admin"          # Administrative data (settings, audit)
    "reference"      # Reference data (lookup tables)
)

for SCOPE in "${SCOPES[@]}"; do
    curl -X POST "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes" \
        -u ${CB_USER}:${CB_PASS} \
        -d name=${SCOPE}
    echo "Created scope: ${SCOPE}"
done

# Core collections
CORE_COLLECTIONS=("patient" "user" "episode" "event" "firm" "site" "institution" "contact" "address")
for COLL in "${CORE_COLLECTIONS[@]}"; do
    curl -X POST "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/core/collections" \
        -u ${CB_USER}:${CB_PASS} \
        -d name=${COLL}
done

# Clinical collections (to be extended in Phase 3)
CLINICAL_COLLECTIONS=("examination" "diagnosis" "procedure" "medication" "allergy")
for COLL in "${CLINICAL_COLLECTIONS[@]}"; do
    curl -X POST "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/clinical/collections" \
        -u ${CB_USER}:${CB_PASS} \
        -d name=${COLL}
done

echo "Scopes and collections created successfully"
```

**Acceptance Criteria**:
- [ ] All defined scopes exist in the bucket
- [ ] Core collections are created
- [ ] Collections are accessible via SDK

### 1.3 PHP SDK Installation

#### 1.3.1 Composer Package Addition
**File**: `composer.json` (update)

Add to require section:
```json
{
    "require": {
        "couchbase/couchbase": "^4.2"
    }
}
```

**Acceptance Criteria**:
- [ ] `composer update` succeeds
- [ ] Couchbase extension is loaded (`php -m | grep couchbase`)
- [ ] No conflicts with existing dependencies

#### 1.3.2 PHP Extension Installation
**File**: `/protected/scripts/install-couchbase-sdk.sh`

```bash
#!/bin/bash
# Install Couchbase PHP SDK

# For Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y libcouchbase3 libcouchbase-dev

# Install via PECL
sudo pecl install couchbase

# Add to php.ini
echo "extension=couchbase.so" | sudo tee /etc/php/8.1/mods-available/couchbase.ini
sudo phpenmod couchbase

# Restart PHP-FPM
sudo systemctl restart php8.1-fpm

# Verify
php -m | grep couchbase
```

**Acceptance Criteria**:
- [ ] `couchbase` extension appears in `php -m` output
- [ ] No PHP errors when loading extension
- [ ] SDK version is 4.2+

### 1.4 Configuration Files

#### 1.4.1 Couchbase Configuration
**File**: `/protected/config/couchbase.php` (create)

```php
<?php
/**
 * Couchbase configuration for OpenEyes
 */

// Read from environment or docker secrets
$cb_host = getenv('COUCHBASE_HOST') ?: 'localhost';
$cb_user = rtrim(@file_get_contents('/run/secrets/COUCHBASE_USER')) 
    ?: (getenv('COUCHBASE_USER') ?: 'Administrator');
$cb_pass = rtrim(@file_get_contents('/run/secrets/COUCHBASE_PASS')) 
    ?: (getenv('COUCHBASE_PASS') ?: 'password');

return [
    'connection' => [
        'host' => $cb_host,
        'username' => $cb_user,
        'password' => $cb_pass,
    ],
    'bucket' => getenv('COUCHBASE_BUCKET') ?: 'openeyes',
    'scopes' => [
        'core' => 'core',
        'clinical' => 'clinical',
        'correspondence' => 'correspondence',
        'booking' => 'booking',
        'admin' => 'admin',
        'reference' => 'reference',
    ],
    'options' => [
        'connect_timeout' => 10000,
        'kv_timeout' => 5000,
        'query_timeout' => 75000,
    ],
];
```

#### 1.4.2 Update Main Configuration
**File**: `/protected/config/core/common.php` (update)

Add after existing db configuration:
```php
// Couchbase configuration (Phase 1 - Infrastructure)
$couchbase_config = require(dirname(__FILE__) . '/../couchbase.php');

// Add to components array
'components' => array(
    // ... existing components ...
    
    'couchbase' => array(
        'class' => 'application.components.CouchbaseConnection',
        'config' => $couchbase_config,
    ),
),
```

**Acceptance Criteria**:
- [ ] Configuration file loads without errors
- [ ] Secrets are properly read from environment/docker
- [ ] No credentials in version control

### 1.5 Connection Component

#### 1.5.1 Couchbase Connection Class
**File**: `/protected/components/CouchbaseConnection.php` (create)

```php
<?php
/**
 * Couchbase connection component for OpenEyes
 * 
 * This class manages the connection to Couchbase and provides
 * access to buckets, scopes, and collections.
 */

use Couchbase\Cluster;
use Couchbase\ClusterOptions;
use Couchbase\Bucket;
use Couchbase\Scope;
use Couchbase\Collection;

class CouchbaseConnection extends CApplicationComponent
{
    public $config;
    
    private $_cluster;
    private $_bucket;
    private $_scopes = [];
    private $_collections = [];
    
    /**
     * Initialize the Couchbase connection
     */
    public function init()
    {
        parent::init();
        
        if (empty($this->config)) {
            throw new CException('Couchbase configuration is required');
        }
    }
    
    /**
     * Get the Couchbase cluster instance
     * @return Cluster
     */
    public function getCluster(): Cluster
    {
        if ($this->_cluster === null) {
            $options = new ClusterOptions();
            $options->credentials(
                $this->config['connection']['username'],
                $this->config['connection']['password']
            );
            
            // Apply timeout options
            if (isset($this->config['options'])) {
                if (isset($this->config['options']['connect_timeout'])) {
                    $options->connectTimeout($this->config['options']['connect_timeout']);
                }
                if (isset($this->config['options']['kv_timeout'])) {
                    $options->kvTimeout($this->config['options']['kv_timeout']);
                }
            }
            
            $connectionString = 'couchbase://' . $this->config['connection']['host'];
            $this->_cluster = new Cluster($connectionString, $options);
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
        }
        
        return $this->_bucket;
    }
    
    /**
     * Get a scope by name
     * @param string $name Scope name (use config key or actual name)
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
     * @param array $params Named parameters
     * @return \Couchbase\QueryResult
     */
    public function query(string $query, array $params = [])
    {
        $options = new \Couchbase\QueryOptions();
        
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        if (isset($this->config['options']['query_timeout'])) {
            $options->timeout($this->config['options']['query_timeout']);
        }
        
        return $this->getCluster()->query($query, $options);
    }
    
    /**
     * Check if connection is alive
     * @return bool
     */
    public function ping(): bool
    {
        try {
            $this->getBucket()->ping();
            return true;
        } catch (\Exception $e) {
            Yii::log('Couchbase ping failed: ' . $e->getMessage(), CLogger::LEVEL_ERROR);
            return false;
        }
    }
}
```

**Acceptance Criteria**:
- [ ] Component can be accessed via `Yii::app()->couchbase`
- [ ] Connection can be established
- [ ] Scopes and collections are accessible
- [ ] Queries can be executed
- [ ] Ping returns true when connected

### 1.6 Health Check & Monitoring

#### 1.6.1 Health Check Endpoint
**File**: `/protected/controllers/CouchbaseHealthController.php` (create)

```php
<?php
/**
 * Couchbase health check controller
 */
class CouchbaseHealthController extends CController
{
    /**
     * Check Couchbase connectivity
     */
    public function actionIndex()
    {
        header('Content-Type: application/json');
        
        $status = [
            'couchbase' => [
                'status' => 'unknown',
                'latency_ms' => null,
                'bucket' => null,
            ],
            'timestamp' => date('c'),
        ];
        
        try {
            $start = microtime(true);
            $connected = Yii::app()->couchbase->ping();
            $latency = round((microtime(true) - $start) * 1000, 2);
            
            $status['couchbase']['status'] = $connected ? 'healthy' : 'unhealthy';
            $status['couchbase']['latency_ms'] = $latency;
            $status['couchbase']['bucket'] = Yii::app()->couchbase->config['bucket'];
            
            http_response_code($connected ? 200 : 503);
        } catch (Exception $e) {
            $status['couchbase']['status'] = 'error';
            $status['couchbase']['error'] = $e->getMessage();
            http_response_code(503);
        }
        
        echo json_encode($status, JSON_PRETTY_PRINT);
        Yii::app()->end();
    }
}
```

**Acceptance Criteria**:
- [ ] Endpoint accessible at `/couchbaseHealth`
- [ ] Returns JSON with status information
- [ ] Returns 200 when healthy, 503 when unhealthy

### 1.7 Index Creation

#### 1.7.1 Primary Indexes
**File**: `/protected/scripts/couchbase/create-indexes.n1ql`

```sql
-- Primary indexes for development (remove in production)
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`patient` USING GSI;
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`user` USING GSI;
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`episode` USING GSI;
CREATE PRIMARY INDEX `#primary` ON `openeyes`.`core`.`event` USING GSI;

-- Essential GSI indexes
CREATE INDEX idx_patient_hos_num ON `openeyes`.`core`.`patient`(hos_num) USING GSI;
CREATE INDEX idx_patient_nhs_num ON `openeyes`.`core`.`patient`(nhs_num) USING GSI;
CREATE INDEX idx_patient_dob ON `openeyes`.`core`.`patient`(dob) USING GSI;
CREATE INDEX idx_patient_name ON `openeyes`.`core`.`patient`(last_name, first_name) USING GSI;

CREATE INDEX idx_episode_patient_id ON `openeyes`.`core`.`episode`(patient_id) USING GSI;
CREATE INDEX idx_event_episode_id ON `openeyes`.`core`.`event`(episode_id) USING GSI;
CREATE INDEX idx_event_created ON `openeyes`.`core`.`event`(created_date) USING GSI;

CREATE INDEX idx_user_username ON `openeyes`.`core`.`user`(username) USING GSI;
CREATE INDEX idx_user_active ON `openeyes`.`core`.`user`(active) USING GSI;
```

**Acceptance Criteria**:
- [ ] All indexes created successfully
- [ ] Index build completes without errors
- [ ] Queries utilize indexes (check via EXPLAIN)

## Testing Criteria

### Unit Tests
- [ ] `CouchbaseConnection::getCluster()` returns valid cluster
- [ ] `CouchbaseConnection::getBucket()` returns valid bucket
- [ ] `CouchbaseConnection::getCollection()` returns valid collection
- [ ] `CouchbaseConnection::query()` executes N1QL successfully
- [ ] `CouchbaseConnection::ping()` returns true when connected

### Integration Tests
- [ ] Docker container starts and initializes
- [ ] Cluster initialization script completes
- [ ] Bucket and scope creation successful
- [ ] PHP SDK connects from application container
- [ ] Health endpoint returns valid response

### Performance Baseline
- [ ] Document `ping()` latency
- [ ] Document simple query latency
- [ ] Document connection pool behavior

## Rollback Plan

1. Remove Couchbase Docker container: `docker-compose -f docker-compose.couchbase.yml down`
2. Remove composer dependency: Remove `couchbase/couchbase` from composer.json
3. Remove configuration files:
   - `/protected/config/couchbase.php`
   - `/protected/components/CouchbaseConnection.php`
   - `/protected/controllers/CouchbaseHealthController.php`
4. Revert `common.php` changes

## Definition of Done

- [ ] All acceptance criteria met
- [ ] All tests passing
- [ ] Documentation complete
- [ ] Code reviewed and approved
- [ ] No impact on existing MariaDB functionality

---

*Phase 1 Completion Sign-off:*
- [ ] Technical Lead
- [ ] Operations
- [ ] QA

*Estimated Duration: 1-2 weeks*
