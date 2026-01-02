<?php

class m220926_023206_add_auto_close_exam_element_setting extends OEMigration
{
    public function safeUp()
    {
        $metadataSchema = $this->dbConnection->schema->getTable('setting_metadata', true);
        if (!$metadataSchema) {
            $this->migrationEcho('Skipping auto-close exam element setting - setting_metadata table missing.');
            return true;
        }

        $data = array(
            'display_order' => 0,
            'field_type_id' => 3,
            'key' => 'close_incomplete_exam_elements',
            'name' => 'Offer to automatically close incomplete examination elements',
            'lowest_setting_level' => 'INSTALLATION',
            'data' => serialize(array('on' => 'On', 'off' => 'Off')),
            'default_value' => 'off'
        );

        if (array_key_exists('description', $metadataSchema->columns)) {
            $data['description'] = '';
        }

        if (array_key_exists('group_id', $metadataSchema->columns)) {
            $data['group_id'] = 1;
        }

        $this->insert('setting_metadata', $data);
    }

    public function safeDown()
    {
        foreach (['setting_institution', 'setting_installation', 'setting_metadata'] as $table) {
            if ($this->dbConnection->schema->getTable($table, true)) {
                $this->delete($table, '`key` = "close_incomplete_exam_elements"');
            }
        }
    }
}
