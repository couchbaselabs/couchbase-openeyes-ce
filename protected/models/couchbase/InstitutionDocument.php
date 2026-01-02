<?php
/**
 * Couchbase document model for Institution
 */

class InstitutionDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'institution';
    protected $scope = 'core';
    protected $collection = 'institution';

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
     * Create document from Institution model
     * @param Institution $institution
     * @return array
     */
    public static function createFromModel($institution)
    {
        $doc = [
            '_type' => 'institution',
            'id' => (int)$institution->id,
            'name' => $institution->name,
            'short_name' => $institution->short_name,
            'remote_id' => $institution->remote_id,
            'pas_key' => $institution->pas_key,
            'contact_id' => $institution->contact_id ? (int)$institution->contact_id : null,
            'created_date' => $institution->created_date,
            'last_modified_date' => $institution->last_modified_date,
        ];

        // Embed contact
        if ($institution->contact) {
            $doc['contact'] = [
                'id' => (int)$institution->contact->id,
                'primary_phone' => $institution->contact->primary_phone,
                'address' => $institution->contact->address ? [
                    'address1' => $institution->contact->address->address1,
                    'address2' => $institution->contact->address->address2,
                    'city' => $institution->contact->address->city,
                    'postcode' => $institution->contact->address->postcode,
                ] : null,
            ];
        }

        // Count sites
        $doc['site_count'] = count($institution->sites ?? []);

        return $doc;
    }

    /**
     * Find all active institutions
     * @return array
     */
    public static function findAllActive()
    {
        $query = "SELECT META().id AS _id, i.* 
                  FROM `openeyes`.`core`.`institution` i 
                  ORDER BY i.name";
        
        return self::executeQuery($query);
    }

    /**
     * Find by remote ID
     * @param string $remoteId
     * @return array|null
     */
    public static function findByRemoteId($remoteId)
    {
        $query = "SELECT META().id AS _id, i.* 
                  FROM `openeyes`.`core`.`institution` i 
                  WHERE i.remote_id = \$remoteId";
        
        $result = self::executeQuery($query, ['remoteId' => $remoteId]);
        return !empty($result) ? $result[0] : null;
    }
}
