<?php
/**
 * Couchbase transaction wrapper implementing TransactionInterface
 * 
 * Couchbase 7.x supports distributed ACID transactions.
 * This class provides a compatible interface for transaction management.
 */

namespace OE\Database;

use Couchbase\Cluster;
use Couchbase\TransactionAttemptContext;

class CouchbaseTransaction implements TransactionInterface
{
    /**
     * @var Cluster Couchbase cluster instance
     */
    private $cluster;
    
    /**
     * @var TransactionAttemptContext|null Transaction context
     */
    private $context = null;
    
    /**
     * @var bool Whether transaction is active
     */
    private $active = true;
    
    /**
     * @var array Queued operations for the transaction
     */
    private $operations = [];
    
    /**
     * @var array Results from executed operations
     */
    private $results = [];
    
    /**
     * @param Cluster $cluster Couchbase cluster instance
     */
    public function __construct(Cluster $cluster)
    {
        $this->cluster = $cluster;
    }
    
    /**
     * Get the transaction context for operations within the transaction
     * @return TransactionAttemptContext|null
     */
    public function getContext(): ?TransactionAttemptContext
    {
        return $this->context;
    }
    
    /**
     * Queue an operation to be executed within the transaction
     * @param callable $operation Function that receives TransactionAttemptContext
     * @return void
     */
    public function addOperation(callable $operation): void
    {
        if (!$this->active) {
            throw new \RuntimeException('Cannot add operation to inactive transaction');
        }
        $this->operations[] = $operation;
    }
    
    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        if (!$this->active) {
            return;
        }
        
        if (empty($this->operations)) {
            $this->active = false;
            return;
        }
        
        try {
            $operations = $this->operations;
            $results = &$this->results;
            
            $this->cluster->transactions()->run(function (TransactionAttemptContext $ctx) use ($operations, &$results) {
                $this->context = $ctx;
                foreach ($operations as $index => $operation) {
                    $results[$index] = $operation($ctx);
                }
            });
            
            $this->active = false;
        } catch (\Exception $e) {
            $this->active = false;
            \Yii::log(
                'Couchbase transaction failed: ' . $e->getMessage(),
                \CLogger::LEVEL_ERROR,
                'application.couchbase'
            );
            throw $e;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        // In Couchbase, rollback is automatic when transaction is not committed
        // We simply clear the operations and mark as inactive
        $this->operations = [];
        $this->results = [];
        $this->active = false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return $this->active;
    }
    
    /**
     * Get results from committed operations
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
