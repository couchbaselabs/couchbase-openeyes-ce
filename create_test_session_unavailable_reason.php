<?php
// Define the base path
define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

$yii = dirname(__FILE__) . '/protected/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

require_once($yii);

Yii::createWebApplication($config)->run();

// Now run the actual command
$reason = new OphTrOperationbooking_Operation_Session_UnavailableReason();
$reason->name = 'Test Session Unavailable Reason ' . time();
$reason->enabled = 1;
$reason->display_order = 1;

if ($reason->save()) {
    echo "SUCCESS: Created reason with ID: " . $reason->id . "\n";
    echo "Name: " . $reason->name . "\n";
} else {
    echo "FAILED: " . print_r($reason->getErrors(), true) . "\n";
}
?>
