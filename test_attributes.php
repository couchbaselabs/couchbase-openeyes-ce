<?php
// Test script to check if attributes are being created

require_once dirname(__FILE__) . '/index.php';

// Get all attributes from the database
$attributes = OphTrOperationnote_Attribute::model()->findAll();

echo "Total attributes found: " . count($attributes) . "\n";

foreach ($attributes as $attr) {
    echo "ID: {$attr->id}, Name: {$attr->name}, Label: {$attr->label}, Proc ID: {$attr->proc_id}\n";
}

// Also try using the search method
echo "\nUsing search method:\n";
$dataProvider = OphTrOperationnote_Attribute::model()->search();
$allResults = $dataProvider->getData();

echo "Total from search: " . count($allResults) . "\n";

foreach ($allResults as $attr) {
    echo "ID: {$attr->id}, Name: {$attr->name}, Label: {$attr->label}, Proc ID: {$attr->proc_id}\n";
}
