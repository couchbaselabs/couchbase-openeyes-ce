<?php
/**
 * Couchbase Cutover Configuration
 * Auto-updated by CouchbaseCutoverManager
 * Last updated: 2026-01-06 06:23:48
 */

return array (
  'enabled' => true,
  'read_source' => 'couchbase',
  'write_mode' => 'couchbase_primary',
  'couchbase_read_percentage' => 100,
  'fallback_enabled' => true,
  'fallback_on_error' => true,
  'fallback_on_timeout' => true,
  'timeout_threshold_ms' => 500,
  'models' => 
  array (
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
      'read_source' => 'hybrid',
      'percentage' => 50,
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
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'Trial' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'Contact' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'Practice' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'OphTrIntravitrealinjection_SkinDrug' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'OphTrOperationbooking_Whiteboard_Settings' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'SubspecialtySubsection' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'SiteSubspecialtyAnaestheticAgent' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'PathwayType' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'User' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'EmailTemplate' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'OphCoTherapyapplication_TherapyDisorder' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'HistoryMacro' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'Mailbox' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'MailboxUser' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'MailboxTeam' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'OphCoTherapyapplication_DecisionTree' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'OphCoDocument_Sub_Types' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'OphCoCvi_ClinicalInfo_Disorder_Section' => 
    array (
      'read_source' => 'mariadb',
      'percentage' => 0,
    ),
    'CommissioningBodyType' => 
    array (
      'read_source' => 'couchbase',
      'percentage' => 100,
    ),
  ),
  'user_targeting' => 
  array (
    'enabled' => true,
    'internal_users' => true,
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
  'log_fallbacks' => true,
  'log_performance' => true,
);
