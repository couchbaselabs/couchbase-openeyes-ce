<?php
// Debug script to check team data in database
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bootstrap Yii
require_once dirname(__FILE__) . '/index.php';

// Force clear the session to allow raw queries
$teams = Team::model()->resetScope()->findAll();

echo "=== Teams Found (with resetScope) ===\n";
echo "Total: " . count($teams) . "\n";
foreach ($teams as $team) {
    echo "\nTeam ID: {$team->id}\n";
    echo "  Name: {$team->name}\n";
    echo "  Email: {$team->email}\n";
    echo "  Institution ID: {$team->institution_id}\n";
    echo "  Active: {$team->active}\n";
    echo "  Created: {$team->created_date}\n";
}

// Try direct DB query using query() instead of querySql
echo "\n=== Direct SQL Query ===\n";
$db = Yii::app()->db;
try {
    $sql = 'SELECT id, name, email, institution_id, active, created_date FROM team ORDER BY created_date DESC LIMIT 10';
    $result = $db->createCommand($sql)->queryAll();
    echo "Query successful. Result count: " . count($result) . "\n";
    foreach ($result as $row) {
        echo "ID: {$row['id']}, Name: {$row['name']}, Institution: {$row['institution_id']}, Active: {$row['active']}, Created: {$row['created_date']}\n";
    }
} catch (Exception $e) {
    echo "Query error: " . $e->getMessage() . "\n";
}

// Check if team table exists
echo "\n=== Team Table Info ===\n";
try {
    $result = $db->createCommand("SHOW TABLES LIKE 'team'")->queryRow();
    if ($result) {
        echo "Team table EXISTS\n";
    } else {
        echo "Team table NOT FOUND - Creating it now...\n";
        
        $createTableSQL = <<<SQL
CREATE TABLE IF NOT EXISTS `team` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_id` bigint(20) DEFAULT NULL,
  `institution_id` bigint(20) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `last_modified_user_id` varchar(10) DEFAULT NULL,
  `last_modified_date` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_user_id` varchar(10) DEFAULT NULL,
  `created_date` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `contact_id` (`contact_id`),
  KEY `institution_id` (`institution_id`),
  KEY `active` (`active`),
  KEY `created_date` (`created_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        
        try {
            $db->createCommand($createTableSQL)->execute();
            echo "SUCCESS: Team table created!\n";
        } catch (Exception $e) {
            echo "ERROR creating table: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking table: " . $e->getMessage() . "\n";
}

// Check contacts
echo "\n=== Contacts ===\n";
$contacts = Contact::model()->findAll();
echo "Total Contacts: " . count($contacts) . "\n";
foreach ($contacts as $contact) {
    echo "Contact ID: {$contact->id}, Email: {$contact->email}\n";
}

// Check admin permissions
echo "\n=== Admin Permissions ===\n";
$admin_user = User::model()->find('username = ?', array('admin'));
if ($admin_user) {
    echo "Admin user ID: {$admin_user->id}\n";
    echo "Admin has 'admin' role: " . ($admin_user->checkAccess('admin') ? 'YES' : 'NO') . "\n";
    echo "Admin has 'Super Team Manager' role: " . ($admin_user->checkAccess('Super Team Manager') ? 'YES' : 'NO') . "\n";
    
    // Check team IDs for user
    $team_ids = Team::getTeamIdsForUser($admin_user->id, Team::ADMIN_VISIBLE_TASKS);
    echo "Teams for admin user (ADMIN_VISIBLE_TASKS): " . json_encode($team_ids) . "\n";
}

// Check session
echo "\n=== Session Info ===\n";
echo "Selected Institution ID: " . Yii::app()->session->get('selected_institution_id') . "\n";
?>
