<?php
/**
 * Patient migrator with embedded contact and address data
 */

namespace OE\Migration;

class PatientMigrator implements TableMigrator
{
    private $adapter;
    
    public function __construct()
    {
        $this->adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
    }
    
    public function migrate(array $record): void
    {
        // Load full patient model for relationship data
        $patient = \Patient::model()->with([
            'contact',
            'contact.address',
            'gp',
            'practice',
        ])->findByPk($record['id']);
        
        if (!$patient) {
            throw new \Exception("Patient not found: {$record['id']}");
        }
        
        // Build embedded document
        $doc = [
            '_mysql_id' => $patient->id,
            '_type' => 'patient',
            '_migrated' => date('c'),
            '_source' => 'mariadb',
            'hos_num' => method_exists($patient, 'hasAttribute') && $patient->hasAttribute('hos_num') ? $patient->hos_num : null,
            'nhs_num' => method_exists($patient, 'hasAttribute') && $patient->hasAttribute('nhs_num') ? $patient->nhs_num : null,
            'dob' => method_exists($patient, 'hasAttribute') && $patient->hasAttribute('dob') ? $patient->dob : null,
            'date_of_death' => method_exists($patient, 'hasAttribute') && $patient->hasAttribute('date_of_death') ? $patient->date_of_death : null,
            'gender' => method_exists($patient, 'hasAttribute') && $patient->hasAttribute('gender') ? $patient->gender : null,
            'ethnic_group_id' => method_exists($patient, 'hasAttribute') && $patient->hasAttribute('ethnic_group_id') ? $patient->ethnic_group_id : null,
            'is_deceased' => (bool)(method_exists($patient, 'hasAttribute') && $patient->hasAttribute('is_deceased') ? $patient->is_deceased : false),
            'deleted' => (bool)(method_exists($patient, 'hasAttribute') && $patient->hasAttribute('deleted') ? $patient->deleted : false),
            'created_date' => method_exists($patient, 'hasAttribute') && $patient->hasAttribute('created_date') ? $patient->created_date : null,
            'last_modified_date' => method_exists($patient, 'hasAttribute') && $patient->hasAttribute('last_modified_date') ? $patient->last_modified_date : null,
        ];
        
        // Embed contact
        if ($patient->contact) {
            $doc['contact'] = [
                'id' => $patient->contact->id,
                'first_name' => $patient->contact->first_name,
                'last_name' => $patient->contact->last_name,
                'title' => $patient->contact->title,
                'primary_phone' => $patient->contact->primary_phone,
                'email' => $patient->contact->email,
            ];
            
            // Embed addresses
            if ($patient->contact->address) {
                $doc['addresses'] = [];
                $addresses = is_array($patient->contact->address) 
                    ? $patient->contact->address 
                    : [$patient->contact->address];
                    
                foreach ($addresses as $addr) {
                    $doc['addresses'][] = [
                        'id' => $addr->id,
                        'address1' => $addr->address1,
                        'address2' => $addr->address2,
                        'city' => $addr->city,
                        'county' => $addr->county,
                        'postcode' => $addr->postcode,
                        'country_id' => $addr->country_id,
                        'address_type_id' => $addr->address_type_id,
                    ];
                }
            }
        }
        
        // Embed GP reference
        if ($patient->gp) {
            $doc['gp'] = [
                'id' => $patient->gp->id,
                'nat_id' => $patient->gp->nat_id,
            ];
        }
        
        // Embed practice reference
        if ($patient->practice) {
            $doc['practice'] = [
                'id' => $patient->practice->id,
                'code' => $patient->practice->code,
            ];
        }
        
        $this->adapter->upsert('patient', $patient->id, $doc);
    }
    
    public function getCollection(): string
    {
        return 'patient';
    }
    
    public function getTable(): string
    {
        return 'patient';
    }
}
