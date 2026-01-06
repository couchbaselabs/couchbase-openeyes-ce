<?php
// This file sets up test biometry data
// Direct database connection - hardcoded for now
$db_host = 'localhost';
$db_name = 'openeyes';
$db_user = 'root';
$db_pass = '';

try {
    // Try different connection methods
    $connected = false;
    $errors = [];
    
    // Try unix socket
    try {
        $pdo = new PDO("mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connected = true;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
    
    // Try TCP if socket failed
    if (!$connected) {
        try {
            $pdo = new PDO("mysql:host=$db_host;port=3306;dbname=$db_name", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connected = true;
        } catch (PDOException $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    if (!$connected) {
        throw new Exception("Could not connect: " . implode(", ", $errors));
    }
    
    // Check if OphInBiometry EventType exists
    $stmt = $pdo->prepare("SELECT id FROM event_type WHERE class_name = 'OphInBiometry'");
    $stmt->execute();
    $eventType = $stmt->fetch();
    
    if (!$eventType) {
        // Create the EventType
        $stmt = $pdo->prepare("
            INSERT INTO event_type (class_name, name, event_group_id) 
            VALUES ('OphInBiometry', 'Biometry', (SELECT id FROM event_group WHERE name = 'Investigation events'))
        ");
        $stmt->execute();
        $eventTypeId = $pdo->lastInsertId();
        echo "Created EventType OphInBiometry with ID: $eventTypeId\n";
    } else {
        $eventTypeId = $eventType['id'];
        echo "EventType OphInBiometry already exists with ID: $eventTypeId\n";
    }
    
    // Check if any biometry events exist
    $stmt = $pdo->prepare("SELECT id FROM event WHERE event_type_id = ?");
    $stmt->execute([$eventTypeId]);
    $events = $stmt->fetchAll();
    
    if (empty($events)) {
        // Get or create a patient
        $stmt = $pdo->prepare("SELECT id FROM patient LIMIT 1");
        $stmt->execute();
        $patient = $stmt->fetch();
        
        if (!$patient) {
            // Create a test patient
            $stmt = $pdo->prepare("
                INSERT INTO patient (hos_num, nhs_num, dob, gender, first_name, last_name, created_user_id, created_date)
                VALUES ('TEST001', NULL, ?, 'M', 'Test', 'Biometry', 1, NOW())
            ");
            $dob = date('Y-m-d', strtotime('-50 years'));
            $stmt->execute([$dob]);
            $patientId = $pdo->lastInsertId();
            echo "Created test patient with ID: $patientId\n";
        } else {
            $patientId = $patient['id'];
            echo "Using existing patient with ID: $patientId\n";
        }
        
        // Get or create an episode
        $stmt = $pdo->prepare("SELECT id FROM episode WHERE patient_id = ? LIMIT 1");
        $stmt->execute([$patientId]);
        $episode = $stmt->fetch();
        
        if (!$episode) {
            $stmt = $pdo->prepare("
                INSERT INTO episode (patient_id, eye_id, episode_status_id, created_user_id, created_date)
                VALUES (?, 3, 1, 1, NOW())
            ");
            $stmt->execute([$patientId]);
            $episodeId = $pdo->lastInsertId();
            echo "Created episode with ID: $episodeId\n";
        } else {
            $episodeId = $episode['id'];
            echo "Using existing episode with ID: $episodeId\n";
        }
        
        // Create the biometry event
        $stmt = $pdo->prepare("
            INSERT INTO event (episode_id, event_type_id, created_user_id, created_date, event_date)
            VALUES (?, ?, 1, NOW(), CURDATE())
        ");
        $stmt->execute([$episodeId, $eventTypeId]);
        $eventId = $pdo->lastInsertId();
        echo "Created biometry event with ID: $eventId\n";
        
        // Create the Measurement element for the event
        $stmt = $pdo->prepare("
            INSERT INTO ophinbiometry_measurement (event_id, eye_id, axial_length_left, axial_length_right, k1_left, k2_left, k1_right, k2_right, snr_left, snr_right)
            VALUES (?, 3, 24.5, 24.3, 43.5, 44.0, 43.2, 43.8, 20, 20)
        ");
        $stmt->execute([$eventId]);
        echo "Created measurement data for event\n";
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Biometry test data created successfully',
            'event_id' => $eventId,
            'view_url' => '/OphInBiometry/default/view/' . $eventId
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => 'Biometry events already exist',
            'event_id' => $events[0]['id'],
            'view_url' => '/OphInBiometry/default/view/' . $events[0]['id']
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit(1);
}
?>
