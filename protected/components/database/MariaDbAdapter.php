<?php
/**
 * MariaDB/MySQL adapter implementing DatabaseAdapterInterface
 * 
 * This adapter wraps the existing Yii CDbConnection to provide
 * a unified database interface for MariaDB/MySQL operations.
 */

namespace OE\Database;

class MariaDbAdapter implements DatabaseAdapterInterface
{
    /**
     * @var \CDbConnection Database connection
     */
    private $connection;
    
    /**
     * @param \CDbConnection|null $connection Database connection (defaults to Yii::app()->cbdb)
     */
    public function __construct(\CDbConnection $connection = null)
    {
        $this->connection = $connection ?: \Yii::app()->cbdb;
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByPk(string $collection, $pk): ?array
    {
        $result = $this->connection->createCommand()
            ->select('*')
            ->from($collection)
            ->where('id = :id', [':id' => $pk])
            ->queryRow();
            
        return $result ?: null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function findByAttributes(string $collection, array $attributes, array $options = []): array
    {
        $command = $this->connection->createCommand()
            ->select($options['select'] ?? '*')
            ->from($collection);
        
        // Build WHERE clause
        if (!empty($attributes)) {
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
                        $paramName = ":p{$paramIndex}";
                        $placeholders[] = $paramName;
                        $params[$paramName] = $v;
                        $paramIndex++;
                    }
                    $conditions[] = "`$key` IN (" . implode(', ', $placeholders) . ")";
                } else {
                    $paramName = ":p{$paramIndex}";
                    $conditions[] = "`$key` = {$paramName}";
                    $params[$paramName] = $value;
                    $paramIndex++;
                }
            }
            
            if (!empty($conditions)) {
                $command->where(implode(' AND ', $conditions), $params);
            }
        }
        
        // Apply options
        if (isset($options['order'])) {
            $command->order($options['order']);
        }
        if (isset($options['limit'])) {
            $command->limit((int)$options['limit']);
        }
        if (isset($options['offset'])) {
            $command->offset((int)$options['offset']);
        }
        if (isset($options['group'])) {
            $command->group($options['group']);
        }
        
        return $command->queryAll();
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
        $this->connection->createCommand()
            ->insert($collection, $data);
            
        return $this->connection->getLastInsertID();
    }
    
    /**
     * {@inheritdoc}
     */
    public function update(string $collection, $pk, array $data): bool
    {
        $rows = $this->connection->createCommand()
            ->update($collection, $data, 'id = :id', [':id' => $pk]);
            
        return $rows > 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(string $collection, $pk): bool
    {
        $rows = $this->connection->createCommand()
            ->delete($collection, 'id = :id', [':id' => $pk]);
            
        return $rows > 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function query(string $query, array $params = []): array
    {
        $command = $this->connection->createCommand($query);
        
        if (!empty($params)) {
            foreach ($params as $key => $value) {
                $paramKey = is_int($key) ? $key : ':' . ltrim($key, ':');
                $command->bindValue($paramKey, $value);
            }
        }
        
        return $command->queryAll();
    }
    
    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): TransactionInterface
    {
        $transaction = $this->connection->beginTransaction();
        return new MariaDbTransaction($transaction);
    }
    
    /**
     * {@inheritdoc}
     */
    public function count(string $collection, array $criteria = []): int
    {
        $command = $this->connection->createCommand()
            ->select('COUNT(*) as cnt')
            ->from($collection);
        
        if (!empty($criteria)) {
            $conditions = [];
            $params = [];
            $paramIndex = 0;
            
            foreach ($criteria as $key => $value) {
                if ($value === null) {
                    $conditions[] = "`$key` IS NULL";
                } else {
                    $paramName = ":p{$paramIndex}";
                    $conditions[] = "`$key` = {$paramName}";
                    $params[$paramName] = $value;
                    $paramIndex++;
                }
            }
            
            $command->where(implode(' AND ', $conditions), $params);
        }
        
        $result = $command->queryRow();
        return (int)($result['cnt'] ?? 0);
    }
    
    /**
     * {@inheritdoc}
     */
    public function exists(string $collection, $pk): bool
    {
        $count = $this->connection->createCommand()
            ->select('COUNT(*)')
            ->from($collection)
            ->where('id = :id', [':id' => $pk])
            ->queryScalar();
            
        return (int)$count > 0;
    }
    
    /**
     * Get the underlying database connection
     * @return \CDbConnection
     */
    public function getConnection(): \CDbConnection
    {
        return $this->connection;
    }
    
    /**
     * Execute multiple inserts in a batch
     * @param string $collection Table name
     * @param array $columns Column names
     * @param array $rows Array of row data arrays
     * @return int Number of rows inserted
     */
    public function batchInsert(string $collection, array $columns, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }
        
        $builder = $this->connection->getSchema()->getCommandBuilder();
        $table = $this->connection->getSchema()->getTable($collection);
        
        $sql = $builder->createMultipleInsertCommand($table, $rows)->getText();
        
        return $this->connection->createCommand($sql)->execute();
    }
}
