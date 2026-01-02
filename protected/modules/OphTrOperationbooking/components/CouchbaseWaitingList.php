<?php
/**
 * Waiting list queries using Couchbase N1QL
 */

namespace OEModule\OphTrOperationbooking\components;

use OE\Database\N1qlQueryBuilder;

class CouchbaseWaitingList
{
    /**
     * Get waiting list for a specific firm
     * Returns operations that are not yet booked
     * 
     * @param int $firmId Firm ID
     * @param int|null $statusId Optional status filter
     * @param int $limit Maximum results
     * @return array
     */
    public function getForFirm($firmId, $statusId = null, $limit = 100)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT META(o).id AS _id, o.*
            FROM `{$bucket}`.`booking`.`operation` o
            WHERE o.booking IS NULL
            AND o.firm_id = \$firmId
        ";
        
        $params = ['firmId' => (int)$firmId];
        
        if ($statusId !== null) {
            $query .= " AND o.status_id = \$statusId";
            $params['statusId'] = (int)$statusId;
        }
        
        $query .= " ORDER BY o.decision_date ASC, o.priority ASC LIMIT \$limit";
        $params['limit'] = $limit;
        
        return $conn->query($query, $params)->rows();
    }
    
    /**
     * Get waiting list statistics
     * Returns counts and average wait times by status
     * 
     * @param int|null $firmId Optional firm filter
     * @param int|null $institutionId Optional institution filter
     * @return array
     */
    public function getStatistics($firmId = null, $institutionId = null)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT 
                o.status_id,
                o.status_name,
                COUNT(*) AS count,
                AVG(DATE_DIFF_STR(NOW_STR(), o.decision_date, 'day')) AS avg_wait_days,
                MIN(o.decision_date) AS oldest_decision_date,
                MAX(o.decision_date) AS newest_decision_date
            FROM `{$bucket}`.`booking`.`operation` o
            WHERE o.booking IS NULL
        ";
        
        $params = [];
        
        if ($firmId !== null) {
            $query .= " AND o.firm_id = \$firmId";
            $params['firmId'] = (int)$firmId;
        }
        
        if ($institutionId !== null) {
            $query .= " AND o.institution_id = \$institutionId";
            $params['institutionId'] = (int)$institutionId;
        }
        
        $query .= " GROUP BY o.status_id, o.status_name ORDER BY count DESC";
        
        return $conn->query($query, $params)->rows();
    }
    
    /**
     * Find pending operations (not yet booked)
     * @param int|null $patientId Optional patient filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findPendingOperations($patientId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('booking', 'operation')
            ->whereNull('booking')
            ->orderBy('decision_date', 'ASC')
            ->limit($limit);
        
        if ($patientId) {
            $builder->where('patient_id = $patientId', ['patientId' => (int)$patientId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Get operations by priority
     * @param int $priority Priority level
     * @param int|null $firmId Optional firm filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findByPriority($priority, $firmId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('booking', 'operation')
            ->where('priority = $priority', ['priority' => (int)$priority])
            ->whereNull('booking')
            ->orderBy('decision_date', 'ASC')
            ->limit($limit);
        
        if ($firmId) {
            $builder->where('firm_id = $firmId', ['firmId' => (int)$firmId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Get operations with specific procedure
     * Searches in procedures array
     * 
     * @param int $procedureId Procedure ID
     * @param int|null $firmId Optional firm filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findByProcedure($procedureId, $firmId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('booking', 'operation')
            ->whereAny('procedures', 'p', 'p.procedure_id = $procedureId', ['procedureId' => (int)$procedureId])
            ->whereNull('booking')
            ->orderBy('decision_date', 'ASC')
            ->limit($limit);
        
        if ($firmId) {
            $builder->where('firm_id = $firmId', ['firmId' => (int)$firmId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Find operations waiting longer than threshold
     * @param int $daysThreshold Number of days
     * @param int|null $firmId Optional firm filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findLongWaiters($daysThreshold, $firmId = null, $limit = 100)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT META(o).id AS _id, o.*,
                   DATE_DIFF_STR(NOW_STR(), o.decision_date, 'day') AS wait_days
            FROM `{$bucket}`.`booking`.`operation` o
            WHERE o.booking IS NULL
            AND DATE_DIFF_STR(NOW_STR(), o.decision_date, 'day') > \$threshold
        ";
        
        $params = ['threshold' => (int)$daysThreshold];
        
        if ($firmId !== null) {
            $query .= " AND o.firm_id = \$firmId";
            $params['firmId'] = (int)$firmId;
        }
        
        $query .= " ORDER BY wait_days DESC LIMIT \$limit";
        $params['limit'] = $limit;
        
        return $conn->query($query, $params)->rows();
    }
    
    /**
     * Get patient's waiting list position for firm
     * @param int $patientId Patient ID
     * @param int $firmId Firm ID
     * @return array|null
     */
    public function getPatientPosition($patientId, $firmId)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        // Get all waiting operations for firm ordered by priority and date
        $query = "
            SELECT META(o).id AS _id, o.*,
                   ROW_NUMBER() OVER (ORDER BY o.priority ASC, o.decision_date ASC) AS position
            FROM `{$bucket}`.`booking`.`operation` o
            WHERE o.booking IS NULL
            AND o.firm_id = \$firmId
        ";
        
        $results = $conn->query($query, ['firmId' => (int)$firmId])->rows();
        
        // Find the patient's operation
        foreach ($results as $index => $op) {
            if ($op['patient_id'] == $patientId) {
                return [
                    'position' => $index + 1,
                    'total' => count($results),
                    'operation' => $op
                ];
            }
        }
        
        return null;
    }
}
