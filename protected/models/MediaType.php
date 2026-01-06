<?php

class MediaType extends BaseActiveRecordVersioned
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * Returns the static model of the specified AR class.
     *
     * @return ProtectedFile the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'media_type';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return array(
            array('type_name, type_class, type_method, type_html_tag, type_mime', 'required'),
        );
    }

    /**
     * @return string the Couchbase scope name for this model
     */
    public function couchbaseScope(): string
    {
        return 'reference';
    }

    /**
     * @return string the Couchbase collection name for this model
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
