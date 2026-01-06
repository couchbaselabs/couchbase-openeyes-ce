<?php

class m260106_000000_set_element_dna_sample_as_default extends CDbMigration
{
    public function up()
    {
        // Find the Element_OphInDnasample_Sample element type and mark it as default
        $this->update('element_type', array('`default`' => 1), "class_name='Element_OphInDnasample_Sample'");
    }

    public function down()
    {
        // Unset the default flag if rolling back
        $this->update('element_type', array('`default`' => 0), "class_name='Element_OphInDnasample_Sample'");
    }
}
