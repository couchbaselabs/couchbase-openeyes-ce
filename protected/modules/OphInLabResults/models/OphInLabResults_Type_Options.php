<?php


class OphInLabResults_Type_Options extends BaseActiveRecord
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'ophinlabresults_type_options';
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

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('type,value', 'required'),
            array('type,value', 'safe'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return array(
            'resultType' => array(self::BELONGS_TO, 'OphInLabResults_Type', 'type')
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'value' => 'Value',
            'Type' => 'Type',
        );
    }
}
