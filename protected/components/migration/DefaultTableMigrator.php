<?php
/**
 * Default table migrator for generic tables
 */

namespace OE\Migration;

use OE\Database\DatabaseAdapterFactory;
use OE\Database\DatabaseAdapterInterface;

class DefaultTableMigrator implements TableMigrator
{
    protected $table;
    protected $adapter;
    protected $scope;
    protected $collection;
    protected $schema;
    
    public function __construct(string $table)
    {
        $this->table = $table;
        $this->adapter = DatabaseAdapterFactory::getAdapter(
            DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
        
        // Determine scope and collection from config
        $configPath = \Yii::getPathOfAlias('application.config') . '/couchbase-collection-map.php';
        if (file_exists($configPath)) {
            $collectionMap = require($configPath);
        } else {
            $collectionMap = [];
        }
        
        $this->scope = '_default';
        $this->collection = $table;
        
        foreach ($collectionMap as $scopeName => $collections) {
            if (in_array($table, $collections)) {
                $this->scope = $scopeName;
                $this->collection = $table;
                break;
            }
        }
        
        // Cache schema for type transformation
        try {
            $this->schema = \Yii::app()->cbdb->getSchema()->getTable($this->table);
        } catch (\Exception $e) {
            $this->schema = null;
        }
    }
    
    public function migrate(array $record): void
    {
        $doc = $this->transformRecord($record);
        $doc['_type'] = $this->table;
        $doc['_migrated'] = date('c');
        $doc['_source'] = 'mariadb';
        $doc['_mysql_id'] = $record['id'];
        
        $this->adapter->upsert($this->collection, $record['id'], $doc);
    }
    
    protected function transformRecord(array $record): array
    {
        $doc = [];
        
        foreach ($record as $column => $value) {
            if ($this->schema && isset($this->schema->columns[$column])) {
                $doc[$column] = TypeTransformer::transform(
                    $value,
                    $this->schema->columns[$column]->dbType
                );
            } else {
                $doc[$column] = $value;
            }
        }
        
        return $doc;
    }
    
    public function getCollection(): string
    {
        return $this->collection;
    }
    
    public function getTable(): string
    {
        return $this->table;
    }
}
