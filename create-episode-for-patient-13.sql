-- Create episode for patient 13 to allow clinical events
-- This creates a general Ophthalmology episode

-- First, check if patient 13 has any episodes
SELECT 
    e.id,
    e.patient_id,
    e.firm_id,
    f.name as firm_name,
    s.name as specialty,
    e.start_date,
    e.end_date
FROM episode e
LEFT JOIN firm f ON e.firm_id = f.id
LEFT JOIN service_specialty_assignment ssa ON f.service_specialty_assignment_id = ssa.id
LEFT JOIN specialty s ON ssa.specialty_id = s.id
WHERE e.patient_id = 13;

-- Check available firms (practices/consultants)
SELECT 
    f.id,
    f.name,
    s.name as specialty,
    sub.name as subspecialty
FROM firm f
LEFT JOIN service_specialty_assignment ssa ON f.service_specialty_assignment_id = ssa.id
LEFT JOIN specialty s ON ssa.specialty_id = s.id
LEFT JOIN subspecialty sub ON ssa.subspecialty_id = sub.id
WHERE f.active = 1
LIMIT 10;

-- Create an episode for patient 13 using the first available firm
-- Adjust firm_id if needed based on the output above
INSERT INTO episode (
    patient_id,
    firm_id,
    start_date,
    end_date,
    episode_status_id,
    last_modified_user_id,
    created_user_id,
    last_modified_date,
    created_date
)
SELECT 
    13 as patient_id,
    (SELECT id FROM firm WHERE active = 1 LIMIT 1) as firm_id,
    CURDATE() as start_date,
    NULL as end_date,
    1 as episode_status_id,  -- 1 is typically 'Current'
    1 as last_modified_user_id,
    1 as created_user_id,
    NOW() as last_modified_date,
    NOW() as created_date
WHERE NOT EXISTS (
    SELECT 1 FROM episode WHERE patient_id = 13
);

-- Verify the episode was created
SELECT 
    e.id,
    e.patient_id,
    CONCAT(c.first_name, ' ', c.last_name) as patient_name,
    e.firm_id,
    f.name as firm_name,
    s.name as specialty,
    e.start_date,
    '✓ Episode Created - Ready for Events' as status
FROM episode e
LEFT JOIN patient p ON e.patient_id = p.id
LEFT JOIN contact c ON p.contact_id = c.id
LEFT JOIN firm f ON e.firm_id = f.id
LEFT JOIN service_specialty_assignment ssa ON f.service_specialty_assignment_id = ssa.id
LEFT JOIN specialty s ON ssa.specialty_id = s.id
WHERE e.patient_id = 13;
