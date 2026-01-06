<?php
/**
 * Couchbase adapter implementing DatabaseAdapterInterface
 * 
 * This adapter provides a unified interface for Couchbase operations,
 * translating the generic database operations to Couchbase-specific
 * key-value and N1QL query operations.
 */

namespace OE\Database;

use Couchbase\Collection;
use Couchbase\QueryOptions;
use Couchbase\MutationResult;
use Couchbase\GetResult;
use Couchbase\Exception\DocumentNotFoundException;

class CouchbaseAdapter implements DatabaseAdapterInterface
{
    /**
     * @var \CouchbaseConnection Couchbase connection component
     */
    private $connection;
    
    /**
     * @var array Collection to scope mapping
     */
    private $scopeMapping = [];
    
    /**
     * @var string Bucket name
     */
    private $bucket;
    
    /**
     * @param \CouchbaseConnection|null $connection Couchbase connection
     */
    public function __construct(\CouchbaseConnection $connection = null)
    {
        $this->connection = $connection ?: \Yii::app()->couchbase;
        $this->bucket = $this->connection->config['bucket'];
        $this->initializeScopeMapping();
    }
    
    /**
     * Initialize the collection to scope mapping
     */
    private function initializeScopeMapping(): void
    {
        $this->scopeMapping = [
            // Core entities
            'patient' => 'core',
            'user' => 'core',
            'episode' => 'core',
            'event' => 'core',
            'firm' => 'core',
            'site' => 'core',
            'institution' => 'core',
            'contact' => 'core',
            'address' => 'core',
            
            // Clinical data
            'examination' => 'clinical',
            'diagnosis' => 'clinical',
            
            // Correspondence
            'letter' => 'correspondence',
            'message' => 'correspondence',
            'document' => 'correspondence',
            
            // Booking
            'operation' => 'booking',
            'session' => 'booking',
            'whiteboard' => 'booking',
            
            // Admin
            'audit' => 'admin',
            'audit_type' => 'admin',
            'audit_action' => 'admin',
            'setting' => 'admin',
            'setting_metadata' => 'admin',
            'setting_installation' => 'admin',
            'setting_institution' => 'admin',
            'setting_site' => 'admin',
            'setting_firm' => 'admin',
            'setting_user' => 'admin',
            'setting_group' => 'admin',
            'setting_field_type' => 'admin',
            'user_authentication' => 'admin',
            'user_authentication_method' => 'admin',
            'institution_authentication' => 'admin',
            'auth_item' => 'admin',
            'auth_assignment' => 'admin',
            
            // Reference data - Phase 10 & 11
            'specialty' => 'reference',
            'subspecialty' => 'reference',
            'event_type' => 'reference',
            'element_type' => 'reference',
            'event_group' => 'reference',
            'eye' => 'reference',
            'gender' => 'reference',
            'ethnic_group' => 'reference',
            'procedure_type' => 'reference',
            
            // Phase 11: Clinical reference data
            'disorder' => 'reference',
            'medication' => 'reference',
            'procedure' => 'reference',
            'drug' => 'reference',
            'allergy' => 'reference',
            'medication_route' => 'reference',
            'medication_form' => 'reference',
            'medication_frequency' => 'reference',
            'medication_duration' => 'reference',
            'medication_laterality' => 'reference',
            'benefit' => 'reference',
            'complication' => 'reference',
            'common_ophthalmic_disorder' => 'reference',
            'opcs_code' => 'reference',

            // Examination workflows (Couchbase-only)
            'ophciexamination_workflow' => 'reference',
            'ophciexamination_workflow_rule' => 'reference',
            
            // Examination reference data
            'ophciexamination_allergy' => 'reference',
            'ophciexamination_allergy_reaction' => 'reference',
            'ophciexamination_allergy_set' => 'reference',
            'ophciexamination_allergy_set_entry' => 'reference',
            'ophciexamination_advice_leaflet' => 'reference',
            'ophciexamination_advice_leaflet_category' => 'reference',
            'ophciexamination_advice_leaflet_category_assignment' => 'reference',
            'ophciexamination_advice_leaflet_entry' => 'reference',
            
            // Address reference data
            'address_type' => 'reference',
            
            // Anaesthetic reference data
            'anaesthetic_agent' => 'reference',
            'anaesthetic_complication' => 'reference',
            'anaesthetic_type' => 'reference',
            
            // OphCiExamination lookup/reference tables
            'ophciexamination_correctiontype' => 'reference',
            'ophciexamination_medication_stop_reason' => 'reference',
            'ophciexamination_stereoacuity_method' => 'reference',
            
            // Prescription reference data
            'ophdrprescription_dispense_condition' => 'reference',
            'ophdrprescription_dispense_location' => 'reference',
            'ophdrprescription_edit_reasons' => 'reference',
            
            // OphTrLaser reference data
            'ophtrlaser_laserprocedure' => 'reference',
            'ophtrlaser_site_laser' => 'reference',
            
            // OphTrOperationbooking - explicitly mapped to clinical scope
            'ophtroperationbooking_operation_theatre' => 'clinical',
            'ophtroperationbooking_operation_ward' => 'clinical',
            
            // OphTrOperationnote - explicitly mapped to clinical scope
            'ophtroperationnote_postop_drug' => 'clinical',
            
            // OphCoCorrespondence - explicitly mapped to clinical scope
            'ophcocorrespondence_letter_string_group' => 'clinical',
            'ophcocorrespondence_letter_string' => 'clinical',
            'ophcocorrespondence_letter_string_institution' => 'clinical',
            'ophcocorrespondence_default_recipient_email_templates' => 'clinical',
            'ophcocorrespondence_letter_type' => 'clinical',
            'ophcocorrespondence_letter_recipient' => 'clinical',
            'ophcocorrespondence_internal_referral_settings' => 'clinical',
            'ophcocorrespondence_letter_macro' => 'clinical',
            
            // OphCoCorrespondence - reference scope (as defined by model)
            'ophcocorrespondence_sender_email_addresses' => 'reference',
            'ophcocorrespondence_letter_macro' => 'reference',
            'ophcocorrespondence_letter_macro_institution' => 'reference',
            'ophcocorrespondence_letter_macro_site' => 'reference',
            'ophcocorrespondence_letter_macro_subspecialty' => 'reference',
            'ophcocorrespondence_letter_macro_firm' => 'reference',
            
            // Messaging
            'mailbox' => 'messaging',
            'mailbox_team' => 'messaging',
            'mailbox_user' => 'messaging',
            'ophcomessaging_message_recipient' => 'messaging',
            'ophcomessaging_message_message_type' => 'messaging',
        ];
    }
    
