<?php

class m180205_140223_replace_IOL_to_fulltext extends CDbMigration
{

    // Use safeUp/safeDown to do migration with transaction
    public function safeUp()
    {
        $rows = $this->dbConnection->createCommand()
            ->select('id, term')
            ->from('proc')
            ->where('term LIKE :needle', [':needle' => '%IOL%'])
            ->queryAll();

        foreach ($rows as $procedure) {
            $words = explode(' ', $procedure['term']);
            $updated = false;

            foreach ($words as $index => $word) {
                if ($word === 'IOL') {
                    $words[$index] = 'Intraocular lens';
                    $updated = true;
                }
            }

            if ($updated) {
                $newTerm = implode(' ', $words);
                $this->update('proc', ['term' => $newTerm], 'id = :id', [':id' => $procedure['id']]);

                $data = [
                    'table' => 'proc',
                    'model' => 'Procedure',
                    'old_term' => $procedure['term'],
                    'new_term' => $newTerm,
                ];

                \Audit::add('Admin', 'update', '<pre>' . print_r($data, true) . '</pre>', '', ['model' => 'Procedure']);
            }
        }
    }

    public function safeDown()
    {
        echo "m180205_140223_replace_IOL_to_fulltext does not support migration down.\n";
        return false;
    }

}
