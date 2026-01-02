<?php
/**
 * Couchbase document model for Medication
 * Includes route, form, frequency embeddings for clinical display
 */

class MedicationDocument extends CouchbaseActiveRecord
{
    protected $documentType = 'medication';
    protected $scope = 'reference';
    protected $collection = 'medication';

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
     * Create document from Medication model
     * @param Medication $medication
     * @return array
     */
    public static function createFromModel($medication)
    {
        $doc = [
            '_type' => 'medication',
            'id' => (int)$medication->id,
            'preferred_term' => $medication->preferred_term,
            'short_term' => $medication->short_term,
            'preferred_code' => $medication->preferred_code,
            'source_type' => $medication->source_type,
            'source_subtype' => $medication->source_subtype,
            'vtm_term' => $medication->vtm_term,
            'vtm_code' => $medication->vtm_code,
            'vmp_term' => $medication->vmp_term,
            'vmp_code' => $medication->vmp_code,
            'amp_term' => $medication->amp_term,
            'amp_code' => $medication->amp_code,
            'default_form_id' => $medication->default_form_id ? (int)$medication->default_form_id : null,
            'default_route_id' => $medication->default_route_id ? (int)$medication->default_route_id : null,
            'default_frequency_id' => isset($medication->default_frequency_id) ? (int)$medication->default_frequency_id : null,
            'default_duration_id' => isset($medication->default_duration_id) ? (int)$medication->default_duration_id : null,
            'default_dose' => $medication->default_dose,
            'default_dose_unit_term' => $medication->default_dose_unit_term,
            'active' => isset($medication->active) ? (bool)$medication->active : true,
            'deleted_date' => $medication->deleted_date,
            'created_date' => $medication->created_date,
            'last_modified_date' => $medication->last_modified_date,
            
            // Computed fields for search
            'preferred_term_lower' => strtolower($medication->preferred_term ?? ''),
            'search_terms' => self::buildSearchTerms($medication),
        ];
        
        // Embed route
        if ($medication->default_route_id && $medication->defaultRoute) {
            $doc['default_route'] = [
                'id' => (int)$medication->defaultRoute->id,
                'term' => $medication->defaultRoute->term,
                'code' => $medication->defaultRoute->code ?? null,
            ];
        }
        
        // Embed form
        if ($medication->default_form_id && $medication->defaultForm) {
            $doc['default_form'] = [
                'id' => (int)$medication->defaultForm->id,
                'term' => $medication->defaultForm->term,
                'code' => $medication->defaultForm->code ?? null,
            ];
        }
        
        // Embed frequency if available
        if (isset($medication->default_frequency_id) && $medication->default_frequency_id) {
            $frequency = MedicationFrequency::model()->findByPk($medication->default_frequency_id);
            if ($frequency) {
                $doc['default_frequency'] = [
                    'id' => (int)$frequency->id,
                    'term' => $frequency->term,
                    'code' => $frequency->code ?? null,
                ];
            }
        }
        
        // Embed allergy warnings
        $doc['allergy_warnings'] = self::embedAllergyWarnings($medication);
        
        return $doc;
    }

    /**
     * Build search terms for FTS
     * @param Medication $medication
     * @return array
     */
    protected static function buildSearchTerms($medication)
    {
        $terms = [];
        
        if ($medication->preferred_term) {
            $terms[] = strtolower($medication->preferred_term);
        }
        if ($medication->short_term) {
            $terms[] = strtolower($medication->short_term);
        }
        if ($medication->vtm_term) {
            $terms[] = strtolower($medication->vtm_term);
        }
        if ($medication->vmp_term) {
            $terms[] = strtolower($medication->vmp_term);
        }
        if ($medication->amp_term) {
            $terms[] = strtolower($medication->amp_term);
        }
        
        return array_unique($terms);
    }

    /**
     * Embed allergy warnings
     * @param Medication $medication
     * @return array
     */
    protected static function embedAllergyWarnings($medication)
    {
        $warnings = [];
        $assignments = MedicationAllergyAssignment::model()->with('allergy')->findAll(
            'medication_id = ?', [$medication->id]
        );
        
        if ($assignments) {
            foreach ($assignments as $assignment) {
                if ($assignment->allergy) {
                    $warnings[] = [
                        'allergy_id' => (int)$assignment->allergy_id,
                        'allergy_name' => $assignment->allergy->name,
                    ];
                }
            }
        }
        
        return $warnings;
    }

    /**
     * Search medications by term
     * @param string $term
     * @param int $limit
     * @return array
     */
    public static function search($term, $limit = 50)
    {
        $query = "SELECT META().id AS _id, m.* 
                  FROM `openeyes`.`reference`.`medication` m 
                  WHERE m.preferred_term_lower LIKE \$term 
                  AND (m.active = true OR m.active IS MISSING)
                  AND (m.deleted_date IS NULL OR m.deleted_date IS MISSING)
                  ORDER BY m.preferred_term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit
        ]);
    }

    /**
     * Find by preferred code (dm+d code)
     * @param string $code
     * @return array|null
     */
    public static function findByCode($code)
    {
        $query = "SELECT META().id AS _id, m.* 
                  FROM `openeyes`.`reference`.`medication` m 
                  WHERE m.preferred_code = \$code";
        
        $result = self::executeQuery($query, ['code' => $code]);
        return !empty($result) ? $result[0] : null;
    }

    /**
     * Find by route
     * @param int $routeId
     * @param int $limit
     * @return array
     */
    public static function findByRoute($routeId, $limit = 100)
    {
        $query = "SELECT META().id AS _id, m.* 
                  FROM `openeyes`.`reference`.`medication` m 
                  WHERE m.default_route_id = \$routeId 
                  AND (m.active = true OR m.active IS MISSING)
                  ORDER BY m.preferred_term 
                  LIMIT \$limit";
        
        return self::executeQuery($query, [
            'routeId' => $routeId,
            'limit' => $limit
        ]);
    }

    /**
     * Find medications with allergy warnings for specific allergy
     * @param int $allergyId
     * @return array
     */
    public static function findWithAllergyWarning($allergyId)
    {
        $query = "SELECT META().id AS _id, m.* 
                  FROM `openeyes`.`reference`.`medication` m 
                  WHERE ANY w IN m.allergy_warnings SATISFIES w.allergy_id = \$allergyId END 
                  AND (m.active = true OR m.active IS MISSING)";
        
        return self::executeQuery($query, ['allergyId' => $allergyId]);
    }
}
