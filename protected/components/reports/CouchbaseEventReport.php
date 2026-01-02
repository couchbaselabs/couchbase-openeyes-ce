<?php
/**
 * Event report using Couchbase
 */

namespace OE\Reports;

use OE\Database\N1qlQueryBuilder;

class CouchbaseEventReport
{
    /**
     * Find events by patient ID (via episode JOIN)
     * @param int $patientId Patient ID
     * @param int $limit Maximum results
     * @return array
     */
    public function findByPatientId($patientId, $limit = 100)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META(e).id AS _id, e.* 
                  FROM `{$bucket}`.`core`.`event` e
                  JOIN `{$bucket}`.`core`.`episode` ep ON e.episode_id = ep._mysql_id
                  WHERE ep.patient_id = \$patientId
                  AND e._type = 'event'
                  ORDER BY e.event_date DESC
                  LIMIT \$limit";
        
        return $conn->query($query, [
            'patientId' => (int)$patientId,
            'limit' => $limit,
        ])->rows();
    }
    
    /**
     * Count events by event type
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array
     */
    public function countByEventType($startDate, $endDate)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT 
                e.event_type_id,
                e.event_type_name,
                COUNT(*) as event_count
            FROM `{$bucket}`.`core`.`event` e
            WHERE e.event_date BETWEEN \$startDate AND \$endDate
            GROUP BY e.event_type_id, e.event_type_name
            ORDER BY event_count DESC
        ";
        
        return $conn->query($query, [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->rows();
    }
}
