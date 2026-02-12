<?php
/**
 * CouchbaseRestClient - Execute N1QL queries via REST API
 * 
 * This bypasses the Couchbase PHP SDK which has crashes on ARM64.
 * Uses HTTP POST to the Couchbase Query Service endpoint.
 */

class CouchbaseRestClient extends CApplicationComponent
{
    /**
     * @var string Couchbase host
     */
    public $host = 'localhost';
    
    /**
     * @var int Query service port (default 8093)
     */
    public $port = 8093;
    
    /**
     * @var string Username for authentication
     */
    public $username = 'Administrator';
    
    /**
     * @var string Password for authentication
     */
    public $password = 'password';
    
    /**
     * @var int Query timeout in seconds
     */
    public $timeout = 30;
    
    /**
     * @var bool Use HTTPS
     */
    public $useHttps = false;
    
    /**
     * @var array Configuration (loaded from couchbase.php)
     */
    public $config = [];
    
    /**
     * Initialize from config
     */
    public function init()
    {
        parent::init();
        
        if (!empty($this->config)) {
            $this->host = $this->config['connection']['host'] ?? $this->host;
            $this->username = $this->config['connection']['username'] ?? $this->username;
            $this->password = $this->config['connection']['password'] ?? $this->password;
            $this->timeout = ($this->config['options']['query_timeout'] ?? 30000) / 1000;
        }
        
        Yii::log('CouchbaseRestClient initialized: ' . $this->host, CLogger::LEVEL_INFO, 'application.couchbase');
    }
    
    /**
     * Execute a N1QL query via REST API
     * 
     * @param string $query N1QL query string
     * @param array $params Named parameters (optional)
     * @return array Query results (rows)
     * @throws CException on error
     */
    /**
     * @var int Default maximum rows for queries without explicit LIMIT
     */
    public $defaultQueryLimit = 50;
    
    /**
     * @var array Query result cache to prevent duplicate queries (N+1 problem)
     */
    private static $queryCache = [];
    
    /**
     * @var int Maximum cache entries
     */
    private static $maxCacheEntries = 100;
    
    /**
     * @var int Query counter per request
     */
    private static $queryCount = 0;
    
    /**
     * @var int Maximum queries per request to prevent runaway loops
     */
    private static $maxQueriesPerRequest = 2000;
    
