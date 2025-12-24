<?php
/**
 * Couchbase Cutover Configuration
 * 
 * Controls the gradual rollout from MariaDB to Couchbase with feature flags,
 * traffic percentage control, and emergency disable capabilities.
 * 
 * Phase 16: Production Cutover
 */

return [
    // ============================================
    // MASTER CONTROL
    // ============================================
    
    // Master switch - set to false to disable all Couchbase reads/writes
    'enabled' => true,
    
    // Read source: 'mariadb', 'couchbase', 'hybrid'
    // - mariadb: All reads from MariaDB
    // - couchbase: All reads from Couchbase (fallback to MariaDB on error if enabled)
    // - hybrid: Use couchbase_read_percentage to split traffic
    'read_source' => 'hybrid',
    
    // Write mode: 'mariadb_only', 'dual_write', 'couchbase_primary'
    // - mariadb_only: Only write to MariaDB
    // - dual_write: Write to both MariaDB and Couchbase
    // - couchbase_primary: Write to Couchbase only (MariaDB optional)
    'write_mode' => 'dual_write',
    
    // ============================================
    // TRAFFIC CONTROL
    // ============================================
    
    // Percentage of read traffic to route to Couchbase (0-100)
    // Start at 10% for canary, gradually increase to 100%
    'couchbase_read_percentage' => 10,
    
    // ============================================
    // FALLBACK SETTINGS
    // ============================================
    
    // Enable fallback to MariaDB on Couchbase errors
    'fallback_enabled' => true,
    
    // Fallback on any Couchbase error
    'fallback_on_error' => true,
    
    // Fallback on timeout
    'fallback_on_timeout' => true,
    
    // Timeout threshold in milliseconds (operations exceeding this will fallback)
    'timeout_threshold_ms' => 500,
    
    // ============================================
    // PER-MODEL OVERRIDES
    // ============================================
    
    // Override settings for specific models
    'models' => [
        'Patient' => [
            'read_source' => 'couchbase',
            'percentage' => 100, // Always use Couchbase for patients
        ],
        'Episode' => [
            'read_source' => 'couchbase',
            'percentage' => 100,
        ],
        'Event' => [
            'read_source' => 'hybrid',
            'percentage' => 50, // 50% of events to Couchbase
        ],
        'Disorder' => [
            'read_source' => 'couchbase',
            'percentage' => 100, // Reference data always from Couchbase
        ],
        'Medication' => [
            'read_source' => 'couchbase',
            'percentage' => 100,
        ],
        'Audit' => [
            'read_source' => 'mariadb', // Keep audit in MariaDB initially
            'percentage' => 0,
        ],
    ],
    
    // ============================================
    // USER-BASED TARGETING
    // ============================================
    
    'user_targeting' => [
        // Enable user-based targeting
        'enabled' => true,
        
        // Internal users (admin, staff) always use Couchbase
        'internal_users' => true,
        
        // Specific beta tester user IDs
        'beta_user_ids' => [
            // Add user IDs for beta testers
            // Example: 1, 2, 3, 4, 5
        ],
        
        // Exclude specific user IDs from Couchbase (always use MariaDB)
        'excluded_user_ids' => [
            // Add user IDs to exclude
        ],
    ],
    
    // ============================================
    // SITE-BASED TARGETING
    // ============================================
    
    'site_targeting' => [
        // Enable site-based targeting
        'enabled' => false,
        
        // Specific site IDs to enable Couchbase
        'enabled_site_ids' => [
            // Add site IDs to enable
        ],
        
        // Exclude specific site IDs from Couchbase
        'excluded_site_ids' => [
            // Add site IDs to exclude
        ],
    ],
    
    // ============================================
    // EMERGENCY CONTROLS
    // ============================================
    
    // Emergency disable flag - set to true to immediately disable all Couchbase operations
    'emergency_disable' => false,
    
    // Reason for emergency disable (for logging/audit)
    'emergency_disable_reason' => '',
    
    // Timestamp of last emergency disable
    'emergency_disable_timestamp' => null,
    
    // ============================================
    // MONITORING & LOGGING
    // ============================================
    
    // Log all traffic routing decisions (useful for debugging, disable in production)
    'log_routing_decisions' => false,
    
    // Log fallback events
    'log_fallbacks' => true,
    
    // Log performance metrics
    'log_performance' => true,
];
