-- Create test attachment types and mime types first
INSERT INTO attachment_type (attachment_type, title) VALUES ('GENERAL', 'General Attachment');
INSERT INTO mime_type (mime_type, title) VALUES ('application/json', 'JSON');

-- Create a test request
INSERT INTO request (request_type, system_message, created_user_id, created_date, last_modified_user_id, last_modified_date) 
VALUES ('TEST_TYPE', 'Test Request', '1', NOW(), '1', NOW());

-- Create attachment data
INSERT INTO attachment_data (
  request_id, 
  attachment_mnemonic, 
  system_only_managed,
  attachment_type, 
  mime_type,
  text_data,
  upload_file_name,
  created_user_id,
  created_date,
  last_modified_user_id,
  last_modified_date
) VALUES (
  LAST_INSERT_ID(),
  'TEST_ATTACHMENT',
  0,
  'GENERAL',
  'application/json',
  '{"test": "data", "created": true}',
  'test.json',
  '1',
  NOW(),
  '1',
  NOW()
);
