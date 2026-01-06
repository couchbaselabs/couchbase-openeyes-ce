<?php

class m2026_01_06_set_default_elements extends CDbMigration
{
    public function up()
    {
        // Set the DNA extraction element as default
        $event_type = $this->dbConnection->createCommand()
            ->select('id')
            ->from('event_type')
            ->where('class_name=:class_name', array(':class_name' => 'OphInDnaextraction'))
            ->queryRow();

        if ($event_type) {
            $this->update('element_type', 
                array('`default`' => 1),
                'event_type_id=:eventTypeId AND name=:name',
                array(':eventTypeId' => $event_type['id'], ':name' => 'DNA extraction')
            );
        }
    }

    public function down()
    {
        // Reset the default flag if needed
        $event_type = $this->dbConnection->createCommand()
            ->select('id')
            ->from('event_type')
            ->where('class_name=:class_name', array(':class_name' => 'OphInDnaextraction'))
            ->queryRow();

        if ($event_type) {
            $this->update('element_type', 
                array('`default`' => 0),
                'event_type_id=:eventTypeId AND name=:name',
                array(':eventTypeId' => $event_type['id'], ':name' => 'DNA extraction')
            );
        }
    }
}
