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
     * @var int Connection pool size
     */
    public $poolSize = 10;
    
    /**
     * @var int Connection timeout in milliseconds
     */
    public $connectionTimeout = 5000;
    
    /**
     * @var int KV operation timeout in milliseconds
     */
    public $operationTimeout = 10000;
    
    /**
     * @var int Query timeout in milliseconds
     */
    public $queryTimeout = 75000;
    
    /**
     * @var int Maximum retry attempts
     */
    public $maxRetries = 3;
    
    /**
     * @var int Initial retry delay in milliseconds
     */
    public $retryDelay = 100;
    
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
     * @var int Timestamp of last use
     */
    private $_lastUsed;
    
    /**
     * @var int Connection operation counter
     */
    private $_operationCount = 0;
    
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
                $connectTimeout = $this->config['options']['connect_timeout'] ?? $this->connectionTimeout;
                $kvTimeout = $this->config['options']['kv_timeout'] ?? $this->operationTimeout;
                $queryTimeout = $this->config['options']['query_timeout'] ?? $this->queryTimeout;
                
                if (method_exists($options, 'connectTimeout')) {
                    $options->connectTimeout($connectTimeout);
                }
                if (method_exists($options, 'kvTimeout')) {
                    $options->kvTimeout($kvTimeout);
                }
                if (method_exists($options, 'queryTimeout')) {
                    $options->queryTimeout($queryTimeout);
                }
                
                // Connection pooling (if supported)
                if (method_exists($options, 'maxConnections')) {
                    $options->maxConnections($this->poolSize);
                }
                if (method_exists($options, 'numIoThreads')) {
                    $options->numIoThreads(4);
                }
                
                // Enable metrics (if supported)
                if (method_exists($options, 'enableMetrics')) {
                    $options->enableMetrics(true);
                }
                
                $connectionString = 'couchbase://' . $this->config['connection']['host'];
                $this->_cluster = new Cluster($connectionString, $options);
                $this->_connected = true;
                $this->_lastUsed = time();
                
                Yii::log(
                    'Connected to Couchbase cluster: ' . $this->config['connection']['host'] . 
                    ' (pool size: ' . $this->poolSize . ')',
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
        
        $this->_lastUsed = time();
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
            
            // Wait for bucket to be ready (if SDK supports it)
            if (method_exists($this->_bucket, 'waitUntilReady')) {
                $this->_bucket->waitUntilReady(10000); // 10 second timeout
            }
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
        
        $this->_lastUsed = time();
        return $this->_collections[$key];
    }
    
    /**
     * Execute operation with retry logic
     * @param callable $operation Operation to execute
     * @param int|null $retries Number of retries (null uses default)
     * @return mixed Operation result
     * @throws Exception Last exception if all retries fail
     */
    public function executeWithRetry(callable $operation, ?int $retries = null)
    {
        $retries = $retries ?? $this->maxRetries;
        $lastException = null;
        
        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                $this->_operationCount++;
                return $operation();
            } catch (\Couchbase\Exception\TimeoutException $e) {
                $lastException = $e;
                if ($attempt < $retries) {
                    // Exponential backoff
                    $delay = $this->retryDelay * pow(2, $attempt);
                    usleep($delay * 1000);
                    
                    Yii::log(
                        "Timeout on attempt " . ($attempt + 1) . ", retrying after {$delay}ms",
                        CLogger::LEVEL_WARNING,
                        'application.couchbase'
                    );
                }
            } catch (\Couchbase\Exception\TemporaryFailureException $e) {
                $lastException = $e;
                if ($attempt < $retries) {
                    usleep($this->retryDelay * 1000);
                    
                    Yii::log(
                        "Temporary failure on attempt " . ($attempt + 1) . ", retrying",
                        CLogger::LEVEL_WARNING,
                        'application.couchbase'
                    );
                }
            } catch (Exception $e) {
                // Non-retryable exception
                throw $e;
            }
        }
        
        Yii::log(
            'All retry attempts failed: ' . $lastException->getMessage(),
            CLogger::LEVEL_ERROR,
            'application.couchbase'
        );
        
        throw $lastException;
    }
    
    /**
     * Get a document with retry
     * @param string $scopeName Scope name
     * @param string $collectionName Collection name
     * @param string $key Document key
     * @return mixed Document content
     */
    public function get(string $scopeName, string $collectionName, string $key)
    {
        return $this->executeWithRetry(function() use ($scopeName, $collectionName, $key) {
            $collection = $this->getCollection($scopeName, $collectionName);
            $result = $collection->get($key);
            return $result->content();
        });
    }
    
    /**
     * Remove a document with retry
     * @param string $scopeName Scope name
     * @param string $collectionName Collection name
     * @param string $key Document key
     * @return mixed
     */
    public function remove(string $scopeName, string $collectionName, string $key)
    {
        return $this->executeWithRetry(function() use ($scopeName, $collectionName, $key) {
            $collection = $this->getCollection($scopeName, $collectionName);
            return $collection->remove($key);
        });
    }

    /**
     * Upsert a document into a collection
     * @param string $scopeName Scope name
     * @param string $collectionName Collection name
     * @param string $key Document key
     * @param array $document Document data
     * @param array $options Optional upsert options
     * @return mixed
     */
    public function upsert(string $scopeName, string $collectionName, string $key, array $document, array $options = [])
    {
        return $this->executeWithRetry(function() use ($scopeName, $collectionName, $key, $document, $options) {
            $collection = $this->getCollection($scopeName, $collectionName);
            $upsertOptions = new \Couchbase\UpsertOptions();
            
            if (isset($options['expiry'])) {
                $upsertOptions->expiry($options['expiry']);
            }
            
            return $collection->upsert($key, $document, $upsertOptions);
        });
    }
    
    /**
     * Execute a N1QL query
     * @param string $query N1QL query string
     * @param array $params Named parameters (optional)
     * @return QueryResult
     */
    public function query(string $query, array $params = []): QueryResult
    {
        Yii::log('N1QL query starting: ' . substr($query, 0, 100), CLogger::LEVEL_INFO, 'application.couchbase');
        
        // Get cluster first to ensure connection
        $cluster = $this->getCluster();
        Yii::log('Got cluster, executing query...', CLogger::LEVEL_INFO, 'application.couchbase');
        
        // Workaround for SDK crash on ARM64: avoid QueryOptions when possible
        $options = null;
        
        if (!empty($params) || isset($this->config['options']['query_timeout'])) {
            $options = new QueryOptions();
            
            if (!empty($params)) {
                $options->namedParameters($params);
            }
            
            if (isset($this->config['options']['query_timeout'])) {
                $options->timeout($this->config['options']['query_timeout']);
            }
        }
        
        $result = ($options === null) 
            ? $cluster->query($query) 
            : $cluster->query($query, $options);
            
        Yii::log('Query completed successfully', CLogger::LEVEL_INFO, 'application.couchbase');
        return $result;
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
     * Get connection statistics
     * @return array Connection stats
     */
    public function getStats(): array
    {
        return [
            'pool_size' => $this->poolSize,
            'collections_cached' => count($this->_collections),
            'scopes_cached' => count($this->_scopes),
            'last_used' => $this->_lastUsed,
            'uptime_seconds' => $this->_lastUsed ? time() - $this->_lastUsed : 0,
            'operations_count' => $this->_operationCount,
            'connected' => $this->_connected,
        ];
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
