<?php
/**
 * Transforms patient MySQL records to Couchbase documents
 */

namespace OE\Couchbase\Transformers;

class PatientTransformer extends DocumentTransformer
{
    public function getTableName()
    {
        return 'patient';
    }
    
    public function getDocumentType()
    {
        return 'patient';
    }
    
    public function getScope()
    {
        return 'core';
    }
    
    public function getCollection()
    {
        return 'patient';
    }
    
    /**
     * Apply patient-specific transformations
     */
    protected function customTransform($transformed, $originalRow)
    {
        // Embed contact information
        if (!empty($originalRow['contact_id'])) {
            $transformed = $this->embedContact($transformed, $originalRow['contact_id']);
        }
        
        // Embed patient identifiers
        $transformed = $this->embedIdentifiers($transformed, $originalRow['id']);
        
        // Compute is_deceased flag
        $transformed['is_deceased'] = !empty($transformed['date_of_death']);
        
        // Remove the contact_id since we're embedding
        unset($transformed['contact_id']);
        
        return $transformed;
    }
    
    /**
     * Embed contact with addresses
     */
    private function embedContact($document, $contactId)
    {
        $contactSql = "SELECT * FROM contact WHERE id = :id";
        $contact = $this->db->createCommand($contactSql)->queryRow(true, [':id' => $contactId]);
        
        if ($contact) {
            $contactTypes = $this->getColumnTypesForTable('contact');
            $document['contact'] = TypeTransformer::transformRow($contact, $contactTypes);
            
            // Remove redundant id
            unset($document['contact']['id']);
            
            // Embed addresses
            $addressSql = "SELECT a.*, at.name as address_type, c.name as country 
                          FROM address a 
                          LEFT JOIN address_type at ON a.address_type_id = at.id
                          LEFT JOIN country c ON a.country_id = c.id
                          WHERE a.contact_id = :contact_id
                          ORDER BY a.date_start DESC";
            $addresses = $this->db->createCommand($addressSql)->queryAll(true, [':contact_id' => $contactId]);
            
            $addressTypes = $this->getColumnTypesForTable('address');
            $document['addresses'] = [];
            
            foreach ($addresses as $i => $addr) {
                $transformedAddr = TypeTransformer::transformRow($addr, $addressTypes);
                $transformedAddr['is_primary'] = ($i === 0);
                unset($transformedAddr['id'], $transformedAddr['contact_id']);
                $document['addresses'][] = $transformedAddr;
            }
        } else {
            $document['contact'] = null;
            $document['addresses'] = [];
        }
        
        return $document;
    }
    
    /**
     * Embed patient identifiers
     */
    private function embedIdentifiers($document, $patientId)
    {
        $sql = "SELECT pi.*, pit.short_title as type
                FROM patient_identifier pi
                JOIN patient_identifier_type pit ON pi.patient_identifier_type_id = pit.id
                WHERE pi.patient_id = :patient_id
                AND pi.deleted = 0";
        $identifiers = $this->db->createCommand($sql)->queryAll(true, [':patient_id' => $patientId]);
        
        $document['identifiers'] = [];
        foreach ($identifiers as $ident) {
            $document['identifiers'][] = [
                'type' => $ident['type'],
                'type_id' => (int)$ident['patient_identifier_type_id'],
                'value' => $ident['value'],
                'institution_id' => $ident['institution_id'] ? (int)$ident['institution_id'] : null,
            ];
        }
        
        return $document;
    }
    
    /**
     * Transform a patient with all related data for migration
     * @param int $patientId Patient ID
     * @return array|null Complete patient document or null if not found
     */
    public function transformById($patientId)
    {
        $sql = "SELECT * FROM patient WHERE id = :id";
        $row = $this->db->createCommand($sql)->queryRow(true, [':id' => $patientId]);
        
        if (!$row) {
            return null;
        }
        
        return $this->transform($row);
    }
}
