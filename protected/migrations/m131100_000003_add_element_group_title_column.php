<?php

class m131100_000003_add_element_group_title_column extends CDbMigration
{
    public function safeUp()
    {
        $schema = $this->dbConnection->schema;

        $elementTypeTable = $schema->getTable('element_type', true);
        if ($elementTypeTable && !array_key_exists('group_title', $elementTypeTable->columns)) {
            $this->addColumn('element_type', 'group_title', 'varchar(255) NULL DEFAULT NULL AFTER `required`');
            $this->update('element_type', array('group_title' => new CDbExpression('`name`')));
        }

        $elementTypeVersionTable = $schema->getTable('element_type_version', true);
        if ($elementTypeVersionTable && !array_key_exists('group_title', $elementTypeVersionTable->columns)) {
            $this->addColumn('element_type_version', 'group_title', 'varchar(255) NULL DEFAULT NULL');
        }
    }

    public function safeDown()
    {
        $schema = $this->dbConnection->schema;

        $elementTypeVersionTable = $schema->getTable('element_type_version', true);
        if ($elementTypeVersionTable && array_key_exists('group_title', $elementTypeVersionTable->columns)) {
            $this->dropColumn('element_type_version', 'group_title');
        }

        $elementTypeTable = $schema->getTable('element_type', true);
        if ($elementTypeTable && array_key_exists('group_title', $elementTypeTable->columns)) {
            $this->dropColumn('element_type', 'group_title');
        }
    }
}
