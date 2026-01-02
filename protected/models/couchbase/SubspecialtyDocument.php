<?php
/**
 * Couchbase document model for Subspecialty
 */

class SubspecialtyDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'subspecialty';
    protected $scope = 'reference';
    protected $collection = 'subspecialty';

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
     * Create document from Subspecialty model
     * @param Subspecialty $subspecialty
     * @return array
     */
    public static function createFromModel($subspecialty)
    {
        $doc = [
            '_type' => 'subspecialty',
            'id' => (int)$subspecialty->id,
            'name' => $subspecialty->name,
            'short_name' => $subspecialty->short_name,
            'ref_spec' => $subspecialty->ref_spec,
            'specialty_id' => $subspecialty->specialty_id ? (int)$subspecialty->specialty_id : null,
            'created_date' => $subspecialty->created_date,
            'last_modified_date' => $subspecialty->last_modified_date,
        ];

        // Embed specialty
        if ($subspecialty->specialty) {
            $doc['specialty'] = [
                'id' => (int)$subspecialty->specialty->id,
                'name' => $subspecialty->specialty->name,
                'code' => $subspecialty->specialty->code,
            ];
        }

        return $doc;
    }

    /**
     * Find by specialty
     * @param int $specialtyId
     * @return array
     */
    public static function findBySpecialty($specialtyId)
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`reference`.`subspecialty` s 
                  WHERE s.specialty_id = \$specialtyId 
                  ORDER BY s.name";
        
        return self::executeQuery($query, ['specialtyId' => $specialtyId]);
    }

    /**
     * Find all subspecialties
     * @return array
     */
    public static function findAll()
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`reference`.`subspecialty` s 
                  ORDER BY s.name";
        
        return self::executeQuery($query);
    }
}