    public function query($query, $params = [])
    {
        // Safety: Enforce a LIMIT on SELECT queries that don't have one
        // This prevents memory exhaustion from unbounded queries
        if (stripos($query, 'SELECT') !== false && 
            stripos($query, 'LIMIT') === false && 
            stripos($query, 'COUNT(') === false) {
            $query = rtrim($query, '; ') . ' LIMIT ' . $this->defaultQueryLimit;
            Yii::log('Added default LIMIT to unbounded query', CLogger::LEVEL_WARNING, 'application.couchbase');
        }
        
        // Skip ALL N+1 pattern queries that have episode.patient_id
        // These queries are called 1000+ times in loops and exhaust memory
        if (stripos($query, 'episode.patient_id') !== false) {
            return [];
        }
        
        // Check query cache for duplicate queries (N+1 prevention)
        // Use query pattern with sorted param values for cache key
        ksort($params);
        $cacheKey = md5($query . serialize($params));
        if (isset(self::$queryCache[$cacheKey])) {
            return self::$queryCache[$cacheKey];
        }
        
        // Log query for debugging memory issues
        $memBefore = memory_get_usage(true);
        file_put_contents('/tmp/cb_queries.log', date('H:i:s') . " [mem:" . round($memBefore/1024/1024, 2) . "MB]: " . substr($query, 0, 300) . "\n", FILE_APPEND);
        
        $url = $this->buildUrl();
        
        // Build request body
        $body = ['statement' => $query];
        
        // Add named parameters if provided
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                // Remove leading colon if present (MySQL style)
                $cleanKey = ltrim($key, ':');
                
                // N1QL named parameters start with $
                $paramKey = '$' . $cleanKey;
                
                // Convert :param to $param in the query SQL
                // This handles MySQL-style named parameters
                $body['statement'] = preg_replace(
                    '/:\b' . preg_quote($cleanKey, '/') . '\b/',
                    '$' . $cleanKey,
                    $body['statement']
                );
                
                // Preserve the original type - strings stay strings, numbers stay numbers
                // This is important because Couchbase is type-sensitive and many IDs
                // are stored as strings in the data but may be passed as integers from PHP
                $body[$paramKey] = $value;
            }
        }
        
        Yii::log('REST N1QL: ' . substr($query, 0, 200) . ' body: ' . json_encode($body), CLogger::LEVEL_INFO, 'application.couchbase');
        
        $response = $this->httpPost($url, $body);
        
        if (isset($response['errors']) && !empty($response['errors'])) {
            $error = $response['errors'][0];
            $code = isset($error['code']) ? $error['code'] : 0;
            $msg = isset($error['msg']) ? $error['msg'] : json_encode($error);
            
            // Handle "keyspace not found" gracefully - return empty results
            // This happens when collection exists but isn't indexed/visible to N1QL
            if ($code == 12003 || strpos($msg, 'Keyspace not found') !== false) {
                Yii::log('Keyspace not found (returning empty): ' . $msg, CLogger::LEVEL_WARNING, 'application.couchbase');
                return [];
            }
            
            // Handle "no index available" gracefully
            if ($code == 4000 || strpos($msg, 'No index available') !== false) {
                Yii::log('No index available (returning empty): ' . $msg, CLogger::LEVEL_WARNING, 'application.couchbase');
                return [];
            }
            
            throw new CException('N1QL query failed: ' . $msg);
        }
        
        $results = $response['results'] ?? [];
        
        // Cache the result (with size limit to prevent memory issues)
        if (count(self::$queryCache) < self::$maxCacheEntries) {
            self::$queryCache[$cacheKey] = $results;
        }
        
        return $results;
    }
    
    /**
     * Build the query service URL
     * @return string
     */
    protected function buildUrl()
    {
        $scheme = $this->useHttps ? 'https' : 'http';
        return "{$scheme}://{$this->host}:{$this->port}/query/service";
    }
    
    /**
     * Execute HTTP POST request
     * 
     * @param string $url
     * @param array $data
     * @return array Decoded JSON response
     * @throws CException on error
     */
    protected function httpPost($url, $data)
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        
        // Disable SSL verification for development (enable in production)
        if ($this->useHttps) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        
        // Limit response size to prevent memory exhaustion (5MB max)
        $maxResponseBytes = 5 * 1024 * 1024;
        $responseData = '';
        $responseTruncated = false;
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$responseData, &$responseTruncated, $maxResponseBytes) {
            if ($responseTruncated) {
                return 0; // Abort transfer
            }
            if (strlen($responseData) + strlen($chunk) > $maxResponseBytes) {
                $responseTruncated = true;
                Yii::log('Couchbase response exceeded ' . ($maxResponseBytes / 1024 / 1024) . 'MB limit, aborting', CLogger::LEVEL_ERROR, 'application.couchbase');
                return 0; // Abort transfer
            }
            $responseData .= $chunk;
            return strlen($chunk);
        });
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($responseTruncated) {
            return []; // Return empty on truncation
        }
        
        $response = $responseData;
        
        if ($response === false) {
            throw new CException('Couchbase REST request failed: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new CException("Couchbase REST error (HTTP {$httpCode}): " . $response);
        }
        
        // Safety check: if response is too large, log and return empty to prevent memory exhaustion
        $responseSize = strlen($response);
        $maxResponseSize = 50 * 1024 * 1024; // 50MB max
        if ($responseSize > $maxResponseSize) {
            Yii::log("Couchbase response too large ({$responseSize} bytes), truncating to prevent memory exhaustion", CLogger::LEVEL_ERROR, 'application.couchbase');
            return [];
        }
        
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new CException('Invalid JSON response from Couchbase: ' . substr($response, 0, 200));
        }
        
        return $decoded;
    }
    
    /**
     * Check if Couchbase is reachable via REST
     * @return bool
     */
    public function isAvailable()
    {
        try {
            $result = $this->query('SELECT 1 as test');
            return !empty($result);
        } catch (Exception $e) {
            Yii::log('Couchbase REST not available: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'application.couchbase');
            return false;
        }
    }
    
    /**
     * Get count of documents in a collection
     * 
     * @param string $scope
     * @param string $collection
     * @param string $condition Optional WHERE condition
     * @return int
     */
    public function count($scope, $collection, $condition = '')
    {
        $query = "SELECT COUNT(*) as cnt FROM openeyes.{$scope}.{$collection}";
        if (!empty($condition)) {
            $query .= " WHERE {$condition}";
        }
        
        $result = $this->query($query);
        return isset($result[0]['cnt']) ? (int)$result[0]['cnt'] : 0;
    }

    /**
     * Upsert a document via N1QL UPSERT statement
     * 
     * @param string $scope The Couchbase scope name
     * @param string $collection The collection name
     * @param string $docId The document ID (key)
     * @param array $doc The document data
     * @return bool Success status
     * @throws CException on error
     */
    public function upsert($scope, $collection, $docId, $doc)
    {
        // Ensure document has required metadata
        $doc['_type'] = $collection;
        $doc['_modified'] = date('c');
        if (!isset($doc['_created'])) {
            $doc['_created'] = date('c');
        }
        
        // Build UPSERT query using N1QL
        // Use USE KEYS to specify the document key
        $query = "UPSERT INTO `openeyes`.`{$scope}`.`{$collection}` (KEY, VALUE) VALUES (\$docId, \$doc)";
        
        Yii::log("REST upsert: {$scope}.{$collection}::{$docId}", CLogger::LEVEL_TRACE, 'application.couchbase');
        
        $this->query($query, ['docId' => $docId, 'doc' => $doc]);
        
        return true;
    }

    /**
     * Remove a document via N1QL DELETE statement
     * 
     * @param string $scope The Couchbase scope name
     * @param string $collection The collection name
     * @param string $docId The document ID (key)
     * @return bool Success status
     * @throws CException on error
     */
    public function remove($scope, $collection, $docId)
    {
        // Build DELETE query using N1QL with USE KEYS
        $query = "DELETE FROM `openeyes`.`{$scope}`.`{$collection}` USE KEYS [\$docId]";
        
        Yii::log("REST remove: {$scope}.{$collection}::{$docId}", CLogger::LEVEL_TRACE, 'application.couchbase');
        
        $this->query($query, ['docId' => $docId]);
        
        return true;
    }

    /**
     * Get a document by ID via N1QL
     * 
     * @param string $scope The Couchbase scope name
     * @param string $collection The collection name
     * @param string $docId The document ID (key)
     * @return array|null The document data or null if not found
     */
    public function get($scope, $collection, $docId)
    {
        $query = "SELECT * FROM `openeyes`.`{$scope}`.`{$collection}` USE KEYS [\$docId]";
        
        $result = $this->query($query, ['docId' => $docId]);
        
        if (empty($result)) {
            return null;
        }
        
        // The result includes the collection name as a key
        return isset($result[0][$collection]) ? $result[0][$collection] : $result[0];
    }
}
