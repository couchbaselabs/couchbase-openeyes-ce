<?php
/**
 * Couchbase document model for Address
 */

class AddressDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'address';
    protected $scope = 'core';
    protected $collection = 'address';

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
     * Create document from Address model
     * @param Address $address
     * @return array
     */
    public static function createFromModel($address)
    {
        $doc = [
            '_type' => 'address',
            'id' => (int)$address->id,
            'contact_id' => (int)$address->contact_id,
            'address1' => $address->address1,
            'address2' => $address->address2,
            'city' => $address->city,
            'postcode' => $address->postcode,
            'county' => $address->county,
            'country_id' => $address->country_id,
            'address_type_id' => $address->address_type_id,
            'date_start' => $address->date_start,
            'date_end' => $address->date_end,
            'created_date' => $address->created_date,
            'last_modified_date' => $address->last_modified_date,
        ];

        // Embed country
        if ($address->country) {
            $doc['country'] = [
                'id' => (int)$address->country->id,
                'name' => $address->country->name,
                'code' => $address->country->code,
            ];
        }

        // Embed address type
        if ($address->addressType) {
            $doc['address_type'] = [
                'id' => (int)$address->addressType->id,
                'name' => $address->addressType->name,
            ];
        }

        return $doc;
    }

    /**
     * Find by postcode
     * @param string $postcode
     * @return array
     */
    public static function findByPostcode($postcode)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`core`.`address` a 
                  WHERE a.postcode = \$postcode";
        
        return self::executeQuery($query, ['postcode' => $postcode]);
    }

    /**
     * Find by contact ID
     * @param int $contactId
     * @return array
     */
    public static function findByContact($contactId)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`core`.`address` a 
                  WHERE a.contact_id = \$contactId";
        
        return self::executeQuery($query, ['contactId' => $contactId]);
    }
}
