<?php
/**
 * Database adapter interface for supporting multiple database backends
 * 
 * This interface defines the contract for all database adapters,
 * enabling the application to work with different database systems
 * (MariaDB, Couchbase) through a unified API.
 */

namespace OE\Database;

interface DatabaseAdapterInterface
{
    /**
     * Find a record by primary key
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key value
     * @return array|null Record data or null if not found
     */
    public function findByPk(string $collection, $pk): ?array;
    
    /**
     * Find records by attributes
     * @param string $collection Collection/table name
     * @param array $attributes Key-value pairs to match
     * @param array $options Additional options (limit, offset, order)
     * @return array Array of matching records
     */
    public function findByAttributes(string $collection, array $attributes, array $options = []): array;
    
    /**
     * Find all records in a collection
     * @param string $collection Collection/table name
     * @param array $options Additional options (limit, offset, order)
     * @return array Array of all records
     */
    public function findAll(string $collection, array $options = []): array;
    
    /**
     * Insert a new record
     * @param string $collection Collection/table name
     * @param array $data Data to insert
     * @return mixed Inserted record ID/key
     */
    public function insert(string $collection, array $data);
    
    /**
     * Update a record by primary key
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key
     * @param array $data Data to update
     * @return bool Success status
     */
    public function update(string $collection, $pk, array $data): bool;
    
    /**
     * Delete a record by primary key
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key
     * @return bool Success status
     */
    public function delete(string $collection, $pk): bool;
    
    /**
     * Execute a raw query
     * @param string $query Query string (SQL or N1QL)
     * @param array $params Query parameters
     * @return array Query results
     */
    public function query(string $query, array $params = []): array;
    
    /**
     * Begin a database transaction
     * @return TransactionInterface Transaction object
     */
    public function beginTransaction(): TransactionInterface;
    
    /**
     * Count records matching criteria
     * @param string $collection Collection/table name
     * @param array $criteria Matching criteria
     * @return int Count of matching records
     */
    public function count(string $collection, array $criteria = []): int;
    
    /**
     * Check if a record exists
     * @param string $collection Collection/table name
     * @param mixed $pk Primary key
     * @return bool True if record exists
     */
    public function exists(string $collection, $pk): bool;
}
