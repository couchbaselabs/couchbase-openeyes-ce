<?php
// Simple script to create a test risk set
defined('YII_DEBUG') or define('YII_DEBUG',true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);

$yii_path = dirname(__FILE__).'/vendor/yiisoft/yii/framework/yii.php';
require_once($yii_path);

$config = require(dirname(__FILE__).'/protected/config/main.php');
$app = Yii::createWebApplication($config);

// Create a new risk set
$riskSet = new OEModule\OphCiExamination\models\OphCiExaminationRiskSet();
$riskSet->name = 'Test Risk Set ' . time();
$riskSet->institution_id = 1;
$riskSet->subspecialty_id = null;
$riskSet->context_id = null;

if ($riskSet->save()) {
    echo "Risk set created with ID: " . $riskSet->id . "\n";
    
    // Create a risk entry
    $entry = new OEModule\OphCiExamination\models\OphCiExaminationRiskSetEntry();
    $entry->ophciexamination_risk_set_id = $riskSet->id;
    $entry->ophciexamination_risk_id = 1; // Cannot Lie Flat risk
    $entry->gender = null;
    $entry->age_min = null;
    $entry->age_max = null;
    
    if ($entry->save()) {
        echo "Risk entry created with ID: " . $entry->id . "\n";
        echo "Success! Risk set ID: " . $riskSet->id . "\n";
    } else {
        echo "Failed to save risk entry\n";
        print_r($entry->getErrors());
    }
} else {
    echo "Failed to save risk set\n";
    print_r($riskSet->getErrors());
}
