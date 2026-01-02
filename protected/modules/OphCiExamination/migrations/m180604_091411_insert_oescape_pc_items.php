<?php

class m180604_091411_insert_oescape_pc_items extends CDbMigration
{

    // Use safeUp/safeDown to do migration with transaction
    public function safeUp()
    {
        $VA_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Visual Acuity History"')->queryScalar();
        $Med_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Medication"')->queryScalar();
        $IOP_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="IOP History"')->queryScalar();

        $PC_id =  $this->getDbConnection()->createCommand('select id from subspecialty where name ="General Ophthalmology"')->queryScalar();

        $this->insertSummaryIfMissing(0, $Med_id, $PC_id);
        $this->insertSummaryIfMissing(2, $IOP_id, $PC_id);
        $this->insertSummaryIfMissing(1, $VA_id, $PC_id);
    }

    public function safeDown()
    {
        $VA_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Visual Acuity History"')->queryScalar();
        $Med_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="Medication"')->queryScalar();
        $IOP_id = $this->getDbConnection()->createCommand('select id from oescape_summary_item where name ="IOP History"')->queryScalar();

        $PC_id =  $this->getDbConnection()->createCommand('select id from subspecialty where name ="General Ophthalmology"')->queryScalar();

        $this->deleteSummary($Med_id, $PC_id);
        $this->deleteSummary($VA_id, $PC_id);
        $this->deleteSummary($IOP_id, $PC_id);
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

    private function deleteSummary($itemId, $subspecialtyId)
    {
        if ($itemId && $subspecialtyId) {
            $this->delete('oescape_summary', '`item_id` = :item AND `subspecialty_id` = :sub', array(':item' => $itemId, ':sub' => $subspecialtyId));
        }
    }

}
