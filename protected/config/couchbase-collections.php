<?php
/**
 * Maps MySQL tables to Couchbase scopes and collections
 * 
 * This configuration defines how MySQL data is organized in Couchbase,
 * including embedding decisions and key patterns.
 */

return [
    // =========================================================================
    // CORE SCOPE - Primary entities
    // =========================================================================
    'core' => [
        'patient' => [
            'source_tables' => ['patient', 'contact', 'address', 'patient_identifier'],
            'document_type' => 'patient',
            'key_pattern' => 'patient::{id}',
            'transformer' => 'OE\\Couchbase\\Transformers\\PatientTransformer',
            'embedded' => ['contact', 'addresses', 'identifiers'],
            'indexes' => [
                'idx_patient_hos_num' => ['hos_num'],
                'idx_patient_nhs_num' => ['nhs_num'],
                'idx_patient_name' => ['contact.last_name', 'contact.first_name'],
                'idx_patient_dob' => ['dob'],
                'idx_patient_institution' => ['primary_institution_id'],
            ],
        ],
        
        'user' => [
            'source_tables' => ['user', 'contact'],
            'document_type' => 'user',
            'key_pattern' => 'user::{id}',
            'transformer' => 'OE\\Couchbase\\Transformers\\UserTransformer',
            'embedded' => ['contact'],
            'indexes' => [
                'idx_user_username' => ['username'],
                'idx_user_active' => ['active'],
            ],
        ],
        
        'episode' => [
            'source_tables' => ['episode'],
            'document_type' => 'episode',
            'key_pattern' => 'episode::{id}',
            'transformer' => 'OE\\Couchbase\\Transformers\\EpisodeTransformer',
            'embedded' => [],
            'indexes' => [
                'idx_episode_patient' => ['patient_id'],
                'idx_episode_firm' => ['firm_id'],
                'idx_episode_status' => ['episode_status_id', 'start_date'],
            ],
        ],
        
        'event' => [
            'source_tables' => ['event'],
            'document_type' => 'event',
            'key_pattern' => 'event::{id}',
            'transformer' => 'OE\\Couchbase\\Transformers\\EventTransformer',
            'embedded' => ['small_elements'],
            'indexes' => [
                'idx_event_episode' => ['episode_id'],
                'idx_event_type' => ['event_type_id'],
                'idx_event_date' => ['event_date DESC'],
                'idx_event_institution' => ['institution_id', 'site_id'],
            ],
        ],

        'event_draft' => [
            'source_tables' => ['event_draft'],
            'document_type' => 'event_draft',
            'key_pattern' => 'event_draft::{id}',
            'embedded' => [],
            'indexes' => [
                'idx_event_draft_patient_eventtype_user' => ['patient_id', 'event_type_id', 'last_modified_user_id'],
            ],
        ],
        
        'firm' => [
            'source_tables' => ['firm'],
            'document_type' => 'firm',
            'key_pattern' => 'firm::{id}',
            'embedded' => [],
        ],
        
        'site' => [
            'source_tables' => ['site'],
            'document_type' => 'site',
            'key_pattern' => 'site::{id}',
            'embedded' => [],
        ],
        
        'institution' => [
            'source_tables' => ['institution'],
            'document_type' => 'institution',
            'key_pattern' => 'institution::{id}',
            'embedded' => ['contact', 'address'],
        ],
    ],
    
    // =========================================================================
    // CLINICAL SCOPE - Examination and clinical data
    // =========================================================================
    'clinical' => [
        'examination' => [
            'source_tables' => ['et_ophciexamination_*'],
            'document_type' => 'examination',
            'key_pattern' => 'examination::{event_id}',
            'embedded' => ['small_elements'],
            'element_size_threshold' => 10240, // 10KB - elements larger than this are referenced
        ],
        
        'diagnosis' => [
            'source_tables' => ['disorder', 'secondary_diagnosis'],
            'document_type' => 'diagnosis',
            'key_pattern' => 'diagnosis::{id}',
            'embedded' => [],
        ],
        
        'medication' => [
            'source_tables' => ['medication', 'medication_drug'],
            'document_type' => 'medication',
            'key_pattern' => 'medication::{id}',
            'embedded' => [],
        ],
        
        'allergy' => [
            'source_tables' => ['allergy', 'patient_allergy_assignment'],
            'document_type' => 'allergy',
            'key_pattern' => 'allergy::{id}',
            'embedded' => [],
        ],
        
        // Large examination elements stored separately
        'fundus_drawing' => [
            'source_tables' => ['et_ophciexamination_fundus'],
            'document_type' => 'fundus_drawing',
            'key_pattern' => 'fundus_drawing::{id}',
            'embedded' => [],
        ],
        
        'anterior_segment' => [
            'source_tables' => ['et_ophciexamination_anteriorsegment'],
            'document_type' => 'anterior_segment',
            'key_pattern' => 'anterior_segment::{id}',
            'embedded' => [],
        ],
        
        // =====================================================================
        // PHASE 13: Clinical Module Elements (22 models across 7 modules)
        // =====================================================================
        
        // Operation Notes Module (6 elements)
        'operationnote_cataract' => [
            'source_tables' => ['et_ophtroperationnote_cataract'],
            'document_type' => 'Element_OphTrOperationnote_Cataract',
            'key_pattern' => 'operationnote_cataract::{id}',
            'embedded' => ['iol_type', 'incision_site', 'incision_type', 'iol_position', 'complications', 'operative_devices'],
        ],
        
        'operationnote_procedurelist' => [
            'source_tables' => ['et_ophtroperationnote_procedurelist'],
            'document_type' => 'Element_OphTrOperationnote_ProcedureList',
            'key_pattern' => 'operationnote_procedurelist::{id}',
            'embedded' => ['eye', 'procedures'],
        ],
        
        'operationnote_surgeon' => [
            'source_tables' => ['et_ophtroperationnote_surgeon'],
            'document_type' => 'Element_OphTrOperationnote_Surgeon',
            'key_pattern' => 'operationnote_surgeon::{id}',
            'embedded' => ['surgeon', 'assistant', 'supervising_surgeon'],
        ],
        
        'operationnote_anaesthetic' => [
            'source_tables' => ['et_ophtroperationnote_anaesthetic'],
            'document_type' => 'Element_OphTrOperationnote_Anaesthetic',
            'key_pattern' => 'operationnote_anaesthetic::{id}',
            'embedded' => ['anaesthetic_types', 'delivery_methods', 'anaesthetist', 'agents', 'complications'],
        ],
        
        'operationnote_comments' => [
            'source_tables' => ['et_ophtroperationnote_comments'],
            'document_type' => 'Element_OphTrOperationnote_Comments',
            'key_pattern' => 'operationnote_comments::{id}',
            'embedded' => [],
        ],
        
        'operationnote_generic' => [
            'source_tables' => ['et_ophtroperationnote_genericprocedure'],
            'document_type' => 'Element_OphTrOperationnote_GenericProcedure',
            'key_pattern' => 'operationnote_generic::{id}',
            'embedded' => ['procedure'],
        ],
        
        // Laser Treatment Module (4 elements)
        'laser_treatment' => [
            'source_tables' => ['et_ophtrlaser_treatment'],
            'document_type' => 'Element_OphTrLaser_Treatment',
            'key_pattern' => 'laser_treatment::{id}',
            'embedded' => ['laser', 'site', 'eye', 'procedures', 'operator'],
        ],
        
        'laser_site' => [
            'source_tables' => ['et_ophtrlaser_site'],
            'document_type' => 'Element_OphTrLaser_Site',
            'key_pattern' => 'laser_site::{id}',
            'embedded' => ['laser'],
        ],
        
        'laser_anteriorsegment' => [
            'source_tables' => ['et_ophtrlaser_anteriorsegment'],
            'document_type' => 'Element_OphTrLaser_AnteriorSegment',
            'key_pattern' => 'laser_anteriorsegment::{id}',
            'embedded' => [],
        ],
        
        'laser_posteriorpole' => [
            'source_tables' => ['et_ophtrlaser_posteriorpole'],
            'document_type' => 'Element_OphTrLaser_PosteriorPole',
            'key_pattern' => 'laser_posteriorpole::{id}',
            'embedded' => [],
        ],
        
        // Biometry Module (3 elements)
        'biometry_measurement' => [
            'source_tables' => ['et_ophinbiometry_measurement'],
            'document_type' => 'Element_OphInBiometry_Measurement',
            'key_pattern' => 'biometry_measurement::{id}',
            'embedded' => ['eye'],
        ],
        
        'biometry_calculation' => [
            'source_tables' => ['et_ophinbiometry_calculation'],
            'document_type' => 'Element_OphInBiometry_Calculation',
            'key_pattern' => 'biometry_calculation::{id}',
            'embedded' => ['eye', 'calculations'],
        ],
        
        'biometry_selection' => [
            'source_tables' => ['et_ophinbiometry_selection'],
            'document_type' => 'Element_OphInBiometry_Selection',
            'key_pattern' => 'biometry_selection::{id}',
            'embedded' => ['eye', 'iol_type'],
        ],
        
        // Prescription Module (1 element)
        'prescription_details' => [
            'source_tables' => ['et_ophdrprescription_details'],
            'document_type' => 'Element_OphDrPrescription_Details',
            'key_pattern' => 'prescription_details::{id}',
            'embedded' => ['prescription_items'],
        ],
        
        // Correspondence Module (1 element - enhanced existing)
        'element_letter' => [
            'source_tables' => ['et_ophcocorrespondence_letter'],
            'document_type' => 'ElementLetter',
            'key_pattern' => 'element_letter::{id}',
            'embedded' => ['letter_type', 'site', 'enclosures', 'document_instances', 'internal_referral'],
        ],
        
        // Operation Booking Module (3 elements)
        'opbooking_operation' => [
            'source_tables' => ['et_ophtroperationbooking_operation'],
            'document_type' => 'Element_OphTrOperationbooking_Operation',
            'key_pattern' => 'opbooking_operation::{id}',
            'embedded' => ['eye', 'procedures', 'anaesthetic_types', 'site', 'priority', 'status', 'booking', 'overnight_stay_required'],
        ],
        
        'opbooking_diagnosis' => [
            'source_tables' => ['et_ophtroperationbooking_diagnosis'],
            'document_type' => 'Element_OphTrOperationbooking_Diagnosis',
            'key_pattern' => 'opbooking_diagnosis::{id}',
            'embedded' => ['eye', 'disorder'],
        ],
        
        'opbooking_schedule' => [
            'source_tables' => ['et_ophtroperationbooking_scheduleope'],
            'document_type' => 'Element_OphTrOperationbooking_ScheduleOperation',
            'key_pattern' => 'opbooking_schedule::{id}',
            'embedded' => ['schedule_options', 'patient_unavailables'],
        ],
        
        // CVI Module (3 elements)
        'cvi_eventinfo' => [
            'source_tables' => ['et_ophcocvi_eventinfo'],
            'document_type' => 'Element_OphCoCvi_EventInfo',
            'key_pattern' => 'cvi_eventinfo::{id}',
            'embedded' => ['site', 'consultant_in_charge', 'generated_document', 'gp_delivery', 'la_delivery', 'rco_delivery'],
        ],
        
        'cvi_clinicalinfo' => [
            'source_tables' => ['et_ophcocvi_clinicinfo'],
            'document_type' => 'Element_OphCoCvi_ClinicalInfo',
            'key_pattern' => 'cvi_clinicalinfo::{id}',
            'embedded' => ['consultant'],
        ],
        
        'cvi_clericalinfo' => [
            'source_tables' => ['et_ophcocvi_clericinfo'],
            'document_type' => 'Element_OphCoCvi_ClericalInfo',
            'key_pattern' => 'cvi_clericalinfo::{id}',
            'embedded' => ['employment_status', 'preferred_language', 'contact_urgency'],
        ],
    ],
    
    // =========================================================================
    // CORRESPONDENCE SCOPE - Letters, messages, documents
    // =========================================================================
    'correspondence' => [
        'letter' => [
            'source_tables' => ['et_ophcocorrespondence_letter'],
            'document_type' => 'letter',
            'key_pattern' => 'letter::{id}',
            'embedded' => [],
        ],
        
        'message' => [
            'source_tables' => ['ophcomessaging_message'],
            'document_type' => 'message',
            'key_pattern' => 'message::{id}',
            'embedded' => [],
        ],
        
        'document' => [
            'source_tables' => ['et_ophcodocument_document', 'protected_file'],
            'document_type' => 'document',
            'key_pattern' => 'document::{id}',
            'embedded' => ['file_metadata'],
        ],
    ],
    
    // =========================================================================
    // BOOKING SCOPE - Operations and scheduling
    // =========================================================================
    'booking' => [
        'operation' => [
            'source_tables' => [
                'et_ophtroperationbooking_operation',
                'ophtroperationbooking_operation_booking',
            ],
            'document_type' => 'operation',
            'key_pattern' => 'operation::{id}',
            'embedded' => ['procedures'],
        ],
        
        'session' => [
            'source_tables' => ['ophtroperationbooking_operation_session'],
            'document_type' => 'session',
            'key_pattern' => 'session::{id}',
            'embedded' => [],
        ],
        
        'theatre' => [
            'source_tables' => ['ophtroperationbooking_operation_theatre'],
            'document_type' => 'theatre',
            'key_pattern' => 'theatre::{id}',
            'embedded' => [],
        ],
    ],
    
    // =========================================================================
    // ADMIN SCOPE - Audit and system data
    // =========================================================================
    'admin' => [
        'audit' => [
            'source_tables' => ['audit'],
            'document_type' => 'audit',
            'key_pattern' => 'audit::{id}',
            'embedded' => [],
            'ttl' => 31536000, // 1 year TTL for audit logs
        ],
        
        'setting' => [
            'source_tables' => ['setting_metadata', 'setting_installation'],
            'document_type' => 'setting',
            'key_pattern' => 'setting::{key}',
            'embedded' => [],
        ],
    ],
    
    // =========================================================================
    // REFERENCE SCOPE - Lookup tables
    // =========================================================================
    'reference' => [
        'ophciexamination_workflow' => [
            'source_tables' => ['ophciexamination_workflow'],
            'document_type' => 'ophciexamination_workflow',
            'key_pattern' => 'ophciexamination_workflow::{id}',
            'embedded' => [],
            'indexes' => [
                'idx_ophciexamination_workflow_inst_name' => ['institution_id', 'name'],
            ],
        ],
        
        'ophciexamination_workflow_rule' => [
            'source_tables' => ['ophciexamination_workflow_rule'],
            'document_type' => 'ophciexamination_workflow_rule',
            'key_pattern' => 'ophciexamination_workflow_rule::{id}',
            'embedded' => [],
            'indexes' => [
                'idx_workflow_rule_workflow' => ['workflow_id'],
                'idx_workflow_rule_firm' => ['firm_id'],
                'idx_workflow_rule_subspecialty' => ['subspecialty_id'],
            ],
        ],
        
        'event_type' => [
            'source_tables' => ['event_type'],
            'document_type' => 'event_type',
            'key_pattern' => 'event_type::{id}',
            'cache' => true, // Cache in memory
        ],
        
        'element_type' => [
            'source_tables' => ['element_type'],
            'document_type' => 'element_type',
            'key_pattern' => 'element_type::{id}',
            'cache' => true,
        ],
        
        'specialty' => [
            'source_tables' => ['specialty'],
            'document_type' => 'specialty',
            'key_pattern' => 'specialty::{id}',
            'cache' => true,
        ],
        
        'subspecialty' => [
            'source_tables' => ['subspecialty'],
            'document_type' => 'subspecialty',
            'key_pattern' => 'subspecialty::{id}',
            'cache' => true,
        ],
        
        'disorder' => [
            'source_tables' => ['disorder'],
            'document_type' => 'disorder',
            'key_pattern' => 'disorder::{id}',
        ],
        
        'ethnic_group' => [
            'source_tables' => ['ethnic_group'],
            'document_type' => 'ethnic_group',
            'key_pattern' => 'ethnic_group::{id}',
            'cache' => true,
        ],
        
        'gender' => [
            'source_tables' => ['gender'],
            'document_type' => 'gender',
            'key_pattern' => 'gender::{id}',
            'cache' => true,
        ],
        
        'country' => [
            'source_tables' => ['country'],
            'document_type' => 'country',
            'key_pattern' => 'country::{id}',
            'cache' => true,
        ],
        
        'medication_set' => [
            'source_tables' => ['medication_set'],
            'document_type' => 'medication_set',
            'key_pattern' => 'medication_set::{id}',
            'embedded' => ['rules'],
            'indexes' => [
                'idx_medication_set_name' => ['name'],
                'idx_medication_set_hidden' => ['hidden'],
            ],
        ],
        
        'medication_set_item' => [
            'source_tables' => ['medication_set_item'],
            'document_type' => 'medication_set_item',
            'key_pattern' => 'medication_set_item::{id}',
            'embedded' => ['medication', 'medication_set', 'default_route', 'default_form', 'default_frequency', 'default_duration'],
            'indexes' => [
                'idx_medication_set_item_medication' => ['medication.id'],
                'idx_medication_set_item_set' => ['medication_set.id'],
                'idx_medication_set_item_combo' => ['medication.id', 'medication_set.id'],
            ],
        ],
    ],
];
