<?php
/**
 * Optimized query patterns for common operations
 * 
 * Provides pre-optimized N1QL queries for frequently-used operations with:
 * - Covering indexes
 * - Efficient joins
 * - Pagination support
 * - Minimal data transfer
 */
class OptimizedQueries
{
    /**
     * Get patient with all relations in single query
     * Fetches patient, recent episodes, and recent events together
     * 
     * @param int $patientId Patient ID
     * @return array Query results
     */
    public static function getPatientWithRelations($patientId)
    {
        $query = "
            SELECT p.*, 
                (SELECT e.* 
                 FROM `openeyes`.`clinical`.`episode` e 
                 WHERE e.patient_id = p.id 
                 ORDER BY e.start_date DESC 
                 LIMIT 10) AS recent_episodes,
                (SELECT ev.* 
                 FROM `openeyes`.`clinical`.`event` ev 
                 WHERE ev.episode_id IN (
                     SELECT RAW ep.id 
                     FROM `openeyes`.`clinical`.`episode` ep 
                     WHERE ep.patient_id = p.id
                 )
                 ORDER BY ev.event_date DESC 
                 LIMIT 20) AS recent_events
            FROM `openeyes`.`clinical`.`patient` p 
            WHERE p.id = \$patientId";
        
        $result = Yii::app()->couchbase->query($query, ['patientId' => $patientId]);
        return $result->rows();
    }

    /**
     * Search patients with pagination and covering index
     * 
     * @param array $criteria Search criteria
     * @param int $offset Pagination offset
     * @param int $limit Results per page
     * @return array Query results
     */
    public static function searchPatients($criteria, $offset = 0, $limit = 50)
    {
        $conditions = ["p._type = 'patient'"];
        $params = [];
        
        if (!empty($criteria['hos_num'])) {
            $conditions[] = "p.hos_num = \$hosNum";
            $params['hosNum'] = $criteria['hos_num'];
        }
        
        if (!empty($criteria['nhs_num'])) {
            $conditions[] = "p.nhs_num = \$nhsNum";
            $params['nhsNum'] = $criteria['nhs_num'];
        }
        
        if (!empty($criteria['last_name'])) {
            $conditions[] = "LOWER(p.last_name) LIKE \$lastName";
            $params['lastName'] = strtolower($criteria['last_name']) . '%';
        }
        
        if (!empty($criteria['first_name'])) {
            $conditions[] = "LOWER(p.first_name) LIKE \$firstName";
            $params['firstName'] = strtolower($criteria['first_name']) . '%';
        }
        
        if (!empty($criteria['dob'])) {
            $conditions[] = "p.dob = \$dob";
            $params['dob'] = $criteria['dob'];
        }
        
        if (!empty($criteria['gender_id'])) {
            $conditions[] = "p.gender_id = \$genderId";
            $params['genderId'] = $criteria['gender_id'];
        }
        
        $where = implode(' AND ', $conditions);
        
        // Use covering index hint if available
        $indexHint = "USE INDEX (idx_patient_search_covering USING GSI)";
        
        $query = "
            SELECT p.id, p.hos_num, p.nhs_num, p.dob, 
                   p.contact.first_name, p.contact.last_name, p.gender_id
            FROM `openeyes`.`clinical`.`patient` p {$indexHint}
            WHERE {$where}
            ORDER BY p.last_name, p.first_name
            OFFSET \$offset LIMIT \$limit";
        
        $params['offset'] = $offset;
        $params['limit'] = $limit;
        
        $result = Yii::app()->couchbase->query($query, $params);
        return $result->rows();
    }

    /**
     * Get episode timeline with efficient indexing
     * 
     * @param int $episodeId Episode ID
     * @param int $limit Maximum events to return
     * @return array Query results
     */
    public static function getEpisodeTimeline($episodeId, $limit = 50)
    {
        $query = "
            SELECT ev.id, ev.event_date, ev.event_type, ev.created_user, ev.info
            FROM `openeyes`.`clinical`.`event` ev 
            USE INDEX (idx_event_timeline_covering USING GSI)
            WHERE ev.episode_id = \$episodeId
            ORDER BY ev.event_date DESC
            LIMIT \$limit";
        
        $result = Yii::app()->couchbase->query($query, [
            'episodeId' => $episodeId,
            'limit' => $limit
        ]);
        
        return $result->rows();
    }

