-- Create test patients
INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, title, is_local, created_user_id, created_date) 
VALUES ('TEST001', 'NHS001', '1980-01-15', 'M', 'John', 'Smith', 'Mr', 1, 1, NOW());

INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, title, is_local, created_user_id, created_date) 
VALUES ('TEST002', 'NHS002', '1980-01-15', 'M', 'John', 'Smith', 'Mr', 1, 1, NOW());

-- Create a merge request between the two patients
INSERT INTO patient_merge_request (
    primary_id, 
    secondary_id, 
    status, 
    created_user_id, 
    created_date
) 
VALUES (
    (SELECT id FROM patient WHERE hos_num = 'TEST001' LIMIT 1),
    (SELECT id FROM patient WHERE hos_num = 'TEST002' LIMIT 1),
    0,  -- STATUS_NOT_PROCESSED
    1,
    NOW()
);

-- Verify
SELECT 'Test data created successfully!' as status;
SELECT * FROM patient_merge_request ORDER BY id DESC LIMIT 1;
