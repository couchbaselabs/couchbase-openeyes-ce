<?php
// Include Yii
require_once(__DIR__ . '/index.php');

try {
    $db = Yii::app()->getDb();
    
    // Check if tag table exists
    $tables = $db->getSchema()->getTableNames();
    
    if (!in_array('tag', $tables)) {
        // Create tag table
        $sql = "CREATE TABLE `tag` (
            `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` varchar(255) NOT NULL,
            `active` tinyint(1) NOT NULL DEFAULT 1,
            `last_modified_user_id` int(10) unsigned NOT NULL DEFAULT 1,
            `last_modified_date` datetime NOT NULL DEFAULT '1901-01-01 00:00:00',
            `created_user_id` int(10) unsigned NOT NULL DEFAULT 1,
            `created_date` datetime NOT NULL DEFAULT '1901-01-01 00:00:00',
            CONSTRAINT `tag_lmui_fk` FOREIGN KEY (`last_modified_user_id`) REFERENCES `user` (`id`),
            CONSTRAINT `tag_cui_fk` FOREIGN KEY (`created_user_id`) REFERENCES `user` (`id`),
            UNIQUE KEY `idx_tag_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
        
        $db->createCommand($sql)->execute();
        echo "Tag table created successfully!";
        
        // Insert sample data
        $db->createCommand("INSERT IGNORE INTO `tag` (`id`, `name`) VALUES (1, 'Preservative free');")->execute();
        echo " Sample data inserted!";
    } else {
        echo "Tag table already exists!";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    echo "<br>" . $e->getTraceAsString();
}
?>
