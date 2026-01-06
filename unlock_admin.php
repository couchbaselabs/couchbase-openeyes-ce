<?php
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

require_once($yii);
Yii::createWebApplication($config);

echo "Attempting to unlock admin user...\n";

// Find the admin user
$admin_user = User::model()->find('username=?', array('admin'));

if (!$admin_user) {
    echo "ERROR: Admin user not found!\n";
    exit(1);
}

echo "Found admin user with ID: " . $admin_user->id . "\n";

// Get their authentication record
$ua = $admin_user->userAuthentication;

if (!$ua) {
    echo "ERROR: No authentication found for admin user\n";
    exit(1);
}

// Reset the password status fields
$ua->pw_status = null;
$ua->password_softlocked_until = null;
$ua->failed_tries = 0;

if ($ua->save()) {
    echo "Successfully unlocked admin user!\n";
    echo "pw_status: null\n";
    echo "password_softlocked_until: null\n";
    echo "failed_tries: 0\n";
} else {
    echo "ERROR saving admin user: " . json_encode($ua->getErrors()) . "\n";
    exit(1);
}
?>
