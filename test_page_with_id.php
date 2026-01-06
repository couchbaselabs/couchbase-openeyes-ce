<?php
require_once 'index.php';

// Try to find any event in the database
$events = Event::model()->findAll(array('limit' => 1));

if (count($events) > 0) {
    $event = $events[0];
    echo "Found event with ID: " . $event->id . "\n";
    echo "Event Type: " . ($event->eventType ? $event->eventType->name : 'Unknown') . "\n";
    echo "Test URL: http://localhost:7777/OphCoTherapyapplication/default/view?id=" . $event->id . "\n";
} else {
    echo "No events found in database\n";
    echo "Creating a minimal event using raw SQL...\n";
    
    // Use raw SQL to create test data
    $db = Yii::app()->db;
    
    // Check if institution 1 exists
    $inst_check = $db->createCommand("SELECT id FROM institution WHERE id = 1")->queryRow();
    if (!$inst_check) {
        // Create institution 1
        $db->createCommand("INSERT INTO institution (id, name, remote_id) VALUES (1, 'Default', 'default')")->execute();
    }
    
    // Check if eye 1 (Left) exists
    $eye_check = $db->createCommand("SELECT id FROM eye WHERE id = 1")->queryRow();
    if (!$eye_check) {
        // Create eye records
        $db->createCommand("INSERT INTO eye (id, name, short_name, display_order) VALUES (1, 'Left', 'L', 1)")->execute();
        $db->createCommand("INSERT INTO eye (id, name, short_name, display_order) VALUES (2, 'Right', 'R', 2)")->execute();
        $db->createCommand("INSERT INTO eye (id, name, short_name, display_order) VALUES (3, 'Both', 'B', 3)")->execute();
    }
    
    // Check if user 1 exists  
    $user_check = $db->createCommand("SELECT id FROM user WHERE id = 1")->queryRow();
    if (!$user_check) {
        // Create a user
        $db->createCommand("INSERT INTO user (id, username, password, email, first_name, last_name, active, created_date, last_modified_date) 
                           VALUES (1, 'admin', '".password_hash('admin', PASSWORD_BCRYPT)."', 'admin@test.com', 'Admin', 'User', 1, NOW(), NOW())")->execute();
    }
    
    // Create patient using raw SQL
    $db->createCommand("INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, created_user_id, created_date) 
                       VALUES ('TEST'.UNIX_TIMESTAMP(), 'NHS'.UNIX_TIMESTAMP(), '1980-01-15', 'M', 'Test', 'Patient', 1, NOW())")->execute();
    $patient_id = $db->lastRowCount ? $db->createCommand("SELECT LAST_INSERT_ID() as id")->queryRow()['id'] : null;
    
    if (!$patient_id) {
        echo "Failed to create patient\n";
    } else {
        echo "Created Patient ID: " . $patient_id . "\n";
        
        // Create episode
        $db->createCommand("INSERT INTO episode (patient_id, start_date, eye_id, status, created_user_id, created_date) 
                           VALUES (:patient_id, CURDATE(), 3, 'active', 1, NOW())")->bindParam(':patient_id', $patient_id)->execute();
        $episode_id = $db->createCommand("SELECT LAST_INSERT_ID() as id")->queryRow()['id'];
        echo "Created Episode ID: " . $episode_id . "\n";
        
        // Get or create event type
        $et = $db->createCommand("SELECT id FROM event_type WHERE class_name = 'OphCoTherapyapplication'")->queryRow();
        if (!$et) {
            $db->createCommand("INSERT INTO event_type (class_name, name) VALUES ('OphCoTherapyapplication', 'Therapy Application')")->execute();
            $event_type_id = $db->createCommand("SELECT LAST_INSERT_ID() as id")->queryRow()['id'];
        } else {
            $event_type_id = $et['id'];
        }
        
        // Create event
        $db->createCommand("INSERT INTO event (episode_id, event_type_id, event_date, created_date, last_modified_date, institution_id) 
                           VALUES (:episode_id, :event_type_id, CURDATE(), NOW(), NOW(), 1)")
           ->bindParam(':episode_id', $episode_id)
           ->bindParam(':event_type_id', $event_type_id)
           ->execute();
        $event_id = $db->createCommand("SELECT LAST_INSERT_ID() as id")->queryRow()['id'];
        echo "Created Event ID: " . $event_id . "\n";
        echo "Test URL: http://localhost:7777/OphCoTherapyapplication/default/view?id=" . $event_id . "\n";
    }
}
?>
