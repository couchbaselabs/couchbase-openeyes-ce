<?php
/**
 * Simple script to fix duplicate admin authentications - no Yii deps
 */

// Get database config from environment variables or defaults
$host = getenv('DATABASE_HOST') ?: 'db';  // Default to 'db' for Docker, fallback to localhost
$dbname = getenv('DATABASE_NAME') ?: 'openeyes';
$username = getenv('DATABASE_USER') ?: 'openeyes';
$password = getenv('DATABASE_PASS') ?: 'openeyes';
$port = getenv('DATABASE_PORT') ?: '3306';

echo "Connecting to database at $host:$port / $dbname\n";

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error . "\n");
}

echo "Connected to database\n";

// Find admin user
$result = $conn->query("SELECT id, username FROM user WHERE username = 'admin' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "Found admin user with ID: " . $user['id'] . "\n";
    
    // Find all user authentications for admin
    $result = $conn->query("SELECT id, user_id, username, institution_authentication_id FROM user_authentication WHERE user_id = " . $user['id'] . " ORDER BY id");
    
    if ($result && $result->num_rows > 1) {
        echo "Found " . $result->num_rows . " duplicate authentication records\n";
        
        $count = 0;
        $first = true;
        $keep_id = null;
        
        while ($auth = $result->fetch_assoc()) {
            if ($first) {
                $keep_id = $auth['id'];
                echo "Keeping auth ID: " . $keep_id . "\n";
                $first = false;
            } else {
                echo "Deleting auth ID: " . $auth['id'] . "\n";
                if ($conn->query("DELETE FROM user_authentication WHERE id = " . $auth['id'])) {
                    $count++;
                } else {
                    echo "  ERROR: " . $conn->error . "\n";
                }
            }
        }
        
        echo "\n✓ Deleted $count duplicate records\n";
    } else {
        echo "No duplicates found\n";
    }
} else {
    echo "ERROR: Admin user not found\n";
}

$conn->close();
echo "Done!\n";
?>
