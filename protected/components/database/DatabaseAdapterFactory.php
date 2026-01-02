<?php
/**
 * Factory for creating and managing database adapters
 * 
 * This factory provides centralized adapter instantiation with support
 * for collection-based routing and feature flags for gradual migration.
 */

namespace OE\Database;

class DatabaseAdapterFactory
{
    /**
     * Adapter type constants
     * Note: MariaDB adapter is deprecated - Couchbase is now the primary database
     */
    const ADAPTER_MARIADB = 'mariadb';       // Deprecated - kept for backward compatibility
    const ADAPTER_COUCHBASE = 'couchbase';
    const ADAPTER_DUAL_WRITE = 'dual_write'; // Deprecated - no longer needed
    
    /**
     * @var array Cached adapter instances
     */
    private static $instances = [];
    
    /**
     * Get an adapter instance by type
     * @param string|null $type Adapter type (defaults to configured default)
     * @return DatabaseAdapterInterface
     * @throws \InvalidArgumentException for unknown adapter type
     */
    public static function getAdapter(string $type = null): DatabaseAdapterInterface
    {
        $type = $type ?? self::getDefaultAdapter();
        
        if (!isset(self::$instances[$type])) {
            self::$instances[$type] = self::createAdapter($type);
        }
        
        return self::$instances[$type];
    }
    
    /**
     * Create a new adapter instance
     * @param string $type Adapter type
     * @return DatabaseAdapterInterface
     * @throws \InvalidArgumentException for unknown adapter type
     */
    private static function createAdapter(string $type): DatabaseAdapterInterface
    {
        switch ($type) {
            case self::ADAPTER_MARIADB:
                return new MariaDbAdapter();
                
            case self::ADAPTER_COUCHBASE:
                return new CouchbaseAdapter();
                
            case self::ADAPTER_DUAL_WRITE:
                return new DualWriteAdapter(
                    new MariaDbAdapter(),
                    new CouchbaseAdapter()
                );
                
            default:
                throw new \InvalidArgumentException("Unknown adapter type: {$type}");
        }
    }
    
    /**
     * Get the default adapter type from configuration
     * @return string Adapter type (always Couchbase - MariaDB is deprecated)
     */
    public static function getDefaultAdapter(): string
    {
        // Couchbase is now the primary and only database
        // MariaDB support is deprecated
        return self::ADAPTER_COUCHBASE;
    }
    
