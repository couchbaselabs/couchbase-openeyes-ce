<?php
/**
 * Correspondence letter search using Couchbase N1QL
 */

namespace OEModule\OphCoCorrespondence\components;

use OE\Database\N1qlQueryBuilder;

class CouchbaseLetterSearch
{
    /**
     * Get patient letter history
     * @param int $patientId Patient ID
     * @param int $limit Maximum results
     * @return array
     */
    public function getPatientHistory($patientId, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('correspondence', 'letter')
            ->where('patient_id = $patientId', ['patientId' => (int)$patientId])
            ->orderBy('event_date', 'DESC')
            ->limit($limit)
            ->execute();
    }
    
    /**
     * Find draft letters
     * @param int|null $userId Optional user filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findDrafts($userId = null, $limit = 50)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('correspondence', 'letter')
            ->where('is_draft = $draft', ['draft' => true])
            ->orderBy('last_modified_date', 'DESC')
            ->limit($limit);
        
        if ($userId) {
            $builder->where('created_user_id = $userId', ['userId' => (int)$userId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Find letters by type
     * @param int $letterTypeId Letter type ID
     * @param int|null $patientId Optional patient filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findByType($letterTypeId, $patientId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('correspondence', 'letter')
            ->where('letter_type_id = $typeId', ['typeId' => (int)$letterTypeId])
            ->orderBy('event_date', 'DESC')
            ->limit($limit);
        
        if ($patientId) {
            $builder->where('patient_id = $patientId', ['patientId' => (int)$patientId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Find letters by date range
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param int|null $siteId Optional site filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findByDateRange($startDate, $endDate, $siteId = null, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('correspondence', 'letter')
            ->whereBetween('event_date', $startDate, $endDate, 'dateRange')
            ->orderBy('event_date', 'DESC')
            ->limit($limit);
        
        if ($siteId) {
            $builder->where('site_id = $siteId', ['siteId' => (int)$siteId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Search letters by recipient
     * Uses array search on recipients array
     * 
     * @param int $contactId Contact/recipient ID
     * @param int $limit Maximum results
     * @return array
     */
    public function findByRecipient($contactId, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('correspondence', 'letter')
            ->whereAny('recipients', 'r', 'r.contact_id = $contactId', ['contactId' => (int)$contactId])
            ->orderBy('event_date', 'DESC')
            ->limit($limit)
            ->execute();
    }
    
    /**
     * Find letters awaiting approval
     * @param int|null $firmId Optional firm filter
     * @param int $limit Maximum results
     * @return array
     */
    public function findAwaitingApproval($firmId = null, $limit = 50)
    {
        $builder = new N1qlQueryBuilder();
        $builder
            ->from('correspondence', 'letter')
            ->where('status = $status', ['status' => 'pending_approval'])
            ->orderBy('event_date', 'ASC')
            ->limit($limit);
        
        if ($firmId) {
            $builder->where('firm_id = $firmId', ['firmId' => (int)$firmId]);
        }
        
        return $builder->execute();
    }
    
    /**
     * Find letters sent but not printed
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param int $limit Maximum results
     * @return array
     */
    public function findSentNotPrinted($startDate, $endDate, $limit = 100)
    {
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('correspondence', 'letter')
            ->whereBetween('event_date', $startDate, $endDate, 'dateRange')
            ->where('is_sent = $sent', ['sent' => true])
            ->whereNull('print_date')
            ->orderBy('event_date', 'DESC')
            ->limit($limit)
            ->execute();
    }
    
    /**
     * Get correspondence statistics
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param int|null $firmId Optional firm filter
     * @return array|null
     */
    public function getStatistics($startDate, $endDate, $firmId = null)
    {
        $conn = \Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "
            SELECT 
                COUNT(*) AS total_letters,
                COUNT(DISTINCT patient_id) AS unique_patients,
                COUNT(CASE WHEN is_draft = true THEN 1 END) AS draft_count,
                COUNT(CASE WHEN is_sent = true THEN 1 END) AS sent_count,
                COUNT(CASE WHEN print_date IS NOT NULL THEN 1 END) AS printed_count
            FROM `{$bucket}`.`correspondence`.`letter` l
            WHERE l.event_date BETWEEN \$startDate AND \$endDate
        ";
        
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        
        if ($firmId !== null) {
            $query .= " AND l.firm_id = \$firmId";
            $params['firmId'] = (int)$firmId;
        }
        
        $results = $conn->query($query, $params)->rows();
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find recent letters across all patients
     * @param int $limit Maximum results
     * @return array
     */
    public function findRecent($limit = 50)
    {
        $builder = new N1qlQueryBuilder();
        return $builder
            ->from('correspondence', 'letter')
            ->select('event_id, patient_id, event_date, letter_type_id, is_draft')
            ->orderBy('event_date', 'DESC')
            ->limit($limit)
            ->execute();
    }
}
