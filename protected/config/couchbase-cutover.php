<?php
/**
 * Couchbase Cutover Configuration
 * 
 * IMPORTANT: MariaDB has been fully deprecated.
 * All models now use Couchbase as the primary (and only) data store.
 * 
 * Last updated: 2026-01-07 - Full Couchbase Migration
 */

return array (
  'enabled' => true,
  'read_source' => 'couchbase',
  'write_mode' => 'couchbase_primary',  // Couchbase only - no MariaDB
  'couchbase_read_percentage' => 100,
  'fallback_enabled' => false,  // Disabled - no MariaDB to fall back to
  'fallback_on_error' => false,
  'fallback_on_timeout' => false,
  'timeout_threshold_ms' => 500,
  'models' => 
  array (
    // Core Models - All using Couchbase
    'Patient' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'Episode' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'Event' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'Disorder' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'Medication' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'Audit' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'Trial' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'Contact' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'Practice' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'User' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'UserAuthentication' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'InstitutionAuthentication' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'AuthAssignment' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    
    // Genetics Models
    'PedigreeAminoAcidChangeType' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'PedigreeGene' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'PedigreeBaseChangeType' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    
    // Module Models - All using Couchbase
    'OphTrIntravitrealinjection_SkinDrug' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'OphTrOperationbooking_Whiteboard_Settings' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'SubspecialtySubsection' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'SiteSubspecialtyAnaestheticAgent' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'PathwayType' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'EmailTemplate' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'OphCoTherapyapplication_TherapyDisorder' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'HistoryMacro' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'Mailbox' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'MailboxUser' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'MailboxTeam' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'OphCoTherapyapplication_DecisionTree' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'OphCoTherapyapplication_Treatment' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'OphCoDocument_Sub_Types' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'OphCoCvi_ClinicalInfo_Disorder_Section' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'CommissioningBodyType' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'EmailTemplate' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'SenderEmailAddresses' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'LetterMacro' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'RequestRoutine' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
    'ProcedureSubspecialtySubsectionAssignment' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
  ),
  'user_targeting' => 
  array (
    'enabled' => false,  // Disabled - all users use Couchbase now
    'internal_users' => false,
    'beta_user_ids' => 
    array (
    ),
    'excluded_user_ids' => 
    array (
    ),
  ),
  'site_targeting' => 
  array (
    'enabled' => false,
    'enabled_site_ids' => 
    array (
    ),
    'excluded_site_ids' => 
    array (
    ),
  ),
  'emergency_disable' => false,
  'emergency_disable_reason' => '',
  'emergency_disable_timestamp' => NULL,
  'log_routing_decisions' => false,
  'log_fallbacks' => false,
  'log_performance' => true,
);
