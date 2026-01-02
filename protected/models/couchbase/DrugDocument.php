<?php
/**
 * Couchbase document model for Drug
 * Simple document model for legacy drug support
 */

class DrugDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'drug';
    protected $scope = 'reference';
    protected $collection = 'drug';

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
     * Create document from Drug model
     * @param Drug $drug
     * @return array
     */
    public static function createFromModel($drug)
    {
        $doc = [
            '_type' => 'drug',
            'id' => (int)$drug->id,
            'name' => $drug->name,
            'tallman' => $drug->tallman,
            'label' => $drug->label ?? null,
            'aliases' => $drug->aliases ?? null,
            'dose_unit' => $drug->dose_unit ?? null,
            'default_dose' => $drug->default_dose ?? null,
            'active' => isset($drug->active) ? (bool)$drug->active : true,
            'created_date' => $drug->created_date ?? null,
            'last_modified_date' => $drug->last_modified_date ?? null,
            
            // Computed for search
            'name_lower' => strtolower($drug->name ?? ''),
        ];
        
        return $doc;
    }

    /**
     * Search drugs by name
     * @param string $name
     * @param int $limit
     * @return array
     */
    public static function search($name, $limit = 50)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`drug` d 
                  WHERE d.name_lower LIKE \$name 
                  AND (d.active = true OR d.active IS MISSING)
                  ORDER BY d.name 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'name' => '%' . strtolower($name) . '%',
            'limit' => $limit
        ]);
    }

    /**
     * Find by exact name
     * @param string $name
     * @return array|null
     */
    public static function findByName($name)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`drug` d 
                  WHERE LOWER(d.name) = \$name";
        
        $result = self::executeQuery($query, ['name' => strtolower($name)]);
        return !empty($result) ? $result[0] : null;
    }
}
