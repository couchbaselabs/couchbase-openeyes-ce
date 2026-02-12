<?php
/**
 * Script to create a test message for the mailbox
 */

// Include Yii bootstrap
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

require_once($yii);
Yii::createWebApplication($config);

// Check EventType for OphCoMessaging
$eventType = EventType::model()->find('class_name = :name', [':name' => 'OphCoMessaging']);
if (!$eventType) {
    echo "ERROR: OphCoMessaging event type not found!\n";
    echo "Available event types:\n";
    $all = EventType::model()->findAll();
    foreach ($all as $et) {
        echo "  - {$et->class_name} ({$et->name})\n";
    }
    exit(1);
}
echo "Event type found: {$eventType->id} - {$eventType->name}\n";

// Find admin user and their mailbox
$admin = User::model()->find('username = :name', [':name' => 'admin']);
if (!$admin) {
    echo "ERROR: Admin user not found!\n";
    exit(1);
}
echo "Admin user: {$admin->id} - {$admin->getFullName()}\n";

// Find admin's mailbox
$adminMailbox = \OEModule\OphCoMessaging\models\Mailbox::model()->find([
    'join' => 'JOIN mailbox_user mu ON mu.mailbox_id = t.id',
    'condition' => 'mu.user_id = :userId',
    'params' => [':userId' => $admin->id]
]);

if (!$adminMailbox) {
    echo "Admin has no mailbox - creating one...\n";
    // Create a personal mailbox for admin
    $adminMailbox = new \OEModule\OphCoMessaging\models\Mailbox();
    $adminMailbox->name = "Admin User";
    $adminMailbox->is_personal = 1;
    $adminMailbox->active = 1;
    if ($adminMailbox->save()) {
        // Link to admin user
        $mailboxUser = new \OEModule\OphCoMessaging\models\MailboxUser();
        $mailboxUser->mailbox_id = $adminMailbox->id;
        $mailboxUser->user_id = $admin->id;
        $mailboxUser->save();
        echo "Created mailbox: {$adminMailbox->id}\n";
    } else {
        echo "Failed to create mailbox: " . print_r($adminMailbox->getErrors(), true) . "\n";
        exit(1);
    }
}
echo "Admin mailbox: {$adminMailbox->id} - {$adminMailbox->name}\n";

// Find a patient
$patient = Patient::model()->find();
if (!$patient) {
    echo "ERROR: No patient found!\n";
    exit(1);
}
echo "Patient: {$patient->id} - {$patient->getFullName()}\n";

// Find or create an episode for the patient
$episode = Episode::model()->find('patient_id = :pid', [':pid' => $patient->id]);
if (!$episode) {
    echo "Creating new episode for patient...\n";
    $episode = new Episode();
    $episode->patient_id = $patient->id;
    $episode->firm_id = Firm::model()->find()->id;
    $episode->start_date = date('Y-m-d');
    $episode->save();
}
echo "Episode: {$episode->id}\n";

// Get message type
$messageType = \OEModule\OphCoMessaging\models\OphCoMessaging_Message_MessageType::model()->find();
if (!$messageType) {
    echo "ERROR: No message type found!\n";
    exit(1);
}
echo "Message type: {$messageType->id} - {$messageType->name}\n";

// Create the event
$event = new Event();
$event->episode_id = $episode->id;
$event->event_type_id = $eventType->id;
$event->event_date = date('Y-m-d');
$event->created_user_id = $admin->id;
$event->last_modified_user_id = $admin->id;

if (!$event->save()) {
    echo "ERROR: Failed to save event: " . print_r($event->getErrors(), true) . "\n";
    exit(1);
}
echo "Created event: {$event->id}\n";

// Create the message element
$message = new \OEModule\OphCoMessaging\models\Element_OphCoMessaging_Message();
$message->event_id = $event->id;
$message->message_type_id = $messageType->id;
$message->message_text = "Test message created at " . date('Y-m-d H:i:s') . " - Hello from the test script!";
$message->sender_mailbox_id = $adminMailbox->id;
$message->urgent = 0;
$message->created_user_id = $admin->id;
$message->last_modified_user_id = $admin->id;

if (!$message->save(false)) {
    echo "ERROR: Failed to save message: " . print_r($message->getErrors(), true) . "\n";
    exit(1);
}
echo "Created message element: {$message->id}\n";

// Create recipient (send to self for testing)
$recipient = new \OEModule\OphCoMessaging\models\OphCoMessaging_Message_Recipient();
$recipient->element_id = $message->id;
$recipient->mailbox_id = $adminMailbox->id;
$recipient->primary_recipient = 1;
$recipient->marked_as_read = 0;

if (!$recipient->save()) {
    echo "ERROR: Failed to save recipient: " . print_r($recipient->getErrors(), true) . "\n";
    exit(1);
}
echo "Created recipient: {$recipient->id}\n";

echo "\n=== SUCCESS ===\n";
echo "Message created successfully! Check the mailbox at:\n";
echo "http://localhost:7777/\n";
