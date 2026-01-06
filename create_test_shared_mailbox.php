<?php

$basePath = dirname(__FILE__);
// Handle both local and Docker paths
if (!file_exists($basePath . '/protected/tests/bootstrap.php')) {
    $basePath = '/var/www/openeyes';
}

require_once $basePath . '/protected/tests/bootstrap.php';

try {
    $mailbox = new OEModule\OphCoMessaging\models\Mailbox();
    $mailbox->name = 'Test Shared Mailbox';
    $mailbox->is_personal = 0;
    $mailbox->active = 1;
    
    if ($mailbox->save()) {
        echo "✓ Mailbox created successfully with ID: " . $mailbox->id . "\n";
        echo "✓ Name: " . $mailbox->name . "\n";
        echo "✓ Is Personal: " . $mailbox->is_personal . "\n";
        echo "✓ Active: " . $mailbox->active . "\n";
    } else {
        echo "✗ Failed to save mailbox\n";
        echo "Errors: " . print_r($mailbox->getErrors(), true) . "\n";
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
    echo "✗ Stack trace: " . $e->getTraceAsString() . "\n";
}
