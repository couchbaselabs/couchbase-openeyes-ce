<?php

class m180601_114000_insert_oescape_summaries extends CDbMigration
{
    public function safeUp()
    {
        $event_type_id = $this->dbConnection->createCommand()
            ->select('id')
            ->from('event_type')
            ->where('class_name = ?', array('OphCiExamination'))
            ->queryScalar();

        $items = array('Visual Acuity History', 'Medication', 'Medical Retinal History', 'IOP History');
        foreach ($items as $name) {
            $exists = $this->dbConnection->createCommand()
                ->select('id')
                ->from('oescape_summary_item')
                ->where('event_type_id = :et AND name = :name', array(':et' => $event_type_id, ':name' => $name))
                ->queryScalar();
            if (!$exists) {
                $this->insert('oescape_summary_item', array('event_type_id' => $event_type_id, 'name' => $name));
            }
        }

        $VA_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Visual Acuity History"')->queryScalar();
        $Med_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Medication"')->queryScalar();
        $MR_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Medical Retinal History"')->queryScalar();
        $IOP_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="IOP History"')->queryScalar();

        $glaucoma_id = $this->getDbConnection()->createCommand('select id from subspecialty where name ="Glaucoma"')->queryScalar();
        $cataract_id = $this->getDbConnection()->createCommand('select id from subspecialty where name ="Cataract"')->queryScalar();
        $MR_sub_id = $this->getDbConnection()->createCommand('select id from subspecialty where name ="Medical Retina"')->queryScalar();

        $this->insertSummaryIfMissing(0, $Med_id, $glaucoma_id);
        $this->insertSummaryIfMissing(1, $IOP_id, $glaucoma_id);
        $this->insertSummaryIfMissing(2, $VA_id, $glaucoma_id);
        $this->insertSummaryIfMissing(0, $MR_id, $MR_sub_id);
        $this->insertSummaryIfMissing(0, $VA_id, $cataract_id);
    }

    public function safeDown()
    {
        $VA_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Visual Acuity History"')->queryScalar();
        $Med_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Medication"')->queryScalar();
        $MR_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Medical Retinal History"')->queryScalar();
        $IOP_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="IOP History"')->queryScalar();

        $glaucoma_id = $this->getDbConnection()->createCommand('select id from subspecialty where name ="Glaucoma"')->queryScalar();
        $cataract_id = $this->getDbConnection()->createCommand('select id from subspecialty where name ="Cataract"')->queryScalar();
        $MR_sub_id = $this->getDbConnection()->createCommand('select id from subspecialty where name ="Medical Retina"')->queryScalar();

        $this->deleteSummaryIfExists($Med_id, $glaucoma_id);
        $this->deleteSummaryIfExists($VA_id, $glaucoma_id);
        $this->deleteSummaryIfExists($IOP_id, $glaucoma_id);
        $this->deleteSummaryIfExists($MR_id, $MR_sub_id);
        $this->deleteSummaryIfExists($VA_id, $cataract_id);

        $event_type_id = $this->dbConnection->createCommand()
            ->select('id')
            ->from('event_type')
            ->where('class_name = ?', array('OphCiExamination'))
            ->queryScalar();

        $this->delete('oescape_summary_item', 'event_type_id = ? and name = ?', array($event_type_id, 'Visual Acuity History'));
        $this->delete('oescape_summary_item', 'event_type_id = ? and name = ?', array($event_type_id, 'Medication'));
        $this->delete('oescape_summary_item', 'event_type_id = ? and name = ?', array($event_type_id, 'Medical Retinal History'));
        $this->delete('oescape_summary_item', 'event_type_id = ? and name = ?', array($event_type_id, 'IOP History'));
    }

    private function insertSummaryIfMissing($displayOrder, $itemId, $subspecialtyId)
    {
        if (!$itemId || !$subspecialtyId) {
            return;
        }

        $exists = $this->dbConnection->createCommand()
            ->select('id')
            ->from('oescape_summary')
            ->where('item_id = :item AND subspecialty_id = :sub', array(':item' => $itemId, ':sub' => $subspecialtyId))
            ->queryScalar();

        if (!$exists) {
            $this->insert('oescape_summary', array(
                'display_order' => $displayOrder,
                'item_id' => $itemId,
                'subspecialty_id' => $subspecialtyId,
            ));
        }
    }

    private function deleteSummaryIfExists($itemId, $subspecialtyId)
    {
        if ($itemId && $subspecialtyId) {
            $this->delete('oescape_summary', '`item_id` = :item AND `subspecialty_id` = :sub', array(':item' => $itemId, ':sub' => $subspecialtyId));
        }
    }
}
