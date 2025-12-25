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
if (!function_exists('getCouchbaseConfigValue')) {
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
        'kv_timeout' => 120000,      // 120 seconds for key-value operations (increased for migration)
        'query_timeout' => 120000,   // 120 seconds for N1QL queries
        'view_timeout' => 120000,    // 120 seconds for views
        'analytics_timeout' => 120000, // 120 seconds for analytics
    ],
    
    // Feature flags for migration phases
    'features' => [
        'enabled' => true,           // Master switch for Couchbase functionality
        'dual_write' => true,        // Write to both MariaDB and Couchbase (Phase 4+) - ENABLED for Phase 13
        'read_from_couchbase' => false, // Read from Couchbase (Phase 6+)
    ],
];
