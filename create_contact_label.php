<?php
// Load Yii application
require_once('protected/yiic.php');

// Create contact label
$label = new ContactLabel();
$label->name = 'General Practitioner';
$label->letter_template_only = 0;
$label->is_private = 0;

if ($label->save()) {
    echo "Contact label created successfully. ID: " . $label->id . "\n";
} else {
    echo "Error creating contact label:\n";
    print_r($label->getErrors());
}
