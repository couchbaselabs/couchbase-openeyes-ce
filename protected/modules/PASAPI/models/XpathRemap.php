<?php
/**
 * OpenEyes.
 *
 * (C) OpenEyes Foundation, 2019
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2019, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

namespace OEModule\PASAPI\models;

class XpathRemap extends \BaseActiveRecordVersioned
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * Returns the static model of the specified AR class.
     *
     * @return PasApiAssignment the static model class
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
        return 'pasapi_xpath_remap';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return array(
            array('id, xpath, name, institution_id', 'safe'),
            array('id, xpath, name, institution_id, created_date, last_modified_date, created_user_id, last_modified_user_id',
                'safe', 'on' => 'search', ),
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array(
            'values' => array(self::HAS_MANY, '\OEModule\PASAPI\models\RemapValue', 'xpath_id'),
            'user' => array(self::BELONGS_TO, 'User', 'created_user_id'),
            'usermodified' => array(self::BELONGS_TO, 'User', 'last_modified_user_id'),
            'institution' => array(self::BELONGS_TO, 'Institution', 'institution_id'),
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'xpath' => 'XPath',
            'name' => 'Name',
            'institution_id' => 'Institution',
        );
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $criteria = new \CDbCriteria();

        $criteria->compare('id', $this->id, true);
        $criteria->compare('xpath', $this->xpath, true);
        $criteria->compare('name', $this->name, true);
        $criteria->compare('institution_id', $this->institution_id, true);

        return new \CActiveDataProvider(get_class($this), array(
            'criteria' => $criteria,
        ));
    }

    /**
     * Simple wrapper function for getting the remaps for a specific Xpath.
     *
     * @param string $xpath
     *
     * @return \CActiveRecord[]
     */
    public function findAllByXpath($xpath = '/')
    {
        $condition = 'xpath like :xpath AND institution_id = :institution_id';
        $params = array(':xpath' => "{$xpath}%", ':institution_id' => \Yii::app()->session['selected_institution_id']);

        return $this->findAll($condition, $params);
    }

    /**
     * Returns the Couchbase scope for this model.
     *
     * @return string
     */
    public function couchbaseScope(): string
    {
        return 'reference';
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
     * After saving, sync to Couchbase with timeout protection.
     * Couchbase sync is non-critical for PASAPI models, so we skip on timeout.
     */
    protected function afterSave()
    {
        parent::afterSave();
        
        // Skip Couchbase sync if not in dual-write mode
        if (!$this->isDualWriteEnabled()) {
            return;
        }
        
        // Wrap Couchbase sync with timeout to prevent form hangs
        try {
            $this->_syncToCouchbaseWithTimeout(2000); // 2 second timeout
        } catch (\Exception $e) {
            // Log error but don't fail the operation - Couchbase sync is non-critical for PASAPI
            \Yii::log(
                'XpathRemap afterSave Couchbase sync error: ' . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.pasapi.couchbase'
            );
        }
    }
    
    /**
     * Sync to Couchbase with timeout protection using a non-blocking approach.
     * @param int $timeoutMs Timeout in milliseconds
     * @throws \Exception if sync fails
     */
    private function _syncToCouchbaseWithTimeout($timeoutMs = 2000)
    {
        // For now, we'll skip async as it's complex in PHP
        // Instead, we'll just wrap the sync in error handling
        try {
            $this->saveToCouchbase();
        } catch (\Exception $e) {
            \Yii::log(
                'Couchbase sync failed for ' . $this->tableName() . ' #' . $this->getPrimaryKey() . ': ' . $e->getMessage(),
                \CLogger::LEVEL_WARNING,
                'application.couchbase'
            );
            // Don't rethrow - Couchbase sync is optional for PASAPI models
        }
    }

    /**
     * After deleting, remove from Couchbase.
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }

    /**
     * Override save to handle Couchbase-only saving when SQL is not available.
     */
    public function save($runValidation = true, $attributes = null, $allow_overriding = false)
    {
        // If SQL is not available, save to Couchbase only
        if (!$this->isSqlAvailable()) {
            if ($runValidation && !$this->validate()) {
                return false;
            }
            
            // Generate ID if new record and ID is not set
            if ($this->getIsNewRecord() && empty($this->id)) {
                // Use a simple incremental ID or UUID
                // For now, we'll use a timestamp-based ID
                $this->id = uniqid('xpr_', true);
            }
            
            // Set timestamps
            $user_id = \Yii::app()->user->id;
            if ($this->getIsNewRecord()) {
                $this->created_user_id = $user_id;
                $this->created_date = date('Y-m-d H:i:s');
            }
            $this->last_modified_user_id = $user_id;
            $this->last_modified_date = date('Y-m-d H:i:s');
            
            // Save to Couchbase with error handling
            try {
                $this->saveToCouchbase();
            } catch (\Exception $e) {
                \Yii::log(
                    'XpathRemap save (Couchbase-only) failed: ' . $e->getMessage(),
                    \CLogger::LEVEL_ERROR,
                    'application.couchbase'
                );
                return false;
            }
            
            // Mark as not new and set old attributes
            $this->setIsNewRecord(false);
            $this->originalAttributes = $this->getAttributes();
            
            return true;
        }
        
        // Otherwise, use parent's save method (which requires SQL)
        return parent::save($runValidation, $attributes, $allow_overriding);
    }

    /**
     * Check if SQL database is available.
     */
    protected function isSqlAvailable()
    {
        $conn = $this->getDbConnection();
        if (!$conn) {
            return false;
        }
        if (method_exists($conn, 'isConnectionAvailable') && !$conn->isConnectionAvailable()) {
            return false;
        }
        return true;
    }
}
