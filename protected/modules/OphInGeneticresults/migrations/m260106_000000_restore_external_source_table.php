<?php

class m260106_000000_restore_external_source_table extends OEMigration
{
    public function up()
    {
        // Re-create the external_source table that was previously dropped
        // This table is needed for the ExternalSourceAdminController list page
        $this->createOETable(
            'ophingeneticresults_external_source',
            array(
                'id' => 'pk',
                'name' => 'varchar(255) NOT NULL',
            ),
            true
        );
    }

    public function down()
    {
        $this->dropOETable('ophingeneticresults_external_source');
    }
}
