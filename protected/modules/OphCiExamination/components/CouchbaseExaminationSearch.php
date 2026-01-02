<?php
/**
 * Examination search using Couchbase N1QL
 * 
 * Provides search capabilities for examination events with support for:
 * - Patient/Episode/Event filtering
 * - Date range queries
 * - Element-specific searches (VisualAcuity, IOP, Refraction, Diagnoses)
 * - Array element queries using ANY/SATISFIES
 */

namespace OEModule\OphCiExamination\components;

use OE\Database\N1qlQueryBuilder;

class CouchbaseExaminationSearch
{
    /**
     * Find examinations by patient ID
     * @param int $patientId Patient ID
     * @param int $limit Maximum results
     * @return array
     */
    public function findByPatientId($patientId, $limit = 100)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT META(ex).id AS _id, ex.*
            FROM `{$bucket}`.`clinical`.`examination` ex
            WHERE ex.patient_id = \$patientId
            ORDER BY ex.event_date DESC
            LIMIT \$limit
        ";
        
        return $conn->query($query, [
            'patientId' => (int)$patientId,
            'limit' => $limit
        ])->rows();
    }
    
    /**
     * Find examinations by episode ID
     * @param int $episodeId Episode ID
     * @param int $limit Maximum results
     * @return array
     */
    public function findByEpisodeId($episodeId, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('clinical', 'examination')
            ->where('episode_id = $episodeId', ['episodeId' => (int)$episodeId])
            ->orderBy('event_date', 'DESC')
            ->limit($limit)
            ->execute();
    }
    
    /**
     * Find examinations by event ID
     * @param int $eventId Event ID
     * @return array|null
     */
    public function findByEventId($eventId)
    {
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('clinical', 'examination')
            ->where('event_id = $eventId', ['eventId' => (int)$eventId])
            ->one();
    }
    
    /**
     * Find examinations by date range
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param int $patientId Optional patient filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findByDateRange($startDate, $endDate, $patientId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('clinical', 'examination')
            ->whereBetween('event_date', $startDate, $endDate, 'dateRange')
            ->orderBy('event_date', 'DESC')
            ->limit($limit);
        
        if ($patientId) {
            $builder->where('patient_id = $patientId', ['patientId' => (int)$patientId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Search examinations with low Visual Acuity readings
     * Finds exams where any VA reading is below threshold
     * 
     * @param float $threshold VA threshold (e.g., 0.5 for 6/12)
     * @param string $eye 'left' or 'right'
     * @param int $limit Maximum results
     * @return array
     */
    public function findLowVisualAcuity($threshold, $eye = 'left', $limit = 100)
    {
        $readingPath = "elements.VisualAcuity.{$eye}_readings";
        
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('clinical', 'examination')
            ->whereAny($readingPath, 'r', 'r.value < $threshold', ['threshold' => $threshold])
            ->orderBy('event_date', 'DESC')
            ->limit($limit)
            ->execute();
    }
    
    /**
     * Search examinations with high Intraocular Pressure readings
     * Finds exams where any IOP reading is above threshold
     * 
     * @param int $threshold IOP threshold in mmHg (e.g., 21)
     * @param string $eye 'left' or 'right'
     * @param int $limit Maximum results
     * @return array
     */
    public function findHighIntraocularPressure($threshold, $eye = 'left', $limit = 100)
    {
        $readingPath = "elements.IntraocularPressure.{$eye}_readings";
        
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('clinical', 'examination')
            ->whereAny($readingPath, 'r', 'r.value > $threshold', ['threshold' => $threshold])
            ->orderBy('event_date', 'DESC')
            ->limit($limit)
            ->execute();
    }
    
    /**
     * Find examinations with specific diagnosis
     * Uses array search on embedded diagnoses
     * 
     * @param int $disorderId Disorder ID
     * @param int $patientId Optional patient filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findByDiagnosis($disorderId, $patientId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('clinical', 'examination')
            ->whereAny('elements.Diagnoses.diagnoses', 'd', 'd.disorder_id = $disorderId', ['disorderId' => (int)$disorderId])
            ->orderBy('event_date', 'DESC')
            ->limit($limit);
        
        if ($patientId) {
            $builder->where('patient_id = $patientId', ['patientId' => (int)$patientId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Find examinations with refraction data
     * @param int $patientId Optional patient filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findWithRefraction($patientId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('clinical', 'examination')
            ->whereNotNull('elements.Refraction')
            ->orderBy('event_date', 'DESC')
            ->limit($limit);
        
        if ($patientId) {
            $builder->where('patient_id = $patientId', ['patientId' => (int)$patientId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Get VA progression for a patient
     * Returns all VA readings over time
     * 
     * @param int $patientId Patient ID
     * @param string $eye 'left' or 'right'
     * @return array
     */
    public function getVisualAcuityProgression($patientId, $eye = 'left')
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $readingPath = "{$eye}_readings";
        
        $query = "
            SELECT 
                ex.event_date,
                ex.elements.VisualAcuity.{$readingPath} AS readings
            FROM `{$bucket}`.`clinical`.`examination` ex
            WHERE ex.patient_id = \$patientId
            AND ex.elements.VisualAcuity IS NOT NULL
            AND ex.elements.VisualAcuity.{$readingPath} IS NOT NULL
            ORDER BY ex.event_date ASC
        ";
        
        return $conn->query($query, ['patientId' => (int)$patientId])->rows();
    }
    
    /**
     * Get IOP progression for a patient
     * Returns all IOP readings over time
     * 
     * @param int $patientId Patient ID
     * @param string $eye 'left' or 'right'
     * @return array
     */
    public function getIntraocularPressureProgression($patientId, $eye = 'left')
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $readingPath = "{$eye}_readings";
        
        $query = "
            SELECT 
                ex.event_date,
                ex.elements.IntraocularPressure.{$readingPath} AS readings
            FROM `{$bucket}`.`clinical`.`examination` ex
            WHERE ex.patient_id = \$patientId
            AND ex.elements.IntraocularPressure IS NOT NULL
            AND ex.elements.IntraocularPressure.{$readingPath} IS NOT NULL
            ORDER BY ex.event_date ASC
        ";
        
        return $conn->query($query, ['patientId' => (int)$patientId])->rows();
    }
    
    /**
     * Search examinations with specific element
     * @param string $elementName Element name (e.g., 'VisualAcuity', 'IntraocularPressure')
     * @param int $patientId Optional patient filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findWithElement($elementName, $patientId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('clinical', 'examination')
            ->whereNotNull("elements.{$elementName}")
            ->orderBy('event_date', 'DESC')
            ->limit($limit);
        
        if ($patientId) {
            $builder->where('patient_id = $patientId', ['patientId' => (int)$patientId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Get examination statistics for a date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Statistics
     */
    public function getStatistics($startDate, $endDate)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT 
                COUNT(*) AS total_examinations,
                COUNT(DISTINCT patient_id) AS unique_patients,
                COUNT(CASE WHEN elements.VisualAcuity IS NOT NULL THEN 1 END) AS with_va,
                COUNT(CASE WHEN elements.IntraocularPressure IS NOT NULL THEN 1 END) AS with_iop,
                COUNT(CASE WHEN elements.Refraction IS NOT NULL THEN 1 END) AS with_refraction,
                COUNT(CASE WHEN elements.Diagnoses IS NOT NULL THEN 1 END) AS with_diagnoses
            FROM `{$bucket}`.`clinical`.`examination` ex
            WHERE ex.event_date BETWEEN \$startDate AND \$endDate
        ";
        
        $results = $conn->query($query, [
            'startDate' => $startDate,
            'endDate' => $endDate
        ])->rows();
        
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find recent examinations across all patients
     * @param int $limit Maximum results
     * @return array
     */
    public function findRecent($limit = 50)
    {
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('clinical', 'examination')
            ->select('event_id, patient_id, event_date, OBJECT_NAMES(elements) AS recorded_elements')
            ->orderBy('event_date', 'DESC')
            ->limit($limit)
            ->execute();
    }
}
