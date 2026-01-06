<?php
// Test script for OphCoTherapyapplication_Treatment
$config = require('protected/config/main.php');
Yii::$enableIncludePath = false;
$app = new CWebApplication($config);

// Get all treatments
$treatments = OphCoTherapyapplication_Treatment::model()->findAll(array('order' => 'id DESC', 'limit' => 10));

echo "Found " . count($treatments) . " treatments\n";
foreach ($treatments as $treatment) {
    echo "Treatment ID: " . $treatment->id . ", Drug: " . ($treatment->drug ? $treatment->drug->name : 'N/A') . ", Name: " . $treatment->getName() . "\n";
}
?>
