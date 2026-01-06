<?php
define('YII_DEBUG', true);
define('YII_ENV', 'dev');

// Setup include path
set_include_path(dirname(__FILE__) . "/protected/framework" . PATH_SEPARATOR . get_include_path());

// Load Yii
require_once('/var/www/openeyes/vendor/yiisoft/yii/framework/yii.php');

// Create Yii app
$config = require('/var/www/openeyes/protected/config/main.php');
$app = new CWebApplication($config);

// Create a UniqueCodes record
$code = new UniqueCodes();
$code->code = 'TEST-CODE-001';
$code->active = 1;

try {
    if ($code->save()) {
        echo "Success! Created UniqueCodes with ID: " . $code->id;
    } else {
        echo "Error saving record: " . json_encode($code->getErrors());
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>
