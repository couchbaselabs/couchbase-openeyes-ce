<?php
/**
 * Couchbase document model for Specialty
 */

class SpecialtyDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'specialty';
    protected $scope = 'reference';
    protected $collection = 'specialty';

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
     * Create document from Specialty model
     * @param Specialty $specialty
     * @return array
     */
    public static function createFromModel($specialty)
    {
        return [
            '_type' => 'specialty',
            'id' => (int)$specialty->id,
            'name' => $specialty->name,
            'code' => $specialty->code,
            'created_date' => $specialty->created_date,
            'last_modified_date' => $specialty->last_modified_date,
        ];
    }

    /**
     * Find all specialties
     * @return array
     */
    public static function findAll()
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`reference`.`specialty` s 
                  ORDER BY s.name";
        
        return self::executeQuery($query);
    }
}
