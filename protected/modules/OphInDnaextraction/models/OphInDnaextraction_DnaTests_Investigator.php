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
 * This is the model class for table "ophindnaextraction_dnatests_investigator".
 *
 * The followings are the available columns in table:
 *
 * @property int $id
 * @property string $name
 * @property int $display_order
 * @property int $last_modified_user_id
 * @property string $last_modified_date
 * @property int $created_user_id
 * @property string $created_date
 */
class OphInDnaextraction_DnaTests_Investigator extends BaseActiveRecord
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * Returns the static model of the specified AR class.
     *
     * @return the static model class
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
        return 'ophindnaextraction_dnatests_investigator';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('name', 'required'),
            array('name, display_order', 'safe'),
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
        $criteria->compare('name', $this->name, true);
        $criteria->compare('display_order', $this->display_order);

        return new CActiveDataProvider(get_class($this), array(
            'criteria' => $criteria,
        ));
    }

    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'name' => 'Name',
            'display_order' => 'Display Order',
        );
    }

    /**
     * Returns the Couchbase scope name for this model.
     *
     * @return string
     */
    public function couchbaseScope(): string
    {
        return 'clinical';
    }

    /**
     * Returns the Couchbase collection name for this model.
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
     * The investigator table was previously dropped and is rarely used.
     * Always use MariaDB for this model to avoid complexity.
     * @return bool
     */
    protected function shouldReadFromCouchbase()
    {
        // Always use MariaDB for DNA investigators
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
                    $model = new self('search');
                    $model->setAttributes($row, false);
                    $models[] = $model;
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
                    $model = new self('search');
                    $model->setAttributes($row, false);
                    $models[] = $model;
                }
                return $models;
            } catch (Throwable $fallbackError) {
                // If all fails, return empty array as a safe default
                \Yii::log("Error finding all " . $this->tableName() . ": Standard Yii and fallback SQL both failed: " . $fallbackError->getMessage(), \CLogger::LEVEL_WARNING);
                return [];
            }
        }
    }
}
