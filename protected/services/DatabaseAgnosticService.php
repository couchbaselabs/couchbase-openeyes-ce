<?php
/**
 * (C) OpenEyes Foundation, 2025
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2025, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

namespace services;

use OE\Database\DatabaseAdapterFactory;
use OE\Database\DatabaseAdapterInterface;

/**
 * Base service class supporting both MariaDB and Couchbase
 * 
 * Extend this class to create services that can transparently
 * switch between database backends based on configuration.
 */
abstract class DatabaseAgnosticService extends InternalService
{
    /** @var DatabaseAdapterInterface */
    protected $adapter;
    
    /** @var bool Whether to use Couchbase for reads */
    protected $useCouchbase = false;
    
    /** @var bool Whether dual-write is enabled */
    protected $dualWriteEnabled = false;
    
    /** @var string|null Override collection for this service */
    protected $collection = null;
    
    public function __construct()
    {
        $this->initializeAdapter();
    }
    
    /**
     * Initialize the database adapter based on configuration
     */
    protected function initializeAdapter()
    {
        $this->useCouchbase = \Yii::app()->params['enable_couchbase_read'] ?? false;
        $this->dualWriteEnabled = \Yii::app()->params['enable_dual_write'] ?? false;
        
        if ($this->useCouchbase && $this->collection) {
            $this->adapter = DatabaseAdapterFactory::getAdapterForCollection($this->collection);
        } else {
            $this->adapter = DatabaseAdapterFactory::getAdapter(
                $this->useCouchbase 
                    ? DatabaseAdapterFactory::ADAPTER_COUCHBASE 
                    : DatabaseAdapterFactory::ADAPTER_MARIADB
            );
        }
    }
    
    /**
     * Get adapter for specific collection
     * @param string $collection Collection name
     * @return DatabaseAdapterInterface
     */
    protected function getAdapterForCollection(string $collection): DatabaseAdapterInterface
    {
        return DatabaseAdapterFactory::getAdapterForCollection($collection);
    }
    
    /**
     * Check if using Couchbase for reads
     * @return bool
     */
    protected function isUsingCouchbase(): bool
    {
        return $this->useCouchbase;
    }
    
    /**
     * Check if dual-write is enabled
     * @return bool
     */
    protected function isDualWriteEnabled(): bool
    {
        return $this->dualWriteEnabled;
    }
    
    /**
     * Get Couchbase adapter directly
     * @return DatabaseAdapterInterface
     */
    protected function getCouchbaseAdapter(): DatabaseAdapterInterface
    {
        return DatabaseAdapterFactory::getAdapter(DatabaseAdapterFactory::ADAPTER_COUCHBASE);
    }
    
    /**
     * Get MariaDB adapter directly
     * @return DatabaseAdapterInterface
     */
    protected function getMariaDbAdapter(): DatabaseAdapterInterface
    {
        return DatabaseAdapterFactory::getAdapter(DatabaseAdapterFactory::ADAPTER_MARIADB);
    }
    
    /**
     * Execute read operation with fallback
     * Tries Couchbase first, falls back to MariaDB on error
     * 
     * @param callable $couchbaseOp Couchbase operation
     * @param callable $mariaDbOp MariaDB operation
     * @return mixed Operation result
     */
    protected function executeWithFallback(callable $couchbaseOp, callable $mariaDbOp)
    {
        if (!$this->useCouchbase) {
            return $mariaDbOp();
        }
        
        try {
            return $couchbaseOp();
        } catch (\Exception $e) {
            \Yii::log(
                "Couchbase operation failed, falling back to MariaDB: " . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.services'
            );
            return $mariaDbOp();
        }
    }
    
    /**
     * Normalize result to array format (handles both model and document results)
     * @param mixed $result Result from database operation
     * @return array|null Normalized array or null
     */
    protected function normalizeResult($result): ?array
    {
        if ($result === null) {
            return null;
        }
        
        if (is_array($result)) {
            return $result;
        }
        
        if (is_object($result)) {
            if (method_exists($result, 'getAttributes')) {
                return $result->getAttributes();
            }
            return (array)$result;
        }
        
        return null;
    }
    
    /**
     * Normalize multiple results to array format
     * @param array $results Array of results
     * @return array Normalized array of arrays
     */
    protected function normalizeResults(array $results): array
    {
        return array_map([$this, 'normalizeResult'], $results);
    }

    /**
     * Get an instance of the primary model for searching (compatibility with legacy services).
     *
     * @return \BaseActiveRecord|null
     */
    protected function getSearchModel()
    {
        $class = static::$primary_model ?? null;
        return $class ? new $class(null) : null;
    }

    /**
     * Convert a model to a lightweight resource (compatibility with legacy ModelService).
     *
     * @param mixed $model
     * @return Resource
     */
    protected function modelToResource($model)
    {
        $class = static::getResourceClass();
        $id = is_object($model) && property_exists($model, 'id') ? $model->id : (is_array($model) && isset($model['id']) ? $model['id'] : null);
        $lastModified = null;
        if (is_object($model) && property_exists($model, 'last_modified_date')) {
            $lastModified = strtotime($model->last_modified_date);
        } elseif (is_array($model) && isset($model['last_modified_date'])) {
            $lastModified = strtotime($model['last_modified_date']);
        }

        return new $class(['id' => $id, 'last_modified' => $lastModified]);
    }

    /**
     * Convert an active data provider to resources (compatibility with legacy ModelService).
     *
     * @param \CActiveDataProvider $provider
     * @return array
     */
    protected function getResourcesFromDataProvider(\CActiveDataProvider $provider): array
    {
        $class = static::getResourceClass();
        $resources = [];
        foreach ($provider->getData() as $model) {
            $resources[] = $this->modelToResource($model);
        }
        return $resources;
    }
}
