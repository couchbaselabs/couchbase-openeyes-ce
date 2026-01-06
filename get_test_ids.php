<?php
// Get valid IDs for testing
require_once 'index.php';

// Get a site ID
$site = Site::model()->find();
if ($site) {
    echo "Site ID: " . $site->id . " (Name: " . $site->short_name . ")\n";
    $site_id = $site->id;
} else {
    echo "No sites found\n";
    exit;
}

// Get a subspecialty ID
$subspecialty = Subspecialty::model()->find();
if ($subspecialty) {
    echo "Subspecialty ID: " . $subspecialty->id . " (Name: " . $subspecialty->name . ")\n";
    $subspecialty_id = $subspecialty->id;
} else {
    echo "No subspecialties found\n";
    exit;
}

// Get a post-op drug ID
$drug = OphTrOperationnote_PostopDrug::model()->find();
if ($drug) {
    echo "Drug ID: " . $drug->id . " (Name: " . $drug->name . ")\n";
    $drug_id = $drug->id;
} else {
    echo "No post-op drugs found\n";
    exit;
}

// Output the IDs
echo "\nAdd URL: /postOpDrugMappings/add?site_id=" . $site_id . "&subspecialty_id=" . $subspecialty_id . "&drug_id=" . $drug_id . "\n";
?>
