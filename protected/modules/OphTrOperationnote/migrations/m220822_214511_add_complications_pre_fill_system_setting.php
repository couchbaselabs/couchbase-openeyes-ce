<?php

class m220822_214511_add_complications_pre_fill_system_setting extends OEMigration
{
    // Use safeUp/safeDown to do migration with transaction
    public function safeUp()
    {
        if (!$this->tableExists('setting_group') || !$this->tableExists('setting_field_type') || !$this->tableExists('setting_metadata')) {
            $this->migrationEcho('Skipping complications pre-fill setting - required tables missing.');
            return true;
        }

        $group_id = $this->dbConnection->createCommand("SELECT id FROM setting_group WHERE `name` = :group_name")
            ->queryScalar([':group_name' => 'Operation Note']);

        if (!$group_id) {
            $this->migrationEcho('Skipping complications pre-fill setting - Operation Note group not found.');
            return true;
        }

        $field_type_id = $this->dbConnection->createCommand('SELECT id FROM setting_field_type WHERE name = "Radio buttons"')
            ->queryScalar();

        if (!$field_type_id) {
            $this->migrationEcho('Skipping complications pre-fill setting - Radio buttons field type missing.');
            return true;
        }

        $this->insert('setting_metadata', array(
            'element_type_id' => null,
            'display_order' => 0,
            'key' => 'allow_complications_in_pre_fill_templates',
            'name' => 'Allow complications to be stored in pre-fill templates',
            'field_type_id' => $field_type_id,
            'data' => 'a:2:{s:2:"on";s:2:"On";s:3:"off";s:3:"Off";}',
            'default_value' => 'off',
            'group_id' => $group_id,
            'description' => 'When enabled: For user defined op note templates, this allows the value of the complications selector to be stored as part of the template (e.g, always default to "None").
            
 When disabled, complications will not be part of any template and must be manually completed each time. For better data quiality it is recommended to keep this setting off, otherwise users may "forget" to record any complications that occurred during the surgery'
        ));
    }

    public function safeDown()
    {
        foreach (['setting_installation', 'setting_metadata'] as $table) {
            if ($this->tableExists($table)) {
                $this->delete($table, '`key`="allow_complications_in_pre_fill_templates"');
            }
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->dbConnection->schema->getTable($table, true) !== null;
    }
}
