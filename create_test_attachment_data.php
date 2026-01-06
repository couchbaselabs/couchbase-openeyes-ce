<?php
// This script creates test attachment data for testing the edit page
require_once dirname(__FILE__) . '/protected/config/main.php';

// Create a request first
$request = new Request();
$request->payload_received = date('Y-m-d H:i:s');
$request->overall_status = 'Complete';
$request->system_message = 'Test request for attachment data';

if ($request->save()) {
    echo "Created request with ID: " . $request->id . "\n";
    
    // Now create attachment data
    $attachmentData = new AttachmentData();
    $attachmentData->request_id = $request->id;
    $attachmentData->attachment_mnemonic = 'TEST_ATTACHMENT';
    $attachmentData->system_only_managed = 0;
    $attachmentData->attachment_type = 'GENERAL';
    $attachmentData->mime_type = 'application/json';
    $attachmentData->text_data = json_encode(['test' => 'data', 'created' => true]);
    $attachmentData->upload_file_name = 'test_file.txt';
    $attachmentData->created_user_id = '1';
    $attachmentData->created_date = date('Y-m-d H:i:s');
    $attachmentData->last_modified_user_id = '1';
    $attachmentData->last_modified_date = date('Y-m-d H:i:s');
    
    if ($attachmentData->save()) {
        echo "Created attachment data with ID: " . $attachmentData->id . "\n";
        echo "You can now test: http://localhost:7777/Api/Request/RequestAdmin/attachmentData/edit?id=" . $attachmentData->id . "\n";
    } else {
        echo "Error creating attachment data:\n";
        print_r($attachmentData->getErrors());
    }
} else {
    echo "Error creating request:\n";
    print_r($request->getErrors());
}
?>
