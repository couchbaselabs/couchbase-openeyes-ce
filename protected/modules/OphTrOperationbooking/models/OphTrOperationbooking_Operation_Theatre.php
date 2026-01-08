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

use OE\factories\models\traits\HasFactory;

/**
 * This is the model class for table "ophtroperationbooking_operation_theatre".
 *
 * The followings are the available columns in table:
 *
 * @property int $id
 * @property string $name
 * @property int $site_id
 * @property int $ward_id
 * @property string $code
 *
 * The followings are the available model relations:
 * @property Site $site
 */
class OphTrOperationbooking_Operation_Theatre extends BaseActiveRecordVersioned
{
    use HasFactory;
    use \OE\Models\Traits\CouchbaseModelBridge;

    /**
     * @var array|null Cached sessions for Couchbase mode
     */
    private $_cachedSessions = null;

    /**
     * Set sessions directly (used in Couchbase mode to avoid relation loading)
     * @param array $sessions
     */
    public function setCachedSessions($sessions)
    {
        $this->_cachedSessions = $sessions;
    }

    /**
     * Get sessions - returns cached sessions if set, otherwise uses relation
     * @return array
     */
    public function getSessions()
    {
        if ($this->_cachedSessions !== null) {
            return $this->_cachedSessions;
        }
        // Fall back to relation
        return $this->getRelated('sessions');
    }

    /**
     * Returns the static model of the specified AR class.
     *
     * @return OphTrOperationbooking_Operation_Theatre|BaseActiveRecord the static model class
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
        return 'ophtroperationbooking_operation_theatre';
    }

    public function defaultScope()
    {
        return array('order' => $this->getTableAlias(true, false).'.name');
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('name, site_id, code, ward_id, institution_id', 'safe'),
            array('name, code', 'required'),
            array('code', 'length', 'max' => 4),
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            array('id, name, site_id, code', 'safe', 'on' => 'search'),
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
            'element_type' => array(self::HAS_ONE, 'ElementType', 'id', 'on' => "element_type.class_name='".get_class($this)."'"),
            'eventType' => array(self::BELONGS_TO, 'EventType', 'event_type_id'),
            'event' => array(self::BELONGS_TO, 'Event', 'event_id'),
            'user' => array(self::BELONGS_TO, 'User', 'created_user_id'),
            'usermodified' => array(self::BELONGS_TO, 'User', 'last_modified_user_id'),
            'site' => array(self::BELONGS_TO, 'Site', 'site_id'),
            'institution' => array(self::BELONGS_TO, 'Institution', 'institution_id'),
            'sessions' => array(self::HAS_MANY, 'OphTrOperationbooking_Operation_Session', 'theatre_id'),
            'ward' => array(self::BELONGS_TO, 'OphTrOperationbooking_Operation_Ward', 'ward_id'),
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'site_id' => 'Site',
            'ward_id' => 'Ward',
        );
    }

    public function behaviors()
    {
        return array(
            'LookupTable' => 'LookupTable',
        );
    }

    /**
     * Returns the Couchbase scope for this model.
     * @return string
     */
    public function couchbaseScope(): string
    {
        return 'clinical';
    }

    /**
     * Returns the Couchbase collection name for this model.
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

        return new CActiveDataProvider(get_class($this), array(
                'criteria' => $criteria,
            ));
    }

    public function getNameWithSite()
    {
        return $this->name.' ('.$this->site?->name.')';
    }

    /**
     * Get all Sites that have theatres attached
     *
     * @param null $current_site_id
     * @return Site[]|null
     */
    public static function getSiteList($current_site_id = null)
    {
        try {
            OELog::log("getSiteList: Starting method");
            $model = static::model();
            // Use full Couchbase collection path
            $collectionPath = '`openeyes`.`' . $model->couchbaseScope() . '`.`' . $model->couchbaseCollection() . '`';
            OELog::log("getSiteList: Collection path: " . $collectionPath);
            
            $cmd = Yii::app()->cbdb->createCommand()
                ->selectDistinct('site_id')
                ->where('(active = 1 OR active = true OR active = "1")')
                ->from($collectionPath);

            $ids = array_map(function ($r) {
                // Convert site_id to integer to match Site.id type
                return (int)$r['site_id'];
            }, $cmd->queryAll());
            
            OELog::log("getSiteList: Theatre site_ids before filter: " . implode(',', $ids));
        } catch (Exception $e) {
            OELog::log("getSiteList: Exception: " . $e->getMessage());
            return [];
        }

        // Filter out invalid IDs (0 or negative)
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });
        
        OELog::log("getSiteList: Theatre site_ids after filter: " . implode(',', $ids));

        if (empty($ids)) {
            OELog::log("getSiteList: No valid theatre site_ids found, returning empty");
            return [];
        }

        $institution = Institution::model()->getCurrent();
        $institutionId = $institution ? $institution->id : 1;
        OELog::log("getSiteList: Current institution ID: " . $institutionId);

        // Use direct N1QL query to bypass CDbCriteria issues with IN conditions
        try {
            $idList = implode(',', array_map('intval', $ids));
            $n1ql = "SELECT META(`site`).id AS _doc_key, `site`.* FROM `openeyes`.`core`.`site` WHERE (active = true OR active = 1) AND short_name IS NOT NULL AND short_name != '' AND institution_id = {$institutionId} AND id IN [{$idList}] ORDER BY short_name";
            OELog::log("getSiteList: N1QL query: " . $n1ql);
            
            $rows = Yii::app()->cbdb->createCommand()
                ->setText($n1ql)
                ->queryAll();
            
            OELog::log("getSiteList: N1QL result count: " . count($rows));
            
            $sites = [];
            foreach ($rows as $row) {
                $site = new Site();
                // Use the existing attribute assignment pattern from CouchbaseModelBridge
                foreach ($row as $key => $value) {
                    if ($key !== '_doc_key' && $site->hasAttribute($key)) {
                        $site->setAttribute($key, $value);
                    }
                }
                // Ensure id is set correctly
                if (isset($row['id'])) {
                    $site->id = $row['id'];
                }
                $site->setIsNewRecord(false);
                $sites[] = $site;
            }
            OELog::log("getSiteList: Found " . count($sites) . " sites after hydration");
            return $sites;
        } catch (Exception $e) {
            OELog::log("getSiteList: Exception in N1QL query: " . $e->getMessage());
            // Fall back to original CDbCriteria approach
        }

        // Fallback to CDbCriteria
        $criteria = new CDbCriteria();
        $criteria->addCondition("(active = 1 OR active = true)");
        $criteria->addCondition("short_name IS NOT NULL AND short_name != ''");
        $criteria->addCondition("institution_id = :institution_id");
        $criteria->params[':institution_id'] = $institutionId;
        $criteria->addInCondition('id', $ids);
        if ($current_site_id) {
            $criteria->addCondition('id = :id', 'OR');
            $criteria->params = array_merge($criteria->params, array(':id' => $current_site_id));
        }
        $criteria->order = 'short_name';

        $sites = Site::model()->findAll($criteria);
        OELog::log("getSiteList: Found " . count($sites) . " sites (fallback)");
        
        return $sites;
    }

    public static function getTheatresForCurrentInstitution()
    {
        $site_id_list = array_map(
            static function ($site) {
                return $site->id;
            },
            Institution::model()->getCurrent()->sites
        );
        return self::model()->active()->findAll('site_id IN (' . implode(', ', $site_id_list) . ')');
    }
}
