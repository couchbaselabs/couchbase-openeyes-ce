<?php
/**
 * Couchbase document model for Procedure
 * Includes OPCS codes, SNOMED codes, benefits, and complications
 */

class ProcedureDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'procedure';
    protected $scope = 'reference';
    protected $collection = 'procedure';

    /**
     * Get the document type
     * @return string
     */
    public function documentType()
    {
        return $this->documentType;
    }

    /**
     * Get the Couchbase scope name
     * @return string
     */
    public function scope()
    {
        return $this->scope;
    }

    /**
     * Get the Couchbase collection name
     * @return string
     */
    public function collectionName()
    {
        return $this->collection;
    }

    /**
     * Create document from Procedure model
     * @param Procedure $procedure
     * @return array
     */
    public static function createFromModel($procedure)
    {
        $doc = [
            '_type' => 'procedure',
            'id' => (int)$procedure->id,
            'term' => $procedure->term,
            'short_format' => $procedure->short_format,
            'snomed_code' => $procedure->snomed_code ?? null,
            'snomed_term' => $procedure->snomed_term ?? null,
            'ecds_code' => $procedure->ecds_code ?? null,
            'ecds_term' => $procedure->ecds_term ?? null,
            'default_duration' => $procedure->default_duration ? (int)$procedure->default_duration : null,
            'unbooked' => isset($procedure->unbooked) ? (bool)$procedure->unbooked : false,
            'active' => isset($procedure->active) ? (bool)$procedure->active : true,
            'is_clinic_proc' => isset($procedure->is_clinic_proc) ? (bool)$procedure->is_clinic_proc : false,
            'aliases' => $procedure->aliases ?? null,
            'low_complexity_criteria' => $procedure->low_complexity_criteria ?? null,
            'created_date' => $procedure->created_date ?? null,
            'last_modified_date' => $procedure->last_modified_date ?? null,
            
            // Computed for search
            'term_lower' => strtolower($procedure->term ?? ''),
        ];
        
        // Embed OPCS codes
        $doc['opcs_codes'] = self::embedOpcsCodes($procedure);
        
        // Embed benefits
        $doc['benefits'] = self::embedBenefits($procedure);
        
        // Embed complications
        $doc['complications'] = self::embedComplications($procedure);
        
        // Embed subspecialty assignments
        $doc['subspecialties'] = self::embedSubspecialties($procedure);
        
        return $doc;
    }

    /**
     * Embed OPCS codes
     */
    protected static function embedOpcsCodes($procedure)
    {
        $codes = [];
        if (!empty($procedure->opcsCodes)) {
            foreach ($procedure->opcsCodes as $opcs) {
                $codes[] = [
                    'id' => (int)$opcs->id,
                    'name' => $opcs->name,  // This is the OPCS code
                    'description' => $opcs->description ?? null,
                ];
            }
        }
        return $codes;
    }

    /**
     * Embed benefits
     */
    protected static function embedBenefits($procedure)
    {
        $benefits = [];
        if (!empty($procedure->benefits)) {
            foreach ($procedure->benefits as $benefit) {
                $benefits[] = [
                    'id' => (int)$benefit->id,
                    'name' => $benefit->name,
                ];
            }
        }
        return $benefits;
    }

    /**
     * Embed complications
     */
    protected static function embedComplications($procedure)
    {
        $complications = [];
        if (!empty($procedure->complications)) {
            foreach ($procedure->complications as $complication) {
                $complications[] = [
                    'id' => (int)$complication->id,
                    'name' => $complication->name,
                ];
            }
        }
        return $complications;
    }

    /**
     * Embed subspecialty assignments
     */
    protected static function embedSubspecialties($procedure)
    {
        $subspecialties = [];
        $assignments = ProcedureSubspecialtyAssignment::model()->with('subspecialty')->findAll(
            'proc_id = ?', [$procedure->id]
        );
        if ($assignments) {
            foreach ($assignments as $assignment) {
                if ($assignment->subspecialty) {
                    $subspecialties[] = [
                        'id' => (int)$assignment->subspecialty->id,
                        'name' => $assignment->subspecialty->name,
                    ];
                }
            }
        }
        return $subspecialties;
    }

    /**
     * Search procedures by term
     * @param string $term
     * @param int $limit
     * @return array
     */
    public static function search($term, $limit = 50)
    {
        $query = "SELECT META().id AS _id, p.* 
                  FROM `openeyes`.`reference`.`procedure` p 
                  WHERE p.term_lower LIKE \$term 
                  AND (p.active = true OR p.active IS MISSING)
                  ORDER BY p.term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }

    /**
     * Find by SNOMED code
     * @param string $snomedCode
     * @return array|null
     */
    public static function findBySnomedCode($snomedCode)
    {
        $query = "SELECT META().id AS _id, p.* 
                  FROM `openeyes`.`reference`.`procedure` p 
                  WHERE p.snomed_code = \$snomedCode";
        
        $result = self::executeQuery($query, ['snomedCode' => $snomedCode]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find by OPCS code
     * @param string $opcsCode
     * @return array
     */
    public static function findByOpcsCode($opcsCode)
    {
        $query = "SELECT META().id AS _id, p.* 
                  FROM `openeyes`.`reference`.`procedure` p 
                  WHERE ANY o IN p.opcs_codes SATISFIES o.code = \$opcsCode END";
        
        return self::executeQuery($query, ['opcsCode' => $opcsCode]);
    }

    /**
     * Find by subspecialty
     * @param int $subspecialtyId
     * @param int $limit
     * @return array
     */
    public static function findBySubspecialty($subspecialtyId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, p.* 
                  FROM `openeyes`.`reference`.`procedure` p 
                  WHERE ANY s IN p.subspecialties SATISFIES s.id = \$subspecialtyId END 
                  AND (p.active = true OR p.active IS MISSING)
                  ORDER BY p.term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'subspecialtyId' => $subspecialtyId,
            'limit' => $limit
        ]);
    }
}
