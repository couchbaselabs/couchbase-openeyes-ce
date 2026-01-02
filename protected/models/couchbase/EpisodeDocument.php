<?php
/**
 * Episode document model for direct Couchbase operations
 */

class EpisodeDocument extends CouchbaseActiveRecord
{
    public function documentType()
    {
        return 'episode';
    }
    
    public function scope()
    {
        return 'core';
    }
    
    public function collectionName()
    {
        return 'episode';
    }
    
    public function rules()
    {
        return [
            [['patient_id', 'start_date'], 'required'],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'patient_id' => 'Patient',
            'firm_id' => 'Firm',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
        ];
    }
    
    /**
     * Find episodes by patient ID
     * @param int $patientId Patient ID
     * @return array Array of EpisodeDocument
     */
    public static function findByPatientId($patientId)
    {
        return static::findAllByAttributes(
            ['patient_id' => (int)$patientId],
            ['order' => 'start_date DESC']
        );
    }
    
    /**
     * Find episodes by firm ID
     * @param int $firmId Firm ID
     * @param int $limit Maximum results
     * @return array Array of EpisodeDocument
     */
    public static function findByFirmId($firmId, $limit = 100)
    {
        return static::findAllByAttributes(
            ['firm_id' => (int)$firmId],
            ['order' => 'start_date DESC', 'limit' => $limit]
        );
    }
    
    /**
     * Get events for this episode
     * @return array Array of EventDocument
     */
    public function getEvents()
    {
        return EventDocument::findAllByAttributes(
            ['episode_id' => $this->getPrimaryKey()],
            ['order' => 'event_date DESC']
        );
    }
    
    /**
     * Get the patient document
     * @return PatientDocument|null
     */
    public function getPatient()
    {
        return PatientDocument::findByPk($this->patient_id);
    }
    
    /**
     * Check if episode is open
     * @return bool
     */
    public function isOpen()
    {
        return empty($this->end_date);
    }
    
    /**
     * Get corresponding MariaDB Episode model
     * @return Episode|null
     */
    public function getMariaDbModel()
    {
        $pk = $this->getPrimaryKey();
        return $pk ? Episode::model()->findByPk($pk) : null;
    }
}
