<?php
/**
 * Full Data Migration Configuration
 * Phase 14: Controls batch sizes, timeouts, and validation thresholds
 * 
 * This file can be customized per environment (development, staging, production)
 */

return [
    /**
     * Batch sizes for different table types
     * Smaller batches for tables with complex embedded relations
     * Larger batches for simple lookup tables
     */
    'batchSizes' => [
        'default' => 1000,
        
        // Core clinical data (complex embedded relations)
        'patient' => 200,
        'episode' => 500,
        'event' => 500,
        
        // High volume data
        'audit' => 200,
        
        // Reference data (large SNOMED/OPCS datasets)
        'disorder' => 500,
        'procedure' => 500,
        'medication' => 500,
        
        // Simple lookups (can be larger)
        'site' => 2000,
        'institution' => 2000,
        'firm' => 2000,
        'user' => 500,
    ],
    
    /**
     * Stage configuration
     * Controls which stages are enabled and their timeouts
     */
    'stages' => [
        1 => [
            'enabled' => true,
            'timeout' => 1800,      // 30 minutes
            'description' => 'Reference Data - Foundation tables',
        ],
        2 => [
            'enabled' => true,
            'timeout' => 7200,      // 2 hours
            'description' => 'Clinical Reference - SNOMED, OPCS, dm+d codes',
        ],
        3 => [
            'enabled' => true,
            'timeout' => 28800,     // 8 hours
            'description' => 'Core Clinical - Patients, Episodes, Events',
        ],
        4 => [
            'enabled' => true,
            'timeout' => 43200,     // 12 hours
            'description' => 'Module Elements - All examination/operation elements',
        ],
        5 => [
            'enabled' => true,
            'timeout' => 28800,     // 8 hours
            'description' => 'Administrative - Audit logs and settings',
        ],
    ],
    
    /**
     * Notification configuration
     * Set up email/Slack alerts for migration progress
     */
    'notifications' => [
        'enabled' => false,
        'email' => [
            'enabled' => false,
            'to' => 'devops@example.com',
            'subject' => 'OpenEyes Migration - Phase 14',
        ],
        'slack' => [
            'enabled' => false,
            'webhook_url' => null,
            'channel' => '#deployments',
        ],
    ],
    
    /**
     * Validation configuration
     * Controls post-migration validation behavior
     */
    'validation' => [
        'enabled' => true,
        'sampleSize' => 500,                    // Number of records to sample
        'failureThreshold' => 0.01,             // 1% acceptable variance
        'criticalTables' => [
            'patient',
            'episode',
            'event',
            'disorder',
            'medication',
        ],
        
        // Tables that must match 100% (no variance allowed)
        'strictTables' => [
            'event_type',
            'element_type',
            'site',
            'institution',
        ],
    ],
    
    /**
     * Performance configuration
     * Tune for your environment
     */
    'performance' => [
        'memoryLimit' => '2G',                  // PHP memory limit for migration
        'gcInterval' => 1000,                   // Run garbage collection every N records
        'progressInterval' => 100,              // Show progress every N records
        'connectionTimeout' => 30,              // Couchbase connection timeout (seconds)
        'retryAttempts' => 3,                   // Retry failed operations
        'retryDelay' => 1000,                   // Delay between retries (milliseconds)
    ],
    
    /**
     * Logging configuration
     */
    'logging' => [
        'level' => 'info',                      // debug, info, warning, error
        'logToFile' => true,
        'logToConsole' => true,
        'logDirectory' => '@runtime/migration-logs',
        'rotateDaily' => true,
    ],
    
    /**
     * Rollback configuration
     */
    'rollback' => [
        'enabled' => true,
        'confirmationRequired' => true,
        'backupBeforeRollback' => true,
    ],
    
    /**
     * Environment-specific settings
     * Override above settings based on environment
     */
    'environments' => [
        'development' => [
            'batchSizes' => [
                'default' => 100,                // Smaller batches for dev
            ],
            'notifications' => [
                'enabled' => false,
            ],
        ],
        'staging' => [
            'batchSizes' => [
                'default' => 500,
            ],
            'notifications' => [
                'enabled' => true,
            ],
        ],
        'production' => [
            'batchSizes' => [
                'default' => 1000,
            ],
            'notifications' => [
                'enabled' => true,
            ],
            'validation' => [
                'sampleSize' => 1000,            // More thorough validation in prod
            ],
        ],
    ],
    
    /**
     * Resume capability
     * Track progress for resuming interrupted migrations
     */
    'resume' => [
        'enabled' => true,
        'checkpointFile' => '@runtime/migration-checkpoint.json',
        'saveInterval' => 1000,                  // Save checkpoint every N records
    ],
];
