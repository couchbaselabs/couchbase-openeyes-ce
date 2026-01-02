<?php
/**
 * Couchbase document model for Disorder
 * Optimized for SNOMED code lookups and clinical searches
 */

class DisorderDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'disorder';
    protected $scope = 'reference';
    protected $collection = 'disorder';

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
     * Create document from Disorder model
     * @param Disorder $disorder
     * @return array
     */
    public static function createFromModel($disorder)
    {
        $doc = [
            '_type' => 'disorder',
            'id' => (int)$disorder->id,
            'fully_specified_name' => $disorder->fully_specified_name,
            'term' => $disorder->term,
            'aliases' => $disorder->aliases,
            'systemic' => (bool)$disorder->systemic,
            'specialty_id' => $disorder->specialty_id ? (int)$disorder->specialty_id : null,
            'active' => (bool)$disorder->active,
            'created_date' => $disorder->created_date,
            'last_modified_date' => $disorder->last_modified_date,
            
            // Computed fields for search
            'term_lower' => strtolower($disorder->term ?? ''),
            'search_terms' => self::buildSearchTerms($disorder),
        ];
        
        // Add SNOMED codes if present
        if (isset($disorder->snomed_code)) {
            $doc['snomed_code'] = $disorder->snomed_code;
        }
        if (isset($disorder->snomed_version)) {
            $doc['snomed_version'] = $disorder->snomed_version;
        }
        
        // Add ECDS codes if present
        if (isset($disorder->ecds_code)) {
            $doc['ecds_code'] = $disorder->ecds_code;
        }
        if (isset($disorder->ecds_term)) {
            $doc['ecds_term'] = $disorder->ecds_term;
        }
        
        // Add ICD10 codes if present
        if (isset($disorder->icd10_code)) {
            $doc['icd10_code'] = $disorder->icd10_code;
        }
        if (isset($disorder->icd10_term)) {
            $doc['icd10_term'] = $disorder->icd10_term;
        }
        
        // Embed specialty
        if ($disorder->specialty_id && $disorder->specialty) {
            $doc['specialty'] = [
                'id' => (int)$disorder->specialty->id,
                'name' => $disorder->specialty->name,
                'code' => $disorder->specialty->code ?? null,
            ];
        }
        
        // Flag for common disorders
        $doc['is_common_ophthalmic'] = CommonOphthalmicDisorder::model()->exists(
            'disorder_id = ?', [$disorder->id]
        );
        
        $doc['is_systemic'] = CommonSystemicDisorder::model()->exists(
            'disorder_id = ?', [$disorder->id]
        );
        
        return $doc;
    }

    /**
     * Build search terms array for FTS
     * @param Disorder $disorder
     * @return array
     */
    protected static function buildSearchTerms($disorder)
    {
        $terms = [];
        
        if ($disorder->term) {
            $terms[] = strtolower($disorder->term);
        }
        if ($disorder->fully_specified_name) {
            $terms[] = strtolower($disorder->fully_specified_name);
        }
        if ($disorder->aliases) {
            $aliases = explode(',', $disorder->aliases);
            foreach ($aliases as $alias) {
                $terms[] = strtolower(trim($alias));
            }
        }
        
        return array_unique($terms);
    }

    /**
     * Find by SNOMED code
     * @param string $snomedCode
     * @return array|null
     */
    public static function findBySnomedCode($snomedCode)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`disorder` d 
                  WHERE d.snomed_code = \$snomedCode";
        
        $result = self::executeQuery($query, ['snomedCode' => $snomedCode]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Search disorders by term
     * @param string $term
     * @param int $limit
     * @param bool $activeOnly
     * @return array
     */
    public static function search($term, $limit = 50, $activeOnly = true)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`disorder` d 
                  WHERE d.term_lower LIKE \$term";
        
        if ($activeOnly) {
            $query .= " AND d.active = true";
        }
        
        $query .= " ORDER BY d.term LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }

    /**
     * Find common ophthalmic disorders
     * @param int $subspecialtyId
     * @return array
     */
    public static function findCommonOphthalmic($subspecialtyId = null)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`disorder` d 
                  WHERE d.is_common_ophthalmic = true 
                  AND d.active = true";
        
        $params = [];
        
        if ($subspecialtyId) {
            $query .= " AND d.specialty_id = \$subspecialtyId";
            $params['subspecialtyId'] = $subspecialtyId;
        }
        
        $query .= " ORDER BY d.term";
        
        return self::executeQuery($query, $params);
    }

    /**
     * Find by specialty
     * @param int $specialtyId
     * @param int $limit
     * @return array
     */
    public static function findBySpecialty($specialtyId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, d.* 
                  FROM `openeyes`.`reference`.`disorder` d 
                  WHERE d.specialty_id = \$specialtyId 
                  AND d.active = true 
                  ORDER BY d.term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'specialtyId' => $specialtyId,
            'limit' => $limit
        ]);
    }
}