    /**
     * Get episodes by patient with status filter
     * 
     * @param int $patientId Patient ID
     * @param int|null $statusId Status filter (optional)
     * @param int $limit Maximum episodes to return
     * @return array Query results
     */
    public static function getPatientEpisodes($patientId, $statusId = null, $limit = 100)
    {
        $conditions = ["e.patient_id = \$patientId"];
        $params = ['patientId' => $patientId, 'limit' => $limit];
        
        if ($statusId !== null) {
            $conditions[] = "e.status.id = \$statusId";
            $params['statusId'] = $statusId;
        }
        
        $where = implode(' AND ', $conditions);
        
        $query = "
            SELECT e.*
            FROM `openeyes`.`clinical`.`episode` e 
            USE INDEX (idx_episode_list_covering USING GSI)
            WHERE {$where}
            ORDER BY e.start_date DESC
            LIMIT \$limit";
        
        $result = Yii::app()->couchbase->query($query, $params);
        return $result->rows();
    }

    /**
     * Get examination elements efficiently using UNION ALL
     * Fetches multiple element types in parallel
     * 
     * @param int $eventId Event ID
     * @return array Query results grouped by element type
     */
    public static function getExaminationElements($eventId)
    {
        $elementTypes = [
            'element_ophciexamination_history',
            'element_ophciexamination_visualacuity',
            'element_ophciexamination_refraction',
            'element_ophciexamination_anteriorsegment',
            'element_ophciexamination_posteriorsegment',
            'element_ophciexamination_fundus',
            'element_ophciexamination_diagnoses',
            'element_ophciexamination_management',
        ];
        
        $unions = [];
        foreach ($elementTypes as $type) {
            $unions[] = "SELECT '{$type}' AS element_type, e.* 
                         FROM `openeyes`.`clinical`.`{$type}` e 
                         WHERE e.event_id = \$eventId";
        }
        
        $query = implode(' UNION ALL ', $unions);
        
        $result = Yii::app()->couchbase->query($query, ['eventId' => $eventId]);
        return $result->rows();
    }

    /**
     * Aggregate query for dashboard statistics
     * 
     * @param int $siteId Site ID
     * @param string $dateFrom Start date (YYYY-MM-DD)
     * @param string $dateTo End date (YYYY-MM-DD)
     * @return array Query results
     */
    public static function getDashboardStats($siteId, $dateFrom, $dateTo)
    {
        $query = "
            SELECT 
                COUNT(CASE WHEN ev.event_type.name = 'Examination' THEN 1 END) AS examinations,
                COUNT(CASE WHEN ev.event_type.name = 'Operation booking' THEN 1 END) AS bookings,
                COUNT(CASE WHEN ev.event_type.name = 'Operation note' THEN 1 END) AS operations,
                COUNT(CASE WHEN ev.event_type.name = 'Prescription' THEN 1 END) AS prescriptions,
                COUNT(DISTINCT ev.episode_id) AS unique_episodes,
                COUNT(DISTINCT ev.created_user.id) AS unique_users
            FROM `openeyes`.`clinical`.`event` ev
            WHERE ev.site_id = \$siteId
            AND ev.event_date >= \$dateFrom
            AND ev.event_date <= \$dateTo";
        
        $result = Yii::app()->couchbase->query($query, [
            'siteId' => $siteId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo
        ]);
        
        return $result->rows();
    }

    /**
     * Get recent audit entries for a patient
     * 
     * @param int $patientId Patient ID
     * @param int $limit Maximum audit entries
     * @return array Query results
     */
    public static function getPatientAudit($patientId, $limit = 100)
    {
        $query = "
            SELECT a.id, a.action, a.target_type, a.user_id, a.created_date
            FROM `openeyes`.`admin`.`audit` a
            USE INDEX (idx_audit_user_date USING GSI)
            WHERE a.patient_id = \$patientId
            ORDER BY a.created_date DESC
            LIMIT \$limit";
        
        $result = Yii::app()->couchbase->query($query, [
            'patientId' => $patientId,
            'limit' => $limit
        ]);
        
        return $result->rows();
    }

