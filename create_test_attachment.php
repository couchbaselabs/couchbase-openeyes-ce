<?php
// Test data creation script
// This creates a test attachment in the database for testing

// We need to access through the web server since CLI might not have all configurations
// Let's try to output a simple HTML form or use curl to submit

echo "Creating test attachment...";

// For now, let's just try to connect and insert directly
require_once('/var/www/openeyes/index.php');

// The application should be initialized at this point
try {
    // Create a request first
    $request = new Request();
    $request->payload_received = date('Y-m-d H:i:s');
    $request->overall_status = 'Complete';
    $request->system_message = 'Test request for attachment display';
    
    if ($request->save()) {
        echo "Created request with ID: " . $request->id . "\n";
        
        // Create attachment data
        $attachment = new AttachmentData();
        $attachment->request_id = $request->id;
        $attachment->attachment_mnemonic = 'TEST_ATTACHMENT';
        $attachment->system_only_managed = 0;
        $attachment->attachment_type = 'GENERAL';
        $attachment->mime_type = 'text/plain';
        $attachment->text_data = 'This is test attachment data';
        $attachment->upload_file_name = 'test.txt';
        $attachment->created_user_id = '1';
        $attachment->created_date = date('Y-m-d H:i:s');
        $attachment->last_modified_user_id = '1';
        $attachment->last_modified_date = date('Y-m-d H:i:s');
        
        if ($attachment->save()) {
            echo "Created attachment with ID: " . $attachment->id . "\n";
            echo "Test it at: /Api/attachmentDisplay/view/" . $attachment->id . "?attachment=text_data&mime=text/plain\n";
        } else {
            echo "Error saving attachment: " . json_encode($attachment->getErrors()) . "\n";
        }
    } else {
        echo "Error saving request: " . json_encode($request->getErrors()) . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
