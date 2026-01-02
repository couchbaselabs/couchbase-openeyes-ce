<?php
/**
 * Transaction interface for database operations
 * 
 * Provides a unified interface for managing database transactions
 * across different database backends.
 */

namespace OE\Database;

interface TransactionInterface
{
    /**
     * Commit the transaction
     * @return void
     * @throws \Exception if commit fails
     */
    public function commit(): void;
    
    /**
     * Rollback the transaction
     * @return void
     */
    public function rollback(): void;
    
    /**
     * Check if transaction is currently active
     * @return bool True if transaction is active
     */
    public function isActive(): bool;
}
