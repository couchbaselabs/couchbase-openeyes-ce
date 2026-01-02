<?php
/**
 * Correspondence letter document for Couchbase
 */

namespace OEModule\OphCoCorrespondence\models;

class LetterDocument extends \CouchbaseActiveRecord
{
    public function documentType()
    {
        return 'letter';
    }
    
    public function scope()
    {
        return 'correspondence';
    }
    
    public function collectionName()
    {
        return 'letter';
    }
    
    /**
     * Create from letter element
     * @param \ElementLetter $letter
     * @return self
     */
    public static function createFromElement($letter)
    {
        $doc = new self();
        $doc->_pk = $letter->event_id;
        
        $event = $letter->event;
        
        $doc->_attributes = [
            'event_id' => (string) $letter->event_id,
            'episode_id' => (string) $event->episode_id,
            'patient_id' => (string) $event->episode->patient_id,
            
            // Letter content
            'date' => $letter->date,
            'letter_type_id' => $letter->letter_type_id,
            'letter_type' => $letter->letterType ? $letter->letterType->name : null,
            'use_nickname' => (bool) ($letter->use_nickname ?? false),
            
            // Addresses
            'address' => $letter->address,
            're' => $letter->re,
            'introduction' => $letter->introduction,
            'body' => $letter->body,
            'footer' => $letter->footer,
            'cc' => $letter->cc ?? null,
            
            // Recipients (embedded)
            'recipients' => [],
            
            // Enclosures (embedded)
            'enclosures' => array_map(function($enc) {
                return ['content' => $enc->content];
            }, $letter->enclosures ?? []),
            
            // Status flags
            'draft' => (bool) ($letter->draft ?? false),
            'print' => (bool) ($letter->print ?? false),
            'locked' => (bool) ($letter->locked ?? false),
            
            'created_date' => $event->created_date,
            'last_modified_date' => $event->last_modified_date,
        ];
        
        // Add recipients with contact details
        if ($letter->recipients) {
            foreach ($letter->recipients as $recipient) {
                $doc->_attributes['recipients'][] = [
                    'type' => $recipient->recipient_type ?? null,
                    'contact_id' => $recipient->contact_id ? (string) $recipient->contact_id : null,
                    'contact_name' => $recipient->contact_name ?? null,
                    'address' => $recipient->address ?? null,
                ];
            }
        }
        
        return $doc;
    }
    
    /**
     * Find letters by patient
     * @param string $patientId
     * @param bool $includeDrafts
     * @return array
     */
    public static function findByPatientId($patientId, $includeDrafts = false)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META().id as _key, * 
                  FROM `{$bucket}`.`correspondence`.`letter` 
                  WHERE patient_id = \$patientId";
        $params = ['patientId' => $patientId];
        
        if (!$includeDrafts) {
            $query .= " AND draft = false";
        }
        
        $query .= " ORDER BY date DESC";
        
        $results = $conn->query($query, $params);
        return self::resultsToDocuments($results);
    }
    
    /**
     * Search letters by content
     * @param string $patientId
     * @param string $searchTerm
     * @return array
     */
    public static function searchContent($patientId, $searchTerm)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        // Full-text search would require FTS index
        // For now, use LIKE on body field
        $query = "SELECT META().id as _key, * 
                  FROM `{$bucket}`.`correspondence`.`letter` 
                  WHERE patient_id = \$patientId
                  AND (body LIKE \$search OR introduction LIKE \$search)
                  ORDER BY date DESC
                  LIMIT 50";
        
        $params = [
            'patientId' => $patientId,
            'search' => "%{$searchTerm}%",
        ];
        
        $results = $conn->query($query, $params);
        return self::resultsToDocuments($results);
    }
    
    /**
     * Convert query results to document objects
     * @param mixed $results
     * @return array
     */
    protected static function resultsToDocuments($results)
    {
        $documents = [];
        foreach ($results as $row) {
            $doc = new self();
            $doc->_attributes = isset($row['letter']) ? (array) $row['letter'] : (array) $row;
            $doc->_pk = $doc->_attributes['event_id'];
            $doc->_isNewRecord = false;
            $documents[] = $doc;
        }
        return $documents;
    }
}
