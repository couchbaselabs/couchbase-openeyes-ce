-- Generate simple examination data for Couchbase sync testing
-- Creates 5 examination events with Visual Acuity elements only

SET @patient_id = 1;
SET @episode_id = 1;
SET @institution_id = 1;
SET @site_id = 1;
SET @user_id = 1;
SET @event_type_id = (SELECT id FROM event_type WHERE class_name = 'OphCiExamination');
SET @va_method_id = (SELECT id FROM ophciexamination_visualacuity_method LIMIT 1);
SET @va_unit_id = (SELECT id FROM ophciexamination_visual_acuity_unit LIMIT 1);

-- Event 1
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 1 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id = LAST_INSERT_ID();

INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id, 3, @user_id, @user_id, NOW(), NOW());
SET @va_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES 
    (@va_id, 0, 65, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW()),
    (@va_id, 1, 70, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW());

-- Event 2
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 2 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id = LAST_INSERT_ID();

INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id, 3, @user_id, @user_id, NOW(), NOW());
SET @va_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES 
    (@va_id, 0, 68, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW()),
    (@va_id, 1, 72, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW());

-- Event 3
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 3 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id = LAST_INSERT_ID();

INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id, 3, @user_id, @user_id, NOW(), NOW());
SET @va_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES 
    (@va_id, 0, 60, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW()),
    (@va_id, 1, 65, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW());

-- Event 4
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 4 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id = LAST_INSERT_ID();

INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id, 3, @user_id, @user_id, NOW(), NOW());
SET @va_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES 
    (@va_id, 0, 75, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW()),
    (@va_id, 1, 80, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW());

-- Event 5
INSERT INTO event (event_type_id, episode_id, institution_id, site_id, event_date, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted, delete_pending)
VALUES (@event_type_id, @episode_id, @institution_id, @site_id, DATE_SUB(NOW(), INTERVAL 5 DAY), @user_id, @user_id, NOW(), NOW(), 0, 0);
SET @event_id = LAST_INSERT_ID();

INSERT INTO et_ophciexamination_visualacuity (event_id, eye_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES (@event_id, 3, @user_id, @user_id, NOW(), NOW());
SET @va_id = LAST_INSERT_ID();

INSERT INTO ophciexamination_visualacuity_reading (element_id, side, value, method_id, unit_id, created_user_id, last_modified_user_id, created_date, last_modified_date)
VALUES 
    (@va_id, 0, 70, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW()),
    (@va_id, 1, 75, @va_method_id, @va_unit_id, @user_id, @user_id, NOW(), NOW());

-- Verify
SELECT 
    'Data created successfully!' as status,
    (SELECT COUNT(*) FROM event WHERE event_type_id = @event_type_id AND deleted = 0) as exam_events,
    (SELECT COUNT(*) FROM et_ophciexamination_visualacuity) as va_elements,
    (SELECT COUNT(*) FROM ophciexamination_visualacuity_reading) as va_readings;
