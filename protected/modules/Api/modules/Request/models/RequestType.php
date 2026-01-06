<?php
/**
 * (C) Copyright Apperta Foundation 2020
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2017, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

use OE\Models\Traits\CouchbaseModelBridge;

/**
 * This is the model class for table "request_type".
 *
 * The followings are the available columns in table 'request_type':
 * @property string $request_type
 * @property string $title_full
 * @property string $title_short
 * @property string $default_routine_name
 * @property string $default_request_queue
 *
 * The followings are the available model relations:
 * @property Request[] $requests
 * @property RequestQueue $defaultRequestQueue
 * @property RoutineLibrary $defaultRoutineName
 */
class RequestType extends CActiveRecord
{
    use CouchbaseModelBridge;
    /**
     * Returns the static model of the specified AR class.
     * Please note that you should have this exact method in all your CActiveRecord descendants!
     * @param string $className active record class name.
     * @return RequestType the static model class
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * Override save to support Couchbase when MariaDB is unavailable
     * @param bool $runValidation
     * @param null $attributes
     * @return bool
     */
    public function save($runValidation = true, $attributes = null)
    {
        // Try the standard MariaDB save first
        $result = parent::save($runValidation, $attributes);
        
        // Log for debugging
        if (!$result) {
            \Yii::log('RequestType save failed. Errors: ' . print_r($this->getErrors(), true), 'error');
        }
        
        // If save fails and Couchbase is available, try Couchbase
        if (!$result && method_exists($this, 'saveToCouchbase')) {
            try {
                $this->saveToCouchbase();
                $result = true;
            } catch (\Exception $e) {
                // Couchbase save also failed - return false
                \Yii::log('Couchbase save failed: ' . $e->getMessage(), 'error');
                $result = false;
            }
        }
        
        return $result;
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'request_type';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['request_type, title_full, title_short', 'required'],
            ['default_routine_name, default_request_queue', 'safe'],
            ['request_type, title_full, title_short, default_request_queue', 'length', 'max' => 45],
            ['default_routine_name', 'length', 'max' => 50],
            // The following rule is used by search().
            // @todo Please remove those attributes that should not be searched.
            ['request_type, title_full, title_short, default_routine_name, default_request_queue', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return [
            'requests' => [self::HAS_MANY, 'Request', 'request_type'],
            'defaultRequestQueue' => [self::BELONGS_TO, 'RequestQueue', 'default_request_queue'],
            'defaultRoutineName' => [self::BELONGS_TO, 'RoutineLibrary', 'default_routine_name'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'request_type' => 'Request Type',
            'title_full' => 'Title Full',
            'title_short' => 'Title Short',
            'default_routine_name' => 'Default Routine Name',
            'default_request_queue' => 'Default Request Queue',
        ];
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * Typical usecase:
     * - Initialize the model fields with values from filter form.
     * - Execute this method to get CActiveDataProvider instance which will filter
     * models according to data in model fields.
     * - Pass data provider to CGridView, CListView or any similar widget.
     *
     * @return CActiveDataProvider the data provider that can return the models
     * based on the search/filter conditions.
     */
    public function search()
    {
        // @todo Please modify the following code to remove attributes that should not be searched.

        $criteria = new CDbCriteria;

        $criteria->compare('request_type', $this->request_type, true);
        $criteria->compare('title_full', $this->title_full, true);
        $criteria->compare('title_short', $this->title_short, true);
        $criteria->compare('default_routine_name', $this->default_routine_name, true);
        $criteria->compare('default_request_queue', $this->default_request_queue, true);

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
        ]);
    }
}
