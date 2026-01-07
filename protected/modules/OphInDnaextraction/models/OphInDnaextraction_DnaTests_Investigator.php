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
     * Override beforeSave to handle the case when tableSchema is null.
     * BaseActiveRecord::beforeSave() accesses $this->tableSchema->foreignKeys which fails
     * when the database schema is unavailable.
     * @return bool
     */
    protected function beforeSave()
    {
        // Skip BaseActiveRecord's foreign key handling if tableSchema is null
        if ($this->tableSchema === null) {
            // Call CActiveRecord::beforeSave() directly, skipping BaseActiveRecord
            return \CActiveRecord::beforeSave();
        }
        return parent::beforeSave();
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
     * Override shouldReadFromCouchbase to enable Couchbase for this model.
     * Since MariaDB is unavailable in couchbase_primary mode, we must use Couchbase.
     * @return bool
     */
    protected function shouldReadFromCouchbase()
    {
        // Always use Couchbase for this model since MariaDB is not available
        return true;
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
     * Define the known attributes for this model.
     * Used when database schema is unavailable.
     * @return array
     */
    protected function getKnownAttributes()
    {
        return [
            'id' => null,
            'name' => null,
            'display_order' => null,
            'last_modified_user_id' => null,
            'last_modified_date' => null,
            'created_user_id' => null,
            'created_date' => null,
        ];
    }

    /**
     * Override getMetaData to provide a fallback when MariaDB is unavailable.
     * Creates a minimal CActiveRecordMetaData object with required defaults.
     * @return CActiveRecordMetaData
     */
    public function getMetaData()
    {
        try {
            $metaData = @parent::getMetaData();
            if ($metaData !== null) {
                return $metaData;
            }
        } catch (\Throwable $e) {
            \Yii::log("Error getting metadata for " . $this->tableName() . ": " . $e->getMessage(), \CLogger::LEVEL_WARNING);
        }

        // Create a minimal fallback metadata object
        // This is needed when MariaDB is unavailable
        static $fallbackMetaData = null;
        if ($fallbackMetaData === null) {
            $fallbackMetaData = new \stdClass();
            $fallbackMetaData->attributeDefaults = $this->getKnownAttributes();
            $fallbackMetaData->tableSchema = null;
            // Create column objects for each known attribute
            $fallbackMetaData->columns = [];
            $dbTypes = [
                'id' => 'int(10) unsigned',
                'name' => 'varchar(255)',
                'display_order' => 'int(10) unsigned',
                'last_modified_user_id' => 'int(10) unsigned',
                'last_modified_date' => 'datetime',
                'created_user_id' => 'int(10) unsigned',
                'created_date' => 'datetime',
            ];
            foreach ($this->getKnownAttributes() as $name => $default) {
                $col = new \stdClass();
                $col->name = $name;
                $col->allowNull = true;
                $col->defaultValue = $default;
                $col->isPrimaryKey = ($name === 'id');
                $col->dbType = $dbTypes[$name] ?? 'varchar(255)';
                $fallbackMetaData->columns[$name] = $col;
            }
            $fallbackMetaData->relations = [];
        }
        return $fallbackMetaData;
    }

    /**
     * Override hasAttribute to check known attributes when schema is unavailable.
     * @param string $name
     * @return bool
     */
    public function hasAttribute($name)
    {
        $result = parent::hasAttribute($name);
        if ($result) {
            return true;
        }
        // Check known attributes as fallback
        $knownAttributes = $this->getKnownAttributes();
        return array_key_exists($name, $knownAttributes);
    }

    /**
     * Store attributes when schema is unavailable
     */
    private $_fallbackAttributes = [];

    /**
     * Override setAttribute to handle attributes when schema is unavailable.
     * @param string $name
     * @param mixed $value
     * @return bool
     */
    public function setAttribute($name, $value)
    {
        $result = parent::setAttribute($name, $value);
        if ($result !== false) {
            return $result;
        }
        // Store in fallback if it's a known attribute
        $knownAttributes = $this->getKnownAttributes();
        if (array_key_exists($name, $knownAttributes)) {
            $this->_fallbackAttributes[$name] = $value;
            return true;
        }
        return false;
    }

    /**
     * Override getAttribute to retrieve from fallback when schema is unavailable.
     * @param string $name
     * @return mixed
     */
    public function getAttribute($name)
    {
        $result = parent::getAttribute($name);
        if ($result !== null) {
            return $result;
        }
        // Check fallback attributes
        if (isset($this->_fallbackAttributes[$name])) {
            return $this->_fallbackAttributes[$name];
        }
        return null;
    }

    /**
     * Override getAttributes to include fallback attributes.
     * @param array|null $names
     * @return array
     */
    public function getAttributes($names = null)
    {
        $attributes = parent::getAttributes($names);
        // Merge fallback attributes
        foreach ($this->_fallbackAttributes as $name => $value) {
            if ($names === null || in_array($name, $names)) {
                if (!isset($attributes[$name])) {
                    $attributes[$name] = $value;
                }
            }
        }
        return $attributes;
    }

    /**
     * Override getPrimaryKey to handle when tableSchema is null.
     * @return mixed
     */
    public function getPrimaryKey()
    {
        // If tableSchema is available, use parent implementation
        if ($this->getTableSchema() !== null) {
            return parent::getPrimaryKey();
        }
        // Fallback: return the id attribute
        return $this->getAttribute('id') ?? $this->_fallbackAttributes['id'] ?? null;
    }

    /**
     * Override setPrimaryKey to handle when tableSchema is null.
     * @param mixed $value
     */
    public function setPrimaryKey($value)
    {
        // If tableSchema is available, use parent implementation
        if ($this->getTableSchema() !== null) {
            parent::setPrimaryKey($value);
            return;
        }
        // Fallback: set the id attribute directly
        $this->_fallbackAttributes['id'] = $value;
        $this->id = $value;
    }

    /**
     * Override count to use Couchbase N1QL.
     * Do NOT call parent::count() to avoid infinite loop with trait method.
     * @param string $condition
     * @param array $params
     * @return int
     */
    public function count($condition = '', $params = [])
    {
        // Handle CDbCriteria objects by extracting condition and params
        if ($condition instanceof \CDbCriteria) {
            $params = $condition->params;
            $condition = $condition->condition;
        }
        
        // Use Couchbase N1QL count
        if ($this->shouldReadFromCouchbase()) {
            return $this->n1qlCount($condition, $params) ?? 0;
        }
        
        // Fallback for MariaDB (should not reach here in couchbase_primary mode)
        return 0;
    }

    /**
     * Override findAll to use Couchbase N1QL.
     * @param mixed $condition
     * @param array $params
     * @return array
     */
    public function findAll($condition = '', $params = [])
    {
        // Check for complex CDbCriteria with `with` (eager loading) that can't be converted to N1QL
        if ($condition instanceof \CDbCriteria && !empty($condition->with)) {
            \Yii::log('Couchbase cannot handle eager loading (with). Skipping Couchbase for this query.', \CLogger::LEVEL_WARNING);
            return [];
        }

        // Use Couchbase N1QL
        if ($this->shouldReadFromCouchbase()) {
            return $this->n1qlFindAll($condition, $params);
        }
        
        // Fallback for MariaDB (should not reach here in couchbase_primary mode)
        return [];
    }
}
