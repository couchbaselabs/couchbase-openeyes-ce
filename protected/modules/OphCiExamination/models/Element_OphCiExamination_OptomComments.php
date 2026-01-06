<?php

namespace OEModule\OphCiExamination\models;

class Element_OphCiExamination_OptomComments extends \BaseEventTypeElement
{
    use traits\CustomOrdering;
    use \OE\Models\Traits\CouchbaseModelBridge;

    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'et_ophciexamination_optom_comments';
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
                'event' => array(self::BELONGS_TO, 'Event', 'event_id'),
        );
    }

    /**
     * @return string the Couchbase scope name
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
