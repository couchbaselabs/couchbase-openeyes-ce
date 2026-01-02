<?php
/**
 * Couchbase document model for Contact
 * Embeds address and label for denormalized queries
 */

class ContactDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'contact';
    protected $scope = 'core';
    protected $collection = 'contact';

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
     * Create document from Contact model
     * @param Contact $contact
     * @return array
     */
    public static function createFromModel($contact)
    {
        $doc = [
            '_type' => 'contact',
            'id' => (int)$contact->id,
            'nick_name' => $contact->nick_name,
            'title' => $contact->title,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'maiden_name' => $contact->maiden_name,
            'qualifications' => $contact->qualifications,
            'contact_label_id' => $contact->contact_label_id ? (int)$contact->contact_label_id : null,
            'primary_phone' => $contact->primary_phone,
            'mobile_phone' => $contact->mobile_phone,
            'fax' => $contact->fax,
            'email' => $contact->email,
            'created_date' => $contact->created_date,
            'last_modified_date' => $contact->last_modified_date,
            // Computed fields for search
            'full_name' => trim($contact->first_name . ' ' . $contact->last_name),
            'full_name_lower' => strtolower(trim($contact->first_name . ' ' . $contact->last_name)),
        ];
        
        // Embed contact label
        if ($contact->label) {
            $doc['label'] = [
                'id' => (int)$contact->label->id,
                'name' => $contact->label->name,
            ];
        }
        
        // Embed addresses (all addresses, not just primary)
        $doc['addresses'] = self::embedAddresses($contact);
        
        // Primary address for convenience
        if ($contact->address) {
            $doc['primary_address'] = self::formatAddress($contact->address);
        }
        
        return $doc;
    }

    /**
     * Embed all addresses for contact
     * @param Contact $contact
     * @return array
     */
    protected static function embedAddresses($contact)
    {
        $addresses = [];
        foreach ($contact->addresses as $address) {
            $addresses[] = self::formatAddress($address);
        }
        return $addresses;
    }

    /**
     * Format address for embedding
     * @param Address $address
     * @return array
     */
    protected static function formatAddress($address)
    {
        return [
            'id' => (int)$address->id,
            'address1' => $address->address1,
            'address2' => $address->address2,
            'city' => $address->city,
            'postcode' => $address->postcode,
            'county' => $address->county,
            'country_id' => $address->country_id,
            'country_name' => $address->country ? $address->country->name : null,
            'address_type_id' => $address->address_type_id,
            'date_start' => $address->date_start,
            'date_end' => $address->date_end,
        ];
    }

    /**
     * Search contacts by name
     * @param string $term
     * @param int $limit
     * @return array
     */
    public static function search($term, $limit = 50)
    {
        $query = "SELECT META().id AS _id, c.* 
                  FROM `openeyes`.`core`.`contact` c 
                  WHERE c.full_name_lower LIKE \$term 
                  ORDER BY c.last_name, c.first_name 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }

    /**
     * Find by email
     * @param string $email
     * @return array|null
     */
    public static function findByEmail($email)
    {
        $query = "SELECT META().id AS _id, c.* 
                  FROM `openeyes`.`core`.`contact` c 
                  WHERE c.email = \$email";
        
        $result = self::executeQuery($query, ['email' => $email]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find by label type
     * @param int $labelId
     * @param int $limit
     * @return array
     */
    public static function findByLabel($labelId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, c.* 
                  FROM `openeyes`.`core`.`contact` c 
                  WHERE c.contact_label_id = \$labelId 
                  ORDER BY c.last_name, c.first_name 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'labelId' => $labelId,
            'limit' => $limit
        ]);
    }
}
