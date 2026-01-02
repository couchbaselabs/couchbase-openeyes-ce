<?php
/**
 * Operation booking document model for Couchbase
 */

namespace OEModule\OphTrOperationbooking\models;

class OperationDocument extends \CouchbaseActiveRecord
{
    public function documentType()
    {
        return 'operation';
    }
    
    public function scope()
    {
        return 'booking';
    }
    
    public function collectionName()
    {
        return 'operation';
    }
    
    /**
     * Create from operation element and event
     * @param \Element_OphTrOperationbooking_Operation $element
     * @return self
     */
    public static function createFromElement(\Element_OphTrOperationbooking_Operation $element)
    {
        $doc = new self();
        $doc->_pk = $element->event_id;
        
        $event = $element->event;
        $booking = $element->booking;
        
        $doc->_attributes = [
            'event_id' => (string) $element->event_id,
            'episode_id' => (string) $event->episode_id,
            'patient_id' => (string) $event->episode->patient_id,
            
            // Operation details
            'eye_id' => $element->eye_id,
            'eye_name' => $element->eye ? $element->eye->name : null,
            'consultant_required' => (bool) $element->consultant_required,
            'senior_fellow_to_do' => (bool) $element->senior_fellow_to_do,
            'anaesthetic_type_id' => $element->anaesthetic_type_id,
            'overnight_stay' => $element->overnight_stay_required_id ? true : false,
            'priority_id' => $element->priority_id,
            'priority_name' => $element->priority ? $element->priority->name : null,
            'decision_date' => $element->decision_date,
            'comments' => $element->comments,
            'comments_rtt' => $element->comments_rtt,
            
            // Procedures (embedded)
            'procedures' => array_map(function($proc) {
                return [
                    'procedure_id' => (string) $proc->id,
                    'term' => $proc->term,
                    'short_format' => $proc->short_format,
                    'snomed_code' => $proc->snomed_code,
                    'snomed_term' => $proc->snomed_term ?? null,
                ];
            }, $element->procedures ?? []),
            
            // Booking details (if booked)
            'booking' => $booking ? [
                'booking_id' => (string) $booking->id,
                'session_id' => (string) $booking->session_id,
                'session_date' => $booking->session->date,
                'session_start_time' => $booking->session->start_time,
                'session_end_time' => $booking->session->end_time,
                'theatre_id' => (string) $booking->session->theatre_id,
                'theatre_name' => $booking->session->theatre->name,
                'site_id' => (string) $booking->session->theatre->site_id,
                'admission_time' => $booking->admission_time,
                'confirmed' => (bool) $booking->confirmed,
                'cancellation_reason' => $booking->cancellation_reason ? $booking->cancellation_reason->text : null,
            ] : null,
            
            // Status
            'status_id' => $element->status_id,
            'status_name' => $element->status ? $element->status->name : null,
            
            'created_date' => $event->created_date,
            'last_modified_date' => $event->last_modified_date,
        ];
        
        return $doc;
    }
    
    /**
     * Find operations by patient
     * @param string $patientId
     * @return array
     */
    public static function findByPatientId($patientId)
    {
        return self::findAllByAttributes(
            ['patient_id' => $patientId],
            ['order' => 'decision_date DESC']
        );
    }
    
    /**
     * Find pending (unbooked) operations
     * @param string $siteId Optional site filter
     * @param int $limit
     * @return array
     */
    public static function findPending($siteId = null, $limit = 100)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META().id as _key, * 
                  FROM `{$bucket}`.`booking`.`operation` 
                  WHERE booking IS NULL
                  AND status_name != 'Cancelled'";
        $params = [];
        
        $query .= " ORDER BY decision_date ASC LIMIT \$limit";
        $params['limit'] = $limit;
        
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
            $doc->_attributes = isset($row['operation']) ? (array) $row['operation'] : (array) $row;
            $doc->_pk = $doc->_attributes['event_id'];
            $doc->_isNewRecord = false;
            $documents[] = $doc;
        }
        return $documents;
    }
}
