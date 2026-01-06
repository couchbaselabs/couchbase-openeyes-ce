<?php
// Initialize Yii
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

require_once($yii);
Yii::createWebApplication($config);

echo "Creating test user for profile testing...\n";

try {
    // Get or create the default institution
    $institution = Institution::model()->find('name = ?', array('OpenEyes Default Institution'));
    if (!$institution) {
        echo "ERROR: Could not find OpenEyes Default Institution\n";
        exit(1);
    }
    
    // Check if test user already exists
    $existing_user = User::model()->findByAttributes(['username' => 'testadmin']);
    if ($existing_user) {
        echo "Test user already exists with ID: " . $existing_user->id . "\n";
        $existing_auth = UserAuthentication::model()->findByAttributes(['user_id' => $existing_user->id]);
        if ($existing_auth) {
            echo "Username: " . $existing_auth->username . "\n";
            echo "Active: " . ($existing_auth->active ? 'Yes' : 'No') . "\n";
        }
        exit(0);
    }
    
    // Create a new user
    $user = new User();
    $user->username = 'testadmin';
    $user->first_name = 'Test';
    $user->last_name = 'Admin';
    $user->email = 'testadmin@test.local';
    
    if (!$user->save()) {
        echo "ERROR creating user:\n";
        print_r($user->getErrors());
        exit(1);
    }
    
    echo "Created user with ID: " . $user->id . "\n";
    
    // Create authentication
    $inst_auth = InstitutionAuthentication::model()->find('institution_id = ? AND user_authentication_method = ?', 
        array($institution->id, 'LOCAL'));
    if (!$inst_auth) {
        echo "ERROR: Could not find LOCAL authentication method for institution\n";
        exit(1);
    }
    
    $auth = new UserAuthentication();
    $auth->user_id = $user->id;
    $auth->username = 'testadmin';
    $auth->password = 'testadmin';
    $auth->password_repeat = 'testadmin';
    $auth->institution_authentication_id = $inst_auth->id;
    $auth->active = true;
    
    if (!$auth->save()) {
        echo "ERROR creating authentication:\n";
        print_r($auth->getErrors());
        exit(1);
    }
    
    echo "Successfully created test user authentication\n";
    echo "Username: testadmin\n";
    echo "Password: testadmin\n";
    echo "You can now login with these credentials\n";
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
?>