    /**
     * Search disorders by term and specialty
     * 
     * @param string $term Search term
     * @param int|null $specialtyId Specialty filter (optional)
     * @param int $limit Maximum results
     * @return array Query results
     */
    public static function searchDisorders($term, $specialtyId = null, $limit = 50)
    {
        $conditions = [
            "d.active = true",
            "LOWER(d.term) LIKE \$term"
        ];
        
        $params = [
            'term' => strtolower($term) . '%',
            'limit' => $limit
        ];
        
        if ($specialtyId !== null) {
            $conditions[] = "d.specialty_id = \$specialtyId";
            $params['specialtyId'] = $specialtyId;
        }
        
        $where = implode(' AND ', $conditions);
        
        $query = "
            SELECT d.id, d.term, d.specialty_id, d.systemic_disorder
            FROM `openeyes`.`reference`.`disorder` d
            USE INDEX (idx_disorder_specialty_term USING GSI)
            WHERE {$where}
            ORDER BY d.term
            LIMIT \$limit";
        
        $result = Yii::app()->couchbase->query($query, $params);
        return $result->rows();
    }

    /**
     * Get medications for a patient
     * 
     * @param int $patientId Patient ID
     * @param bool $activeOnly Only active medications
     * @return array Query results
     */
    public static function getPatientMedications($patientId, $activeOnly = true)
    {
        $conditions = ["m.patient_id = \$patientId"];
        $params = ['patientId' => $patientId];
        
        if ($activeOnly) {
            $conditions[] = "(m.end_date IS NULL OR m.end_date >= CURRENT_DATE())";
        }
        
        $where = implode(' AND ', $conditions);
        
        $query = "
            SELECT m.id, m.medication_id, m.dose, m.frequency, m.route, 
                   m.start_date, m.end_date, m.prescription_item_id
            FROM `openeyes`.`clinical`.`medication_usage` m
            WHERE {$where}
            ORDER BY m.start_date DESC";
        
        $result = Yii::app()->couchbase->query($query, $params);
        return $result->rows();
    }

    /**
     * Get waiting list entries by site and specialty
     * 
     * @param int $siteId Site ID
     * @param int|null $specialtyId Specialty filter (optional)
     * @param int $limit Maximum results
     * @return array Query results
     */
    public static function getWaitingList($siteId, $specialtyId = null, $limit = 100)
    {
        $conditions = [
            "w.site_id = \$siteId",
            "w.status = 'active'"
        ];
        
        $params = [
            'siteId' => $siteId,
            'limit' => $limit
        ];
        
        if ($specialtyId !== null) {
            $conditions[] = "w.specialty_id = \$specialtyId";
            $params['specialtyId'] = $specialtyId;
        }
        
        $where = implode(' AND ', $conditions);
        
        $query = "
            SELECT w.id, w.patient_id, w.episode_id, w.booking_date, 
                   w.priority, w.waiting_days
            FROM `openeyes`.`clinical`.`waiting_list` w
            WHERE {$where}
            ORDER BY w.priority DESC, w.waiting_days DESC
            LIMIT \$limit";
        
        $result = Yii::app()->couchbase->query($query, $params);
        return $result->rows();
    }

    /**
     * Count query with efficient aggregation
     * 
     * @param string $collection Collection name
     * @param array $conditions Where conditions
     * @return int Count result
     */
    public static function count($collection, $conditions = [])
    {
        $where = empty($conditions) ? "1=1" : implode(' AND ', $conditions);
        
        $query = "SELECT COUNT(*) AS count FROM `openeyes`.`clinical`.`{$collection}` WHERE {$where}";
        
        $result = Yii::app()->couchbase->query($query);
        $rows = $result->rows();
        
        return isset($rows[0]['count']) ? (int)$rows[0]['count'] : 0;
    }

    /**
     * Batch get documents by IDs
     * More efficient than multiple single gets
     * 
     * @param string $scope Scope name
     * @param string $collection Collection name
     * @param array $ids Array of document IDs
     * @return array Documents
     */
    public static function batchGet($scope, $collection, array $ids)
    {
        if (empty($ids)) {
            return [];
        }
        
        $placeholders = [];
        $params = [];
        
        foreach ($ids as $index => $id) {
            $paramName = "id{$index}";
            $placeholders[] = "\${$paramName}";
            $params[$paramName] = $id;
        }
        
        $inClause = implode(', ', $placeholders);
        
        $query = "
            SELECT META(d).id AS _id, d.*
            FROM `openeyes`.`{$scope}`.`{$collection}` d
            WHERE META(d).id IN [{$inClause}]";
        
        $result = Yii::app()->couchbase->query($query, $params);
        return $result->rows();
    }
}
