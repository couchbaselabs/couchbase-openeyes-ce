<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2019
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2019, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

/**
 * This is the model class for table "anaesthetic_complication".
 *
 * The followings are the available columns in table 'anaesthetic_complication':
 *
 * @property int $id
 * @property string $name
 * @property int $display_order
 */
class AnaestheticComplication extends BaseActiveRecordVersioned
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * Returns the static model of the specified AR class.
     *
     * @param string $className
     * @return AnaestheticComplication|BaseActiveRecord the static model class
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
        return 'anaesthetic_complication';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
        );
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return array();
    }

    /**
     * @return string the Couchbase scope name for this model
     */
    public function couchbaseScope(): string
    {
        return 'reference';
    }

    /**
     * @return string the Couchbase collection name for this model
     */
    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }

    /**
     * After saving, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    /**
     * After deleting, remove from Couchbase
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
}
