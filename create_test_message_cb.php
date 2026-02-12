<?php
/**
 * Script to create a test message using Couchbase REST API
 */

$baseUrl = 'http://localhost:8093';  // N1QL query service
$auth = base64_encode('Administrator:password');
$bucket = 'openeyes';

function executeQuery($query) {
    global $baseUrl, $auth;
    
    $ch = curl_init("$baseUrl/query/service");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic $auth",
            "Content-Type: application/x-www-form-urlencoded"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['statement' => $query])
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "Query failed (HTTP $httpCode): $response\n";
        return null;
    }
    
    return json_decode($response, true);
}

function getOne($query) {
    $result = executeQuery($query);
    if ($result && isset($result['results']) && count($result['results']) > 0) {
        return $result['results'][0];
    }
    return null;
}

function insertDoc($scope, $collection, $key, $doc) {
    global $baseUrl, $auth, $bucket;
    
    $query = "INSERT INTO `$bucket`.`$scope`.`$collection` (KEY, VALUE) VALUES (\"$key\", " . json_encode($doc) . ")";
    return executeQuery($query);
}

function getNextId($scope, $collection) {
    global $bucket;
    $result = getOne("SELECT MAX(TONUMBER(id)) as max_id FROM `$bucket`.`$scope`.`$collection` WHERE id IS NOT MISSING");
    return ($result && isset($result['max_id'])) ? (int)$result['max_id'] + 1 : 1;
}

echo "=== Creating Test Message ===\n\n";

// 1. Check event type
echo "1. Checking event type...\n";
$eventType = getOne("SELECT e.* FROM `openeyes`.`reference`.`event_type` e WHERE e.class_name = 'OphCoMessaging'");
if (!$eventType) {
    echo "ERROR: OphCoMessaging event type not found!\n";
    $all = executeQuery("SELECT class_name, name FROM `openeyes`.`reference`.`event_type`");
    print_r($all);
    exit(1);
}
echo "   Found: ID={$eventType['id']}, Name={$eventType['name']}\n";

// 2. Find admin user
echo "\n2. Finding admin user...\n";
$admin = getOne("SELECT u.* FROM `openeyes`.`admin`.`user` u WHERE u.username = 'admin'");
if (!$admin) {
    echo "ERROR: Admin user not found!\n";
    exit(1);
}
echo "   Found: ID={$admin['id']}, Name={$admin['first_name']} {$admin['last_name']}\n";

