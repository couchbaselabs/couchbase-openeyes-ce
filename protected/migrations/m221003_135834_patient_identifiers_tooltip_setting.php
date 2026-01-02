<?php

class m221003_135834_patient_identifiers_tooltip_setting extends CDbMigration
{
    public function safeUp()
    {
        $metadataSchema = $this->getDbConnection()->schema->getTable('setting_metadata', true);
        $fieldTypeSchema = $this->getDbConnection()->schema->getTable('setting_field_type', true);

        if (!$metadataSchema || !$fieldTypeSchema) {
            echo "Skipping patient identifier tooltip setting - required tables missing.\n";
            return true;
        }

        $checkbox_field_type_id = $this->dbConnection
            ->createCommand('SELECT `id` FROM setting_field_type WHERE `name`="Radio buttons"')
            ->queryScalar();

        $this->insert('setting_metadata', array(
            'element_type_id' => null,
            'field_type_id' => $checkbox_field_type_id,
            'key' => 'enable_patient_identifier_tooltip',
            'name' => 'Enable Patient Identifier tooltip',
            'data' => serialize(['on' => 'On', 'off' => 'Off']),
            'default_value' => 'off'
        ));

        if (array_key_exists('description', $metadataSchema->columns)) {
            $this->update('setting_metadata', ['description' => 'Enable Patient Identifier tooltip'], 'key = :key', [':key' => 'enable_patient_identifier_tooltip']);
        }

        if (array_key_exists('group_id', $metadataSchema->columns)) {
            $this->update('setting_metadata', ['group_id' => 15], 'key = :key', [':key' => 'enable_patient_identifier_tooltip']);
        }
    }

    public function safeDown()
    {
        if ($this->getDbConnection()->schema->getTable('setting_metadata', true)) {
            $this->delete(
                'setting_metadata',
                '`key` = :key',
                ['key' => 'enable_patient_identifier_tooltip']
            );
        }
    }
}
