<?php

class m171010_104800_add_more_colorvision_methods extends OEMigration
{
    public function up()
    {
        //$unit = $this->dbConnection->createCommand()->select('id')->from('ophciexamination_visual_acuity_unit')->where('name = :name', array(':name' => 'Snellen Metre'))->queryRow();

        # Check that these values do not already exist
        $is13 = $this->dbConnection->createCommand()->select('id')->from('ophciexamination_colourvision_method')->where('name = :name', array(':name' => 'Ishihara /13'))->queryScalar();
        $is17 = $this->dbConnection->createCommand()->select('id')->from('ophciexamination_colourvision_method')->where('name = :name', array(':name' => 'Ishihara /17'))->queryScalar();
        $is24 = $this->dbConnection->createCommand()->select('id')->from('ophciexamination_colourvision_method')->where('name = :name', array(':name' => 'Ishihara /24'))->queryScalar();

        # Insert values if they don't already exist
        if (!$is13) {
            $this->insert('ophciexamination_colourvision_method', array(
                          'name' => 'Ishihara /13',
                          'active' => '1',
                          'display_order' => '1',
                  ));
        # Add values
            $method_id = $this->dbConnection->createCommand()->select('id')->from('ophciexamination_colourvision_method')->where('name = :name', array(':name' => 'Ishihara /13'))->queryScalar();

            for ($i=0; $i<14; $i++) {
                $this->insert('ophciexamination_colourvision_value', array(
                            'name' => $i . '/13',
                            'active' => '1',
                            'display_order' => $i+1,
                            'method_id' => $method_id,
                    ));
            }
        }

        if (!$is17) {
            $this->insert('ophciexamination_colourvision_method', array(
                          'name' => 'Ishihara /17',
                          'active' => '1',
                          'display_order' => '3',
                  ));

            # Add values
            $method_id = $this->dbConnection->createCommand()->select('id')->from('ophciexamination_colourvision_method')->where('name = :name', array(':name' => 'Ishihara /17'))->queryScalar();
            for ($i=0; $i < 18; $i++) {
                $this->insert('ophciexamination_colourvision_value', array(
                            'name' => $i . '/17',
                            'active' => '1',
                            'display_order' => $i+1,
                            'method_id' => $method_id,
                    ));
            }
        }

        if (!$is24) {
            $this->insert('ophciexamination_colourvision_method', array(
                          'name' => 'Ishihara /24',
                          'active' => '1',
                          'display_order' => '5',
                  ));

            # Add values
            $method_id = $this->dbConnection->createCommand()->select('id')->from('ophciexamination_colourvision_method')->where('name = :name', array(':name' => 'Ishihara /24'))->queryScalar();
            for ($i=0; $i < 25; $i++) {
                $this->insert('ophciexamination_colourvision_value', array(
                            'name' => $i . '/24',
                            'active' => '1',
                            'display_order' => $i+1,
                            'method_id' => $method_id,
                    ));
            }
        }

    # update display order
        $this->update('ophciexamination_colourvision_method', array('display_order' => '2'), "`name` = 'Ishihara /15'");
        $this->update('ophciexamination_colourvision_method', array('display_order' => '4'), "`name` = 'Ishihara /21'");
        $this->update('ophciexamination_colourvision_method', array('display_order' => '6'), "`name` = 'Red desaturation'");

    }

    public function down()
    {

    }
}
