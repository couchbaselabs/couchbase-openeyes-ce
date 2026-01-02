<?php
/**
 * Episode statistics report using Couchbase
 */

namespace OE\Reports;

use OE\Database\N1qlQueryBuilder;

class CouchbaseEpisodeReport
{
    /**
     * Get episode counts by subspecialty
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param string|null $institutionId Institution ID filter
     * @return array
     */
    public function countBySubspecialty($startDate, $endDate, $institutionId = null)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT 
                e.subspecialty_id,
                COUNT(*) as episode_count,
                COUNT(DISTINCT e.patient_id) as patient_count
            FROM `{$bucket}`.`core`.`episode` e
            WHERE e.start_date BETWEEN \$startDate AND \$endDate
        ";
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
        
        if ($institutionId) {
            $query .= " AND e.institution_id = \$institutionId";
            $params['institutionId'] = $institutionId;
        }
        
        $query .= " GROUP BY e.subspecialty_id ORDER BY episode_count DESC";
        
        return $conn->query($query, $params)->rows();
    }
    
    /**
     * Get patient episode history
     * @param string $patientId Patient ID
     * @return array
     */
    public function patientHistory($patientId)
    {
        $builder = new N1qlQueryBuilder();
        $builder->from('core', 'episode')
            ->select('META().id AS _id, subspecialty_id, firm_id, start_date, end_date, episode_status_id')
            ->where('patient_id = $patientId', ['patientId' => $patientId])
            ->orderBy('start_date', 'DESC');
        
        return $builder->execute();
    }
}
