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

use OE\Models\Traits\CouchbaseModelBridge;

/**
 * This is the model class for table "authassignment".
 *
 * The followings are the available columns in table 'authassignment':
 * @property string $itemname
 * @property string $userid
 * @property string $bizrule
 * @property string $data
 *
 * The followings are the available model relations:
 * @property User $user
 * @property AuthItem $item
 */
class AuthAssignment extends BaseActiveRecord
{
    use CouchbaseModelBridge;

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'authassignment';
    }

    /**
     * @return array composite primary key
     */
    public function primaryKey()
    {
        return array('itemname', 'userid');
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return array(
            array('itemname, userid', 'required'),
            array('itemname, userid', 'length', 'max' => 64),
            array('bizrule, data', 'safe'),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'user' => array(self::BELONGS_TO, 'User', 'userid'),
            'item' => array(self::BELONGS_TO, 'AuthItem', 'itemname'),
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'itemname' => 'Item Name',
            'userid' => 'User ID',
            'bizrule' => 'Business Rule',
            'data' => 'Data',
        );
    }

    /**
     * Returns the static model of the specified AR class.
     * @param string $className active record class name.
     * @return AuthAssignment the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * Get the Couchbase scope for this model
     * @return string
     */
    public function couchbaseScope()
    {
        return 'admin';
    }

    /**
     * Get the Couchbase collection name
     * @return string
     */
    public function couchbaseCollection()
    {
        return 'authassignment';
    }

    /**
     * Generate custom document key for composite primary key
     * Format: auth_assignment::{userid}::{itemname}
     * @return string
     */
    public function getCouchbaseDocumentKey()
    {
        return $this->couchbaseCollection() . '::' . $this->userid . '::' . $this->itemname;
    }

    /**
     * Get embedded relations for Couchbase document
     * @return array
     */
    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed user info
        if ($this->user) {
            $data['user'] = [
                'id' => (int)$this->user->id,
                'username' => $this->user->username,
                'first_name' => $this->user->first_name,
                'last_name' => $this->user->last_name,
                'active' => (bool)$this->user->active,
            ];
        }
        
        // Embed auth item info
        if ($this->item) {
            $data['item'] = [
                'name' => $this->item->name,
                'type' => (int)$this->item->type,
                'description' => $this->item->description,
            ];
        }
        
        return $data;
    }

    /**
     * Hook: After saving to MariaDB, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    /**
     * Hook: After deleting from MariaDB, delete from Couchbase
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }

    /**
     * Override exists to ensure userid is cast to string for Couchbase comparison
     * The authassignment table stores userid as a string, but queries often pass integers
     * @param mixed $condition
     * @param array $params
     * @return bool
     */
    public function exists($condition = '', $params = [])
    {
        // Cast userid parameter to string for proper Couchbase N1QL comparison
        foreach ($params as $key => $value) {
            // Handle both :uid and uid style parameters
            $cleanKey = ltrim($key, ':');
            if (in_array($cleanKey, ['uid', 'userid', 'user_id']) && is_numeric($value)) {
                $params[$key] = (string)$value;
            }
        }
        
        // Check if we should read from Couchbase
        if ($this->shouldReadFromCouchbase()) {
            return $this->existsInCouchbase($condition, $params);
        }
        
        return parent::exists($condition, $params);
    }
    
    /**
     * Check if record exists in Couchbase
     * @param mixed $condition SQL condition
     * @param array $params Query parameters
     * @return bool
     */
    protected function existsInCouchbase($condition, $params = [])
    {
        $scope = $this->couchbaseScope();
        $collection = $this->couchbaseCollection();
        
        // Build N1QL query
        $n1ql = "SELECT 1 FROM `openeyes`.`{$scope}`.`{$collection}` WHERE 1=1";
        
        // Convert SQL condition to N1QL
        if (!empty($condition)) {
            // Convert Yii-style :param placeholders to N1QL $param style
            $n1qlCondition = preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '\$$1', $condition);
            $n1ql .= " AND ({$n1qlCondition})";
        }
        
        $n1ql .= " LIMIT 1";
        
        \Yii::log("AuthAssignment.existsInCouchbase: {$n1ql}, params: " . json_encode($params), \CLogger::LEVEL_INFO, 'application.couchbase');
        
        try {
            $results = $this->executeN1ql($n1ql, $params);
            return !empty($results);
        } catch (\Exception $e) {
            \Yii::log("AuthAssignment.existsInCouchbase failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR, 'application.couchbase');
            return false;
        }
    }
}
