<?php
// Direct test of attachment creation using PDO
$db_config = array(
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'openeyes',
    'username' => 'openeyes',
    'password' => 'openeyes',
);

try {
    $pdo = new PDO(
        'mysql:host=' . $db_config['host'] . ';dbname=' . $db_config['dbname'],
        $db_config['username'],
        $db_config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // First, check if the attachment_data table exists
    $result = $pdo->query("SHOW TABLES LIKE 'attachment_data'")->fetch();
    if ($result) {
        echo "attachment_data table found\n";
        
        // Check if there are any existing attachments
        $stmt = $pdo->query("SELECT * FROM attachment_data LIMIT 5");
        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Found " . count($attachments) . " attachments\n";
        
        if (count($attachments) > 0) {
            foreach ($attachments as $att) {
                echo "ID: " . $att['id'] . ", request_id: " . $att['request_id'] . "\n";
            }
        } else {
            // Try to create test data
            echo "No attachments found. Trying to create test data...\n";
            
            // First check if request table exists and create a request
            $result = $pdo->query("SHOW TABLES LIKE 'request'")->fetch();
            if ($result) {
                // Check if test request exists
                $stmt = $pdo->prepare("SELECT id FROM request WHERE system_message = 'Test for attachment display'");
                $stmt->execute();
                $request_id = $stmt->fetchColumn();
                
                if (!$request_id) {
                    // Create a test request
                    $stmt = $pdo->prepare("INSERT INTO request (payload_received, overall_status, system_message) VALUES (NOW(), 'Complete', 'Test for attachment display')");
                    $stmt->execute();
                    $request_id = $pdo->lastInsertId();
                    echo "Created test request with ID: $request_id\n";
                }
                
                // Now create attachment
                $stmt = $pdo->prepare("INSERT INTO attachment_data (request_id, attachment_mnemonic, system_only_managed, attachment_type, mime_type, text_data, upload_file_name, created_user_id, created_date, last_modified_user_id, last_modified_date) VALUES (?, 'TEST', 0, 'GENERAL', 'text/plain', 'Test attachment data', 'test.txt', 1, NOW(), 1, NOW())");
                $stmt->execute([$request_id]);
                echo "Created attachment with ID: " . $pdo->lastInsertId() . "\n";
            } else {
                echo "request table not found\n";
            }
        }
    } else {
        echo "attachment_data table not found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
