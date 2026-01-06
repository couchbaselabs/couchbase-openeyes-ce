<?php
/**
 * Create a test Session Unavailable Reason
 */
chdir(dirname(__FILE__));

$config=require_once('protected/config/main.php');
$app=Yii::createWebApplication($config);

// Create a new unavailable reason
$reason = new OphTrOperationbooking_Operation_Session_UnavailableReason();
$reason->name = 'Test Unavailable Reason ' . uniqid();
$reason->display_order = 1;
$reason->enabled = true;

if ($reason->save()) {
    echo "Successfully created Session Unavailable Reason with ID: " . $reason->id . "\n";
    echo "Name: " . $reason->name . "\n";
} else {
    echo "Failed to create reason:\n";
    print_r($reason->getErrors());
}
?>
