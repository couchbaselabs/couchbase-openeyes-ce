<?php

/**
 * Class StudyParticipationStatus
 */
class StudyParticipationStatus  extends BaseActiveRecord
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'study_participation_status';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return array();
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array();
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array();
    }

    /**
     * @return string the Couchbase scope name
     */
    public function couchbaseScope(): string
    {
        return 'reference';
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
