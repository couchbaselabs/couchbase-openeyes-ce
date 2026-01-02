<?php

class m20251222_215000_create_attribute_option_exclude_table extends OEMigration
{
    public function safeUp()
    {
        $table = $this->dbConnection->schema->getTable('ophciexamination_attribute_option_exclude', true);
        if ($table === null) {
            $this->createOETable('ophciexamination_attribute_option_exclude', [
                'id' => 'pk',
                'option_id' => 'int(10) unsigned NOT NULL',
                'subspecialty_id' => 'int(10) unsigned NOT NULL',
            ], true);

            $this->createIndex(
                'uk_ophciexam_opt_excl_option_sub',
                'ophciexamination_attribute_option_exclude',
                ['option_id', 'subspecialty_id'],
                true
            );

            $this->addForeignKey(
                'fk_ophciexam_opt_excl_option',
                'ophciexamination_attribute_option_exclude',
                'option_id',
                'ophciexamination_attribute_option',
                'id',
                'CASCADE',
                'CASCADE'
            );

            $this->addForeignKey(
                'fk_ophciexam_opt_excl_subspecialty',
                'ophciexamination_attribute_option_exclude',
                'subspecialty_id',
                'subspecialty',
                'id',
                'CASCADE',
                'CASCADE'
            );
        } else {
            $this->migrationEcho("Table ophciexamination_attribute_option_exclude already exists. Skipping create.\n");
        }

        return true;
    }

    public function safeDown()
    {
        if ($this->dbConnection->schema->getTable('ophciexamination_attribute_option_exclude', true) !== null) {
            $this->dropForeignKey('fk_ophciexam_opt_excl_option', 'ophciexamination_attribute_option_exclude');
            $this->dropForeignKey('fk_ophciexam_opt_excl_subspecialty', 'ophciexamination_attribute_option_exclude');
            $this->dropOETable('ophciexamination_attribute_option_exclude', true);
        }

        return true;
    }
}
