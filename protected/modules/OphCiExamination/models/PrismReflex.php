<?php

namespace OEModule\OphCiExamination\models;

use OEModule\OphCiExamination\widgets\PrismReflex as PrismReflexWidget;

/**
 * Class PrismReflex
 *
 * @package OEModule\OphCiExamination\models
 * @property int $id
 * @property PrismReflex_Entry[] $entries
 * @property string $comments
 */
class PrismReflex extends \BaseEventTypeElement
{
    use \OE\Models\Traits\CouchbaseModelBridge;
    use traits\CustomOrdering;
    use traits\HasChildrenWithEventScopeValidation;

    public $widgetClass = PrismReflexWidget::class;
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
        return 'et_ophciexamination_prismreflex';
    }

    public function rules()
    {
        return [
            ['event_id, entries, comments', 'safe'],
            ['entries', 'required'],
            ['comments', 'length', 'min' => 5]
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
            'entries' => [self::HAS_MANY, PrismReflex_Entry::class, 'element_id']
        ];
    }

    public function attributeLabels()
    {
        return [
            'comments' => 'Comments',
        ];
    }

    public function getLetter_string()
    {
        $comments_string = strlen(trim($this->comments)) ? " " . \OELinebreakReplacer::plainTextReplace($this->comments) : "";

        return "Prism Reflex Test: " . ( count($this->entries) > 0 ? implode(", ", $this->entries) : "No entries" ) . $comments_string;
    }

    /**
     * Returns the Couchbase scope name for this model.
     * @return string
     */
    public function couchbaseScope(): string
    {
        return 'clinical';
    }

    /**
     * Returns the Couchbase collection name for this model.
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
}
