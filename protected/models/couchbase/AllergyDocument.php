<?php
namespace OE\Models\Couchbase;

/**
 * Couchbase document model for Allergies
 */
class AllergyDocument
{
    /**
     * Create a Couchbase document from an Allergy model
     * @param \Allergy $allergy The Allergy model instance
     * @return array Document structure for Couchbase
     */
    public static function createFromModel($allergy)
    {
        return [
            '_type' => 'allergy',
            'id' => (int)$allergy->id,
            'name' => $allergy->name,
            'active' => (bool)($allergy->active ?? true),
            '_modified' => date('c'),
        ];
    }

    /**
     * Search allergies by term
     * @param string $term Search term
     * @param int $limit Maximum results
     * @return array Search results
     */
    public static function search($term, $limit = 200)
    {
        $query = "
            SELECT a.* 
            FROM `openeyes`.`reference`.`allergy` a
            WHERE a.active = true
              AND LOWER(a.name) LIKE \$term
            ORDER BY a.name
            LIMIT \$limit
        ";
        
        $options = [
            'term' => '%' . strtolower($term) . '%',
            'limit' => $limit,
        ];
        
        return \Yii::app()->couchbase->query($query, $options);
    }

    /**
     * Find allergy by exact name
     * @param string $name Allergy name
     * @return array|null Document or null if not found
     */
    public static function findByName($name)
    {
        $query = "
            SELECT a.*
            FROM `openeyes`.`reference`.`allergy` a
            WHERE a.name = \$name AND a.active = true
            LIMIT 1
        ";
        
        $results = \Yii::app()->couchbase->query($query, ['name' => $name]);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Find all active allergies
     * @param int $limit Maximum results
     * @return array All active allergies
     */
    public static function findAll($limit = 1000)
    {
        $query = "
            SELECT a.*
            FROM `openeyes`.`reference`.`allergy` a
            WHERE a.active = true
            ORDER BY a.name
            LIMIT \$limit
        ";
        
        return \Yii::app()->couchbase->query($query, ['limit' => $limit]);
    }
}
