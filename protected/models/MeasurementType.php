<?php

class MeasurementType extends BaseActiveRecordVersioned
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    public function tableName()
    {
        return 'measurement_type';
    }

    /**
     * Returns the Couchbase scope for this model.
     * @return string
     */
    public function couchbaseScope(): string
    {
        return 'reference';
    }

    /**
     * Returns the Couchbase collection for this model.
     * @return string
     */
    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }

    /**
     * After saving, sync to Couchbase.
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    /**
     * After deleting, remove from Couchbase.
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['class_name, attachable', 'required'],
        ];
    }

    /**
     * @param string $class_name
     *
     * @return MeasurementType
     */
    public function findByClassName($class_name)
    {
        return $this->find('class_name = ?', array($class_name));
    }
}
