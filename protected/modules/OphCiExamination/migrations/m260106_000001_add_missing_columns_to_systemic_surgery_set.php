<?php

class m260106_000001_add_missing_columns_to_systemic_surgery_set extends OEMigration
{
    public function safeUp()
    {
        // Add missing institution_id column
        if (!$this->columnExists('ophciexamination_systemic_surgery_set', 'institution_id')) {
            $this->addColumn('ophciexamination_systemic_surgery_set', 'institution_id', 'int(10) unsigned NOT NULL DEFAULT 1');
        }
        
        // Add missing audit columns
        if (!$this->columnExists('ophciexamination_systemic_surgery_set', 'created_user_id')) {
            $this->addColumn('ophciexamination_systemic_surgery_set', 'created_user_id', 'int(10)');
        }
        
        if (!$this->columnExists('ophciexamination_systemic_surgery_set', 'created_date')) {
            $this->addColumn('ophciexamination_systemic_surgery_set', 'created_date', 'datetime');
        }
        
        if (!$this->columnExists('ophciexamination_systemic_surgery_set', 'last_modified_user_id')) {
            $this->addColumn('ophciexamination_systemic_surgery_set', 'last_modified_user_id', 'int(10)');
        }
        
        if (!$this->columnExists('ophciexamination_systemic_surgery_set', 'last_modified_date')) {
            $this->addColumn('ophciexamination_systemic_surgery_set', 'last_modified_date', 'datetime');
        }
        
        // Add foreign key for institution
        if (!$this->foreignKeyExists('ophciexamination_systemic_surgery_set', 'institution_id')) {
            $this->addForeignKey(
                'systemic_surgery_set_institution_fk',
                'ophciexamination_systemic_surgery_set',
                'institution_id',
                'institution',
                'id'
            );
        }
    }

    public function safeDown()
    {
        // Remove foreign key
        if ($this->foreignKeyExists('ophciexamination_systemic_surgery_set', 'institution_id')) {
            $this->dropForeignKey('systemic_surgery_set_institution_fk', 'ophciexamination_systemic_surgery_set');
        }
        
        // Drop columns
        if ($this->columnExists('ophciexamination_systemic_surgery_set', 'last_modified_date')) {
            $this->dropColumn('ophciexamination_systemic_surgery_set', 'last_modified_date');
        }
        
        if ($this->columnExists('ophciexamination_systemic_surgery_set', 'last_modified_user_id')) {
            $this->dropColumn('ophciexamination_systemic_surgery_set', 'last_modified_user_id');
        }
        
        if ($this->columnExists('ophciexamination_systemic_surgery_set', 'created_date')) {
            $this->dropColumn('ophciexamination_systemic_surgery_set', 'created_date');
        }
        
        if ($this->columnExists('ophciexamination_systemic_surgery_set', 'created_user_id')) {
            $this->dropColumn('ophciexamination_systemic_surgery_set', 'created_user_id');
        }
        
        if ($this->columnExists('ophciexamination_systemic_surgery_set', 'institution_id')) {
            $this->dropColumn('ophciexamination_systemic_surgery_set', 'institution_id');
        }
    }
}
?>
