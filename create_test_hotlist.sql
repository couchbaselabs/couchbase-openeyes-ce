-- Find admin user ID
-- Find first patient ID
-- Create a hotlist item

-- First, let's see what we have
SELECT id, username FROM user WHERE username = 'admin' LIMIT 1;
SELECT id FROM patient LIMIT 1;
