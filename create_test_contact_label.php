<?php
// Define required constants
if (!defined('YII_DEBUG')) {
    define('YII_DEBUG', true);
}
if (!defined('YII_TRACE_LEVEL')) {
    define('YII_TRACE_LEVEL', 3);
}

// Initialize Yii application using the web config
$config = require('protected/config/main.php');
$app = Yii::createWebApplication($config);

// Create contact label
$label = new ContactLabel();
$label->name = 'General Practitioner';
$label->letter_template_only = 0;
$label->is_private = 0;

if ($label->save()) {
    echo "Contact label created successfully. ID: " . $label->id;
} else {
    echo "Error creating contact label: ";
    print_r($label->getErrors());
}
?>
