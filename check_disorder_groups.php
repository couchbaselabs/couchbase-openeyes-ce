<?php
// Check what's in the CommonOphthalmicDisorderGroup table
require_once 'protected/config/core/main.php';
Yii::createWebApplication($config);

// Query the database
$groups = Yii::app()->db->createCommand('SELECT * FROM common_ophthalmic_disorder_group')->queryAll();
var_dump($groups);

// Count total
$count = Yii::app()->db->createCommand('SELECT COUNT(*) FROM common_ophthalmic_disorder_group')->queryScalar();
echo "\nTotal groups: " . $count . "\n";
?>
