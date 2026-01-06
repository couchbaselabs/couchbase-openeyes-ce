<?php
// Simple script to get the ID of the newly created mapping
require_once 'protected/yiic.php';
$config = require 'protected/config/main.php';
$app = Yii::createApplication('CWebApplication', $config);

// Query for all SiteSubspecialtyAnaestheticAgent records
$criteria = new CDbCriteria();
$criteria->order = 'id DESC';
$criteria->limit = 5;

$records = SiteSubspecialtyAnaestheticAgent::model()->findAll($criteria);

echo "Found " . count($records) . " records:\n";
foreach ($records as $record) {
    echo "ID: " . $record->id . ", Site: " . $record->site_id . ", Subspecialty: " . $record->subspecialty_id . ", Agent: " . $record->anaesthetic_agent_id . "\n";
}
?>
