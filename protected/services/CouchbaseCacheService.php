<?php
/**
 * Caching service for frequently accessed data
 * 
 * Implements a multi-level caching strategy:
 * - Level 1: Local PHP array cache (per-request, 60s TTL)
 * - Level 2: Couchbase document cache (configurable TTL)
 * 
 * Usage:
 *   $cache = new CouchbaseCacheService();
 *   $data = $cache->getOrSet('key', function() { return expensiveOperation(); }, 3600);
 */
class CouchbaseCacheService
{
    /**
     * @var CouchbaseConnection
     */
    protected $couchbase;
    
    /**
     * @var array Local cache storage
     */
    protected $localCache = [];
    
    /**
     * @var int Local cache TTL in seconds
     */
    protected $localCacheTTL = 60;
    
    /**
     * @var array Local cache timestamps
     */
    protected $localCacheTimestamps = [];
    
    /**
     * @var string Default cache scope
     */
    protected $cacheScope = 'cache';
    
    /**
     * @var string Default cache collection
     */
    protected $cacheCollection = 'cache';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->couchbase = Yii::app()->couchbase;
    }

    /**
     * Get value with multi-level caching
     * Level 1: Check local cache
     * Level 2: Check Couchbase cache
     * Level 3: Return null (cache miss)
     * 
     * @param string $key Cache key
     * @param string $scope Scope name
     * @param string $collection Collection name
     * @return mixed|null Cached value or null
     */
    public function get($key, $scope = null, $collection = null)
    {
        $scope = $scope ?? $this->cacheScope;
        $collection = $collection ?? $this->cacheCollection;
        
        // Level 1: Check local cache
        if ($this->isLocalCacheValid($key)) {
            Yii::log("Cache hit (local): {$key}", CLogger::LEVEL_TRACE, 'cache');
            return $this->localCache[$key];
        }

        // Level 2: Check Couchbase cache
        try {
            $result = $this->couchbase->get($scope, $collection, $key);
            
            if ($result) {
                // Populate local cache
                $this->setLocalCache($key, $result);
                Yii::log("Cache hit (Couchbase): {$key}", CLogger::LEVEL_TRACE, 'cache');
                return $result;
            }
        } catch (Exception $e) {
            // Cache miss or error
            Yii::log("Cache miss: {$key} - " . $e->getMessage(), CLogger::LEVEL_TRACE, 'cache');
        }

        return null;
    }

    /**
     * Set value in cache with TTL
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @param string $scope Scope name
     * @param string $collection Collection name
     * @return bool Success status
     */
    public function set($key, $value, $ttl = 3600, $scope = null, $collection = null)
    {
        $scope = $scope ?? $this->cacheScope;
        $collection = $collection ?? $this->cacheCollection;
        
        // Set in Couchbase with TTL
        try {
            $this->couchbase->upsert($scope, $collection, $key, [
                'data' => $value,
                'cached_at' => time(),
            ], ['expiry' => $ttl]);
            
            // Set in local cache
            $this->setLocalCache($key, $value);
            
            Yii::log("Cache set: {$key} (TTL: {$ttl}s)", CLogger::LEVEL_TRACE, 'cache');
            return true;
        } catch (Exception $e) {
            Yii::log("Cache set error: {$key} - " . $e->getMessage(), CLogger::LEVEL_ERROR, 'cache');
            return false;
        }
    }

    /**
     * Delete from cache
     * 
     * @param string $key Cache key
     * @param string $scope Scope name
     * @param string $collection Collection name
     * @return bool Success status
     */
    public function delete($key, $scope = null, $collection = null)
    {
        $scope = $scope ?? $this->cacheScope;
        $collection = $collection ?? $this->cacheCollection;
        
        // Remove from local cache
        unset($this->localCache[$key]);
        unset($this->localCacheTimestamps[$key]);

        // Remove from Couchbase
        try {
            $this->couchbase->remove($scope, $collection, $key);
            Yii::log("Cache delete: {$key}", CLogger::LEVEL_TRACE, 'cache');
            return true;
        } catch (Exception $e) {
            Yii::log("Cache delete error: {$key} - " . $e->getMessage(), CLogger::LEVEL_WARNING, 'cache');
            return false;
        }
    }

    /**
     * Get or set pattern - fetch from cache or generate and cache
     * 
     * @param string $key Cache key
     * @param callable $callback Function to generate value if not cached
     * @param int $ttl Time to live in seconds
     * @param string $scope Scope name
     * @param string $collection Collection name
     * @return mixed Cached or generated value
     */
    public function getOrSet($key, $callback, $ttl = 3600, $scope = null, $collection = null)
    {
        $value = $this->get($key, $scope, $collection);
        
        if ($value === null) {
            $value = call_user_func($callback);
            
            if ($value !== null) {
                $this->set($key, $value, $ttl, $scope, $collection);
            }
        }
        
        return $value;
    }

    /**
     * Cache patient summary
     * 
     * @param int $patientId Patient ID
     * @param array $data Patient summary data
     * @param int $ttl Time to live (default: 300s / 5 minutes)
     * @return bool Success status
     */
    public function cachePatientSummary($patientId, $data, $ttl = 300)
    {
        $key = "patient_summary::{$patientId}";
        return $this->set($key, $data, $ttl, 'cache', 'patient_cache');
    }

    /**
     * Get cached patient summary
     * 
     * @param int $patientId Patient ID
     * @return array|null Patient summary or null
     */
    public function getPatientSummary($patientId)
    {
        $key = "patient_summary::{$patientId}";
        return $this->get($key, 'cache', 'patient_cache');
    }

    /**
     * Cache patient episodes
     * 
     * @param int $patientId Patient ID
     * @param array $episodes Episode data
     * @param int $ttl Time to live (default: 300s)
     * @return bool Success status
     */
    public function cachePatientEpisodes($patientId, $episodes, $ttl = 300)
    {
        $key = "patient_episodes::{$patientId}";
        return $this->set($key, $episodes, $ttl, 'cache', 'patient_cache');
    }

    /**
     * Get cached patient episodes
     * 
     * @param int $patientId Patient ID
     * @return array|null Episodes or null
     */
    public function getPatientEpisodes($patientId)
    {
        $key = "patient_episodes::{$patientId}";
        return $this->get($key, 'cache', 'patient_cache');
    }

    /**
     * Cache lookup table
     * 
     * @param string $tableName Lookup table name
     * @param array $data Lookup table data
     * @param int $ttl Time to live (default: 86400s / 24 hours)
     * @return bool Success status
     */
    public function cacheLookupTable($tableName, $data, $ttl = 86400)
    {
        $key = "lookup::{$tableName}";
        return $this->set($key, $data, $ttl, 'cache', 'lookup_cache');
    }

    /**
     * Get cached lookup table
     * 
     * @param string $tableName Lookup table name
     * @return array|null Lookup data or null
     */
    public function getLookupTable($tableName)
    {
        $key = "lookup::{$tableName}";
        return $this->get($key, 'cache', 'lookup_cache');
    }

    /**
     * Cache event data
     * 
     * @param int $eventId Event ID
     * @param array $data Event data
     * @param int $ttl Time to live (default: 300s)
     * @return bool Success status
     */
    public function cacheEvent($eventId, $data, $ttl = 300)
    {
        $key = "event::{$eventId}";
        return $this->set($key, $data, $ttl, 'cache', 'event_cache');
    }

    /**
     * Get cached event data
     * 
     * @param int $eventId Event ID
     * @return array|null Event data or null
     */
    public function getEvent($eventId)
    {
        $key = "event::{$eventId}";
        return $this->get($key, 'cache', 'event_cache');
    }

    /**
     * Invalidate patient cache (all related entries)
     * 
     * @param int $patientId Patient ID
     * @return void
     */
    public function invalidatePatientCache($patientId)
    {
        $keys = [
            "patient_summary::{$patientId}",
            "patient_episodes::{$patientId}",
            "patient_events::{$patientId}",
        ];

        foreach ($keys as $key) {
            $this->delete($key, 'cache', 'patient_cache');
        }
        
        Yii::log("Invalidated patient cache: {$patientId}", CLogger::LEVEL_INFO, 'cache');
    }

    /**
     * Invalidate event cache
     * 
     * @param int $eventId Event ID
     * @return void
     */
    public function invalidateEventCache($eventId)
    {
        $key = "event::{$eventId}";
        $this->delete($key, 'cache', 'event_cache');
        
        Yii::log("Invalidated event cache: {$eventId}", CLogger::LEVEL_INFO, 'cache');
    }

    /**
     * Invalidate lookup cache
     * 
     * @param string $tableName Lookup table name
     * @return void
     */
    public function invalidateLookupCache($tableName)
    {
        $key = "lookup::{$tableName}";
        $this->delete($key, 'cache', 'lookup_cache');
        
        Yii::log("Invalidated lookup cache: {$tableName}", CLogger::LEVEL_INFO, 'cache');
    }

    /**
     * Check if local cache entry is valid
     * 
     * @param string $key Cache key
     * @return bool True if valid
     */
    protected function isLocalCacheValid($key)
    {
        if (!isset($this->localCache[$key])) {
            return false;
        }
        
        $timestamp = $this->localCacheTimestamps[$key] ?? 0;
        return (time() - $timestamp) < $this->localCacheTTL;
    }

    /**
     * Set value in local cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @return void
     */
    protected function setLocalCache($key, $value)
    {
        $this->localCache[$key] = $value;
        $this->localCacheTimestamps[$key] = time();
    }

    /**
     * Clear all local cache
     * 
     * @return void
     */
    public function clearLocalCache()
    {
        $this->localCache = [];
        $this->localCacheTimestamps = [];
        
        Yii::log("Cleared local cache", CLogger::LEVEL_INFO, 'cache');
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public function getStats()
    {
        return [
            'local_cache_size' => count($this->localCache),
            'local_cache_ttl' => $this->localCacheTTL,
        ];
    }
}
