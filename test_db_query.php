<?php
require_once 'protected/yiic.php';
$config = require('protected/config/main.php');
$app = Yii::createWebApplication($config);

// Test database connection
try {
    $sql = 'SELECT id, name FROM pathway_type ORDER BY id DESC LIMIT 5';
    $result = Yii::app()->db->createCommand($sql)->queryAll();
    echo "Results:\n";
    print_r($result);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