    /**
     * Check if a collection should use Couchbase
     * @param string $collection Collection name
     * @return bool True - all collections now use Couchbase (MariaDB deprecated)
     */
    public static function shouldUseCouchbase(string $collection): bool
    {
        // All data is now in Couchbase - MariaDB is deprecated
        // This function now always returns true for all collections
        // The pattern matching below is kept for reference/logging purposes
        
        // Skip only the Yii migration tracking table
        if ($collection === 'tbl_migration') {
            return false; // This table tracks MariaDB schema changes
        }
        
        return true;
        
        // Legacy pattern matching code below - kept for reference
        // Auto-detect module tables by naming convention
        // This covers all element tables and module-specific tables
        // These tables have CouchbaseModelBridge trait and write to Couchbase,
        // so they should also read from Couchbase
        $moduleTablePatterns = [
            // Module element tables
            '/^et_/',                    // Element tables (et_ophciexamination_*, et_ophtrconsent_*, etc.)
            '/^oph/',                    // Module tables (ophtroperationbooking_*, ophcocorrespondence_*, etc.)
            '/^element_/',               // Legacy element tables (element_ophtrlaser_*)
            '/^patientticketing_/',      // PatientTicketing module
            '/^genetics_/',              // Genetics module
            '/^oetrial_/',               // OETrial module tables
            '/^mailbox/',                // Messaging mailbox tables
            '/^trial/',                  // Trial tables
            '/^queue/',                  // Queue tables
            '/^ticket_/',                // Ticket tables
            '/^cat_prom5_/',             // CatProm5 tables
            '/^catprom/',                // CatProm tables (alternate naming)
            '/^eur_/',                   // EUR (Enhanced Utilisation Report) tables
            '/^pasapi_/',                // PASAPI module tables
            '/^dicom_/',                 // DICOM tables
            '/^archive_/',               // Archive tables
            '/^case_search_/',           // OECaseSearch tables
            '/^automatic_examination_/', // Automatic examination tables
            '/^event_associated_/',      // Event associated content
            '/^macro_init_/',            // Macro initialization tables
            '/^setting_internal_/',      // Internal settings tables
            '/^request_details$/',       // Request details table
            '/^import_status$/',         // Import status table
            '/^treatment_type$/',        // Treatment type table
            '/^user_trial_/',            // User trial assignment tables
            
            // Core infrastructure tables (Phase 2 migration)
            '/^medication_/',            // Medication system tables (23 tables)
            '/^patient_/',               // Patient extension tables (18 tables)
            '/^worklist/',               // Worklist tables (16 tables + worklist)
            '/^pathway/',                // Pathway tables (8 tables + pathway)
            '/^event_/',                 // Event extension tables (12 tables)
            '/^user_/',                  // User extension tables (8 tables)
            '/^proc/',                   // Procedure tables (proc, proc_set, procedure_*)
            '/^document_/',              // Document system tables (6 tables)
            '/^commissioning_/',         // Commissioning body tables (6 tables)
            '/^audit_/',                 // Audit extension tables (5 tables)
            '/^site_/',                  // Site extension tables (5 tables)
            '/^pedigree/',               // Pedigree/genetics tables (5 tables + pedigree)
            '/^setting_/',               // All setting tables (extends existing)
            '/^referral/',               // Referral tables (3 tables)
            '/^anaesthetic_/',           // Anaesthetic tables (5 tables)
            '/^common_/',                // Common reference tables (7 tables)
            '/^contact_/',               // Contact extension tables (4 tables)
            '/^sso_/',                   // SSO tables (5 tables)
            '/^team/',                   // Team tables (3 tables)
            '/^visual_field_/',          // Visual field tables (5 tables)
            '/^episode_/',               // Episode extension tables
            '/^secondary_/',             // Secondary diagnosis tables
            '/^secondaryto_/',           // Secondary to tables
            '/^service/',                // Service tables
            '/^signature_/',             // Signature tables
            '/^virus_/',                 // Virus scan tables
            '/^unique_codes/',           // Unique codes tables
            '/^import/',                 // Import tables
            '/^measurement_/',           // Measurement tables
            '/^media_/',                 // Media tables
            '/^firm_/',                  // Firm extension tables
            '/^followup_/',              // Followup tables
            '/^ldap_/',                  // LDAP config tables
            '/^lsoa_/',                  // LSOA mapping tables
            '/^postcode_/',              // Postcode mapping tables
            '/^imd_/',                   // IMD import tables
            '/^protected_file/',         // Protected file tables
            '/^quarantined_/',           // Quarantined file tables
            '/^pdf_/',                   // PDF tables
            '/^eyedraw_/',               // Eyedraw tables
            '/^drawing_/',               // Drawing templates
            '/^pcr_/',                   // PCR risk tables
            '/^plans_/',                 // Plans/problems tables
            '/^oescape_/',               // OEscape tables
            '/^nsc_/',                   // NSC grade tables
            '/^operative_/',             // Operative device tables
            '/^previous_/',              // Previous operation tables
            '/^study_/',                 // Study tables
            '/^subspecialty_/',          // Subspecialty extension tables
            '/^specialty_/',             // Specialty extension tables
            '/^tbl_audit/',              // Audit trail tables
        ];
        
        foreach ($moduleTablePatterns as $pattern) {
            if (preg_match($pattern, $collection)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get the appropriate adapter for a specific collection
     * @param string $collection Collection name
     * @return DatabaseAdapterInterface (always Couchbase - MariaDB deprecated)
     */
    public static function getAdapterForCollection(string $collection): DatabaseAdapterInterface
    {
        // All collections now use Couchbase
        // MariaDB support is deprecated
        return self::getAdapter(self::ADAPTER_COUCHBASE);
    }
    
    /**
     * Clear cached adapter instances (useful for testing)
     */
    public static function clearInstances(): void
    {
        self::$instances = [];
    }
    
    /**
     * Check if dual-write mode is enabled
     * @deprecated Dual-write is no longer needed - Couchbase is the only database
     * @return bool Always false - dual-write is deprecated
     */
    public static function isDualWriteEnabled(): bool
    {
        return false; // Deprecated - Couchbase is now the only database
    }
    
    /**
     * Check if Couchbase read is enabled
     * @deprecated Couchbase is now the only database
     * @return bool Always true - all reads are from Couchbase
     */
    public static function isCouchbaseReadEnabled(): bool
    {
        return true; // Always true - Couchbase is the only database
    }
    
    /**
     * Get list of collections migrated to Couchbase
     * @return array
     */
    public static function getMigratedCollections(): array
    {
        return \Yii::app()->params['couchbase_migrated_collections'] ?? [];
    }
}
