<?php
// Debug the Couchbase cutover configuration for AuthAssignment
opcache_reset();

$yii = '/var/www/openeyes/vendor/yiisoft/yii/framework/yii.php';
$config = '/var/www/openeyes/protected/config/main.php';
require_once($yii);
Yii::createWebApplication($config);

echo "=== Couchbase Cutover Debug ===\n\n";

try {
    $manager = CouchbaseCutoverManager::getInstance();
    $config = $manager->getConfig();
    
    echo "Global Config:\n";
    echo "  enabled: " . ($config['enabled'] ? 'true' : 'false') . "\n";
    echo "  read_source: " . $config['read_source'] . "\n";
    echo "  write_mode: " . $config['write_mode'] . "\n";
    echo "  emergency_disable: " . ($config['emergency_disable'] ? 'true' : 'false') . "\n\n";
    
    echo "AuthAssignment model config:\n";
    if (isset($config['models']['AuthAssignment'])) {
        print_r($config['models']['AuthAssignment']);
    } else {
        echo "  NOT FOUND in models config!\n";
    }
    echo "\n";
    
    echo "shouldUseCouchbase('AuthAssignment', 'read'): ";
    echo ($manager->shouldUseCouchbase('AuthAssignment', 'read') ? 'true' : 'false') . "\n\n";
    
    // Test direct model lookup
    echo "Direct model check:\n";
    $model = AuthAssignment::model();
    
    // Check if it uses the trait
    echo "  Uses CouchbaseModelBridge: ";
    echo (method_exists($model, 'shouldReadFromCouchbase') ? 'yes' : 'no') . "\n";
    
    // Try to call shouldReadFromCouchbase via reflection
    if (method_exists($model, 'shouldReadFromCouchbase')) {
        $method = new ReflectionMethod($model, 'shouldReadFromCouchbase');
        $method->setAccessible(true);
        echo "  shouldReadFromCouchbase(): " . ($method->invoke($model) ? 'true' : 'false') . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
