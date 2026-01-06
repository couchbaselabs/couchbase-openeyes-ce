<?php
// Check what Couchbase/database adapter is being used
define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

// Set up minimal environment
$_SERVER['HTTP_HOST'] = 'localhost:7777';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '7777';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['QUERY_STRING'] = '';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';

$basePath = dirname(__FILE__);
require_once($basePath . '/vendor/autoload.php');

// Load configuration
$config = require($basePath . '/protected/config/main.php');

// Check if using couchbase
echo "Database configuration:" . PHP_EOL;
if (isset($config['components']['db'])) {
    echo "  DB class: " . $config['components']['db']['class'] . PHP_EOL;
} else {
    echo "  No traditional DB configured" . PHP_EOL;
}

if (isset($config['components']['couchbase'])) {
    echo "  Couchbase configured:" . PHP_EOL;
    echo "    Host: " . $config['components']['couchbase']['host'] . PHP_EOL;
    echo "    Bucket: " . $config['components']['couchbase']['bucket'] . PHP_EOL;
}

echo "Environment variables:" . PHP_EOL;
echo "  OPENEYES_DATABASE_ADAPTER: " . getenv('OPENEYES_DATABASE_ADAPTER') . PHP_EOL;
echo "  OPENEYES_ENABLE_COUCHBASE_READ: " . getenv('OPENEYES_ENABLE_COUCHBASE_READ') . PHP_EOL;
echo "  OPENEYES_ENABLE_DUAL_WRITE: " . getenv('OPENEYES_ENABLE_DUAL_WRITE') . PHP_EOL;

// Try to initialize just the base application without web
try {
    Yii::$classMap['CWebApplication'] = Yii::getPathOfAlias('system.web') . '.CWebApplication';
    
    // Create a basic app instance just to get access to config
    $app = Yii::createConsoleApplication($config);
    
    echo "\nAttempting to count TrialPatient records..." . PHP_EOL;
    // Try to access the database through the ORM
    $count = Yii::app()->db->createCommand('SELECT COUNT(*) FROM trial_patient')->queryScalar();
    echo "TrialPatient count: " . $count . PHP_EOL;
    
    if ($count > 0) {
        echo "\nFirst trial patient:" . PHP_EOL;
        $tp = Yii::app()->db->createCommand('SELECT * FROM trial_patient LIMIT 1')->queryRow();
        echo "  ID: " . $tp['id'] . PHP_EOL;
        echo "  Trial ID: " . $tp['trial_id'] . PHP_EOL;
        echo "  Patient ID: " . $tp['patient_id'] . PHP_EOL;
        echo "  External ID: " . $tp['external_trial_identifier'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    // This is expected if using Couchbase only
}
?>
