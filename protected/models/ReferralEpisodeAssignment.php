<?php
/**
 * OpenReferralEpisodeAssignments.
 *
 * (C) Moorfields ReferralEpisodeAssignment Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenReferralEpisodeAssignments Foundation, 2011-2013
 * This file is part of OpenReferralEpisodeAssignments.
 * OpenReferralEpisodeAssignments is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenReferralEpisodeAssignments is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenReferralEpisodeAssignments in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenReferralEpisodeAssignments <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields ReferralEpisodeAssignment Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2013, OpenReferralEpisodeAssignments Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

/**
 * This is the model class for table "referral_episode_assignment".
 *
 * The followings are the available columns in table 'referral_episode_assignment':
 *
 * @property int $id
 * @property string $name
 * @property string $ShortName
 */
class ReferralEpisodeAssignment extends BaseActiveRecordVersioned
{
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * Returns the static model of the specified AR class.
     *
     * @return ReferralEpisodeAssignment the static model class
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
        return 'referral_episode_assignment';
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
        return array(
        );
    }

    /**
     * @return string the Couchbase scope name
     */
    public function couchbaseScope(): string
    {
        return 'clinical';
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
