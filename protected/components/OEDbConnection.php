<?php
/**
 * OpenEyes.
 *
 * 
 * Copyright OpenEyes Foundation, 2017
 *
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright 2017, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
class OEDbConnection extends CDbConnection
{
    /**
     * @var bool Whether to use static schema cache (no MariaDB required for schema)
     */
    public $useStaticSchema = false;
    
    /**
     * @var string Path to static schema cache file
     */
    public $staticSchemaFile = 'protected/config/schema-cache.php';
    
    /**
     * @var bool Whether connection is available
     */
    private $_connectionAvailable = null;
    
    /**
     * Initialize the component
     */
    public function init()
    {
        // Check if we should use static schema (when MariaDB is unavailable)
        if ($this->useStaticSchema) {
            $this->initStaticSchema();
        }
        
        // Try to initialize parent, but don't fail if MariaDB is unavailable
        try {
            parent::init();
        } catch (CDbException $e) {
            // MariaDB not available - we'll use static schema
            Yii::log('MariaDB connection failed, using static schema: ' . $e->getMessage(), CLogger::LEVEL_WARNING);
            $this->_connectionAvailable = false;
        }
    }
    
    /**
     * Check if database connection is available
     * @return bool
     */
    public function isConnectionAvailable()
    {
        // Quick check - if already determined, return cached value
        if ($this->_connectionAvailable !== null) {
            return $this->_connectionAvailable;
        }
        
        // If connection string is empty, MariaDB is removed
        if (empty($this->connectionString)) {
            $this->_connectionAvailable = false;
            return false;
        }
        
        // Default to false - will be set to true only on successful connection
        $this->_connectionAvailable = false;
        return false;
    }
    
    /**
     * Override setActive to handle connection failures gracefully
     * @param bool $value
     */
    public function setActive($value)
    {
        if ($value && $this->useStaticSchema) {
            try {
                parent::setActive($value);
                $this->_connectionAvailable = true;
            } catch (\Throwable $e) {
                // Connection failed - use static schema
                Yii::log('MariaDB connection unavailable, using static schema: ' . $e->getMessage(), CLogger::LEVEL_INFO);
                $this->_connectionAvailable = false;
            }
        } else {
            try {
                parent::setActive($value);
            } catch (\Throwable $e) {
                $this->_connectionAvailable = false;
                throw $e;
            }
        }
    }
    
    /**
     * Initialize static schema support
     */
    protected function initStaticSchema()
    {
        // Override the schema class to use CouchbaseDbSchema
        $this->driverMap = array_merge($this->driverMap, [
            'mysql' => 'CouchbaseDbSchema',
            'mysqli' => 'CouchbaseDbSchema',
        ]);
    }
    
    /**
     * @var CDbSchema Cached schema instance
     */
    private $_staticSchema;
    
    /**
     * Create schema instance with static schema support
     * @return CDbSchema
     */
    protected function createSchema()
    {
        // For static schema mode, create schema without requiring connection
        if ($this->useStaticSchema && !$this->isConnectionAvailable()) {
            if ($this->_staticSchema === null) {
                $this->_staticSchema = new CouchbaseDbSchema($this);
                $this->_staticSchema->useStaticSchemas = true;
                $this->_staticSchema->schemaCacheFile = $this->staticSchemaFile;
            }
            return $this->_staticSchema;
        }
        
        $schema = parent::createSchema();
        
        // Configure static schema settings if applicable
        if ($schema instanceof CouchbaseDbSchema) {
            $schema->useStaticSchemas = $this->useStaticSchema;
            $schema->schemaCacheFile = $this->staticSchemaFile;
        }
        
        return $schema;
    }
    
    /**
     * Override getSchema to handle static schema mode
     * @return CDbSchema
     */
    public function getSchema()
    {
        if ($this->useStaticSchema && !$this->isConnectionAvailable()) {
            return $this->createSchema();
        }
        return parent::getSchema();
    }
    
    /**
     * Override getServerVersion to handle no-connection case
     * @return string
     */
    public function getServerVersion()
    {
        if (!$this->isConnectionAvailable()) {
            return 'Couchbase (MariaDB removed)';
        }
        try {
            return parent::getServerVersion();
        } catch (\Throwable $e) {
            return 'N/A';
        }
    }
    
    /**
     * Override getServerInfo to handle no-connection case
     * @return string
     */
    public function getServerInfo()
    {
        if (!$this->isConnectionAvailable()) {
            return 'Using Couchbase via REST API';
        }
        try {
            return parent::getServerInfo();
        } catch (\Throwable $e) {
            return 'N/A';
        }
    }
    
    /**
     * Override getAttribute to handle no-connection case
     * @param int $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        if (!$this->isConnectionAvailable()) {
            return null;
        }
        try {
            return parent::getAttribute($name);
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Override getPdoInstance to handle no-connection case
     * @return PDO|null
     */
    public function getPdoInstance()
    {
        if (!$this->isConnectionAvailable()) {
            return null;
        }
        try {
            return parent::getPdoInstance();
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Override createCommand to handle no-connection case
     * @param string $query
     * @return CDbCommand
     */
    public function createCommand($query = null)
    {
        if (!$this->isConnectionAvailable()) {
            if ($query !== null) {
                Yii::log('SQL query attempted without MariaDB (returning empty): ' . substr($query, 0, 100), CLogger::LEVEL_INFO, 'application.db');
            }
            // Return a dummy command that won't execute
            return new OEDummyDbCommand($this, $query);
        }
        return parent::createCommand($query);
    }
    
    /**
     * Override quoteValue to handle no-connection case
     * @param mixed $value
     * @return string
     */
    public function quoteValue($value)
    {
        if (!$this->isConnectionAvailable()) {
            // Return a quoted value without using PDO
            if (is_string($value)) {
                return "'" . addcslashes($value, "\\000\n\r\\\\\\032") . "'";
            } elseif (is_bool($value)) {
                return $value ? '1' : '0';
            } elseif ($value === null) {
                return 'NULL';
            } else {
                return (string)$value;
            }
        }
        return parent::quoteValue($value);
    }

    /**
     * Override quote to handle no-connection case
     * @param mixed $value
     * @param int $type
     * @return string
     */
    public function quote($value, $type = \PDO::PARAM_STR)
    {
        if (!$this->isConnectionAvailable()) {
            // Handle without using PDO
            return $this->quoteValue($value);
        }
        return parent::quote($value, $type);
    }

    /**
     * Override getDriverName to handle no-connection case
     * @return string
     */
    public function getDriverName()
    {
        if (!$this->isConnectionAvailable()) {
            return 'couchbase';
        }
        try {
            return parent::getDriverName();
        } catch (\Throwable $e) {
            return 'couchbase';
        }
    }
    
    public function beginTransaction()
    {
        // If the underlying PDO is unavailable (no MariaDB), return a stub transaction
        if (!$this->isConnectionAvailable()) {
            return new OETransactionStub();
        }

        if (Yii::app()->params['enable_transactions']) {
            return parent::beginTransaction();
        }

        return new OETransactionStub();
    }

    /**
     * Begin a transaction if there is not already one in progress.
     */
    public function beginInternalTransaction()
    {
        if ($this->getCurrentTransaction()) {
            return new OETransactionStub();
        }

        // If connection is unavailable, return stub instead of attempting PDO transaction
        if (!$this->isConnectionAvailable()) {
            return new OETransactionStub();
        }

        return $this->beginTransaction();
    }
}
