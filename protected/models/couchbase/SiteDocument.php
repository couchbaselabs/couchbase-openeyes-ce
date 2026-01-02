<?php
/**
 * Couchbase document model for Site
 */

class SiteDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'site';
    protected $scope = 'core';
    protected $collection = 'site';

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
     * Create document from Site model
     * @param Site $site
     * @return array
     */
    public static function createFromModel($site)
    {
        $doc = [
            '_type' => 'site',
            'id' => (int)$site->id,
            'name' => $site->name,
            'short_name' => $site->short_name,
            'remote_id' => $site->remote_id,
            'institution_id' => (int)$site->institution_id,
            'location' => $site->location,
            'telephone' => $site->telephone,
            'fax' => $site->fax,
            'active' => (bool)$site->active,
            'created_date' => $site->created_date,
            'last_modified_date' => $site->last_modified_date,
        ];
        
        // Embed institution
        if ($site->institution) {
            $doc['institution'] = [
                'id' => (int)$site->institution->id,
                'name' => $site->institution->name,
                'short_name' => $site->institution->short_name,
            ];
        }
        
        // Embed contact with address
        if ($site->contact) {
            $doc['contact'] = self::embedContact($site->contact);
        }
        
        return $doc;
    }

    /**
     * Embed contact data
     * @param Contact $contact
     * @return array
     */
    protected static function embedContact($contact)
    {
        $data = [
            'id' => (int)$contact->id,
            'nick_name' => $contact->nick_name,
            'primary_phone' => $contact->primary_phone,
            'email' => $contact->email,
        ];
        
        if ($contact->address) {
            $data['address'] = [
                'address1' => $contact->address->address1,
                'address2' => $contact->address->address2,
                'city' => $contact->address->city,
                'postcode' => $contact->address->postcode,
                'county' => $contact->address->county,
                'country_id' => $contact->address->country_id,
            ];
        }
        
        return $data;
    }

    /**
     * Find all active sites
     * @return array
     */
    public static function findAllActive()
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`core`.`site` s 
                  WHERE s.active = true 
                  ORDER BY s.name";
        
        return self::executeQuery($query);
    }

    /**
     * Find by institution
     * @param int $institutionId
     * @return array
     */
    public static function findByInstitution($institutionId)
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`core`.`site` s 
                  WHERE s.institution_id = \$institutionId 
                  AND s.active = true 
                  ORDER BY s.name";
        
        return self::executeQuery($query, ['institutionId' => $institutionId]);
    }

    /**
     * Search sites by name
     * @param string $term
     * @param int $limit
     * @return array
     */
    public static function search($term, $limit = 20)
    {
        $query = "SELECT META().id AS _id, s.* 
                  FROM `openeyes`.`core`.`site` s 
                  WHERE LOWER(s.name) LIKE \$term 
                  AND s.active = true 
                  ORDER BY s.name 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }
}
