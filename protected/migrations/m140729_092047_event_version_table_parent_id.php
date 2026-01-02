<?php

class m140729_092047_event_version_table_parent_id extends OEMigration
{
    public function up()
    {
        $schema = $this->dbConnection->schema;
        $eventVersion = $schema->getTable('event_version', true);
        if ($eventVersion && !array_key_exists('parent_id', $eventVersion->columns)) {
            $this->addColumn('event_version', 'parent_id', 'int(10) unsigned NULL');
        }

        $eventTypeVersion = $schema->getTable('event_type_version', true);
        if ($eventTypeVersion && !array_key_exists('parent_id', $eventTypeVersion->columns)) {
            $this->addColumn('event_type_version', 'parent_id', 'int(10) unsigned NULL');
        }
    }

    public function down()
    {
        $schema = $this->dbConnection->schema;
        $eventTypeVersion = $schema->getTable('event_type_version', true);
        if ($eventTypeVersion && array_key_exists('parent_id', $eventTypeVersion->columns)) {
            $this->dropColumn('event_type_version', 'parent_id');
        }

        $eventVersion = $schema->getTable('event_version', true);
        if ($eventVersion && array_key_exists('parent_id', $eventVersion->columns)) {
            $this->dropColumn('event_version', 'parent_id');
        }
    }
}
