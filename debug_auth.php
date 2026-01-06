<?php
/**
 * Debug the authentication issue in detail
 */

// Initialize Yii application to see what actually happens
define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

$yii_path = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
require_once($yii_path);

$config = require(dirname(__FILE__) . '/protected/config/main.php');

try {
    // Suppress some Yii2Debug issues
    $config['preload'] = array_filter($config['preload'] ?? [], function($item) {
        return $item !== 'debug';
    });
    
    $app = Yii::createWebApplication($config);
    
    echo "=== OpenEyes Authentication Debug ===\n\n";
    
    // Test with different institution/site combinations
    $institution_id = 1;
    $site_id = 1;
    
    echo "Testing UserAuthentication::findAvailableAuthentications('admin', $institution_id, $site_id)\n";
    echo "================================================\n\n";
    
    list($matches, $error) = UserAuthentication::findAvailableAuthentications('admin', $institution_id, $site_id);
    
    echo "Result:\n";
    echo "Error: " . json_encode($error) . "\n";
    echo "Matches:\n";
    
    foreach ($matches as $match_type => $auths) {
        $match_name = ($match_type == 0) ? "NO_MATCH" : (($match_type == 1) ? "PERMISSIVE_MATCH" : (($match_type == 2) ? "EXACT_MATCH" : "UNKNOWN"));
        echo "  [$match_type = $match_name]: " . count($auths) . " record(s)\n";
        
        foreach ($auths as $i => $auth) {
            echo "    Record " . ($i+1) . ":\n";
            echo "      ID: " . (isset($auth->id) ? $auth->id : 'NULL') . "\n";
            echo "      Username: " . (isset($auth->username) ? $auth->username : 'NULL') . "\n";
            echo "      User ID: " . (isset($auth->user_id) ? $auth->user_id : 'NULL') . "\n";
            echo "      Active: " . (isset($auth->active) ? json_encode($auth->active) : 'NULL') . "\n";
            echo "      Institution Auth ID: " . (isset($auth->institution_authentication_id) ? $auth->institution_authentication_id : 'NULL') . "\n";
        }
    }
    
    echo "\n\nAttempting full authentication flow...\n";
    echo "================================================\n\n";
    
    // Try to authenticate
    $user_identity = new UserIdentity('admin', 'admin', $institution_id, $site_id);
    $result = $user_identity->authenticate();
    
    echo "Authentication result:\n";
    echo "Success: " . ($result[0] ? 'true' : 'false') . "\n";
    echo "Message: " . json_encode($result[1]) . "\n";
    echo "Error code: " . (isset($user_identity->errorCode) ? $user_identity->errorCode : 'N/A') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

?>
