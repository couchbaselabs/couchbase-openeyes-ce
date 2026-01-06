<?php
// This script creates test request routine data for testing the edit page
define('STDIN',fopen("php://stdin","r"));
require_once dirname(__FILE__) . '/protected/config/main.php';

// Create a request first
$request = new Request();
$request->payload_received = date('Y-m-d H:i:s');
$request->overall_status = 'Complete';
$request->system_message = 'Test request for request routine';

if ($request->save()) {
    echo "Created request with ID: " . $request->id . "\n";
    
    // Now create request routine
    $routine = new RequestRoutine();
    $routine->request_id = $request->id;
    $routine->execute_request_queue = 'DEFAULT';
    $routine->status = 'NEW';
    $routine->routine_name = 'TEST_ROUTINE';
    $routine->try_count = 0;
    $routine->execute_sequence = 1;
    $routine->hash_code = 123;
    
    if ($routine->save()) {
        echo "Created request routine with ID: " . $routine->id . "\n";
        echo "You can now test: http://localhost:7777/Api/Request/admin/requestAdmin/requestRoutine/edit?id=" . $routine->id . "\n";
    } else {
        echo "Error creating request routine:\n";
        print_r($routine->getErrors());
    }
} else {
    echo "Error creating request:\n";
    print_r($request->getErrors());
}
?>
