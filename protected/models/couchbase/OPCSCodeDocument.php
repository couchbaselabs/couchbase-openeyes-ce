<?php
namespace OE\Models\Couchbase;

/**
 * Couchbase document model for OPCS codes
 * OPCS (Office of Population Censuses and Surveys Classification of Interventions and Procedures)
 */
class OPCSCodeDocument
{
    /**
     * Create a Couchbase document from an OPCSCode model
     * @param \OPCSCode $opcsCode The OPCSCode model instance
     * @return array Document structure for Couchbase
     */
    public static function createFromModel($opcsCode)
    {
        return [
            '_type' => 'opcs_code',
            'id' => (int)$opcsCode->id,
            'name' => $opcsCode->name,  // This is the OPCS code itself (e.g., "C75.1")
            'description' => $opcsCode->description ?? null,
            'active' => (bool)($opcsCode->active ?? true),
            '_modified' => date('c'),
        ];
    }

    /**
     * Search OPCS codes by term
     * @param string $term Search term
     * @param int $limit Maximum results
     * @return array Search results
     */
    public static function search($term, $limit = 20)
    {
        $query = "
            SELECT o.* 
            FROM `openeyes`.`reference`.`opcs_code` o
            WHERE o.active = true
              AND (LOWER(o.name) LIKE \$term OR LOWER(o.description) LIKE \$term)
            ORDER BY o.name
            LIMIT \$limit
        ";
        
        $options = [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit,
        ];
        
        return \Yii::app()->couchbase->query($query, $options);
    }

    /**
     * Find OPCS code by exact code name
     * @param string $code OPCS code name (e.g., "C75.1")
     * @return array|null Document or null if not found
     */
    public static function findByCode($code)
    {
        $query = "
            SELECT o.*
            FROM `openeyes`.`reference`.`opcs_code` o
            WHERE o.name = \$code AND o.active = true
            LIMIT 1
        ";
        
        $results = \Yii::app()->couchbase->query($query, ['code' => $code]);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Find all active OPCS codes
     * @param int $limit Maximum results
     * @return array All active codes
     */
    public static function findAll($limit = 1000)
    {
        $query = "
            SELECT o.*
            FROM `openeyes`.`reference`.`opcs_code` o
            WHERE o.active = true
            ORDER BY o.name
            LIMIT \$limit
        ";
        
        return \Yii::app()->couchbase->query($query, ['limit' => $limit]);
    }
}
