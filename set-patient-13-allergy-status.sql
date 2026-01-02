-- Set no allergies status for patient_id = 13
-- This allows the patient to receive prescriptions

-- First, check current status
SELECT 
    p.id,
    p.hos_num,
    CONCAT(c.first_name, ' ', c.last_name) as patient_name,
    p.no_allergies_date,
    (SELECT COUNT(*) FROM patient_allergy_assignment WHERE patient_id = p.id) as allergy_count,
    CASE 
        WHEN p.no_allergies_date IS NOT NULL THEN 'No Allergies Set'
        WHEN (SELECT COUNT(*) FROM patient_allergy_assignment WHERE patient_id = p.id) > 0 THEN 'Has Allergies'
        ELSE 'Status Unknown - NEEDS FIXING'
    END as current_status
FROM patient p
LEFT JOIN contact c ON p.contact_id = c.id
WHERE p.id = 13;

-- Update the patient to set no allergies status
UPDATE patient 
SET 
    no_allergies_date = NOW(),
    last_modified_date = NOW()
WHERE id = 13
AND no_allergies_date IS NULL  -- Only update if not already set
AND (SELECT COUNT(*) FROM patient_allergy_assignment WHERE patient_id = 13) = 0;  -- Only if no allergies exist

-- Verify the update
SELECT 
    p.id,
    p.hos_num,
    CONCAT(c.first_name, ' ', c.last_name) as patient_name,
    p.no_allergies_date,
    CASE 
        WHEN p.no_allergies_date IS NOT NULL THEN '✓ No Allergies Set - READY FOR PRESCRIPTIONS'
        ELSE '✗ Status Not Set'
    END as updated_status
FROM patient p
LEFT JOIN contact c ON p.contact_id = c.id
WHERE p.id = 13;
