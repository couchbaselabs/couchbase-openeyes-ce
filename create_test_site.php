<?php
/**
 * Create a test site for institution 1
 */

define('YII_DEBUG', true);
define('YII_ENABLE_ERROR_HANDLER', false);
define('YII_TRACE_LEVEL', 3);

require_once dirname(__FILE__) . '/index.php';

// Get the database connection
$db = Yii::app()->db;

// Check if site 1 exists
$site_id = $db->createCommand('SELECT id FROM site WHERE id = 1')->queryScalar();

if (!$site_id) {
    echo "Site 1 doesn't exist, creating it...\n";
    
    // Insert a test site
    $sql = "INSERT INTO site (id, name, short_name, institution_id, active) VALUES (1, 'Test Site', 'TEST', 1, 1)";
    $db->createCommand($sql)->execute();
    echo "Site 1 created successfully.\n";
} else {
    echo "Site 1 already exists.\n";
}

// Check the relationship
$institution = Institution::model()->findByPk(1);
if ($institution) {
    echo "Institution 1: " . $institution->name . "\n";
    $sites = Site::model()->getListForCurrentInstitution();
    echo "Sites for institution 1: " . count($sites) . "\n";
    foreach ($sites as $id => $name) {
        echo "  - Site ID: $id, Name: $name\n";
    }
}  else {
    echo "Institution 1 not found.\n";
}
?>
