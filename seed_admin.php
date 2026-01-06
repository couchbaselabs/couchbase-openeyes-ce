<?php
/**
 * Temporary script to seed admin user for testing
 */
// Initialize Yii with proper configuration
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

require_once($yii);
Yii::createWebApplication($config);

echo "Attempting to seed admin user...\n";

// Check if admin user exists
$existing_user = User::model()->findByAttributes(['username' => 'admin']);

if ($existing_user) {
    echo "Admin user already exists with ID: " . $existing_user->id . "\n";
    
    // Check if authentication exists
    $existing_auth = UserAuthentication::model()->findByAttributes(['user_id' => $existing_user->id]);
    
    if ($existing_auth) {
        echo "Authentication already exists for admin user\n";
        echo "Username: " . $existing_auth->username . "\n";
    } else {
        echo "Creating authentication for existing admin user...\n";
        
        // Get or create institution authentication
        $inst_auth = InstitutionAuthentication::model()->find();
        if (!$inst_auth) {
            echo "ERROR: No InstitutionAuthentication found\n";
            exit(1);
        }
        
        $auth = new UserAuthentication();
        $auth->user_id = $existing_user->id;
        $auth->username = 'admin';
        $auth->password = 'admin';
        $auth->institution_authentication_id = $inst_auth->id;
        $auth->active = true;
        
        if ($auth->save()) {
            echo "Successfully created authentication for admin user\n";
        } else {
            echo "ERROR creating authentication:\n";
            print_r($auth->getErrors());
            exit(1);
        }
    }
} else {
    echo "Creating admin user...\n";
    
    // Create user
    $user = new User();
    $user->username = 'admin';
    $user->first_name = 'Admin';
    $user->last_name = 'User';
    
    if (!$user->save()) {
        echo "ERROR creating user:\n";
        print_r($user->getErrors());
        exit(1);
    }
    
    echo "Successfully created admin user with ID: " . $user->id . "\n";
    
    // Create authentication
    $inst_auth = InstitutionAuthentication::model()->find();
    if (!$inst_auth) {
        echo "ERROR: No InstitutionAuthentication found\n";
        exit(1);
    }
    
    $auth = new UserAuthentication();
    $auth->user_id = $user->id;
    $auth->username = 'admin';
    $auth->password = 'admin';
    $auth->institution_authentication_id = $inst_auth->id;
    $auth->active = true;
    
    if ($auth->save()) {
        echo "Successfully created authentication for admin user\n";
    } else {
        echo "ERROR creating authentication:\n";
        print_r($auth->getErrors());
        exit(1);
    }
}

echo "\nAdmin user setup complete!\n";
echo "You can now login with username: admin, password: admin\n";
?>
