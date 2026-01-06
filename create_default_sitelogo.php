<?php
// Create a default SiteLogo record
require_once dirname(__FILE__) . '/index.php';

// Check if SiteLogo with id 1 exists
$logo = SiteLogo::model()->findByPk(1);

if ($logo) {
    echo "SiteLogo with id 1 already exists\n";
} else {
    // Create a new SiteLogo
    $logo = new SiteLogo();
    
    // Create a simple 1x1 transparent PNG
    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    );
    
    $logo->primary_logo = $png;
    $logo->secondary_logo = $png;
    $logo->parent_logo = null;
    
    if ($logo->save()) {
        echo "Successfully created default SiteLogo with id: " . $logo->id . "\n";
    } else {
        echo "Failed to create default SiteLogo\n";
        print_r($logo->getErrors());
    }
}
