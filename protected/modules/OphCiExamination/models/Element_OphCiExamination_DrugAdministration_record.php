<?php

namespace OEModule\OphCiExamination\models;

use OEModule\OphDrPGDPSD\models\Element_DrugAdministration_record;

class Element_OphCiExamination_DrugAdministration_record extends Element_DrugAdministration_record
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    public static function getUsageType()
    {
        return "OphCiExamination";
    }

    public static function getUsageSubtype()
    {
        return "DrugAdministration";
    }

    public function couchbaseScope(): string
    {
        return 'clinical';
    }

    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }

    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
}
