<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

/**
 * This is the model class for table "et_ophingeneticresults_test".
 *
 * The followings are the available columns in table:
 *
 * @property string $id
 * @property int $event_id
 * @property string $result
 *
 * The followings are the available model relations:
 * @property ElementType $element_type
 * @property EventType $eventType
 * @property Event $event
 * @property User $user
 * @property User $usermodified
 */
class OphInGeneticresults_External_Source extends BaseActiveRecord
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * Returns the static model of the specified AR class.
     *
     * @return OphInGeneticresults_External_Source the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'ophingeneticresults_external_source';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('name', 'safe'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return array(
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
        );
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        // Warning: Please modify the following code to remove attributes that
        // should not be searched.

        $criteria = new CDbCriteria();

        $criteria->compare('id', $this->id, true);
        $criteria->compare('name', $this->name);

        return new CActiveDataProvider(get_class($this), array(
            'criteria' => $criteria,
        ));
    }

    /**
     * Returns the Couchbase scope for this model.
     *
     * @return string
     */
    public function couchbaseScope(): string
    {
        return 'clinical';
    }

    /**
     * Returns the Couchbase collection for this model.
     *
     * @return string
     */
    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }

    /**
     * After save, sync to Couchbase.
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    /**
     * After delete, remove from Couchbase.
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }

    /**
     * Override shouldReadFromCouchbase to disable Couchbase for this model.
     * The external_source table was previously dropped and is rarely used.
     * Always use MariaDB for this model to avoid complexity.
     * @return bool
     */
    protected function shouldReadFromCouchbase()
    {
        // Always use MariaDB for external source
        return false;
    }

    /**
     * Override isDualWriteEnabled to disable dual-write for this model.
     * @return bool
     */
    protected function isDualWriteEnabled()
    {
        // Disable dual-write for this model
        return false;
    }

    /**
     * Override getTableSchema to handle gracefully if table doesn't exist.
     * Wraps the parent call in error handling to return null gracefully.
     * Suppresses errors to avoid PHP warnings when table schema is unavailable.
     * @return CDbTableSchema|null
     */
    public function getTableSchema()
    {
        try {
            // Suppress errors while accessing parent's getTableSchema to avoid PHP warnings
            // when the database connection is unavailable or table doesn't exist
            $schema = @parent::getTableSchema();
            return $schema;
        } catch (\Throwable $e) {
            // If an exception is thrown, log it and return null
            \Yii::log("Error getting table schema for " . $this->tableName() . ": " . $e->getMessage(), \CLogger::LEVEL_WARNING);
            return null;
        }
    }

    /**
     * Override count to handle gracefully if table schema is unavailable.
     * Uses direct SQL as a fallback to bypass Yii's schema requirements.
     * @param string $condition
     * @param array $params
     * @return int
     */
    public function count($condition = '', $params = [])
    {
        // First check if table schema is available
        if ($this->getTableSchema() === null) {
            // Skip Yii's count() and use direct SQL
            try {
                $sql = "SELECT COUNT(*) FROM `" . $this->tableName() . "`";
                if (!empty($condition)) {
                    if (is_string($condition)) {
                        $sql .= " WHERE " . $condition;
                    }
                }
                $result = $this->getDbConnection()->createCommand($sql)->queryScalar();
                return intval($result);
            } catch (Throwable $fallbackError) {
                // If all fails, return 0 as a safe default
                \Yii::log("Error counting " . $this->tableName() . " with direct SQL: " . $fallbackError->getMessage(), \CLogger::LEVEL_WARNING);
                return 0;
            }
        }
        
        try {
            // Try the standard Yii approach
            return parent::count($condition, $params);
        } catch (Throwable $e) {
            // Try direct SQL as fallback
            try {
                $sql = "SELECT COUNT(*) FROM `" . $this->tableName() . "`";
                if (!empty($condition)) {
                    if (is_string($condition)) {
                        $sql .= " WHERE " . $condition;
                    }
                }
                $result = $this->getDbConnection()->createCommand($sql)->queryScalar();
                return intval($result);
            } catch (Throwable $fallbackError) {
                // If all fails, return 0 as a safe default
                \Yii::log("Error counting " . $this->tableName() . ": Standard Yii and fallback SQL both failed: " . $fallbackError->getMessage(), \CLogger::LEVEL_WARNING);
                return 0;
            }
        }
    }

    /**
     * Override findAll to handle gracefully if table schema is unavailable.
     * Uses direct SQL as a fallback to bypass Yii's schema requirements.
     * @param mixed $condition
     * @param array $params
     * @return array
     */
    public function findAll($condition = '', $params = [])
    {
        // First check if table schema is available
        if ($this->getTableSchema() === null) {
            // Skip Yii's findAll() and use direct SQL
            try {
                $sql = "SELECT * FROM `" . $this->tableName() . "`";
                if (!empty($condition)) {
                    if (is_string($condition)) {
                        $sql .= " WHERE " . $condition;
                    }
                }
                $rows = $this->getDbConnection()->createCommand($sql)->queryAll();
                $models = [];
                foreach ($rows as $row) {
                    $model = $this->populateRecord($row);
                    if ($model) {
                        $models[] = $model;
                    }
                }
                return $models;
            } catch (Throwable $fallbackError) {
                // If all fails, return empty array as a safe default
                \Yii::log("Error finding all " . $this->tableName() . " with direct SQL: " . $fallbackError->getMessage(), \CLogger::LEVEL_WARNING);
                return [];
            }
        }
        
        try {
            // Try the standard Yii approach
            return parent::findAll($condition, $params);
        } catch (Throwable $e) {
            // Try direct SQL as fallback
            try {
                $sql = "SELECT * FROM `" . $this->tableName() . "`";
                if (!empty($condition)) {
                    if (is_string($condition)) {
                        $sql .= " WHERE " . $condition;
                    }
                }
                $rows = $this->getDbConnection()->createCommand($sql)->queryAll();
                $models = [];
                foreach ($rows as $row) {
                    $model = $this->populateRecord($row);
                    if ($model) {
                        $models[] = $model;
                    }
                }
                return $models;
            } catch (Throwable $fallbackError) {
                // If all fails, return empty array as a safe default
                \Yii::log("Error finding all " . $this->tableName() . ": Standard Yii and fallback SQL both failed: " . $fallbackError->getMessage(), \CLogger::LEVEL_WARNING);
                return [];
            }
        }
    }

    /**
     * Override findByPk to handle gracefully if table schema is unavailable.
     * Uses direct SQL as a fallback to bypass Yii's schema requirements.
     * @param mixed $pk
     * @param string $condition
     * @param array $params
     * @return OphInGeneticresults_External_Source|null
     */
    public function findByPk($pk, $condition = '', $params = [])
    {
        // First check if table schema is available
        if ($this->getTableSchema() === null) {
            // Skip Yii's findByPk() and use direct SQL
            try {
                $sql = "SELECT * FROM `" . $this->tableName() . "` WHERE `id` = :id";
                if (!empty($condition)) {
                    if (is_string($condition)) {
                        $sql .= " AND " . $condition;
                    }
                }
                $row = $this->getDbConnection()->createCommand($sql)->bindParam(':id', $pk)->queryRow();
                if ($row) {
                    return $this->populateRecord($row);
                }
                return null;
            } catch (Throwable $fallbackError) {
                // If all fails, return null
                \Yii::log("Error finding by PK " . $this->tableName() . " with direct SQL: " . $fallbackError->getMessage(), \CLogger::LEVEL_WARNING);
                return null;
            }
        }
        
        try {
            // Try the standard Yii approach
            return parent::findByPk($pk, $condition, $params);
        } catch (Throwable $e) {
            // Try direct SQL as fallback
            try {
                $sql = "SELECT * FROM `" . $this->tableName() . "` WHERE `id` = :id";
                if (!empty($condition)) {
                    if (is_string($condition)) {
                        $sql .= " AND " . $condition;
                    }
                }
                $row = $this->getDbConnection()->createCommand($sql)->bindParam(':id', $pk)->queryRow();
                if ($row) {
                    return $this->populateRecord($row);
                }
                return null;
            } catch (Throwable $fallbackError) {
                // If all fails, return null
                \Yii::log("Error finding by PK " . $this->tableName() . ": Standard Yii and fallback SQL both failed: " . $fallbackError->getMessage(), \CLogger::LEVEL_WARNING);
                return null;
            }
        }
    }

    /**
     * Override populateRecord to handle model instantiation when metadata is unavailable.
     * Avoids calling new which would trigger constructor that accesses metadata.
     * @param array $attributes
     * @param bool $callAfterFind
     * @return OphInGeneticresults_External_Source|null
     */
    public function populateRecord($attributes, $callAfterFind = true)
    {
        try {
            // Try standard Yii approach
            return parent::populateRecord($attributes, $callAfterFind);
        } catch (\Throwable $e) {
            // If metadata access fails, create model manually
            try {
                // Create a model instance without calling constructor
                $model = new static(null);
                $model->setAttributes($attributes, false);
                if ($callAfterFind) {
                    $model->afterFind();
                }
                return $model;
            } catch (\Throwable $fallbackError) {
                // Final fallback - manual instance creation
                \Yii::log("Error populating record: " . $fallbackError->getMessage(), \CLogger::LEVEL_WARNING);
                return null;
            }
        }
    }
}
