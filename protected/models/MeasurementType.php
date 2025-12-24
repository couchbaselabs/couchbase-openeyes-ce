<?php

class MeasurementType extends BaseActiveRecordVersioned
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    public function tableName()
    {
        return 'measurement_type';
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
