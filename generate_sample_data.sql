-- Generate sample examination data for Couchbase sync testing
-- This creates 5 examination events with Visual Acuity, IOP, Refraction, and Diagnoses elements

-- Variables (adjust as needed)
SET @patient_id = 1;
SET @episode_id = 1;
SET @institution_id = 1;
SET @site_id = 1;
SET @user_id = 1;
SET @event_type_id = (SELECT id FROM event_type WHERE class_name = 'OphCiExamination');

-- Get lookup IDs
SET @va_method_id = (SELECT id FROM ophciexamination_visualacuity_method LIMIT 1);
SET @va_unit_id = (SELECT id FROM ophciexamination_visual_acuity_unit LIMIT 1);
SET @iop_instrument_id = (SELECT id FROM ophciexamination_instrument LIMIT 1);
SET @refraction_type_id = (SELECT id FROM ophciexamination_refraction_type LIMIT 1);
SET @disorder_id = (SELECT id FROM disorder LIMIT 1);

-- Create 5 examination events
-- Event 1
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 1 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id_1 = LAST_INSERT_ID();

-- Visual Acuity for Event 1
INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_1, 3, @user_id, @user_id, NOW(), NOW());
SET @va_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_date, last_modified_date)
VALUES 
    (@va_element_id, 0, 65, @va_method_id, @va_unit_id, NOW(), NOW()),  -- Left eye
    (@va_element_id, 1, 70, @va_method_id, @va_unit_id, NOW(), NOW());  -- Right eye

-- IOP for Event 1
INSERT INTO et_ophciexamination_intraocularpressure (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_1, 3, @user_id, @user_id, NOW(), NOW());
SET @iop_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_intraocularpressure_value (element_id, eye_id, reading, instrument_id, created_date, last_modified_date)
VALUES 
    (@iop_element_id, 1, 15, @iop_instrument_id, NOW(), NOW()),  -- Left eye
    (@iop_element_id, 2, 16, @iop_instrument_id, NOW(), NOW());  -- Right eye

-- Refraction for Event 1
INSERT INTO et_ophciexamination_refraction (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_1, 3, @user_id, @user_id, NOW(), NOW());
SET @refraction_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_refraction_reading (element_id, eye_id, sphere, cylinder, axis, type_id, created_date, last_modified_date)
VALUES 
    (@refraction_element_id, 1, -2.50, -0.75, 90, @refraction_type_id, NOW(), NOW()),   -- Left eye
    (@refraction_element_id, 2, -2.25, -0.50, 85, @refraction_type_id, NOW(), NOW());   -- Right eye

-- Diagnoses for Event 1
INSERT INTO et_ophciexamination_diagnoses (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_1, 3, @user_id, @user_id, NOW(), NOW());
SET @diagnoses_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_diagnosis (element_id, disorder_id, eye_id, principal, created_date, last_modified_date)
VALUES (@diagnoses_element_id, @disorder_id, 3, 1, NOW(), NOW());


-- Event 2
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 2 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id_2 = LAST_INSERT_ID();

INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_2, 3, @user_id, @user_id, NOW(), NOW());
SET @va_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_date, last_modified_date)
VALUES 
    (@va_element_id, 0, 68, @va_method_id, @va_unit_id, NOW(), NOW()),
    (@va_element_id, 1, 72, @va_method_id, @va_unit_id, NOW(), NOW());

INSERT INTO et_ophciexamination_intraocularpressure (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_2, 3, @user_id, @user_id, NOW(), NOW());
SET @iop_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_intraocularpressure_value (element_id, eye_id, reading, instrument_id, created_date, last_modified_date)
VALUES 
    (@iop_element_id, 1, 14, @iop_instrument_id, NOW(), NOW()),
    (@iop_element_id, 2, 15, @iop_instrument_id, NOW(), NOW());

INSERT INTO et_ophciexamination_refraction (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_2, 3, @user_id, @user_id, NOW(), NOW());
SET @refraction_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_refraction_reading (element_id, eye_id, sphere, cylinder, axis, type_id, created_date, last_modified_date)
VALUES 
    (@refraction_element_id, 1, -2.75, -0.50, 95, @refraction_type_id, NOW(), NOW()),
    (@refraction_element_id, 2, -2.00, -0.75, 80, @refraction_type_id, NOW(), NOW());

INSERT INTO et_ophciexamination_diagnoses (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_2, 3, @user_id, @user_id, NOW(), NOW());
SET @diagnoses_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_diagnosis (element_id, disorder_id, eye_id, principal, created_date, last_modified_date)
VALUES (@diagnoses_element_id, @disorder_id, 3, 1, NOW(), NOW());


-- Event 3
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 3 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id_3 = LAST_INSERT_ID();

INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_3, 3, @user_id, @user_id, NOW(), NOW());
SET @va_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_date, last_modified_date)
VALUES 
    (@va_element_id, 0, 60, @va_method_id, @va_unit_id, NOW(), NOW()),
    (@va_element_id, 1, 65, @va_method_id, @va_unit_id, NOW(), NOW());

