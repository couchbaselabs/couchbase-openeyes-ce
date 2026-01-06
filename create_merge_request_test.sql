-- Create a test merge request
INSERT INTO patient_merge_request (
    primary_id, 
    secondary_id, 
    status, 
    comment,
    created_user_id,
    last_modified_user_id,
    created_date,
    last_modified_date,
    deleted
) VALUES (
    17677260488599,
    17677260262264,
    0,
    'Test merge request for delete page testing',
    1,
    1,
    NOW(),
    NOW(),
    0
);

-- Get the ID of the newly created merge request
SELECT LAST_INSERT_ID() as merge_request_id;
