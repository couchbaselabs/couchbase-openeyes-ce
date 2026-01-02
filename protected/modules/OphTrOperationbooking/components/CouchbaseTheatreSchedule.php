<?php
/**
 * Theatre schedule queries using Couchbase N1QL
 */

namespace OEModule\OphTrOperationbooking\components;

use OE\Database\N1qlQueryBuilder;

class CouchbaseTheatreSchedule
{
    /**
     * Find available theatre sessions
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param int|null $siteId Optional site filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findAvailableSessions($startDate, $endDate, $siteId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('booking', 'session')
            ->whereBetween('date', $startDate, $endDate, 'dateRange')
            ->orderBy('date', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->limit($limit);
        
        if ($siteId) {
            $builder->where('site_id = $siteId', ['siteId' => (int)$siteId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Find sessions by theatre
     * @param int $theatreId Theatre ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array
     */
    public function findByTheatre($theatreId, $startDate, $endDate)
    {
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('booking', 'session')
            ->where('theatre_id = $theatreId', ['theatreId' => (int)$theatreId])
            ->whereBetween('date', $startDate, $endDate, 'dateRange')
            ->orderBy('date', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->execute();
    }
    
    /**
     * Find sessions for a firm
     * @param int $firmId Firm ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array
     */
    public function findByFirm($firmId, $startDate, $endDate)
    {
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('booking', 'session')
            ->where('firm_id = $firmId', ['firmId' => (int)$firmId])
            ->whereBetween('date', $startDate, $endDate, 'dateRange')
            ->orderBy('date', 'ASC')
            ->execute();
    }
    
    /**
     * Get session utilization statistics
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param int|null $siteId Optional site filter
     * @return array
     */
    public function getUtilizationStats($startDate, $endDate, $siteId = null)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT 
                COUNT(*) AS total_sessions,
                SUM(available_time) AS total_available_minutes,
                SUM(booked_time) AS total_booked_minutes,
                AVG((booked_time / available_time) * 100) AS avg_utilization_percent
            FROM `{$bucket}`.`booking`.`session` s
            WHERE s.date BETWEEN \$startDate AND \$endDate
        ";
        
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        
        if ($siteId !== null) {
            $query .= " AND s.site_id = \$siteId";
            $params['siteId'] = (int)$siteId;
        }
        
        $results = $conn->query($query, $params)->rows();
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find upcoming sessions with capacity
     * @param int $minAvailableMinutes Minimum available time in minutes
     * @param int $limit Maximum results
     * @return array
     */
    public function findUpcomingWithCapacity($minAvailableMinutes, $limit = 50)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT META(s).id AS _id, s.*,
                   (s.available_time - s.booked_time) AS remaining_minutes
            FROM `{$bucket}`.`booking`.`session` s
            WHERE s.date >= SUBSTR(NOW_STR(), 0, 10)
            AND (s.available_time - s.booked_time) >= \$minMinutes
            ORDER BY s.date ASC, s.start_time ASC
            LIMIT \$limit
        ";
        
        return $conn->query($query, [
            'minMinutes' => (int)$minAvailableMinutes,
            'limit' => $limit
        ])->rows();
    }
}
