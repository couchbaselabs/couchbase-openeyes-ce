<?php
/**
 * Couchbase document model for simple lookup tables
 * Used for Eye, Gender, EthnicGroup and other simple reference tables
 */

class SimpleLookupDocument extends CouchbaseActiveRecord
{
    protected $documentType;
    protected $scope = 'reference';
    protected $collection;

    public function __construct($collection, $documentType = null)
    {
        parent::__construct();
        $this->collection = $collection;
        $this->documentType = $documentType ?? $collection;
    }

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
     * Create document from any simple lookup model
     * @param CActiveRecord $model
     * @return array
     */
    public static function createFromModel($model)
    {
        $tableName = $model->tableName();
        $attributes = $model->attributes;
        $attributes['_type'] = $tableName;
        
        // Ensure id is integer
        if (isset($attributes['id'])) {
            $attributes['id'] = (int)$attributes['id'];
        }
        
        return $attributes;
    }

    /**
     * Find all records
     * @param string $collection
     * @return array
     */
    public static function findAll($collection)
    {
        $query = "SELECT META().id AS _id, t.* 
                  FROM `openeyes`.`reference`.`{$collection}` t 
                  ORDER BY t.display_order, t.name";
        
        return self::executeQuery($query);
    }
}
