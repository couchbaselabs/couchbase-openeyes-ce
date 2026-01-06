<?php

class m2026_01_06_restore_investigator_table extends CDbMigration
{
    public function up()
    {
        // Recreate the investigator table that was previously dropped in m170316_123900
        if (!$this->dbConnection->getSchema()->getTable('ophindnaextraction_dnatests_investigator')) {
            $this->createTable('ophindnaextraction_dnatests_investigator', array(
                'id' => 'int(10) unsigned NOT NULL AUTO_INCREMENT',
                'name' => 'varchar(100) COLLATE utf8_bin NOT NULL',
                'display_order' => 'int(10) unsigned NOT NULL',
                'last_modified_user_id' => 'int(10) unsigned NOT NULL DEFAULT 1',
                'last_modified_date' => 'datetime NOT NULL DEFAULT \'1901-01-01 00:00:00\'',
                'created_user_id' => 'int(10) unsigned NOT NULL DEFAULT 1',
                'created_date' => 'datetime NOT NULL DEFAULT \'1901-01-01 00:00:00\'',
                'PRIMARY KEY (`id`)',
                'KEY `ophindnaextraction_dnatests_investigator_lmui_fk` (`last_modified_user_id`)',
                'KEY `ophindnaextraction_dnatests_investigator_cui_fk` (`created_user_id`)',
                'CONSTRAINT `ophindnaextraction_dnatests_investigator_lmui_fk` FOREIGN KEY (`last_modified_user_id`) REFERENCES `user` (`id`)',
                'CONSTRAINT `ophindnaextraction_dnatests_investigator_cui_fk` FOREIGN KEY (`created_user_id`) REFERENCES `user` (`id`)',
            ), 'ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin');
        }

        // Recreate the version table
        if (!$this->dbConnection->getSchema()->getTable('ophindnaextraction_dnatests_investigator_version')) {
            $this->createTable('ophindnaextraction_dnatests_investigator_version', array(
                'id' => 'int(10) unsigned NOT NULL',
                'name' => 'varchar(100) COLLATE utf8_bin NOT NULL',
                'display_order' => 'int(10) unsigned NOT NULL',
                'last_modified_user_id' => 'int(10) unsigned NOT NULL DEFAULT 1',
                'last_modified_date' => 'datetime NOT NULL DEFAULT \'1901-01-01 00:00:00\'',
                'created_user_id' => 'int(10) unsigned NOT NULL DEFAULT 1',
                'created_date' => 'datetime NOT NULL DEFAULT \'1901-01-01 00:00:00\'',
                'version_date' => 'datetime NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'version_id' => 'int(10) unsigned NOT NULL AUTO_INCREMENT',
                'PRIMARY KEY (`version_id`)',
                'KEY `ophindnaextraction_dnatests_investigator_version_id_fk` (`id`)',
                'KEY `ophindnaextraction_dnatests_investigator_version_lmui_fk` (`last_modified_user_id`)',
                'KEY `ophindnaextraction_dnatests_investigator_version_cui_fk` (`created_user_id`)',
                'CONSTRAINT `ophindnaextraction_dnatests_investigator_version_id_fk` FOREIGN KEY (`id`) REFERENCES `ophindnaextraction_dnatests_investigator` (`id`)',
                'CONSTRAINT `ophindnaextraction_dnatests_investigator_version_lmui_fk` FOREIGN KEY (`last_modified_user_id`) REFERENCES `user` (`id`)',
                'CONSTRAINT `ophindnaextraction_dnatests_investigator_version_cui_fk` FOREIGN KEY (`created_user_id`) REFERENCES `user` (`id`)',
            ), 'ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_bin');
        }
    }

    public function down()
    {
        // Drop the tables if rolling back
        $this->dropForeignKey('ophindnaextraction_dnatests_investigator_lmui_fk', 'ophindnaextraction_dnatests_investigator');
        $this->dropForeignKey('ophindnaextraction_dnatests_investigator_cui_fk', 'ophindnaextraction_dnatests_investigator');
        $this->dropTable('ophindnaextraction_dnatests_investigator');

        $this->dropForeignKey('ophindnaextraction_dnatests_investigator_version_lmui_fk', 'ophindnaextraction_dnatests_investigator_version');
        $this->dropForeignKey('ophindnaextraction_dnatests_investigator_version_cui_fk', 'ophindnaextraction_dnatests_investigator_version');
        $this->dropTable('ophindnaextraction_dnatests_investigator_version');
    }
}
