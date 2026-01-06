<?php
require 'index.php';

try {
    // Create a test protected file
    $file = new ProtectedFile();
    $file->uid = 'test_' . time();
    $file->name = 'test.txt';
    $file->mimetype = 'text/plain';
    $file->size = 100;
    
    if ($file->save()) {
        echo json_encode([
            'success' => true,
            'file_id' => $file->id,
            'file_uid' => $file->uid,
            'message' => "Test file created successfully!"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'errors' => $file->errors,
            'message' => "Error creating test file"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
