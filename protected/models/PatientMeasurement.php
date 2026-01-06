<?php

use OE\Models\Traits\CouchbaseModelBridge;

class PatientMeasurement extends BaseActiveRecordVersioned
{
    use CouchbaseModelBridge;
    public function tableName()
    {
        return 'patient_measurement';
    }

    public function relations()
    {
        return array(
            'patient' => array(self::BELONGS_TO, 'Patient', 'patient_id'),
            'type' => array(self::BELONGS_TO, 'MeasurementType', 'measurement_type_id'),
            'originReference' => array(self::HAS_ONE, 'MeasurementReference', 'patient_measurement_id', 'on' => 'originReference.origin = true'),
            'references' => array(self::HAS_MANY, 'MeasurementReference', 'patient_measurement_id'),
        );
    }

    /**
     * @return string the Couchbase scope for this model
     */
    public function couchbaseScope(): string
    {
        return 'clinical';
    }

    /**
     * @return string the Couchbase collection name
     */
    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }

    /**
     * After saving, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    /**
     * After deleting, remove from Couchbase
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
}
