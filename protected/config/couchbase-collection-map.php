<?php
/**
 * MySQL table to Couchbase scope/collection mapping
 * 
 * Comprehensive mapping for ALL OpenEyes models
 * Updated: Phase 17 - Complete Model Migration
 */

return [
    // ============================================
    // CLINICAL SCOPE - Patient & Episode Data
    // ============================================
    'clinical' => [
        // Core clinical
        'patient',
        'patient_allergy_assignment',
        'patient_contact_assignment',
        'patient_contact_associate',
        'patient_identifier',
        'patient_identifier_status',
        'patient_identifier_type',
        'patient_measurement',
        'patient_merge_request',
        'patient_oph_info',
        'patient_referral',
        'patient_risk_assignment',
        'patient_shortcode',
        'patient_statistic',
        'contact',
        'address',
        'episode',
        'episode_status',
        'episode_summary_item',
        'event',
        'event_draft',
        'event_icon',
        'event_image',
        'event_issue',
        'event_medication_use',
        'event_subtype',
        'event_template',
        'deleted_episode',
        'deleted_event',
        // Worklist
        'worklist',
        'worklist_attribute',
        'worklist_definition',
        'worklist_filter',
        'worklist_patient',
        // Pathway
        'pathway',
        'pathway_step',
        'pathway_step_type',
        'pathway_type',
        // Firm/Service
        'firm',
        'firm_user_assignment',
        'service',
        'service_subspecialty_assignment',
        
        // Consent Module (OphTrConsent)
        'ophtrconsent_type_type',
        'ophtrconsent_type_assessment',
        'ophtrconsent_additional_risk',
        'ophtrconsent_additional_risk_subspecialty_assignment',
        'ophtrconsent_patient_contact_method',
        'ophtrconsent_procedure_extra',
        'ophtrconsent_extra_proc_subspecialty_assignment',
        'ophtrconsent_template',
        'ophtrconsent_template_procedure',
        'ophtrconsent_leaflets',
        'ophtrconsent_leaflet_firm',
        'ophtrconsent_leaflet_subspecialty',
        'ophtrconsent_patient_relationship',
        'ophtrconsent_supplementary_consent_question_type',
        'ophtrconsent_supplementary_consent_question_assignment',
        'ophtrconsent_supplementary_consent_question_answer',
        'ophtrconsent_authorised_decision',
        'ophtrconsent_considered_decision',
        'ophtrconsent_lack_of_capacity_reason',
        'ophtrconsent_medical_capacity_advocate_instructed',
        'ophtrconsent_paper_copies',
        'ophtrconsent_signature',
        'ophtrconsent_procedure_anaesthetic_type',
        'ophtrconsent_procedure_extra_assignment',
    ],
    
    // ============================================
    // EXAMINATION SCOPE - OphCiExamination Module
    // ============================================
    'examination' => [
        // All Element_OphCiExamination_* tables
        // All OphCiExamination_* lookup tables
        // Dynamically populated based on model name prefix
    ],
    
    // ============================================
    // OPERATION NOTE SCOPE - OphTrOperationnote Module
    // ============================================
    'operationnote' => [
        // All Element_OphTrOperationnote_* tables
        // All OphTrOperationnote_* lookup tables
    ],
    
    // ============================================
    // BOOKING SCOPE - OphTrOperationbooking Module
    // ============================================
    'booking' => [
        'operation',
        'session',
        'whiteboard',
        'booking',
        'theatre',
        // All Element_OphTrOperationbooking_* tables
        // All OphTrOperationbooking_* lookup tables
    ],
    
    // ============================================
    // CORRESPONDENCE SCOPE - OphCoCorrespondence Module
    // ============================================
    'correspondence' => [
        'letter',
        'letter_macro',
        'letter_string',
        'letter_recipient',
        'letter_enclosure',
        'element_letter',
        'message',
        'document',
        'macro',
    ],
    
    // ============================================
    // CONSENT SCOPE - OphTrConsent Module
    // ============================================
    'consent' => [
        // All Element_OphTrConsent_* tables
        // All OphTrConsent_* lookup tables
    ],
    
    // ============================================
    // PRESCRIPTION SCOPE - OphDrPrescription Module
    // ============================================
    'prescription' => [
        // All Element_OphDrPrescription_* tables
        // All OphDrPrescription_* lookup tables
    ],
    
    // ============================================
    // LASER SCOPE - OphTrLaser Module
    // ============================================
    'laser' => [
        // All Element_OphTrLaser_* tables
        // All OphTrLaser_* lookup tables
    ],
    
    // ============================================
    // INJECTION SCOPE - OphTrIntravitrealinjection Module
    // ============================================
    'injection' => [
        // All Element_OphTrIntravitrealinjection_* tables
        // All OphTrIntravitrealinjection_* lookup tables
    ],
    
    // ============================================
    // BIOMETRY SCOPE - OphInBiometry Module
    // ============================================
    'biometry' => [
        // All Element_OphInBiometry_* tables
        // All OphInBiometry_* lookup tables
    ],
    
    // ============================================
    // CVI SCOPE - OphCoCvi Module
    // ============================================
    'cvi' => [
        // All Element_OphCoCvi_* tables
        // All OphCoCvi_* lookup tables
    ],
    
    // ============================================
    // VISUAL FIELDS SCOPE - OphInVisualfields Module
    // ============================================
    'visualfields' => [
        // All Element_OphInVisualfields_* tables
        // All OphInVisualfields_* lookup tables
    ],
    
    // ============================================
    // THERAPY SCOPE - OphCoTherapyapplication Module
    // ============================================
    'therapy' => [
        // All Element_OphCoTherapyapplication_* tables
        // All OphCoTherapyapplication_* lookup tables
    ],
    
    // ============================================
    // CHECKLISTS SCOPE - OphTrOperationchecklists Module
    // ============================================
    'checklists' => [
        // All Element_OphTrOperationchecklists_* tables
        // All OphTrOperationchecklists_* lookup tables
    ],
    
    // ============================================
    // MESSAGING SCOPE - OphCoMessaging Module
    // ============================================
    'messaging' => [
        'mailbox',
        'mailbox_team',
        'mailbox_user',
        // All Element_OphCoMessaging_* tables
        // All OphCoMessaging_* lookup tables
    ],
    
    // ============================================
    // LAB RESULTS SCOPE - OphInLabResults Module
    // ============================================
    'labresults' => [
        // All Element_OphInLabResults_* tables
        // All OphInLabResults_* lookup tables
    ],
    
    // ============================================
    // GENETICS SCOPE - Genetics & DNA Modules
    // ============================================
    'genetics' => [
        'genetics_patient',
        'genetics_study',
        // All OphInDnaextraction_* tables
        // All OphInDnasample_* tables
        // All OphInGeneticresults_* tables
    ],
    
    // ============================================
    // PGDPSD SCOPE - OphDrPGDPSD Module
    // ============================================
    'pgdpsd' => [
        // All Element_DrugAdministration_* tables
        // All OphDrPGDPSD_* lookup tables
    ],
    
    // ============================================
    // TRIAL SCOPE - OETrial Module
    // ============================================
    'trial' => [
        'trial',
        'trial_patient',
        'trial_type',
        // All OETrial_* tables
    ],
    
    // ============================================
    // SEARCH SCOPE - OECaseSearch Module
    // ============================================
    'search' => [
        'saved_search',
        // All CaseSearch* tables
        // All *Parameter tables
        // All *Variable tables
    ],
    
    // ============================================
    // TICKETING SCOPE - PatientTicketing Module
    // ============================================
    'ticketing' => [
        'queue',
        'queue_set',
        'ticket',
        // All PatientTicketing_* tables
    ],
    
    // ============================================
    // GENERIC SCOPE - OphGeneric Module
    // ============================================
    'generic' => [
        // All OphGeneric_* tables
    ],
    
    // ============================================
    // DOCUMENT SCOPE - OphCoDocument Module
    // ============================================
    'document' => [
        // All Element_OphCoDocument_* tables
        // All OphCoDocument_* tables
    ],
    
    // ============================================
    // ADMIN SCOPE - Administrative Data
    // ============================================
    'admin' => [
        // User management
        'user',
        'user_authentication',
        'user_authentication_method',
        'user_firm',
        'user_firm_preference',
        'user_firm_rights',
        'user_hotlist_item',
        'user_out_of_office',
        'user_pincode',
        'user_service_rights',
        'user_site',
        // Audit
        'audit',
        'audit_action',
        'audit_type',
        'audit_ip_addr',
        'audit_model',
        'audit_module',
        'audit_server',
        'audit_trail',
        'audit_useragent',
        // Settings
        'setting_metadata',
        'setting_installation',
        'setting_institution',
        'setting_site',
        'setting_firm',
        'setting_user',
        'setting_specialty',
        'setting_subspecialty',
        'setting_group',
        'setting_field_type',
        // Auth
        'auth_item',
        'auth_assignment',
        // SSO
        'sso_default_firms',
        'sso_default_rights',
        'sso_default_roles',
        'sso_openid_connect',
        'sso_roles',
        // Site/Institution
        'site',
        'site_logo',
        'institution',
        'institution_authentication',
        // Import/Export
        'import',
        'import_log',
        'import_source',
        'import_status',
        // Break Glass
        'break_glass_model',
    ],
    
    // ============================================
    // REFERENCE SCOPE - Lookup/Reference Data
    // ============================================
    'reference' => [
        // API Reference Data
        'attachment_type',
        // Disorders & Diagnoses
        'disorder',
        'common_ophthalmic_disorder',
        'common_systemic_disorder',
        'secondary_diagnosis',
        // Medications & Drugs
        'medication',
        'medication_attribute',
        'medication_drug',
        'medication_form',
        'medication_frequency',
        'medication_route',
        'medication_duration',
        'medication_laterality',
        'medication_set',
        'medication_set_item',
        'drug',
        'allergy',
        // Procedures
        'procedure',
        'procedure_benefit',
        'procedure_complication',
        'procedure_risk',
        'procedure_set',
        'opcs_code',
        // Anaesthetic
        'anaesthetic_agent',
        'anaesthetic_complication',
        'anaesthetic_delivery',
        'anaesthetic_type',
        'anaesthetist',
        // General Reference
        'address_type',
        'contact_label',
        'country',
        'language',
        'ethnic_group',
        'gender',
        'eye',
        'period',
        'priority',
        'risk',
        'finding',
        'benefit',
        'complication',
        'tag',
        // Practice/GP
        'practice',
        'gp',
        'commissioning_body',
        'commissioning_body_type',
        // Specialty
        'specialty',
        'specialty_type',
        'subspecialty',
        'subspecialty_subsection',
        // Event/Element Types
        'event_type',
        'event_group',
        'element_type',
        'element_group',
        // Doctor/Staff
        'doctor_grade',
        // Previous Operations
        'previous_operation',
        'common_previous_operation',
        // Referral
        'referral',
        'referral_type',
        // Pedigree/Inheritance
        'pedigree',
        'pedigree_amino_acid_change_type',
        'pedigree_gene',
        'pedigree_inheritance',
        'pedigree_status',
        // Consent Reference Data
        'ophtrconsent_leaflet',
        'ophtrconsent_supplementary_consent_question',
        // PASAPI - XPath Remapping
        'pasapi_xpath_remap',
    ],
];
