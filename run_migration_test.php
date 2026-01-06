<?php
// This script runs the DNA investigator table restoration migration

// Include Yii
require_once 'index.php';

// Get database connection
$db = Yii::app()->db;

echo "Starting migration to restore DNA investigator table...\n\n";

// Check if table already exists
try {
    $schema = $db->getSchema();
    $table = $schema->getTable('ophindnaextraction_dnatests_investigator');
    
    if ($table !== null) {
        echo "Table 'ophindnaextraction_dnatests_investigator' already exists!\n";
    } else {
        echo "Table does not exist, creating...\n";
        
        // Create the main table
        $sql = "
        CREATE TABLE `ophindnaextraction_dnatests_investigator` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(100) COLLATE utf8_bin NOT NULL,
            `display_order` int(10) unsigned NOT NULL,
            `last_modified_user_id` int(10) unsigned NOT NULL DEFAULT 1,
            `last_modified_date` datetime NOT NULL DEFAULT '1901-01-01 00:00:00',
            `created_user_id` int(10) unsigned NOT NULL DEFAULT 1,
            `created_date` datetime NOT NULL DEFAULT '1901-01-01 00:00:00',
            PRIMARY KEY (`id`),
            KEY `ophindnaextraction_dnatests_investigator_lmui_fk` (`last_modified_user_id`),
            KEY `ophindnaextraction_dnatests_investigator_cui_fk` (`created_user_id`),
            CONSTRAINT `ophindnaextraction_dnatests_investigator_lmui_fk` FOREIGN KEY (`last_modified_user_id`) REFERENCES `user` (`id`),
            CONSTRAINT `ophindnaextraction_dnatests_investigator_cui_fk` FOREIGN KEY (`created_user_id`) REFERENCES `user` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin
        ";
        
        $db->createCommand($sql)->execute();
        echo "✓ Main table created successfully\n";
        
        // Create the version table
        $sql = "
        CREATE TABLE `ophindnaextraction_dnatests_investigator_version` (
            `id` int(10) unsigned NOT NULL,
            `name` varchar(100) COLLATE utf8_bin NOT NULL,
            `display_order` int(10) unsigned NOT NULL,
            `last_modified_user_id` int(10) unsigned NOT NULL DEFAULT 1,
            `last_modified_date` datetime NOT NULL DEFAULT '1901-01-01 00:00:00',
            `created_user_id` int(10) unsigned NOT NULL DEFAULT 1,
            `created_date` datetime NOT NULL DEFAULT '1901-01-01 00:00:00',
            `version_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `version_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            PRIMARY KEY (`version_id`),
            KEY `ophindnaextraction_dnatests_investigator_version_id_fk` (`id`),
            KEY `ophindnaextraction_dnatests_investigator_version_lmui_fk` (`last_modified_user_id`),
            KEY `ophindnaextraction_dnatests_investigator_version_cui_fk` (`created_user_id`),
            CONSTRAINT `ophindnaextraction_dnatests_investigator_version_id_fk` FOREIGN KEY (`id`) REFERENCES `ophindnaextraction_dnatests_investigator` (`id`),
            CONSTRAINT `ophindnaextraction_dnatests_investigator_version_lmui_fk` FOREIGN KEY (`last_modified_user_id`) REFERENCES `user` (`id`),
            CONSTRAINT `ophindnaextraction_dnatests_investigator_version_cui_fk` FOREIGN KEY (`created_user_id`) REFERENCES `user` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin
        ";
        
        $db->createCommand($sql)->execute();
        echo "✓ Version table created successfully\n";
        
        echo "\n✓ Migration completed successfully!\n";
    }
    
    // Verify table exists now
    $schema->refresh();
    $table = $schema->getTable('ophindnaextraction_dnatests_investigator');
    if ($table !== null) {
        echo "✓ Table verified to exist with columns: " . implode(', ', array_keys($table->columns)) . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