INSERT INTO et_ophciexamination_intraocularpressure (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_3, 3, @user_id, @user_id, NOW(), NOW());
SET @iop_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_intraocularpressure_value (element_id, eye_id, reading, instrument_id, created_date, last_modified_date)
VALUES 
    (@iop_element_id, 1, 17, @iop_instrument_id, NOW(), NOW()),
    (@iop_element_id, 2, 18, @iop_instrument_id, NOW(), NOW());

INSERT INTO et_ophciexamination_refraction (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_3, 3, @user_id, @user_id, NOW(), NOW());
SET @refraction_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_refraction_reading (element_id, eye_id, sphere, cylinder, axis, type_id, created_date, last_modified_date)
VALUES 
    (@refraction_element_id, 1, -3.00, -1.00, 85, @refraction_type_id, NOW(), NOW()),
    (@refraction_element_id, 2, -2.50, -0.50, 90, @refraction_type_id, NOW(), NOW());

INSERT INTO et_ophciexamination_diagnoses (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_3, 3, @user_id, @user_id, NOW(), NOW());
SET @diagnoses_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_diagnosis (element_id, disorder_id, eye_id, principal, created_date, last_modified_date)
VALUES (@diagnoses_element_id, @disorder_id, 3, 1, NOW(), NOW());


-- Event 4
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 4 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id_4 = LAST_INSERT_ID();

INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_4, 3, @user_id, @user_id, NOW(), NOW());
SET @va_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_date, last_modified_date)
VALUES 
    (@va_element_id, 0, 75, @va_method_id, @va_unit_id, NOW(), NOW()),
    (@va_element_id, 1, 80, @va_method_id, @va_unit_id, NOW(), NOW());

INSERT INTO et_ophciexamination_intraocularpressure (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_4, 3, @user_id, @user_id, NOW(), NOW());
SET @iop_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_intraocularpressure_value (element_id, eye_id, reading, instrument_id, created_date, last_modified_date)
VALUES 
    (@iop_element_id, 1, 13, @iop_instrument_id, NOW(), NOW()),
    (@iop_element_id, 2, 14, @iop_instrument_id, NOW(), NOW());

INSERT INTO et_ophciexamination_refraction (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_4, 3, @user_id, @user_id, NOW(), NOW());
SET @refraction_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_refraction_reading (element_id, eye_id, sphere, cylinder, axis, type_id, created_date, last_modified_date)
VALUES 
    (@refraction_element_id, 1, -1.75, -0.25, 100, @refraction_type_id, NOW(), NOW()),
    (@refraction_element_id, 2, -1.50, -0.50, 75, @refraction_type_id, NOW(), NOW());

INSERT INTO et_ophciexamination_diagnoses (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_4, 3, @user_id, @user_id, NOW(), NOW());
SET @diagnoses_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_diagnosis (element_id, disorder_id, eye_id, principal, created_date, last_modified_date)
VALUES (@diagnoses_element_id, @disorder_id, 3, 1, NOW(), NOW());


-- Event 5
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 5 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id_5 = LAST_INSERT_ID();

INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_5, 3, @user_id, @user_id, NOW(), NOW());
SET @va_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_date, last_modified_date)
VALUES 
    (@va_element_id, 0, 70, @va_method_id, @va_unit_id, NOW(), NOW()),
    (@va_element_id, 1, 75, @va_method_id, @va_unit_id, NOW(), NOW());

INSERT INTO et_ophciexamination_intraocularpressure (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_5, 3, @user_id, @user_id, NOW(), NOW());
SET @iop_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_intraocularpressure_value (element_id, eye_id, reading, instrument_id, created_date, last_modified_date)
VALUES 
    (@iop_element_id, 1, 16, @iop_instrument_id, NOW(), NOW()),
    (@iop_element_id, 2, 17, @iop_instrument_id, NOW(), NOW());

INSERT INTO et_ophciexamination_refraction (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_5, 3, @user_id, @user_id, NOW(), NOW());
SET @refraction_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_refraction_reading (element_id, eye_id, sphere, cylinder, axis, type_id, created_date, last_modified_date)
VALUES 
    (@refraction_element_id, 1, -2.25, -0.75, 92, @refraction_type_id, NOW(), NOW()),
    (@refraction_element_id, 2, -2.00, -0.50, 88, @refraction_type_id, NOW(), NOW());

INSERT INTO et_ophciexamination_diagnoses (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id_5, 3, @user_id, @user_id, NOW(), NOW());
SET @diagnoses_element_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_diagnosis (element_id, disorder_id, eye_id, principal, created_date, last_modified_date)
VALUES (@diagnoses_element_id, @disorder_id, 3, 1, NOW(), NOW());

-- Verification query
SELECT 
    COUNT(*) as examination_count,
    (SELECT COUNT(*) FROM et_ophciexamination_visualacuity) as va_count,
    (SELECT COUNT(*) FROM et_ophciexamination_intraocularpressure) as iop_count,
    (SELECT COUNT(*) FROM et_ophciexamination_refraction) as refraction_count,
    (SELECT COUNT(*) FROM et_ophciexamination_diagnoses) as diagnoses_count
FROM event 
WHERE event_type_id = @event_type_id 
AND deleted = 0;
