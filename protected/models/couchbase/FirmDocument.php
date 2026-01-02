<?php
/**
 * Couchbase document model for Firm
 */

class FirmDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'firm';
    protected $scope = 'core';
    protected $collection = 'firm';

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
     * Create document from Firm model
     * @param Firm $firm
     * @return array
     */
    public static function createFromModel($firm)
    {
        $doc = [
            '_type' => 'firm',
            'id' => (int)$firm->id,
            'name' => $firm->name,
            'pas_code' => $firm->pas_code,
            'cost_code' => $firm->cost_code,
            'service_subspecialty_assignment_id' => $firm->service_subspecialty_assignment_id ? (int)$firm->service_subspecialty_assignment_id : null,
            'institution_id' => $firm->institution_id ? (int)$firm->institution_id : null,
            'consultant_id' => $firm->consultant_id ? (int)$firm->consultant_id : null,
            'active' => (bool)$firm->active,
            'runtime_selectable' => (bool)$firm->runtime_selectable,
            'can_own_an_episode' => (bool)$firm->can_own_an_episode,
            'created_date' => $firm->created_date,
            'last_modified_date' => $firm->last_modified_date,
        ];

        // Embed service subspecialty assignment
        if ($firm->serviceSubspecialtyAssignment) {
            $ssa = $firm->serviceSubspecialtyAssignment;
            $doc['service_subspecialty'] = [
                'id' => (int)$ssa->id,
                'service_id' => (int)$ssa->service_id,
                'subspecialty_id' => (int)$ssa->subspecialty_id,
                'subspecialty' => $ssa->subspecialty ? [
                    'id' => (int)$ssa->subspecialty->id,
                    'name' => $ssa->subspecialty->name,
                    'ref_spec' => $ssa->subspecialty->ref_spec,
                ] : null,
            ];
        }

        // Embed consultant user
        if ($firm->consultant) {
            $doc['consultant'] = [
                'id' => (int)$firm->consultant->id,
                'username' => $firm->consultant->username,
                'first_name' => $firm->consultant->first_name,
                'last_name' => $firm->consultant->last_name,
                'title' => $firm->consultant->title,
            ];
        }

        return $doc;
    }

    /**
     * Find by subspecialty
     * @param int $subspecialtyId
     * @return array
     */
    public static function findBySubspecialty($subspecialtyId)
    {
        $query = "SELECT META().id AS _id, f.* 
                  FROM `openeyes`.`core`.`firm` f 
                  WHERE f.service_subspecialty.subspecialty_id = \$subspecialtyId 
                  AND f.active = true 
                  ORDER BY f.name";
        
        return self::executeQuery($query, ['subspecialtyId' => $subspecialtyId]);
    }

    /**
     * Find all active firms
     * @return array
     */
    public static function findAllActive()
    {
        $query = "SELECT META().id AS _id, f.* 
                  FROM `openeyes`.`core`.`firm` f 
                  WHERE f.active = true 
                  ORDER BY f.name";
        
        return self::executeQuery($query);
    }
}
