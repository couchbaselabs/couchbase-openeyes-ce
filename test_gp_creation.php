<?php
// Test script to check if GP was created

// Load Yii configuration
require_once('protected/config/main.php');

$app = Yii::createWebApplication($config);

// Check Contact table for the newly created contact
$contacts = Contact::model()->findAll("first_name = 'John' AND last_name = 'Smith'");
echo "Found " . count($contacts) . " contacts with first_name='John' AND last_name='Smith'\n";

foreach ($contacts as $contact) {
    echo "Contact ID: " . $contact->id . ", Title: " . $contact->title . ", Primary Phone: " . $contact->primary_phone . "\n";
    
    // Check if this contact has an associated GP
    if ($contact->gp) {
        echo "  - Has associated GP with ID: " . $contact->gp->id . ", is_active: " . $contact->gp->is_active . "\n";
    } else {
        echo "  - No associated GP\n";
    }
}

// Check GP table directly
$gps = Gp::model()->findAll();
echo "\nTotal GPs in database: " . count($gps) . "\n";
echo "Last 5 GPs:\n";
$gps = Gp::model()->findAll(array('order' => 'id DESC', 'limit' => 5));
foreach ($gps as $gp) {
    echo "GP ID: " . $gp->id . ", contact_id: " . $gp->contact_id . ", is_active: " . $gp->is_active . "\n";
}
?>
