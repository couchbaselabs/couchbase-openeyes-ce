<?php
/**
 * Patient search using Couchbase N1QL
 */

namespace OE\Reports;

use OE\Database\N1qlQueryBuilder;

class CouchbasePatientSearch
{
    /**
     * Search patients and return as CActiveDataProvider for UI compatibility
     * @param array $patient_criteria Search criteria from PatientSearch::prepareSearch()
     * @return \CDataProvider
     */
    public function searchForDataProvider(array $patient_criteria): \CDataProvider
    {
        $criteria = [];
        
        // Name search
        if (!empty($patient_criteria['first_name'])) {
            $criteria['first_name'] = $patient_criteria['first_name'];
        }
        if (!empty($patient_criteria['last_name'])) {
            $criteria['last_name'] = $patient_criteria['last_name'];
        }
        if (!empty($patient_criteria['dob'])) {
            $criteria['dob'] = $patient_criteria['dob'];
        }
        
        // Identifier search (hospital number, NHS number)
        if (!empty($patient_criteria['term']) && !$patient_criteria['is_name_search']) {
            // Try as hospital number first, then NHS number
            $criteria['hos_num'] = $patient_criteria['term'];
        }
        
        // Pagination
        $criteria['limit'] = $patient_criteria['pageSize'] ?? 20;
        if (!empty($patient_criteria['currentPage'])) {
            $criteria['offset'] = ($patient_criteria['currentPage'] - 1) * $criteria['limit'];
        }
        
        // Execute Couchbase search
        $results = $this->search($criteria);
        
        // If no results with hos_num, try nhs_num
        if (empty($results) && !empty($criteria['hos_num'])) {
            unset($criteria['hos_num']);
            $criteria['nhs_num'] = $patient_criteria['term'];
            $results = $this->search($criteria);
        }
        
        // Convert Couchbase results to Patient models
        $patients = $this->convertToPatientModels($results);
        
        $dataProvider = new \CArrayDataProvider($patients, [
            'keyField' => 'id',
            'pagination' => [
                'pageSize' => $criteria['limit'],
            ],
        ]);
        
        return $dataProvider;
    }
    
    /**
     * Convert Couchbase search results to Patient model instances
     * @param array $results Couchbase search results
     * @return array Array of Patient models
     */
    protected function convertToPatientModels(array $results): array
    {
        $patients = [];
        
        foreach ($results as $row) {
            $data = is_object($row) ? (array)$row : $row;
            
            // Handle nested result structure (may be nested under 'patient')
            if (isset($data['patient']) && is_array($data['patient'])) {
                $patientData = $data['patient'];
                // Preserve _id from outer level if present
                if (isset($data['_id'])) {
                    $patientData['_id'] = $data['_id'];
                }
                $data = $patientData;
            }
            
            // Get the MySQL ID from the document
            $mysqlId = $data['_mysql_id'] ?? null;
            
            // If no _mysql_id, try to extract from document ID (e.g., "patient::1001" -> 1001)
            if (!$mysqlId && isset($data['_id'])) {
                $idParts = explode('::', $data['_id']);
                $mysqlId = end($idParts);
            }
            
            if (!$mysqlId) {
                \Yii::log("Skipping Couchbase patient result with no ID: " . json_encode($data), 
                          \CLogger::LEVEL_WARNING, 'application.search');
                continue;
            }
            
            // Try to load from MariaDB for model compatibility, then overlay Couchbase fields
            $patient = \Patient::model()->findByPk($mysqlId);

            if ($patient) {
                // Overlay key fields from Couchbase so Couchbase remains the source of truth
                foreach (['hos_num', 'nhs_num', 'dob', 'gender', 'date_of_death'] as $field) {
                    if (isset($data[$field]) && method_exists($patient, 'hasAttribute') && $patient->hasAttribute($field)) {
                        $patient->{$field} = $data[$field];
                    }
                }
                if (isset($data['is_deceased']) && method_exists($patient, 'hasAttribute') && $patient->hasAttribute('is_deceased')) {
                    $patient->is_deceased = (bool)$data['is_deceased'];
                }

                if (!empty($data['contact'])) {
                    $contactData = is_object($data['contact']) ? (array)$data['contact'] : $data['contact'];
                    $contact = $patient->contact ?: new \Contact();
                    $contact->setIsNewRecord(false);
                    foreach (['id','first_name','last_name','title','primary_phone'] as $cField) {
                        if (isset($contactData[$cField])) {
                            $contact->{$cField} = $contactData[$cField];
                        }
                    }
                    $patient->contact = $contact;
                }
            } else {
                // Create a pseudo-Patient from Couchbase data for display
                $patient = $this->createPatientFromCouchbaseData($data, $mysqlId);
            }
            
            if ($patient) {
                $patients[] = $patient;
            }
        }
        
        return $patients;
    }
    
