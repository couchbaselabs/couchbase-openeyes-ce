<?php

namespace OEModule\OphCiExamination\models;

use OEModule\OphCiExamination\widgets\StereoAcuity as StereoAcuityWidget;

class StereoAcuity extends \BaseEventTypeElement
{
    use \OE\Models\Traits\CouchbaseModelBridge;
    use traits\CustomOrdering;
    use traits\HasChildrenWithEventScopeValidation;

    protected $widgetClass = StereoAcuityWidget::class;
    protected $auto_update_relations = true;
    protected $auto_validate_relations = true;

    protected const EVENT_SCOPED_CHILDREN = [
        'entries' => 'with_head_posture'
    ];

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'et_ophciexamination_stereoacuity';
    }

    public function rules()
    {
        return [
            ['event_id, entries', 'safe'],
            ['entries', 'required']
        ];
    }

    /**
     * @return array
     */
    public function relations()
    {
        return [
            'event' => [self::BELONGS_TO, 'Event', 'event_id'],
            'user' => [self::BELONGS_TO, 'User', 'created_user_id'],
            'usermodified' => [self::BELONGS_TO, 'User', 'last_modified_user_id'],
            'entries' => [self::HAS_MANY, StereoAcuity_Entry::class, 'element_id']
        ];
    }

    public function getLetter_string()
    {
        return "Stereo Acuity: " . ( count($this->entries) > 0 ? implode(", ", $this->entries): "No entries" );
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
