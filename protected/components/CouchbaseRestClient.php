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
    public function query($query, $params = [])
    {
        $url = $this->buildUrl();
        
        // Build request body
        $body = ['statement' => $query];
        
        // Add named parameters if provided
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                // N1QL named parameters start with $
                $paramKey = strpos($key, '$') === 0 ? $key : '$' . $key;
                $body[$paramKey] = $value;
            }
        }
        
        Yii::log('REST N1QL: ' . substr($query, 0, 100), CLogger::LEVEL_TRACE, 'application.couchbase');
        
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
        
        return $response['results'] ?? [];
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
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            throw new CException('Couchbase REST request failed: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new CException("Couchbase REST error (HTTP {$httpCode}): " . $response);
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
}
