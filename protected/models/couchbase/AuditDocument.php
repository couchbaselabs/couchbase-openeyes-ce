<?php
/**
 * Couchbase document model for Audit
 * Optimized for time-series queries and compliance reporting
 */

class AuditDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'audit';
    protected $scope = 'admin';
    protected $collection = 'audit';

    /**
     * Create document from Audit model
     * Denormalizes related data for fast queries without joins
     * @param Audit $audit
     * @return array
     */
    public static function createFromModel($audit)
    {
        $doc = [
            '_type' => 'audit',
            'id' => (int)$audit->id,
            'action_id' => $audit->action_id ? (int)$audit->action_id : null,
            'type_id' => $audit->type_id ? (int)$audit->type_id : null,
            'patient_id' => $audit->patient_id ? (int)$audit->patient_id : null,
            'episode_id' => $audit->episode_id ? (int)$audit->episode_id : null,
            'event_id' => $audit->event_id ? (int)$audit->event_id : null,
            'user_id' => $audit->user_id ? (int)$audit->user_id : null,
            'site_id' => $audit->site_id ? (int)$audit->site_id : null,
            'institution_id' => $audit->institution_id ? (int)$audit->institution_id : null,
            'firm_id' => $audit->firm_id ? (int)$audit->firm_id : null,
            'event_type_id' => $audit->event_type_id ? (int)$audit->event_type_id : null,
            'data' => $audit->data,
            'remote_addr' => $audit->remote_addr,
            'http_user_agent' => $audit->http_user_agent,
            'server_name' => $audit->server_name,
            'request_uri' => $audit->request_uri,
            'created_date' => $audit->created_date,
            
            // Computed fields for time-series queries
            'created_timestamp' => $audit->created_date ? strtotime($audit->created_date) : null,
            'created_date_only' => $audit->created_date ? substr($audit->created_date, 0, 10) : null,
            'created_hour' => $audit->created_date ? (int)date('H', strtotime($audit->created_date)) : null,
            'created_year_month' => $audit->created_date ? substr($audit->created_date, 0, 7) : null,
        ];
        
        // Embed action name for filtering without joins
        if ($audit->action) {
            $doc['action'] = [
                'id' => (int)$audit->action->id,
                'name' => $audit->action->name,
            ];
        }
        
        // Embed type name
        if ($audit->target_type) {
            $doc['type'] = [
                'id' => (int)$audit->target_type->id,
                'name' => $audit->target_type->name,
            ];
        }
        
        // Embed user info for quick display in audit logs
        if ($audit->user) {
            $doc['user'] = [
                'id' => (int)$audit->user->id,
                'username' => $audit->user->username,
                'first_name' => $audit->user->first_name,
                'last_name' => $audit->user->last_name,
                'full_name' => trim($audit->user->first_name . ' ' . $audit->user->last_name),
            ];
        }
        
        // Embed patient info for audit trail
        if ($audit->patient) {
            $doc['patient'] = [
                'id' => (int)$audit->patient->id,
                'hos_num' => $audit->patient->hos_num,
                'nhs_num' => $audit->patient->nhs_num,
            ];
        }
        
        // Embed site info
        if ($audit->site) {
            $doc['site'] = [
                'id' => (int)$audit->site->id,
                'name' => $audit->site->name,
                'short_name' => $audit->site->short_name,
            ];
        }
        
        // Embed institution info
        if ($audit->institution) {
            $doc['institution'] = [
                'id' => (int)$audit->institution->id,
                'name' => $audit->institution->name,
            ];
        }
        
        // Embed firm info
        if ($audit->firm) {
            $doc['firm'] = [
                'id' => (int)$audit->firm->id,
                'name' => $audit->firm->name,
            ];
        }
        
        // Embed event type info
        if ($audit->event_type_id && $audit->event_type) {
            $doc['event_type'] = [
                'id' => (int)$audit->event_type->id,
                'name' => $audit->event_type->name,
                'class_name' => $audit->event_type->class_name,
            ];
        }
        
        return $doc;
    }

    /**
     * Find audits by date range
     * Primary query for audit log viewing
     * @param string $startDate Format: YYYY-MM-DD
     * @param string $endDate Format: YYYY-MM-DD
     * @param int $limit
     * @return array
     */
    public static function findByDateRange($startDate, $endDate, $limit = 1000)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.created_date_only >= \$startDate 
                  AND a.created_date_only <= \$endDate 
                  ORDER BY a.created_timestamp DESC 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'limit' => $limit
        ]);
    }

    /**
     * Find audits by user
     * For user activity tracking
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function findByUser($userId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.user_id = \$userId 
                  ORDER BY a.created_timestamp DESC 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'userId' => $userId,
            'limit' => $limit
        ]);
    }

    /**
     * Find audits by patient
     * For patient access audit trail
     * @param int $patientId
     * @param int $limit
     * @return array
     */
    public static function findByPatient($patientId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.patient_id = \$patientId 
                  ORDER BY a.created_timestamp DESC 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'patientId' => $patientId,
            'limit' => $limit
        ]);
    }

    /**
     * Find audits by event
     * For event-level audit trail
     * @param int $eventId
     * @param int $limit
     * @return array
     */
    public static function findByEvent($eventId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.event_id = \$eventId 
                  ORDER BY a.created_timestamp DESC 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'eventId' => $eventId,
            'limit' => $limit
        ]);
    }

    /**
     * Count audits by action type for date range
     * For audit reporting and analytics
     * @param string $startDate Format: YYYY-MM-DD
     * @param string $endDate Format: YYYY-MM-DD
     * @return array
     */
    public static function countByAction($startDate, $endDate)
    {
        $query = "SELECT a.action.name AS action_name, COUNT(*) AS count 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.created_date_only >= \$startDate 
                  AND a.created_date_only <= \$endDate 
                  GROUP BY a.action.name 
                  ORDER BY count DESC";
        
        return self::executeQuery($query, [
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    /**
     * Count audits by user for date range
     * For user activity reporting
     * @param string $startDate Format: YYYY-MM-DD
     * @param string $endDate Format: YYYY-MM-DD
     * @param int $limit
     * @return array
     */
    public static function countByUser($startDate, $endDate, $limit = 50)
    {
        $query = "SELECT a.user.full_name AS user_name, 
                         a.user_id, 
                         COUNT(*) AS count 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.created_date_only >= \$startDate 
                  AND a.created_date_only <= \$endDate 
                  AND a.user_id IS NOT NULL
                  GROUP BY a.user.full_name, a.user_id 
                  ORDER BY count DESC 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'limit' => $limit
        ]);
    }

    /**
     * Find audits by site and date range
     * For site-specific audit reporting
     * @param int $siteId
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return array
     */
    public static function findBySite($siteId, $startDate, $endDate, $limit = 1000)
    {
        $query = "SELECT META().id AS _id, a.* 
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.site_id = \$siteId 
                  AND a.created_date_only >= \$startDate 
                  AND a.created_date_only <= \$endDate 
                  ORDER BY a.created_timestamp DESC 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'siteId' => $siteId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'limit' => $limit
        ]);
    }

    /**
     * Get audit statistics for date range
     * Summary statistics for reporting
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getStatistics($startDate, $endDate)
    {
        $query = "SELECT COUNT(*) AS total_audits,
                         COUNT(DISTINCT a.user_id) AS unique_users,
                         COUNT(DISTINCT a.patient_id) AS unique_patients,
                         COUNT(DISTINCT a.site_id) AS unique_sites
                  FROM `openeyes`.`admin`.`audit` a 
                  WHERE a.created_date_only >= \$startDate 
                  AND a.created_date_only <= \$endDate";
        
        $result = self::executeQuery($query, [
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
        
        return !empty($result) ? $result[0] : null;
    }
}
