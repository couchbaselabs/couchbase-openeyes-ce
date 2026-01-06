<?php
// Check if post-op drug mapping was created
require_once 'index.php';

// Query the database
$mappings = OphTrOperationnote_PostopSiteSubspecialtyDrug::model()->findAll();

echo "Total mappings in database: " . count($mappings) . "\n";
foreach ($mappings as $mapping) {
    echo "ID: " . $mapping->id . " | Site: " . $mapping->site_id . " | Subspecialty: " . $mapping->subspecialty_id . " | Drug: " . $mapping->drug_id . "\n";
}

// Check specific mapping
$specific = OphTrOperationnote_PostopSiteSubspecialtyDrug::model()->find('site_id = ? AND subspecialty_id = ? AND drug_id = ?', array(2, 1, 1));
echo "\nSearching for site_id=2, subspecialty_id=1, drug_id=1:\n";
if ($specific) {
    echo "Found: ID=" . $specific->id . "\n";
} else {
    echo "Not found\n";
}
?>
