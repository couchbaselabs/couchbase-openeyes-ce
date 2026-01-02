<?php
/**
 * Couchbase document model for ElementType
 */

class ElementTypeDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'element_type';
    protected $scope = 'reference';
    protected $collection = 'element_type';

    public function documentType()
    {
        return $this->documentType;
    }

    public function scope()
    {
        return $this->scope;
    }

    public function collectionName()
    {
        return $this->collection;
    }

    /**
     * Create document from ElementType model
     * @param ElementType $elementType
     * @return array
     */
    public static function createFromModel($elementType)
    {
        $doc = [
            '_type' => 'element_type',
            'id' => (int)$elementType->id,
            'name' => $elementType->name,
            'class_name' => $elementType->class_name,
            'event_type_id' => (int)$elementType->event_type_id,
            'display_order' => (int)$elementType->display_order,
            'required' => (bool)$elementType->required,
            'default' => (bool)$elementType->default,
            'parent_element_type_id' => $elementType->parent_element_type_id ? (int)$elementType->parent_element_type_id : null,
            'element_group_id' => $elementType->element_group_id ? (int)$elementType->element_group_id : null,
            'created_date' => $elementType->created_date,
            'last_modified_date' => $elementType->last_modified_date,
        ];

        // Embed element group
        if ($elementType->element_group_id && $elementType->elementGroups) {
            $doc['element_group'] = [
                'id' => (int)$elementType->elementGroups->id,
                'name' => $elementType->elementGroups->name,
                'display_order' => (int)$elementType->elementGroups->display_order,
            ];
        }

        // Embed event type reference
        if ($elementType->eventType) {
            $doc['event_type'] = [
                'id' => (int)$elementType->eventType->id,
                'name' => $elementType->eventType->name,
                'class_name' => $elementType->eventType->class_name,
            ];
        }

        return $doc;
    }

    /**
     * Find by event type ID
     * @param int $eventTypeId
     * @return array
     */
    public static function findByEventType($eventTypeId)
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`element_type` e 
                  WHERE e.event_type_id = \$eventTypeId 
                  ORDER BY e.display_order";
        
        return self::executeQuery($query, ['eventTypeId' => $eventTypeId]);
    }

    /**
     * Find by class name
     * @param string $className
     * @return array|null
     */
    public static function findByClassName($className)
    {
        $query = "SELECT META().id AS _id, e.* 
                  FROM `openeyes`.`reference`.`element_type` e 
                  WHERE e.class_name = \$className";
        
        $result = self::executeQuery($query, ['className' => $className]);
        return !empty($result) ? $result[0] : null;
    }
}
