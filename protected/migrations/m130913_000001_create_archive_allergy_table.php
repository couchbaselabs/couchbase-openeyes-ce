<?php

class m130913_000001_create_archive_allergy_table extends CDbMigration
{
    private const TABLE = 'archive_allergy';

    public function safeUp()
    {
        if ($this->dbConnection->schema->getTable(self::TABLE, true) === null) {
            $this->createTable(
                self::TABLE,
                [
                    'id' => 'int(10) unsigned NOT NULL AUTO_INCREMENT',
                    'name' => 'varchar(64) DEFAULT NULL',
                    'display_order' => 'tinyint(3) unsigned NOT NULL DEFAULT 0',
                    'active' => 'tinyint(1) unsigned NOT NULL DEFAULT 1',
                    'last_modified_user_id' => 'int(10) unsigned NOT NULL DEFAULT 1',
                    'last_modified_date' => "datetime NOT NULL DEFAULT '1900-01-01 00:00:00'",
                    'created_user_id' => 'int(10) unsigned NOT NULL DEFAULT 1',
                    'created_date' => "datetime NOT NULL DEFAULT '1900-01-01 00:00:00'",
                    'PRIMARY KEY (`id`)',
                ],
                'ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci'
            );

            $this->createIndex('archive_allergy_name_idx', self::TABLE, 'name');
            $this->createIndex('archive_allergy_lmui_fk', self::TABLE, 'last_modified_user_id');
            $this->createIndex('archive_allergy_cui_fk', self::TABLE, 'created_user_id');

            $this->addForeignKey('archive_allergy_lmui_fk', self::TABLE, 'last_modified_user_id', 'user', 'id');
            $this->addForeignKey('archive_allergy_cui_fk', self::TABLE, 'created_user_id', 'user', 'id');

            $allergyTable = $this->dbConnection->schema->getTable('allergy', true);
            if ($allergyTable !== null) {
                $displayOrderExpr = array_key_exists('display_order', $allergyTable->columns)
                    ? 'COALESCE(display_order, 0)'
                    : '0';
                $activeExpr = array_key_exists('active', $allergyTable->columns)
                    ? 'COALESCE(active, 1)'
                    : '1';

                $this->execute(
                    sprintf(
                        'INSERT INTO archive_allergy (id, name, display_order, active, last_modified_user_id, last_modified_date, created_user_id, created_date)
                         SELECT id, name, %s, %s, last_modified_user_id, last_modified_date, created_user_id, created_date FROM allergy',
                        $displayOrderExpr,
                        $activeExpr
                    )
                );
            }
        }
    }

    public function safeDown()
    {
        if ($this->dbConnection->schema->getTable(self::TABLE, true) !== null) {
            $this->dropForeignKey('archive_allergy_lmui_fk', self::TABLE);
            $this->dropForeignKey('archive_allergy_cui_fk', self::TABLE);
            $this->dropTable(self::TABLE);
        }
    }
}
