<?php
/**
 * Examination document model for Couchbase
 * Embeds all element data within a single document per examination event
 */

namespace OEModule\OphCiExamination\models\couchbase;

class ExaminationDocument extends \CouchbaseActiveRecord
{
    /**
     * @inheritdoc
     */
    public function documentType()
    {
        return 'examination';
    }
    
    /**
     * @inheritdoc
     */
    public function scope()
    {
        return 'clinical';
    }
    
    /**
     * @inheritdoc
     */
    public function collectionName()
    {
        return 'examination';
    }
    
    /**
     * Create examination document from Event
     * @param \Event $event
     * @return self
     */
    public static function createFromEvent(\Event $event)
    {
        $doc = new self();
        $doc->_pk = $event->id;
        $doc->_isNewRecord = true;
        
        // Base event metadata
        $doc->_attributes = [
            'event_id' => (string) $event->id,
            'episode_id' => (string) $event->episode_id,
            'patient_id' => (string) $event->episode->patient_id,
            'event_date' => $event->event_date,
            'event_type_id' => (string) $event->event_type_id,
            'institution_id' => (string) $event->institution_id,
            'site_id' => (string) $event->site_id,
            'firm_id' => $event->firm_id ? (string) $event->firm_id : null,
            'created_date' => $event->created_date,
            'created_user_id' => (string) $event->created_user_id,
            'last_modified_date' => $event->last_modified_date,
            'last_modified_user_id' => (string) $event->last_modified_user_id,
            'deleted' => (bool) $event->deleted,
            
            // Element storage
            'elements' => [],
            'element_refs' => [], // For large elements stored separately
        ];
        
        // Load and embed all elements
        $elements = $event->getElements();
        foreach ($elements as $element) {
            $elementName = $element->getElementTypeName();
            
            // Check if element should be embedded or referenced
            if (method_exists($element, 'shouldBeEmbedded') && !$element->shouldBeEmbedded()) {
                // Reference large element (stored separately)
                $doc->_attributes['element_refs'][] = [
                    'element_type' => $elementName,
                    'element_id' => (string) $element->id,
                ];
            } else {
                // Embed element data
                if (method_exists($element, 'toCouchbaseEmbedded')) {
                    $doc->_attributes['elements'][$elementName] = $element->toCouchbaseEmbedded();
                } else {
                    // Fallback for elements without trait
                    $doc->_attributes['elements'][$elementName] = $element->attributes;
                }
            }
        }
        
        return $doc;
    }
    
    /**
     * Get visual acuity element data
     * @return array|null
     */
    public function getVisualAcuity()
    {
        return $this->_attributes['elements']['VisualAcuity'] ?? null;
    }
    
    /**
     * Get intraocular pressure element data
     * @return array|null
     */
    public function getIntraocularPressure()
    {
        return $this->_attributes['elements']['IntraocularPressure'] ?? null;
    }
    
    /**
     * Get refraction element data
     * @return array|null
     */
    public function getRefraction()
    {
        return $this->_attributes['elements']['Refraction'] ?? null;
    }
    
    /**
     * Get diagnosis element data
     * @return array|null
     */
    public function getDiagnoses()
    {
        return $this->_attributes['elements']['Diagnoses'] ?? null;
    }
    
    /**
     * Get all element names present in this examination
     * @return array
     */
    public function getElementNames()
    {
        return array_keys($this->_attributes['elements'] ?? []);
    }
    
    /**
     * Check if element exists in this examination
     * @param string $elementName
     * @return bool
     */
    public function hasElement($elementName)
    {
        return isset($this->_attributes['elements'][$elementName]);
    }
    
    /**
     * Find examinations by patient ID
     * @param string $patientId
     * @param int $limit
     * @return array
     */
    public static function findByPatientId($patientId, $limit = 50)
    {
        return self::findAllByAttributes(['patient_id' => $patientId], [
            'order' => 'event_date DESC',
            'limit' => $limit,
        ]);
    }
    
    /**
     * Find recent examinations with specific element
     * @param string $patientId
     * @param string $elementName
     * @param int $limit
     * @return array
     */
    public static function findByPatientWithElement($patientId, $elementName, $limit = 10)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META().id as _key, * 
                  FROM `{$bucket}`.`clinical`.`examination` 
                  WHERE patient_id = \$patientId 
                  AND `elements`.`{$elementName}` IS NOT NULL
                  ORDER BY event_date DESC 
                  LIMIT \$limit";
        
        $params = [
            'patientId' => $patientId,
            'limit' => $limit,
        ];
        
        $results = $conn->query($query, $params);
        $documents = [];
        
        foreach ($results as $row) {
            $doc = new self();
            $doc->_attributes = (array) $row['examination'];
            $doc->_pk = $doc->_attributes['event_id'];
            $doc->_isNewRecord = false;
            $documents[] = $doc;
        }
        
        return $documents;
    }
}
