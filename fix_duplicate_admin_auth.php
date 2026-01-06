<?php
/**
 * Script to fix duplicate admin user authentication
 */
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

require_once($yii);
Yii::createWebApplication($config);

echo "Checking for duplicate admin authentications...\n";

// Find the admin user
$admin_user = User::model()->findByAttributes(['username' => 'admin']);

if (!$admin_user) {
    echo "ERROR: Admin user not found!\n";
    exit(1);
}

echo "Found admin user with ID: " . $admin_user->id . "\n";

// Find all authentications for admin user
$authentications = UserAuthentication::model()->findAllByAttributes(['user_id' => $admin_user->id]);

echo "Found " . count($authentications) . " authentication record(s)\n";

if (count($authentications) > 1) {
    echo "\nDuplicate authentications found! Keeping first, deleting others...\n";
    
    $keep_auth = $authentications[0];
    echo "Keeping authentication ID: " . $keep_auth->id . " (Institution Auth ID: " . $keep_auth->institution_authentication_id . ")\n";
    
    for ($i = 1; $i < count($authentications); $i++) {
        echo "Deleting authentication ID: " . $authentications[$i]->id . "\n";
        if ($authentications[$i]->delete()) {
            echo "  Successfully deleted\n";
        } else {
            echo "  ERROR deleting: " . print_r($authentications[$i]->getErrors(), true) . "\n";
        }
    }
    
    echo "\nDuplicate removal complete!\n";
} else {
    echo "No duplicates found. Single authentication exists.\n";
}

echo "\nFinal check - Admin user authentications:\n";
$final_auths = UserAuthentication::model()->findAllByAttributes(['user_id' => $admin_user->id]);
foreach ($final_auths as $auth) {
    echo "  ID: " . $auth->id . ", Institution Auth ID: " . $auth->institution_authentication_id . ", Username: " . $auth->username . "\n";
}

echo "\nDone!\n";
?>
