<?php
/**
 * Patient document model for direct Couchbase operations
 * 
 * Use this model when you need to:
 * - Query Couchbase directly (bypassing MariaDB)
 * - Perform Couchbase-specific operations
 * - Access embedded data efficiently
 */

class PatientDocument extends CouchbaseActiveRecord
{
    /**
     * @return string Document type
     */
    public function documentType()
    {
        return 'patient';
    }
    
    /**
     * @return string Couchbase scope
     */
    public function scope()
    {
        return 'core';
    }
    
    /**
     * @return string Collection name
     */
    public function collectionName()
    {
        return 'patient';
    }
    
    /**
     * @return array Validation rules
     */
    public function rules()
    {
        return [
            [['dob'], 'required'],
            ['hos_num', 'length', 'max' => 40],
            ['nhs_num', 'length', 'max' => 40],
            ['gender', 'in', 'range' => ['M', 'F', 'U', null]],
        ];
    }
    
    /**
     * @return array Attribute labels
     */
    public function attributeLabels()
    {
        return [
            'hos_num' => 'Hospital Number',
            'nhs_num' => 'NHS Number',
            'dob' => 'Date of Birth',
            'gender' => 'Gender',
            'contact.first_name' => 'First Name',
            'contact.last_name' => 'Last Name',
        ];
    }
    
    /**
     * Get contact first name (from embedded contact)
     * @return string|null
     */
    public function getFirstName()
    {
        return isset($this->contact['first_name']) ? $this->contact['first_name'] : null;
    }
    
    /**
     * Get contact last name (from embedded contact)
     * @return string|null
     */
    public function getLastName()
    {
        return isset($this->contact['last_name']) ? $this->contact['last_name'] : null;
    }
    
    /**
     * Get full name
     * @return string
     */
    public function getFullName()
    {
        return trim($this->getFirstName() . ' ' . $this->getLastName());
    }
    
    /**
     * Get primary address (from embedded addresses)
     * @return array|null
     */
    public function getPrimaryAddress()
    {
        if (empty($this->addresses)) {
            return null;
        }
        
        foreach ($this->addresses as $addr) {
            if (!empty($addr['is_primary'])) {
                return $addr;
            }
        }
        
        return $this->addresses[0] ?? null;
    }
    
    /**
     * Find patient by hospital number
     * @param string $hosNum Hospital number
     * @return PatientDocument|null
     */
    public static function findByHosNum($hosNum)
    {
        $results = static::findAllByAttributes(['hos_num' => $hosNum], ['limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find patient by NHS number
     * @param string $nhsNum NHS number
     * @return PatientDocument|null
     */
    public static function findByNhsNum($nhsNum)
    {
        $results = static::findAllByAttributes(['nhs_num' => $nhsNum], ['limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Search patients by name
     * @param string $lastName Last name (or partial)
     * @param string|null $firstName First name (or partial)
     * @param int $limit Maximum results
     * @return array Array of PatientDocument
     */
    public static function searchByName($lastName, $firstName = null, $limit = 50)
    {
        $conn = Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META().id as _key, p.* 
                  FROM `{$bucket}`.`core`.`patient` p 
                  WHERE p._type = 'patient' 
                  AND LOWER(p.contact.last_name) LIKE LOWER(\$lastName)";
        $params = ['lastName' => $lastName . '%'];
        
        if ($firstName) {
            $query .= " AND LOWER(p.contact.first_name) LIKE LOWER(\$firstName)";
            $params['firstName'] = $firstName . '%';
        }
        
        $query .= " ORDER BY p.contact.last_name, p.contact.first_name LIMIT \$limit";
        $params['limit'] = $limit;
        
        try {
            $results = $conn->query($query, $params);
            $models = [];
            
            foreach ($results as $row) {
                $model = new static();
                $data = is_object($row) ? (array)$row : $row;
                
                // Handle nested result structure
                if (isset($data['p'])) {
                    $data = array_merge($data, (array)$data['p']);
                    unset($data['p']);
                }
                
                $model->setAttributes($data);
                $model->setPrimaryKey(isset($data['_mysql_id']) ? $data['_mysql_id'] : null);
                $model->setIsNewRecord(false);
                $models[] = $model;
            }
            
            return $models;
        } catch (\Exception $e) {
            Yii::log("PatientDocument search error: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Search patients by date of birth
     * @param string $dob Date of birth (YYYY-MM-DD)
     * @param int $limit Maximum results
     * @return array Array of PatientDocument
     */
    public static function searchByDob($dob, $limit = 50)
    {
        return static::findAllByAttributes(['dob' => $dob], ['limit' => $limit]);
    }
    
    /**
     * Get episodes for this patient
     * @return array Array of EpisodeDocument
     */
    public function getEpisodes()
    {
        return EpisodeDocument::findAllByAttributes(
            ['patient_id' => $this->getPrimaryKey()],
            ['order' => 'start_date DESC']
        );
    }
    
    /**
     * Check if patient is deceased
     * @return bool
     */
    public function isDeceased()
    {
        return !empty($this->is_deceased) || !empty($this->date_of_death);
    }
    
    /**
     * Get age in years
     * @return int|null
     */
    public function getAge()
    {
        if (empty($this->dob)) {
            return null;
        }
        
        $dob = new DateTime($this->dob);
        $now = $this->isDeceased() && !empty($this->date_of_death) 
            ? new DateTime($this->date_of_death)
            : new DateTime();
            
        return $dob->diff($now)->y;
    }
    
    /**
     * Get corresponding MariaDB Patient model
     * @return Patient|null
     */
    public function getMariaDbModel()
    {
        $pk = $this->getPrimaryKey();
        return $pk ? Patient::model()->findByPk($pk) : null;
    }
}
