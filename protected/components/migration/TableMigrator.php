<?php
/**
 * Interface for table migrators
 */

namespace OE\Migration;

interface TableMigrator
{
    /**
     * Migrate a single record
     * @param array $record Raw database record
     * @return void
     * @throws \Exception on migration failure
     */
    public function migrate(array $record): void;
    
    /**
     * Get the target Couchbase collection name
     * @return string
     */
    public function getCollection(): string;
    
    /**
     * Get the source MySQL table name
     * @return string
     */
    public function getTable(): string;
}
