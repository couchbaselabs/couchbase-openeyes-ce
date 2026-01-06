<?php
// Setup Yii
require_once(dirname(__FILE__) . '/protected/config/constants.php');
Yii::createWebApplication(require(dirname(__FILE__) . '/protected/config/main.php'));

// Test SiteLogo model
$logo1 = SiteLogo::model()->findByPk(1);
echo "Logo 1: " . ($logo1 ? 'Found' : 'Not found') . "\n";
if ($logo1) {
    echo "  ID: " . $logo1->id . "\n";
    echo "  Primary Logo: " . (strlen($logo1->primary_logo) > 0 ? 'YES' : 'NO') . "\n";
    echo "  Secondary Logo: " . (strlen($logo1->secondary_logo) > 0 ? 'YES' : 'NO') . "\n";
    echo "  Parent Logo: " . $logo1->parent_logo . "\n";
    echo "  Last Modified: " . $logo1->last_modified_date . "\n";
}

// Check all logos
$allLogos = SiteLogo::model()->findAll();
echo "\nTotal logos in database: " . count($allLogos) . "\n";
foreach ($allLogos as $logo) {
    echo "  Logo " . $logo->id . ": Primary=" . (strlen($logo->primary_logo) > 0 ? 'YES' : 'NO') . ", Secondary=" . (strlen($logo->secondary_logo) > 0 ? 'YES' : 'NO') . "\n";
}
