<?php
/**
 * Theatre session document for Couchbase
 * 
 * Stores theatre session details including capacity, availability, and scheduling.
 */

namespace OEModule\OphTrOperationbooking\models\couchbase;

class SessionDocument extends \CouchbaseActiveRecord
{
    /**
     * Get document type identifier
     * @return string
     */
    public function documentType()
    {
        return 'session';
    }
    
    /**
     * Get Couchbase scope
     * @return string
     */
    public function scope()
    {
        return 'booking';
    }
    
    /**
     * Get Couchbase collection name
     * @return string
     */
    public function collectionName()
    {
        return 'session';
    }
    
    /**
     * Create session document from model
     * 
     * @param \OphTrOperationbooking_Operation_Session $session Session model
     * @return self Session document
     */
    public static function createFromModel($session)
    {
        $doc = new self();
        $doc->_pk = $session->id;
        
        $doc->_attributes = [
            'session_id' => (string) $session->id,
            'sequence_id' => $session->sequence_id ? (string) $session->sequence_id : null,
            'theatre_id' => $session->theatre_id ? (string) $session->theatre_id : null,
            'theatre_name' => $session->theatre ? $session->theatre->name : null,
            'theatre_code' => $session->theatre ? $session->theatre->code : null,
            'site_id' => $session->theatre && $session->theatre->site_id ? (string) $session->theatre->site_id : null,
            'date' => $session->date,
            'start_time' => $session->start_time,
            'end_time' => $session->end_time,
            'default_admission_time' => $session->default_admission_time,
            
            // Firm/consultant
            'firm_id' => $session->firm_id ? (string) $session->firm_id : null,
            'firm_name' => $session->firm ? $session->firm->name : null,
            
            // Capacity
            'max_procedures' => $session->max_procedures,
            'max_complex_bookings' => $session->max_complex_bookings,
            'available' => (bool) $session->available,
            'available_theatre' => (bool) $session->available_theatre,
            
            // Comments and notes
            'comments' => $session->comments,
            
            // Timestamps
            'created_date' => $session->created_date,
            'last_modified_date' => $session->last_modified_date,
        ];
        
        return $doc;
    }
    
    /**
     * Find available sessions
     * 
     * @param string|null $theatreId Theatre ID filter
     * @param string|null $fromDate Date filter (YYYY-MM-DD)
     * @param int $limit Result limit
     * @return array Array of session documents
     */
    public static function findAvailable($theatreId = null, $fromDate = null, $limit = 50)
    {
        $query = "SELECT META().id, * FROM `openeyes`.`booking`.`session` 
                  WHERE available = true";
        $params = ['limit' => $limit];
        
        if ($theatreId) {
            $query .= " AND theatre_id = \$theatreId";
            $params['theatreId'] = (string) $theatreId;
        }
        
        if ($fromDate) {
            $query .= " AND date >= \$fromDate";
            $params['fromDate'] = $fromDate;
        }
        
        $query .= " ORDER BY date, start_time LIMIT \$limit";
        
        return self::findByN1QL($query, $params);
    }
    
    /**
     * Find sessions by date range
     * 
     * @param string $fromDate Start date (YYYY-MM-DD)
     * @param string $toDate End date (YYYY-MM-DD)
     * @param string|null $siteId Site ID filter
     * @return array Array of session documents
     */
    public static function findByDateRange($fromDate, $toDate, $siteId = null)
    {
        $query = "SELECT META().id, * FROM `openeyes`.`booking`.`session` 
                  WHERE date BETWEEN \$fromDate AND \$toDate";
        $params = [
            'fromDate' => $fromDate,
            'toDate' => $toDate
        ];
        
        if ($siteId) {
            $query .= " AND site_id = \$siteId";
            $params['siteId'] = (string) $siteId;
        }
        
        $query .= " ORDER BY date, start_time";
        
        return self::findByN1QL($query, $params);
    }
    
    /**
     * Find sessions by theatre
     * 
     * @param string $theatreId Theatre ID
     * @param int $limit Result limit
     * @return array Array of session documents
     */
    public static function findByTheatre($theatreId, $limit = 100)
    {
        $query = "SELECT META().id, * FROM `openeyes`.`booking`.`session` 
                  WHERE theatre_id = \$theatreId 
                  ORDER BY date DESC, start_time 
                  LIMIT \$limit";
        
        return self::findByN1QL($query, [
            'theatreId' => (string) $theatreId,
            'limit' => $limit
        ]);
    }
    
    /**
     * Execute N1QL query and return model instances
     * 
     * @param string $query N1QL query string
     * @param array $params Query parameters
     * @return array Array of model instances
     */
    protected static function findByN1QL($query, $params = [])
    {
        try {
            $connection = \Yii::app()->couchbase;
            $result = $connection->query($query, $params);
            
            $models = [];
            foreach ($result->rows() as $row) {
                $model = new self();
                
                $data = isset($row['session']) ? (array) $row['session'] : (array) $row;
                unset($data['_key']);
                
                $model->_attributes = $data;
                $model->_pk = isset($data['session_id']) ? $data['session_id'] : null;
                $model->_isNewRecord = false;
                
                $models[] = $model;
            }
            
            return $models;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase query error: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return [];
        }
    }
}
