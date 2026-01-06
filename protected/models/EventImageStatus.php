<?php

/**
 * This is the model class for table "event_image_status".
 *
 * The followings are the available columns in table 'event_image_status':
 * @property integer $id
 * @property string $name
 *
 * The followings are the available model relations:
 * @property EventImage[] $eventImages
 */
class EventImageStatus extends BaseActiveRecordVersioned
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    public const STATUS_NOT_CREATED = "NOT_CREATED";
    public const STATUS_CREATED = "CREATED";
    public const STATUS_FAILED = "FAILED";
    public const STATUS_GENERATING = "GENERATING";

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'event_image_status';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return array(
            array('name', 'length', 'max' => 50),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'eventImages' => array(self::HAS_MANY, 'EventImage', 'status_id'),
        );
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return EventImageStatus the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the Couchbase scope name
     */
    public function couchbaseScope(): string
    {
        return 'core';
    }

    /**
     * @return string the Couchbase collection name
     */
    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }

    /**
     * After save, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    /**
     * After delete, remove from Couchbase
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
}
