<?php

class m185401_134847_create_generic_event_and_comments_table extends \OEMigration
{
    public function safeUp()
    {
        $eventTypeTable = $this->dbConnection->schema->getTable('event_type', true);
        $event_type_id = \Yii::app()->db->createCommand()->select('id')->from('event_type')->where('class_name=:class_name', array(':class_name' => 'OphGeneric'))->queryScalar();

        if (!$event_type_id) {
            $eventTypeData = ['name' => 'Device Information', 'event_group_id' => 1, 'class_name' => 'OphGeneric'];
            if ($eventTypeTable && isset($eventTypeTable->columns['can_be_created_manually'])) {
                $eventTypeData['can_be_created_manually'] = 0;
            }
            $this->insert('event_type', $eventTypeData);
            $event_type_id = \Yii::app()->db->createCommand()->select('id')->from('event_type')->where('class_name=:class_name', array(':class_name' => 'OphGeneric'))->queryScalar();
        }

        $this->insertElementTypeIfMissing($event_type_id, 'OEModule\OphGeneric\models\Comments', 'Comments', 10);
        $this->insertElementTypeIfMissing($event_type_id, 'OEModule\OphGeneric\models\Attachment', 'Attachment', 1);

        $this->createOETable('et_ophgeneric_attachment', [
            'id' => 'pk',
            'event_id' => 'INT(10) UNSIGNED NOT NULL DEFAULT 0',
        ], true);
        $this->createOETable(
            'et_ophgeneric_comments',
            ['id' => 'pk', 'comment' => 'text', 'event_id' => 'int(10) unsigned NOT NULL'],
            true
        );
        $this->addForeignKey('fk_documentophgeneric_event_id', 'et_ophgeneric_comments', 'event_id', 'event', 'id');
        $this->addForeignKey('et_ophgeneric_attach_ev_fk', 'et_ophgeneric_attachment', 'event_id', 'event', 'id');
    }

    public function safeDown()
    {
            $event_type_id = \Yii::app()->db->createCommand()->select('id')->from('event_type')->where('class_name=:class_name', array(':class_name' => 'OphGeneric'))->queryScalar();
            $this->delete('element_type', 'class_name = ? AND event_type_id = ?', ['OEModule\OphGeneric\models\Comments', $event_type_id]);
            $this->delete('element_type', 'class_name = ? AND event_type_id = ?', ['OEModule\OphGeneric\models\Attachment', $event_type_id]);
            $this->delete('event_type', 'class_name = ?', ["OphGeneric"]);
            $this->dropForeignKey('fk_documentophgeneric_event_id', 'et_ophgeneric_comments');
            $this->dropForeignKey('et_ophgeneric_attach_ev_fk', 'et_ophgeneric_attachment');
            $this->dropOETable('et_ophgeneric_comments', true);
            $this->dropOETable('et_ophgeneric_attachment', true);

            $event_type_id = \Yii::app()->db->createCommand()->select('id')->from('event_type')->where('class_name=:class_name', array(':class_name' => 'OphInBiometry'))->queryScalar();
            $this->delete('element_type', 'class_name = ? AND event_type_id = ?', [ 'OEModule\OphGeneric\models\Attachment', $event_type_id]);
    }

    private function insertElementTypeIfMissing($eventTypeId, $className, $name, $displayOrder)
    {
        if (!$eventTypeId) {
            return;
        }

        $exists = $this->dbConnection->createCommand()
            ->select('id')
            ->from('element_type')
            ->where('class_name = :class AND event_type_id = :event', array(':class' => $className, ':event' => $eventTypeId))
            ->queryScalar();

        if (!$exists) {
            $this->insert('element_type', array(
                'name' => $name,
                'class_name' => $className,
                'event_type_id' => $eventTypeId,
                'display_order' => $displayOrder,
                'required' => 1,
            ));
        }
    }
}