    /**
     * Get the scope name for a collection
     * @param string $collection Collection name
     * @return string Scope name
     */
    public function getScopeForCollection(string $collection): string
    {
        // Direct mapping
        if (isset($this->scopeMapping[$collection])) {
            return $this->scopeMapping[$collection];
        }
        
        // Check for pattern matches (e.g., examination elements)
        if (strpos($collection, 'et_ophciexamination_') === 0) {
            return 'clinical';
        }
        // OphCiExamination reference/lookup tables (e.g., ophciexamination_familyhistory_condition)
        if (strpos($collection, 'ophciexamination_') === 0) {
            return 'clinical';
        }
        if (strpos($collection, 'archive_') === 0) {
            return 'clinical';
        }
        if (strpos($collection, 'ophtr') === 0) {
            return 'booking';
        }
        if (strpos($collection, 'ophco') === 0) {
            return 'correspondence';
        }
        
        // Default to core scope
        return 'core';
    }
    
    /**
     * Get a Couchbase collection object
     * @param string $collection Collection name
     * @return Collection
     */
    private function getCollection(string $collection): Collection
    {
        $scope = $this->getScopeForCollection($collection);
        return $this->connection->getCollection($scope, $collection);
    }
    
    /**
     * Generate a document key
     * @param string $collection Collection name
     * @param mixed $pk Primary key (optional)
     * @return string Document key
     */
    private function generateKey(string $collection, $pk = null): string
    {
        if ($pk !== null) {
            return "{$collection}::{$pk}";
        }
        return "{$collection}::" . uniqid('', true);
    }
    
