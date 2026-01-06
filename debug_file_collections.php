<?php
// Debug script to check file collections in database
require_once 'yii.php';
require_once 'protected/config/main.php';

$app = Yii::createApplication('CWebApplication', $config);

// Query file collections
echo "=== FILE COLLECTIONS IN DATABASE ===\n";
$collections = OphCoTherapyapplication_FileCollection::model()->findAll();
echo "Count: " . count($collections) . "\n";

foreach ($collections as $collection) {
    echo "\nID: " . $collection->id;
    echo "\nName: " . $collection->name;
    echo "\nSummary: " . $collection->summary;
    echo "\nInstitution ID: " . $collection->institution_id;
    echo "\n---\n";
}

// Check selected institution
echo "\n=== SESSION INFO ===\n";
echo "Selected Institution ID: " . Yii::app()->session['selected_institution_id'] . "\n";

// Check Institution
$institution = Institution::model()->findByPk(Yii::app()->session['selected_institution_id']);
if ($institution) {
    echo "Institution Name: " . $institution->name . "\n";
}
?>
