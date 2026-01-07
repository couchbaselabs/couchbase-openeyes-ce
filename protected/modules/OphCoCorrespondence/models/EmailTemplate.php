<?php

/**
 * This is the model class for table "ophcocorrespondence_email_template".
 *
 * The followings are the available columns in table 'ophcocorrespondence_email_template':
 * @property integer $id
 * @property string $institution_id
 * @property string $site_id
 * @property string $recipient_type
 * @property string $title
 * @property string $subject
 * @property string $body
 * @property string $last_modified_user_id
 * @property string $last_modified_date
 * @property string $created_user_id
 * @property string $created_date
 *
 * The followings are the available model relations:
 * @property User $created_user
 * @property Institution $institution
 * @property Site $site
 * @property User $last_modified_user
 */
class EmailTemplate extends BaseActiveRecordVersioned
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * Returns the Couchbase scope name for this model.
     *
     * @return string
     */
    public function couchbaseScope(): string
    {
        return 'correspondence';
    }

    /**
     * Returns the Couchbase collection name for this model.
     *
     * @return string
     */
    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }

    /**
     * After save, sync to Couchbase.
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    /**
     * After delete, remove from Couchbase.
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'ophcocorrespondence_email_template';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('recipient_type', 'required'),
            array('institution_id, last_modified_user_id, created_user_id', 'length', 'max'=>10),
            array('institution_id, site_id', 'default', 'setOnEmpty' => true, 'value' => null),
            array('recipient_type', 'institutionSiteRecipientValidator'),
            array('recipient_type', 'length', 'max'=>20),
            array('title, subject', 'length', 'max'=>255),
            array('body, last_modified_date, created_date', 'safe'),
            // The following rule is used by search().
            // @todo Please remove those attributes that should not be searched.
            array('id, institution_id, recipient_type, title, subject, body', 'safe', 'on'=>'search'),
        );
    }

    public function institutionSiteRecipientValidator($attribute, $params)
    {
        try {
            // Build the WHERE clause conditions
            $conditions = [];
            $queryParams = [];

            // Institution condition
            if ($this->institution_id != '') {
                $conditions[] = 'oet.institution_id = :institution_id';
                $queryParams[':institution_id'] = $this->institution_id;
            } else {
                $conditions[] = 'oet.institution_id IS NULL';
            }

            // Site condition
            if ($this->site_id != '') {
                $conditions[] = 'oet.site_id = :site_id';
                $queryParams[':site_id'] = $this->site_id;
            } else {
                $conditions[] = 'oet.site_id IS NULL';
            }

            // Recipient type and ID conditions
            $conditions[] = 'LOWER(oet.recipient_type) = LOWER(:recipient_type)';
            $conditions[] = 'oet.id != :email_template_id';
            $queryParams[':recipient_type'] = $this->recipient_type;
            $queryParams[':email_template_id'] = $this->id ?: -1;

            $whereClause = implode(' AND ', $conditions);

            // Use MariaDB connection directly instead of Couchbase
            $query = Yii::app()->db->createCommand()
                ->select('oet.id')
                ->from('ophcocorrespondence_email_template oet')
                ->where($whereClause, $queryParams)
                ->queryAll();

            if (count($query) !== 0) {
                $this->addError($attribute, 'This combination of institution, site and recipient already exists.');
            }
        } catch (Exception $e) {
            // Log the error but don't block validation
            Yii::log('Email template validator error: ' . $e->getMessage(), CLogger::LEVEL_ERROR);
        }
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return array(
            'created_user' => array(self::BELONGS_TO, 'User', 'created_user_id'),
            'institution' => array(self::BELONGS_TO, 'Institution', 'institution_id'),
            'site' => array(self::BELONGS_TO, 'Site', 'site_id'),
            'last_modified_user' => array(self::BELONGS_TO, 'User', 'last_modified_user_id'),
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'institution_id' => 'Institution',
            'recipient_type' => 'Recipient Type',
            'title' => 'Title',
            'subject' => 'Subject',
            'body' => 'Body',
            'last_modified_user_id' => 'Last Modified User',
            'last_modified_date' => 'Last Modified Date',
            'created_user_id' => 'Created User',
            'created_date' => 'Created Date',
        );
    }

    /**
     * @param $type
     * @return EmailTemplate|null
     */
    public static function getTemplateForRecipientType($type)
    {
        return self::model()->findByAttributes(array('recipient_type' => $type));
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * Typical usecase:
     * - Initialize the model fields with values from filter form.
     * - Execute this method to get CActiveDataProvider instance which will filter
     * models according to data in model fields.
     * - Pass data provider to CGridView, CListView or any similar widget.
     *
     * @return CActiveDataProvider the data provider that can return the models
     * based on the search/filter conditions.
     */
    public function search()
    {
        // @todo Please modify the following code to remove attributes that should not be searched.

        $criteria=new CDbCriteria;

        $criteria->compare('id', $this->id);
        $criteria->compare('institution_id', $this->institution_id, true);
        $criteria->compare('recipient_type', $this->recipient_type, true);
        $criteria->compare('title', $this->title, true);
        $criteria->compare('subject', $this->subject, true);
        $criteria->compare('body', $this->body, true);
        $criteria->compare('last_modified_user_id', $this->last_modified_user_id, true);
        $criteria->compare('last_modified_date', $this->last_modified_date, true);
        $criteria->compare('created_user_id', $this->created_user_id, true);
        $criteria->compare('created_date', $this->created_date, true);

        return new CActiveDataProvider($this, array(
            'criteria'=>$criteria,
        ));
    }

    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return EmailTemplate the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

}
