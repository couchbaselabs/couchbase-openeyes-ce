<?php
// Include the OpenEyes configuration
require_once('protected/config/main.php');

// Create application instance
$app = Yii::createWebApplication($config);

// Get all pathway types
$pathways = PathwayType::model()->findAll();

echo "Found " . count($pathways) . " pathway types\n";

foreach ($pathways as $pathway) {
    echo "ID: " . $pathway->id . ", Name: " . $pathway->name . "\n";
}
