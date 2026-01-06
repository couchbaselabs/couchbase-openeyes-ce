<?php
// Simulate a request to load the app properly
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost:7777';
$_SERVER['REQUEST_URI'] = '/insert_contact_label.php';

// Load the main index file bootstrap
require_once('index.php');

// Now create the contact label
$label = new ContactLabel();
$label->name = 'General Practitioner';
$label->letter_template_only = 0;
$label->is_private = 0;

if ($label->save()) {
    echo "Contact label created successfully. ID: " . $label->id;
    // Also try with Couchbase if enabled
    if ($label->saveToCouchbase !== false && method_exists($label, 'saveToCouchbase')) {
        $label->saveToCouchbase();
    }
} else {
    echo "Error creating contact label: ";
    print_r($label->getErrors());
}
?>
