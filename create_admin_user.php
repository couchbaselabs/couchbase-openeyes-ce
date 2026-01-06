<?php
// Direct database insertion without Yii ORM

$db_config = [
    'host' => 'localhost',
    'user' => 'openeyes',
    'password' => 'Openeyes123',
    'database' => 'openeyes',
];

// Connect to database
$conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);

if ($conn->connect_error) {
    die('Connection error: ' . $conn->connect_error);
}

echo "Connected to database successfully\n";

// Check if admin user exists
$result = $conn->query("SELECT COUNT(*) as cnt FROM user WHERE username='admin'");
$row = $result->fetch_assoc();
echo "Current admin user count: " . $row['cnt'] . "\n";

if ($row['cnt'] == 0) {
    echo "Creating admin user...\n";
    
    // Insert admin user
    $stmt = $conn->prepare("INSERT INTO user (username, first_name, last_name, email, active) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param('ssss', $username, $first_name, $last_name, $email);
    
    $username = 'admin';
    $first_name = 'Admin';
    $last_name = 'User';
    $email = 'admin@openeyes.test';
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        echo "Admin user created with ID: $user_id\n";
        
        // Get or create institution authentication
        $result = $conn->query("SELECT id FROM institution_authentication LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) {
            $inst_auth_id = $row['id'];
            echo "Using institution_authentication ID: $inst_auth_id\n";
            
            // Create user authentication
            $password_hash = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO user_authentication (user_id, username, password, institution_authentication_id, active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param('issi', $user_id, $username, $password_hash, $inst_auth_id);
            
            if ($stmt->execute()) {
                echo "User authentication created successfully\n";
                echo "You can now login with: admin / admin\n";
            } else {
                echo "ERROR creating user authentication: " . $stmt->error . "\n";
            }
        } else {
            echo "ERROR: No institution_authentication found\n";
        }
    } else {
        echo "ERROR creating user: " . $stmt->error . "\n";
    }
} else {
    echo "Admin user already exists. Updating authentication...\n";
    
    // Get admin user ID
    $result = $conn->query("SELECT id FROM user WHERE username='admin'");
    $row = $result->fetch_assoc();
    $user_id = $row['id'];
    
    // Check if authentication exists
    $result = $conn->query("SELECT id FROM user_authentication WHERE user_id=$user_id");
    
    if ($result->num_rows > 0) {
        echo "User authentication already exists\n";
    } else {
        echo "Creating user authentication...\n";
        
        // Get or create institution authentication
        $result = $conn->query("SELECT id FROM institution_authentication LIMIT 1");
        if ($result && $row = $result->fetch_assoc()) {
            $inst_auth_id = $row['id'];
            
            // Create user authentication
            $password_hash = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO user_authentication (user_id, username, password, institution_authentication_id, active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param('issi', $user_id, $username, $password_hash, $inst_auth_id);
            $username = 'admin';
            
            if ($stmt->execute()) {
                echo "User authentication created successfully\n";
            } else {
                echo "ERROR: " . $stmt->error . "\n";
            }
        }
    }
}

// Verify
$result = $conn->query("SELECT COUNT(*) as cnt FROM user_authentication WHERE username='admin' AND active=1");
$row = $result->fetch_assoc();
echo "\nFinal check: Active admin authentications: " . $row['cnt'] . "\n";

$conn->close();
?>
