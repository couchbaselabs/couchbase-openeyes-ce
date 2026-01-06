<?php

class m260106_000001_insert_laser_types extends OEMigration
{
    public function up()
    {
        // Check if laser types already exist
        $existingCount = Yii::app()->db->createCommand('SELECT COUNT(*) FROM ophtrlaser_type')->queryScalar();
        
        if ($existingCount == 0) {
            // Insert default laser types
            $types = array(
                array('id' => 1, 'name' => 'Unknown'),
                array('id' => 2, 'name' => 'Argon'),
                array('id' => 3, 'name' => 'Diode'),
                array('id' => 4, 'name' => 'Excimer'),
                array('id' => 5, 'name' => 'YAG'),
            );
            
            foreach ($types as $type) {
                $this->insert('ophtrlaser_type', $type);
            }
        }
    }

    public function down()
    {
        // Don't delete data on rollback to prevent data loss
    }
}