// 3. Find admin mailbox
echo "\n3. Finding admin mailbox...\n";
$adminMailbox = getOne("SELECT m.* FROM `openeyes`.`admin`.`mailbox` m 
                        JOIN `openeyes`.`admin`.`mailbox_user` mu ON mu.mailbox_id = m.id 
                        WHERE mu.user_id = " . (int)$admin['id']);
if (!$adminMailbox) {
    echo "   Admin has no mailbox - creating one...\n";
    $mailboxId = getNextId('admin', 'mailbox');
    $mailboxDoc = [
        'id' => (string)$mailboxId,
        'name' => 'Admin User',
        'is_personal' => 1,
        'active' => 1,
        'created_user_id' => (int)$admin['id'],
        'last_modified_user_id' => (int)$admin['id'],
        'created_date' => date('Y-m-d H:i:s'),
        'last_modified_date' => date('Y-m-d H:i:s')
    ];
    insertDoc('admin', 'mailbox', "mailbox::$mailboxId", $mailboxDoc);
    
    // Link to admin user
    $muId = getNextId('admin', 'mailbox_user');
    insertDoc('admin', 'mailbox_user', "mailbox_user::$muId", [
        'id' => (string)$muId,
        'mailbox_id' => $mailboxId,
        'user_id' => (int)$admin['id']
    ]);
    
    $adminMailbox = ['id' => $mailboxId, 'name' => 'Admin User'];
    echo "   Created mailbox: ID={$adminMailbox['id']}\n";
} else {
    echo "   Found: ID={$adminMailbox['id']}, Name={$adminMailbox['name']}\n";
}

// 4. Find a patient
echo "\n4. Finding patient...\n";
$patient = getOne("SELECT p.* FROM `openeyes`.`clinical`.`patient` p WHERE p.deleted = 0 LIMIT 1");
if (!$patient) {
    echo "ERROR: No patient found!\n";
    exit(1);
}
echo "   Found: ID={$patient['id']}, Name={$patient['first_name']} {$patient['last_name']}\n";

// 5. Find episode
echo "\n5. Finding episode...\n";
$episode = getOne("SELECT e.* FROM `openeyes`.`clinical`.`episode` e WHERE e.patient_id = " . (int)$patient['id'] . " AND e.deleted = 0 LIMIT 1");
if (!$episode) {
    echo "ERROR: No episode found!\n";
    exit(1);
}
echo "   Found: ID={$episode['id']}\n";

// 6. Find message type
echo "\n6. Finding message type...\n";
$messageType = getOne("SELECT mt.* FROM `openeyes`.`reference`.`ophcomessaging_message_message_type` mt WHERE mt.deleted = 0 LIMIT 1");
if (!$messageType) {
    echo "ERROR: No message type found!\n";
    exit(1);
}
echo "   Found: ID={$messageType['id']}, Name={$messageType['name']}\n";

// 7. Create event
echo "\n7. Creating event...\n";
$eventId = getNextId('clinical', 'event');
$eventDoc = [
    'id' => (string)$eventId,
    'episode_id' => (int)$episode['id'],
    'event_type_id' => (int)$eventType['id'],
    'event_date' => date('Y-m-d'),
    'info' => 'unread',
    'deleted' => 0,
    'delete_pending' => 0,
    'created_user_id' => (int)$admin['id'],
    'last_modified_user_id' => (int)$admin['id'],
    'created_date' => date('Y-m-d H:i:s'),
    'last_modified_date' => date('Y-m-d H:i:s')
];
$result = insertDoc('clinical', 'event', "event::$eventId", $eventDoc);
if (!$result) {
    echo "ERROR: Failed to create event\n";
    exit(1);
}
echo "   Created event: ID=$eventId\n";

// 8. Create message element
echo "\n8. Creating message element...\n";
$messageId = getNextId('clinical', 'et_ophcomessaging_message');
$messageDoc = [
    'id' => (string)$messageId,
    'event_id' => $eventId,
    'message_type_id' => (int)$messageType['id'],
    'message_text' => "Test message created at " . date('Y-m-d H:i:s') . " - Hello from Couchbase test script!",
    'sender_mailbox_id' => (int)$adminMailbox['id'],
    'urgent' => 0,
    'cc_enabled' => 0,
    'deleted' => 0,
    'created_user_id' => (int)$admin['id'],
    'last_modified_user_id' => (int)$admin['id'],
    'created_date' => date('Y-m-d H:i:s'),
    'last_modified_date' => date('Y-m-d H:i:s')
];
$result = insertDoc('clinical', 'et_ophcomessaging_message', "et_ophcomessaging_message::$messageId", $messageDoc);
if (!$result) {
    echo "ERROR: Failed to create message\n";
    exit(1);
}
echo "   Created message: ID=$messageId\n";

// 9. Create recipient
echo "\n9. Creating recipient...\n";
$recipientId = getNextId('clinical', 'ophcomessaging_message_recipient');
$recipientDoc = [
    'id' => (string)$recipientId,
    'element_id' => $messageId,
    'mailbox_id' => (int)$adminMailbox['id'],
    'primary_recipient' => 1,
    'marked_as_read' => 0,
    'created_user_id' => (int)$admin['id'],
    'last_modified_user_id' => (int)$admin['id'],
    'created_date' => date('Y-m-d H:i:s'),
    'last_modified_date' => date('Y-m-d H:i:s')
];
$result = insertDoc('clinical', 'ophcomessaging_message_recipient', "ophcomessaging_message_recipient::$recipientId", $recipientDoc);
if (!$result) {
    echo "ERROR: Failed to create recipient\n";
    exit(1);
}
echo "   Created recipient: ID=$recipientId\n";

echo "\n=== SUCCESS ===\n";
echo "Message created! Check the mailbox at:\n";
echo "http://localhost:7777/\n";
