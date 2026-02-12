<?php
/**
 * Web-accessible script to create user authentication for user 9 (Dr. Strange)
 */
header('Content-Type: text/plain');

// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';
require_once($yii);
Yii::createWebApplication($config);

echo "=== Create User Authentication for User 9 (Dr. Strange) ===\n\n";

try {
    // Find user 9
    $user = User::model()->findByPk(9);
    if (!$user) {
        die("User 9 not found!\n");
    }
    echo "User found: {$user->first_name} {$user->last_name} (ID: {$user->id})\n\n";

    // Find the first institution authentication for Default institution
    $instAuth = InstitutionAuthentication::model()->find("active = 1 OR active = true");
    
    if (!$instAuth) {
        die("No active institution authentication found!\n");
    }

    echo "Using Institution Authentication:\n";
    echo "  ID: {$instAuth->id}\n";
    echo "  Description: {$instAuth->description}\n";
    echo "  Method: {$instAuth->user_authentication_method}\n";
    echo "  Institution ID: {$instAuth->institution_id}\n\n";

    // Check existing auth
    echo "Checking for existing 'strange' authentication...\n";
    $existingStrange = UserAuthentication::model()->findByAttributes([
        'username' => 'strange'
    ]);

    if ($existingStrange) {
        echo "Found existing authentication:\n";
        echo "  ID: {$existingStrange->id}\n";
        echo "  User ID: {$existingStrange->user_id}\n";
        echo "  Active: {$existingStrange->active}\n";
        echo "  Has password hash: " . (!empty($existingStrange->password_hash) ? 'Yes' : 'No') . "\n";
        
        // Update to ensure it's linked to user 9
        if ($existingStrange->user_id != 9) {
            echo "\nUpdating to link to user 9...\n";
            $existingStrange->user_id = 9;
            $existingStrange->save(false);
        }
        
        // Test password
        echo "\nTesting password verification...\n";
        $verified = $existingStrange->verifyPassword('strange');
        echo "Password 'strange' verification: " . ($verified ? 'PASSED' : 'FAILED') . "\n";
        
        if (!$verified) {
            echo "\nResetting password...\n";
            $existingStrange->password = 'strange';
            $existingStrange->password_repeat = 'strange';
            $existingStrange->password_status = 'current';
            $existingStrange->password_salt = null;
            $existingStrange->password_hash = password_hash('strange', PASSWORD_BCRYPT);
            $existingStrange->active = 1;
            if ($existingStrange->save(false)) {
                echo "Password reset successful!\n";
            } else {
                echo "Password reset failed: " . print_r($existingStrange->getErrors(), true) . "\n";
            }
        }
    } else {
        echo "No existing authentication found. Creating new one...\n\n";
        
        $auth = new UserAuthentication();
        $auth->user_id = 9;
        $auth->username = 'strange';
        $auth->institution_authentication_id = $instAuth->id;
        $auth->active = 1;
        $auth->password_status = 'current';
        $auth->password_salt = null;
        $auth->password_hash = password_hash('strange', PASSWORD_BCRYPT);
        
        if ($auth->save(false)) {
            echo "User authentication created successfully!\n";
            echo "  ID: {$auth->id}\n";
            echo "  Username: {$auth->username}\n";
            echo "  User ID: {$auth->user_id}\n";
            echo "  Hash: " . substr($auth->password_hash, 0, 30) . "...\n";
        } else {
            echo "Failed to create user authentication:\n";
            print_r($auth->getErrors());
        }
    }

    // Final verification
    echo "\n=== Final Verification ===\n";
    $verifyAuth = UserAuthentication::model()->findAllByAttributes(['username' => 'strange', 'active' => 1]);
    echo "Active authentications for 'strange': " . count($verifyAuth) . "\n";
    foreach ($verifyAuth as $v) {
        echo "  - ID: {$v->id}, User ID: {$v->user_id}\n";
        $verified = $v->verifyPassword('strange');
        echo "    Password verification: " . ($verified ? 'PASSED' : 'FAILED') . "\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nDone!\n";
