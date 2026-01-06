<?php
/**
 * Create a test operation checklists event
 */

// Get the Yii application
require_once('protected/config/main.php');
$config = require('protected/config/main.php');
$app = Yii::createWebApplication($config);

// Get a test patient or create one
$criteria = new CDbCriteria();
$criteria->limit = 1;
$patient = Patient::model()->find($criteria);

if (!$patient) {
    echo "No patients found in the system.\n";
    exit(1);
}

echo "Using patient: {$patient->id}\n";

// Get the OphTrOperationchecklists event type
$event_type = EventType::model()->find('class_name = ?', array('OphTrOperationchecklists'));

if (!$event_type) {
    echo "OphTrOperationchecklists event type not found.\n";
    exit(1);
}

echo "Found event type: {$event_type->class_name}\n";

// Create an event
$event = new Event();
$event->patient_id = $patient->id;
$event->event_type_id = $event_type->id;
$event->episode_id = $patient->episodes[0]->id; // Use first episode
$event->created_user_id = Yii::app()->user->id ?: 1;
$event->last_modified_user_id = Yii::app()->user->id ?: 1;

if (!$event->save()) {
    echo "Failed to create event: " . print_r($event->getErrors(), true) . "\n";
    exit(1);
}

echo "Created event with ID: {$event->id}\n";

// Create the operation checklists event record
$op_event = new OphTrOperationchecklists_Event();
$op_event->event_id = $event->id;
$op_event->draft = 0;

if (!$op_event->save()) {
    echo "Failed to create operation checklists event: " . print_r($op_event->getErrors(), true) . "\n";
    exit(1);
}

echo "Created operation checklists event!\n";
echo "Test with event ID: {$event->id}\n";
?>
