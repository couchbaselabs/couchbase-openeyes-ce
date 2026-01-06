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
 * This is the model class for table "pedigree_amino_acid_change_type".
 *
 * The followings are the available columns in table 'issue':
 *
 * @property int $id
 * @property string $name
 */
class PedigreeAminoAcidChangeType extends BaseActiveRecord
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * Returns the static model of the specified AR class.
     *
     * @return PedigreeAminoAcidChangeType Issue the static model class
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
        return 'pedigree_amino_acid_change_type';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('id, change', 'safe'),
            array('change', 'required'),
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
     * Override save to handle Couchbase-only mode
     * In couchbase_primary mode, save to Couchbase only without trying MariaDB
     */
    public function save($runValidation = true, $attributes = null, $allow_overriding = false)
    {
        // Check if MariaDB is available
        if (!Yii::app()->db->isConnectionAvailable() && $this->isDualWriteEnabled()) {
            // In Couchbase-primary mode with no MariaDB, save directly to Couchbase
            if ($this->getIsNewRecord() || !isset($this->id)) {
                $this->created_user_id = Yii::app()->user->id ?? 1;
                $this->created_date = date('Y-m-d H:i:s');
                // Generate a UUID-like ID for Couchbase storage
                if (!isset($this->id) || empty($this->id)) {
                    $this->id = (int)(microtime(true) * 10000) % 2147483647;
                }
            }
            $this->last_modified_user_id = Yii::app()->user->id ?? 1;
            $this->last_modified_date = date('Y-m-d H:i:s');

            // Validate if needed
            if ($runValidation && !$this->validate()) {
                return false;
            }

            // Save to Couchbase
            return $this->saveToCouchbase();
        }

        // Use parent save for normal MariaDB mode
        return parent::save($runValidation, $attributes, $allow_overriding);
    }


    /**
     * @return string the Couchbase scope name
     */
    public function couchbaseScope(): string
    {
        return 'reference';
    }

    /**
     * @return string the Couchbase collection name
     */
    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }

    /**
     * After save, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    /**
     * After delete, remove from Couchbase
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
}