    /**
     * Create a Patient model instance from Couchbase data
     * Used when patient doesn't exist in MariaDB
     * @param array $data Couchbase document data
     * @param mixed $id Patient ID
     * @return \Patient
     */
    protected function createPatientFromCouchbaseData(array $data, $id): \Patient
    {
        $patient = new \Patient();
        $patient->id = $id;
        $patient->hos_num = $data['hos_num'] ?? null;
        $patient->nhs_num = $data['nhs_num'] ?? null;
        $patient->dob = $data['dob'] ?? null;
        $patient->gender = $data['gender'] ?? null;
        $patient->date_of_death = $data['date_of_death'] ?? null;
        $patient->is_deceased = $data['is_deceased'] ?? false;
        
        // Handle embedded contact data
        if (!empty($data['contact'])) {
            $contact = new \Contact();
            $contactData = is_object($data['contact']) ? (array)$data['contact'] : $data['contact'];
            $contact->id = $contactData['id'] ?? null;
            $contact->first_name = $contactData['first_name'] ?? '';
            $contact->last_name = $contactData['last_name'] ?? '';
            $contact->title = $contactData['title'] ?? '';
            $contact->primary_phone = $contactData['primary_phone'] ?? '';
            
            // Set as relation
            $patient->contact = $contact;
        }
        
        // Mark as not new record so it can be displayed
        $patient->setIsNewRecord(false);
        
        return $patient;
    }

    /**
     * Search patients by various criteria
     * @param array $criteria Search criteria
     * @return array Search results
     */
    public function search(array $criteria)
    {
        $builder = new N1qlQueryBuilder();
        $builder->from('core', 'patient')
            ->select('META().id AS _id, _mysql_id, hos_num, nhs_num, dob, gender, date_of_death, is_deceased, contact, identifiers');
        
        // Hospital number - exact match
        if (!empty($criteria['hos_num'])) {
            $builder->where('hos_num = $hosNum', ['hosNum' => $criteria['hos_num']]);
        }
        
        // NHS number - exact match
        if (!empty($criteria['nhs_num'])) {
            $builder->where('nhs_num = $nhsNum', ['nhsNum' => $criteria['nhs_num']]);
        }
        
        // Last name - case-insensitive prefix match
        if (!empty($criteria['last_name'])) {
            $builder->whereILike('contact.last_name', $criteria['last_name'] . '%', 'lastName');
        }
        
        // First name - case-insensitive prefix match
        if (!empty($criteria['first_name'])) {
            $builder->whereILike('contact.first_name', $criteria['first_name'] . '%', 'firstName');
        }
        
        // Date of birth - exact match
        if (!empty($criteria['dob'])) {
            $builder->where('dob = $dob', ['dob' => $criteria['dob']]);
        }
        
        // Gender
        if (!empty($criteria['gender'])) {
            $builder->where('gender = $gender', ['gender' => $criteria['gender']]);
        }
        
        // Exclude deceased
        if (!empty($criteria['exclude_deceased'])) {
            $builder->whereNull('date_of_death');
        }
        
        // Default ordering
        $builder->orderBy('contact.last_name')
            ->orderBy('contact.first_name');
        
        // Pagination
        $limit = $criteria['limit'] ?? 50;
        $builder->limit($limit);
        
        if (!empty($criteria['offset'])) {
            $builder->offset($criteria['offset']);
        }
        
        return $builder->execute();
    }
    
    /**
     * Find patient by hospital number
     * @param string $hosNum Hospital number
     * @return array|null
     */
    public function findByHosNum($hosNum)
    {
        $results = $this->search(['hos_num' => $hosNum, 'limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find patient by NHS number
     * @param string $nhsNum NHS number
     * @return array|null
     */
    public function findByNhsNum($nhsNum)
    {
        $results = $this->search(['nhs_num' => $nhsNum, 'limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Search patients born on a specific date
     * @param string $dob Date of birth (YYYY-MM-DD)
     * @param int $limit Max results
     * @return array
     */
    public function findByDob($dob, $limit = 50)
    {
        return $this->search(['dob' => $dob, 'limit' => $limit]);
    }
    
    /**
     * Full name search (both first and last name)
     * @param string $name Name to search
     * @param int $limit Max results
     * @return array
     */
    public function searchByName($name, $limit = 50)
    {
        $parts = preg_split('/\s+/', trim($name), 2);
        
        $criteria = ['limit' => $limit];
        
        if (count($parts) === 1) {
            // Single term - search both first and last name
            $builder = new N1qlQueryBuilder();
            $builder->from('core', 'patient')
                ->select('META().id AS _id, hos_num, nhs_num, dob, gender, contact')
                ->where('(LOWER(contact.last_name) LIKE LOWER($term) OR LOWER(contact.first_name) LIKE LOWER($term))', 
                    ['term' => $parts[0] . '%'])
                ->orderBy('contact.last_name')
                ->orderBy('contact.first_name')
                ->limit($limit);
            
            return $builder->execute();
        }
        
        // Two terms - assume first_name last_name
        $criteria['first_name'] = $parts[0];
        $criteria['last_name'] = $parts[1];
        
        return $this->search($criteria);
    }
    
    /**
     * Get count of patients matching criteria
     * @param array $criteria Search criteria
     * @return int
     */
    public function count(array $criteria)
    {
        $builder = new N1qlQueryBuilder();
        $builder->from('core', 'patient');
        
        if (!empty($criteria['hos_num'])) {
            $builder->where('hos_num = $hosNum', ['hosNum' => $criteria['hos_num']]);
        }
        if (!empty($criteria['nhs_num'])) {
            $builder->where('nhs_num = $nhsNum', ['nhsNum' => $criteria['nhs_num']]);
        }
        if (!empty($criteria['last_name'])) {
            $builder->whereILike('contact.last_name', $criteria['last_name'] . '%', 'lastName');
        }
        
        return $builder->count();
    }
}
