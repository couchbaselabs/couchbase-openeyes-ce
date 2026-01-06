<?php
// Initialize Yii without debug mode
define('YII_DEBUG', false);
define('YII_TRACE_LEVEL', 0);

$yii = '/var/www/openeyes/vendor/yiisoft/yii/framework/yii.php';
$config = '/var/www/openeyes/protected/config/main.php';

require_once($yii);
Yii::createWebApplication($config);

// Check users
$users = User::model()->findAll();
echo "Total users: " . count($users) . "\n";
foreach($users as $user) {
    echo "  - " . $user->username . " (ID: " . $user->id . ")\n";
}

// Check user authentications
$auths = UserAuthentication::model()->findAll();
echo "\nTotal authentications: " . count($auths) . "\n";
foreach($auths as $auth) {
    echo "  - " . $auth->username . " (User ID: " . $auth->user_id . ", Active: " . $auth->active . ")\n";
}

// Check institution authentications
$inst_auths = InstitutionAuthentication::model()->findAll();
echo "\nTotal institution authentications: " . count($inst_auths) . "\n";
foreach($inst_auths as $inst_auth) {
    echo "  - Method: " . $inst_auth->user_authentication_method . " (Institution ID: " . $inst_auth->institution_id . ")\n";
}
?>
