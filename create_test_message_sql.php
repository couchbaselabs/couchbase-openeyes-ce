<?php
/**
 * Script to create a test message using SQL directly
 */

// Simple database connection
try {
    $pdo = new PDO(
        'mysql:host=db;dbname=openeyes;charset=utf8',
        'openeyes',
        'openeyes',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Connected to database\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Check EventType for OphCoMessaging
$stmt = $pdo->query("SELECT id, class_name, name FROM event_type WHERE class_name = 'OphCoMessaging'");
$eventType = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$eventType) {
    echo "ERROR: OphCoMessaging event type not found!\n";
    echo "Available event types:\n";
    $all = $pdo->query("SELECT id, class_name, name FROM event_type")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all as $et) {
        echo "  - {$et['id']}: {$et['class_name']} ({$et['name']})\n";
    }
    exit(1);
}
echo "Event type found: {$eventType['id']} - {$eventType['name']}\n";

// Find admin user
$stmt = $pdo->prepare("SELECT id, first_name, last_name FROM user WHERE username = :username");
$stmt->execute([':username' => 'admin']);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    echo "ERROR: Admin user not found!\n";
    exit(1);
}
echo "Admin user: {$admin['id']} - {$admin['first_name']} {$admin['last_name']}\n";

// Find admin's mailbox
$stmt = $pdo->prepare("SELECT m.id, m.name FROM mailbox m 
                       JOIN mailbox_user mu ON mu.mailbox_id = m.id 
                       WHERE mu.user_id = :userId");
$stmt->execute([':userId' => $admin['id']]);
$adminMailbox = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$adminMailbox) {
    echo "Admin has no mailbox - creating one...\n";
    // Create a personal mailbox for admin
    $pdo->exec("INSERT INTO mailbox (name, is_personal, active, created_user_id, last_modified_user_id, created_date, last_modified_date) 
                VALUES ('Admin User', 1, 1, 1, 1, NOW(), NOW())");
    $mailboxId = $pdo->lastInsertId();
    
    // Link to admin user
    $stmt = $pdo->prepare("INSERT INTO mailbox_user (mailbox_id, user_id) VALUES (:mailboxId, :userId)");
    $stmt->execute([':mailboxId' => $mailboxId, ':userId' => $admin['id']]);
    
    $adminMailbox = ['id' => $mailboxId, 'name' => 'Admin User'];
    echo "Created mailbox: {$adminMailbox['id']}\n";
}
echo "Admin mailbox: {$adminMailbox['id']} - {$adminMailbox['name']}\n";

// Find a patient  
$patient = $pdo->query("SELECT id, first_name, last_name FROM patient LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$patient) {
    echo "ERROR: No patient found!\n";
    exit(1);
}
echo "Patient: {$patient['id']} - {$patient['first_name']} {$patient['last_name']}\n";

// Find an episode for the patient
$stmt = $pdo->prepare("SELECT id FROM episode WHERE patient_id = :pid AND deleted = 0 LIMIT 1");
$stmt->execute([':pid' => $patient['id']]);
$episode = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$episode) {
    echo "ERROR: No episode found for patient!\n";
    exit(1);
}
echo "Episode: {$episode['id']}\n";

// Get message type
$messageType = $pdo->query("SELECT id, name FROM ophcomessaging_message_message_type WHERE deleted = 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$messageType) {
    echo "ERROR: No message type found! Creating one...\n";
    $pdo->exec("INSERT INTO ophcomessaging_message_message_type (name, display_order, deleted, created_user_id, last_modified_user_id, created_date, last_modified_date, reply_required)
                VALUES ('General', 1, 0, 1, 1, NOW(), NOW(), 1)");
    $messageType = ['id' => $pdo->lastInsertId(), 'name' => 'General'];
}
echo "Message type: {$messageType['id']} - {$messageType['name']}\n";

// Create the event
$stmt = $pdo->prepare("INSERT INTO event (episode_id, event_type_id, event_date, info, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted) 
                       VALUES (:episode_id, :event_type_id, :event_date, 'unread', :created_user_id, :last_modified_user_id, NOW(), NOW(), 0)");
$stmt->execute([
    ':episode_id' => $episode['id'],
    ':event_type_id' => $eventType['id'],
    ':event_date' => date('Y-m-d'),
    ':created_user_id' => $admin['id'],
    ':last_modified_user_id' => $admin['id']
]);
$eventId = $pdo->lastInsertId();
echo "Created event: {$eventId}\n";

// Create the message element
$stmt = $pdo->prepare("INSERT INTO et_ophcomessaging_message 
                       (event_id, message_type_id, message_text, sender_mailbox_id, urgent, cc_enabled, created_user_id, last_modified_user_id, created_date, last_modified_date, deleted) 
                       VALUES (:event_id, :message_type_id, :message_text, :sender_mailbox_id, 0, 0, :created_user_id, :last_modified_user_id, NOW(), NOW(), 0)");
$stmt->execute([
    ':event_id' => $eventId,
    ':message_type_id' => $messageType['id'],
    ':message_text' => "Test message created at " . date('Y-m-d H:i:s') . " - Hello from the test script!",
    ':sender_mailbox_id' => $adminMailbox['id'],
    ':created_user_id' => $admin['id'],
    ':last_modified_user_id' => $admin['id']
]);
$messageId = $pdo->lastInsertId();
echo "Created message element: {$messageId}\n";

// Create recipient (send to self for testing - unread)
$stmt = $pdo->prepare("INSERT INTO ophcomessaging_message_recipient 
                       (element_id, mailbox_id, primary_recipient, marked_as_read, created_user_id, last_modified_user_id, created_date, last_modified_date) 
                       VALUES (:element_id, :mailbox_id, 1, 0, :created_user_id, :last_modified_user_id, NOW(), NOW())");
$stmt->execute([
    ':element_id' => $messageId,
    ':mailbox_id' => $adminMailbox['id'],
    ':created_user_id' => $admin['id'],
    ':last_modified_user_id' => $admin['id']
]);
$recipientId = $pdo->lastInsertId();
echo "Created recipient: {$recipientId}\n";

echo "\n=== SUCCESS ===\n";
echo "Message created successfully! Check the mailbox at:\n";
echo "http://localhost:7777/\n";
