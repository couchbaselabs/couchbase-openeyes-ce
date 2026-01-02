<?php
/**
 * Couchbase document model for EventType
 */

class EventTypeDocument extends CouchbaseActiveRecord
{
    /**
     * @var string Document type identifier
     */
    protected $documentType = 'event_type';

    /**
     * @var string Couchbase scope
     */
    protected $scope = 'reference';

    /**
     * @var string Couchbase collection
     */
    protected $collection = 'event_type';

    /**
     * Get the document type
     * @return string
     */
    public function documentType()
    {
        return $this->documentType;
    }

    /**
     * Get the Couchbase scope name
     * @return string
     */
    public function scope()
    {
        return $this->scope;
    }

    /**
     * Get the Couchbase collection name
     * @return string
     */
    public function collectionName()
    {
        return $this->collection;
    }

    /**
     * Create document from EventType model
     * @param EventType $eventType
     * @return array
     */
    public static function createFromModel($eventType)
    {
        $doc = [
            '_type' => 'event_type',
            'id' => (int)$eventType->id,
            'name' => $eventType->name,
            'class_name' => $eventType->class_name,
            'event_group_id' => $eventType->event_group_id ? (int)$eventType->event_group_id : null,
            'support_services' => (bool)$eventType->support_services,
            'disabled' => (bool)$eventType->disabled,
            'custom_hint_text' => $eventType->custom_hint_text,
            'hint_position' => $eventType->hint_position,
            'created_date' => $eventType->created_date,
            'last_modified_date' => $eventType->last_modified_date,
        ];

        // Embed event group
        if ($eventType->event_group_id && isset($eventType->eventGroup)) {
            $doc['event_group'] = [
                'id' => (int)$eventType->eventGroup->id,
                'name' => $eventType->eventGroup->name,
                'code' => $eventType->eventGroup->code,
            ];
        }

        // Embed element types for this event
        $doc['element_types'] = self::embedElementTypes($eventType);

        return $doc;
    }

    /**
     * Embed element types for quick access
     * @param EventType $eventType
     * @return array
     */
    protected static function embedElementTypes($eventType)
    {
        $elementTypes = [];
        foreach ($eventType->elementTypes as $et) {
            $elementTypes[] = [
                'id' => (int)$et->id,
                'name' => $et->name,
                'class_name' => $et->class_name,
                'display_order' => (int)$et->display_order,
                'required' => (bool)$et->required,
                'default' => (bool)$et->default,
            ];
        }
        return $elementTypes;
    }

    /**
     * Find by class name
     * @param string $className
     * @return array|null
     */
    public static function findByClassName($className)
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`event_type` e 
                  WHERE e.class_name = \$className";
        
        $result = self::executeQuery($query, ['className' => $className]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find all active event types
     * @return array
     */
    public static function findAllActive()
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`event_type` e 
                  WHERE e.disabled = false 
                  ORDER BY e.name";
        
        return self::executeQuery($query);
    }

    /**
     * Find by event group
     * @param int $groupId
     * @return array
     */
    public static function findByGroup($groupId)
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`event_type` e 
                  WHERE e.event_group_id = \$groupId 
                  ORDER BY e.name";
        
        return self::executeQuery($query, ['groupId' => $groupId]);
    }
}
