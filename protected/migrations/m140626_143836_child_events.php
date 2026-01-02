<?php

class m140626_143836_child_events extends CDbMigration
{
    public function up()
    {
        $schema = $this->dbConnection->schema;

        $eventTypeTable = $schema->getTable('event_type', true);
        if ($eventTypeTable && !array_key_exists('parent_id', $eventTypeTable->columns)) {
            $this->addColumn('event_type', 'parent_id', 'int(10) unsigned NULL');
            $this->createIndex('event_type_parent_id_fk', 'event_type', 'parent_id');
            $this->addForeignKey('event_type_parent_id_fk', 'event_type', 'parent_id', 'event_type', 'id');
        }

        $eventTable = $schema->getTable('event', true);
        if ($eventTable && !array_key_exists('parent_id', $eventTable->columns)) {
            $this->addColumn('event', 'parent_id', 'int(10) unsigned NULL');
            $this->createIndex('event_parent_id_fk', 'event', 'parent_id');
            $this->addForeignKey('event_parent_id_fk', 'event', 'parent_id', 'event', 'id');
        }
    }

    public function down()
    {
        $schema = $this->dbConnection->schema;

        $eventTable = $schema->getTable('event', true);
        if ($eventTable && array_key_exists('parent_id', $eventTable->columns)) {
            $this->dropForeignKey('event_parent_id_fk', 'event');
            $this->dropIndex('event_parent_id_fk', 'event');
            $this->dropColumn('event', 'parent_id');
        }

        $eventTypeTable = $schema->getTable('event_type', true);
        if ($eventTypeTable && array_key_exists('parent_id', $eventTypeTable->columns)) {
            $this->dropForeignKey('event_type_parent_id_fk', 'event_type');
            $this->dropIndex('event_type_parent_id_fk', 'event_type');
            $this->dropColumn('event_type', 'parent_id');
        }

    }


}
