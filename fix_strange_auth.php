<?php
/**
 * Script to create/fix user authentication for user 9 (Dr. Strange)
 */

// Bootstrap Yii
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';
require_once($yii);
Yii::createWebApplication($config);

echo "=== Fix User Authentication for User 9 (Dr. Strange) ===\n\n";

// Find user 9
$user = User::model()->findByPk(9);
if (!$user) {
    die("User 9 not found!\n");
}
echo "User found: {$user->first_name} {$user->last_name}\n\n";

// Get existing authentications
$existingAuths = UserAuthentication::model()->findAllByAttributes(['user_id' => 9]);
echo "Existing authentications for user 9: " . count($existingAuths) . "\n";
foreach ($existingAuths as $auth) {
    echo "  - ID: {$auth->id}, Username: {$auth->username}, Active: {$auth->active}\n";
}

// Find the first institution authentication for Default institution
$instAuth = InstitutionAuthentication::model()->find("institution_id = 1 AND active = 1");
if (!$instAuth) {
    // Fall back to any active institution authentication
    $instAuth = InstitutionAuthentication::model()->find("active = 1");
}

if (!$instAuth) {
    die("No active institution authentication found!\n");
}

echo "\nUsing Institution Authentication ID: {$instAuth->id}\n";
echo "  Description: {$instAuth->description}\n";
echo "  Method: {$instAuth->user_authentication_method}\n\n";

// Check if 'strange' username already exists for user 9
$existingStrange = UserAuthentication::model()->findByAttributes([
    'username' => 'strange',
    'user_id' => 9
]);

if ($existingStrange) {
    echo "User authentication 'strange' already exists for user 9:\n";
    echo "  ID: {$existingStrange->id}\n";
    echo "  Active: {$existingStrange->active}\n";
    echo "  Password hash exists: " . (!empty($existingStrange->password_hash) ? 'Yes' : 'No') . "\n";
} else {
    echo "Creating new user authentication for 'strange'...\n";
    
    $auth = new UserAuthentication();
    $auth->user_id = 9;
    $auth->username = 'strange';
    $auth->institution_authentication_id = $instAuth->id;
    $auth->active = 1;
    $auth->password = 'strange';
    $auth->password_repeat = 'strange';
    $auth->password_status = 'current';
    
    // Handle password
    $auth->handlePassword();
    $auth->setPasswordHash();
    
    if ($auth->save(false)) {
        echo "User authentication created successfully!\n";
        echo "  ID: {$auth->id}\n";
        echo "  Username: {$auth->username}\n";
        echo "  Password hash: " . substr($auth->password_hash, 0, 20) . "...\n";
    } else {
        echo "Failed to create user authentication:\n";
        print_r($auth->getErrors());
    }
}

// Verify by querying again
echo "\n=== Verification ===\n";
$verify = UserAuthentication::model()->findAllByAttributes(['username' => 'strange', 'active' => 1]);
echo "Authentications found for 'strange' (active): " . count($verify) . "\n";
foreach ($verify as $v) {
    echo "  - ID: {$v->id}, User ID: {$v->user_id}, Active: {$v->active}\n";
    
    // Test password verification
    $testPwd = 'strange';
    $verified = $v->verifyPassword($testPwd);
    echo "    Password 'strange' verification: " . ($verified ? 'PASSED' : 'FAILED') . "\n";
}

echo "\nDone!\n";
