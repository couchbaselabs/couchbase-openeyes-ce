<?php
// Include Yii
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

require_once($yii);
Yii::createWebApplication($config);

// Create a test consent event
$event = new Event();
$event->patient_id = 13; // Use patient 13
$event->event_type_id = EventType::model()->find('class_name = ?', array('OphTrConsent'))->id;
$event->created_user_id = 1;
$event->last_modified_user_id = 1;
$event->created_date = date('Y-m-d H:i:s');
$event->last_modified_date = date('Y-m-d H:i:s');

if ($event->save()) {
    echo "Created Event ID: " . $event->id . PHP_EOL;
} else {
    echo "Failed to create event" . PHP_EOL;
    var_dump($event->getErrors());
}
