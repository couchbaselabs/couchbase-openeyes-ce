<?php

class m200100_000000_create_index_search_table_stub extends OEMigration
{
    public function up()
    {
        if ($this->verifyTableExists('index_search')) {
            return;
        }

        $this->createOETable('index_search', array(
            'id' => 'pk',
            'event_type_id' => 'int(10) unsigned',
            'parent' => 'int(11)',
            'primary_term' => 'varchar(128)',
            'secondary_term_list' => 'varchar(1024)',
            'description' => 'varchar(512)',
            'general_note' => 'varchar(256)',
            'open_element_class_name' => 'varchar(256)',
            'goto_id' => 'varchar(256)',
            'goto_tag' => 'varchar(256)',
            'goto_text' => 'varchar(256)',
            'img_url' => 'varchar(256)',
            'goto_subcontainer_class' => 'varchar(256)',
            'goto_doodle_class_name' => 'varchar(256)',
            'goto_property' => 'varchar(256)',
            'warning_note' => 'varchar(256)'
        ));

        $this->addForeignKey('event_type_id_indexsearch_fk', 'index_search', 'event_type_id', 'event_type', 'id');
        $this->addForeignKey('parent_id_fk', 'index_search', 'parent', 'index_search', 'id');
    }

    public function down()
    {
        if (!$this->verifyTableExists('index_search')) {
            return;
        }

        if ($this->verifyForeignKeyExists('index_search', 'event_type_id_indexsearch_fk')) {
            $this->dropForeignKey('event_type_id_indexsearch_fk', 'index_search');
        }

        if ($this->verifyForeignKeyExists('index_search', 'parent_id_fk')) {
            $this->dropForeignKey('parent_id_fk', 'index_search');
        }

        $this->dropTable('index_search');
    }
}