    /**
     * Extract the ID portion from a document key
     * @param string $key Document key
     * @return string ID portion
     */
    private function extractId(string $key): string
    {
        $parts = explode('::', $key);
        return end($parts);
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByPk(string $collection, $pk): ?array
    {
        try {
            $key = $this->generateKey($collection, $pk);
            $result = $this->getCollection($collection)->get($key);
            
            $data = $result->content();
            if (is_object($data)) {
                $data = (array)$data;
            }
            $data['id'] = $pk;
            
            return $data;
        } catch (DocumentNotFoundException $e) {
            return null;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase findByPk failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return null;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByAttributes(string $collection, array $attributes, array $options = []): array
    {
        $scope = $this->getScopeForCollection($collection);
        
        // Build N1QL query
        $selectClause = $options['select'] ?? '*';
        $query = "SELECT META().id as _meta_id, {$selectClause} FROM `{$this->bucket}`.`{$scope}`.`{$collection}`";
        
        $conditions = [];
        $params = [];
        $paramIndex = 0;
        
        foreach ($attributes as $key => $value) {
            if ($value === null) {
                $conditions[] = "`$key` IS NULL";
            } elseif (is_array($value)) {
                // Handle IN clause
                $placeholders = [];
                foreach ($value as $v) {
                    $paramName = "p{$paramIndex}";
                    $placeholders[] = "\${$paramName}";
                    $params[$paramName] = $v;
                    $paramIndex++;
                }
                $conditions[] = "`$key` IN [" . implode(', ', $placeholders) . "]";
            } else {
                $paramName = "p{$paramIndex}";
                $conditions[] = "`$key` = \${$paramName}";
                $params[$paramName] = $value;
                $paramIndex++;
            }
        }
        
        if (!empty($conditions)) {
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        // Apply options
        if (isset($options['order'])) {
            $query .= " ORDER BY " . $options['order'];
        }
        if (isset($options['limit'])) {
            $query .= " LIMIT " . (int)$options['limit'];
        }
        if (isset($options['offset'])) {
            $query .= " OFFSET " . (int)$options['offset'];
        }
        
        $queryOptions = new QueryOptions();
        if (!empty($params)) {
            $queryOptions->namedParameters($params);
        }
        
        try {
            $result = $this->connection->getCluster()->query($query, $queryOptions);
            
            // Transform results
            $rows = [];
            foreach ($result->rows() as $row) {
                $rowArray = (array)$row;
                
                // Extract document data
                if (isset($rowArray[$collection])) {
                    $data = (array)$rowArray[$collection];
                } else {
                    $data = $rowArray;
                }
                
                // Set ID from meta
                if (isset($rowArray['_meta_id'])) {
                    $data['id'] = $this->extractId($rowArray['_meta_id']);
                    unset($data['_meta_id']);
                }
                
                $rows[] = $data;
            }
            
            return $rows;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase findByAttributes failed for {$collection}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return [];
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function findAll(string $collection, array $options = []): array
    {
        return $this->findByAttributes($collection, [], $options);
    }
    
    /**
     * {@inheritdoc}
     */
    public function insert(string $collection, array $data)
    {
        // Generate ID if not provided
        $id = $data['id'] ?? uniqid('', true);
        unset($data['id']); // Don't store id in document body
        
        $key = $this->generateKey($collection, $id);
        
        // Add metadata
        $data['_type'] = $collection;
        $data['_created'] = date('c');
        $data['_modified'] = date('c');
        
        try {
            $this->getCollection($collection)->insert($key, $data);
            return $id;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase insert failed for {$collection}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function update(string $collection, $pk, array $data): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            unset($data['id']); // Don't store id in document body
            
            // Update modified timestamp
            $data['_modified'] = date('c');
            
            // Preserve _type and _created
            $existing = $this->findByPk($collection, $pk);
            if ($existing) {
                $data['_type'] = $existing['_type'] ?? $collection;
                $data['_created'] = $existing['_created'] ?? date('c');
            }
            
            $this->getCollection($collection)->replace($key, $data);
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase update failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $collection, $pk): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            $this->getCollection($collection)->remove($key);
            return true;
        } catch (DocumentNotFoundException $e) {
            // Document doesn't exist, consider delete successful
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase delete failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function query(string $query, array $params = []): array
    {
        $options = new QueryOptions();
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        try {
            $result = $this->connection->getCluster()->query($query, $options);
            
            return array_map(function($row) {
                return (array)$row;
            }, $result->rows());
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase query failed: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): TransactionInterface
    {
        return new CouchbaseTransaction($this->connection->getCluster());
    }
    
    /**
     * {@inheritdoc}
     */
    public function count(string $collection, array $criteria = []): int
    {
        $scope = $this->getScopeForCollection($collection);
        
        $query = "SELECT COUNT(*) as cnt FROM `{$this->bucket}`.`{$scope}`.`{$collection}`";
        
        $params = [];
        $paramIndex = 0;
        
        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $key => $value) {
                if ($value === null) {
                    $conditions[] = "`$key` IS NULL";
                } else {
                    $paramName = "p{$paramIndex}";
                    $conditions[] = "`$key` = \${$paramName}";
                    $params[$paramName] = $value;
                    $paramIndex++;
                }
            }
            $query .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $options = new QueryOptions();
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        try {
            $result = $this->connection->getCluster()->query($query, $options);
            $rows = $result->rows();
            
            return (int)($rows[0]['cnt'] ?? 0);
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase count failed for {$collection}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return 0;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function exists(string $collection, $pk): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            $result = $this->getCollection($collection)->exists($key);
            return $result->exists();
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Upsert a document (insert or update)
     * @param string $collection Collection name
     * @param mixed $pk Primary key
     * @param array $data Document data
     * @return bool Success status
     */
    public function upsert(string $collection, $pk, array $data): bool
    {
        try {
            $key = $this->generateKey($collection, $pk);
            unset($data['id']);
            
            $data['_type'] = $collection;
            $data['_modified'] = date('c');
            
            // Check if document exists for _created
            $existing = $this->findByPk($collection, $pk);
            $data['_created'] = $existing['_created'] ?? date('c');
            
            $this->getCollection($collection)->upsert($key, $data);
            return true;
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase upsert failed for {$collection}:{$pk}: " . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            return false;
        }
    }
    
    /**
     * Get the underlying Couchbase connection
     * @return \CouchbaseConnection
     */
    public function getConnection(): \CouchbaseConnection
    {
        return $this->connection;
    }
    
    /**
     * Set custom scope mapping
     * @param array $mapping Collection to scope mapping
     */
    public function setScopeMapping(array $mapping): void
    {
        $this->scopeMapping = array_merge($this->scopeMapping, $mapping);
    }
}
