<?php
/**
 * MariaDB transaction wrapper implementing TransactionInterface
 */

namespace OE\Database;

class MariaDbTransaction implements TransactionInterface
{
    /**
     * @var \CDbTransaction The underlying Yii transaction
     */
    private $transaction;
    
    /**
     * @param \CDbTransaction $transaction Yii database transaction
     */
    public function __construct(\CDbTransaction $transaction)
    {
        $this->transaction = $transaction;
    }
    
    /**
     * {@inheritdoc}
     */
    public function commit(): void
    {
        if ($this->transaction->getActive()) {
            $this->transaction->commit();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        if ($this->transaction->getActive()) {
            $this->transaction->rollback();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isActive(): bool
    {
        return $this->transaction->getActive();
    }
    
    /**
     * Get the underlying Yii transaction object
     * @return \CDbTransaction
     */
    public function getTransaction(): \CDbTransaction
    {
        return $this->transaction;
    }
}
