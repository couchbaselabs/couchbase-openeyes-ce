<?php
/**
 * Script to fix duplicate admin user authentication records
 */

require_once("vendor/yiisoft/yii/framework/yii.php");

$config = require("protected/config/main.php");
$app = Yii::createWebApplication($config);

// Find admin user
$user = User::model()->find('username = ?', ['admin']);

if (!$user) {
    echo "Admin user not found\n";
    exit(1);
}

echo "Found admin user: " . $user->id . "\n";

// Find all user authentications for admin
$auths = UserAuthentication::model()->findAll('user_id = ?', [$user->id]);

echo "Admin has " . count($auths) . " UserAuthentications\n";

// Group by institution_authentication_id
$grouped = [];
foreach ($auths as $auth) {
    $key = $auth->institution_authentication_id ?: 'NULL';
    if (!isset($grouped[$key])) {
        $grouped[$key] = [];
    }
    $grouped[$key][] = $auth;
}

// Find duplicates
$to_delete = [];
foreach ($grouped as $inst_auth_id => $auths_group) {
    if (count($auths_group) > 1) {
        echo "Found " . count($auths_group) . " duplicates for institution_authentication_id: $inst_auth_id\n";
        // Keep the first one, delete the rest
        for ($i = 1; $i < count($auths_group); $i++) {
            $to_delete[] = $auths_group[$i];
            echo "  - Marked auth ID " . $auths_group[$i]->id . " for deletion\n";
        }
    }
}

if (empty($to_delete)) {
    echo "No duplicates found\n";
    exit(0);
}

// Delete duplicates
foreach ($to_delete as $auth) {
    if ($auth->delete()) {
        echo "✓ Deleted auth ID " . $auth->id . "\n";
    } else {
        echo "✗ Failed to delete auth ID " . $auth->id . "\n";
        print_r($auth->getErrors());
    }
}

echo "\n✓ Duplicate admin authentications removed!\n";
?>
