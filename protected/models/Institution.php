<?php

use OE\factories\models\traits\HasFactory;
use OE\Models\Traits\CouchbaseModelBridge;

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
 * This is the model class for table "institution".
 *
 * The followings are the available columns in table 'institution':
 *
 * @property int $id
 * @property string $name
 * @property int $remote_id
 * @property string $pas_key
 * @property string $short_name
 * @property int $contact_id
 * @property int $source_id
 *
 * The followings are the available model relations:
 * @property Contact $contact
 * @property Site[] $sites
 */
class Institution extends BaseActiveRecordVersioned
{
    use HasFactory;
    use CouchbaseModelBridge;

    /**
     * Get the Couchbase scope for this model
     * @return string
     */
    public function couchbaseScope()
    {
        return 'core';
    }

    /**
     * Get the Couchbase collection name
     * @return string
     */
    public function couchbaseCollection()
    {
        return $this->tableName();
    }

    /**
     * Get embedded relations for Couchbase document
     * @return array
     */
    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Embed contact/address
        if ($this->contact) {
            $data['contact'] = [
                'id' => (int)$this->contact->id,
                'primary_phone' => $this->contact->primary_phone,
                'address' => $this->contact->address ? [
                    'address1' => $this->contact->address->address1,
                    'address2' => $this->contact->address->address2,
                    'city' => $this->contact->address->city,
                    'postcode' => $this->contact->address->postcode,
                ] : null,
            ];
        }
        
        // Count sites (note: sites relation may not be loaded in migration context)
        if (isset($this->sites) && is_array($this->sites)) {
            $data['site_count'] = count($this->sites);
        } else {
            $data['site_count'] = 0;
        }
        
        return $data;
    }

    /**
     * Returns the static model of the specified AR class.
     *
     * @return Institution the static model class
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
        return 'institution';
    }

    public function defaultScope()
    {
        return array('order' => $this->getTableAlias(true, false) . '.name');
    }

    public function behaviors()
    {
        return array(
            'ContactBehavior' => array(
                'class' => 'application.behaviors.ContactBehavior',
            ),
            'LookupTable' => 'LookupTable',
        );
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return array(
            array('pas_key', 'length', 'max' => 10),
            array('name, remote_id, short_name', 'required'),
            // The following rule is used by search().
            // Please remove those attributes that should not be searched.
            ['any_number_search_allowed, first_used_site_id, logo_id', 'safe'],
            ['id, name, any_number_search_allowed', 'safe', 'on' => 'search'],
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
            'contact' => array(self::BELONGS_TO, 'Contact', 'contact_id'),
            'sites' => array(self::HAS_MANY, 'Site', 'institution_id',
                'condition' => 'sites.active = 1',
                'order' => 'name asc',
            ),
            'authenticationMethods' =>  [
                self::HAS_MANY,
                InstitutionAuthentication::class,
                'institution_id',
                'condition' => 'authenticationMethods.active = 1',
            ],
            'logo' => array(self::BELONGS_TO, 'SiteLogo', 'logo_id'),
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'pas_key' => 'PAS Key',
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

        return new CActiveDataProvider(get_class($this), array(
            'criteria' => $criteria,
        ));
    }

    public function isTenanted(): bool
    {
        return count($this->authenticationMethods) > 0;
    }

    /**
     * @return Institution
     */
    public function getCurrent()
    {
        // In Couchbase-only mode, session may not implement getSelectedInstitution; fallback to default
        $session = Yii::app()->session;
        if (method_exists($session, 'getSelectedInstitution')) {
            $institution = $session->getSelectedInstitution();
            if ($institution) {
                return $institution;
            }
        }

        // Fallback: return first institution or configured default
        $inst = self::model()->findByPk(1);
        if ($inst) {
            return $inst;
        }
        $inst = self::model()->find();
        if ($inst) {
            return $inst;
        }

        throw new RuntimeException('Institution is not set for application session');
    }

    public function getList($current_institution_only = true)
    {
        $result = array();
        if ($current_institution_only) {
            $current_institution = $this->getCurrent();
            $result[$current_institution->id] = $current_institution->name;
        } else {
            $cmd = Yii::app()->db->createCommand()
                ->select('i.id, i.name')
                ->from('institution i');

            foreach ($cmd->queryAll() as $institution) {
                $result[$institution['id']] = $institution['name'];
            }

            natcasesort($result);
        }
        return $result;
    }

    public function getTenanted($condition = '', $params = array(), $user_must_be_member = false)
    {
        if (!$user_must_be_member) {
            return $this->with('authenticationMethods')->findAll($condition, $params);
        } else {
            $criteria = $this->getCommandBuilder()->createCriteria($condition, $params);

            $userCriteria = new CDbCriteria();
            $userCriteria->join = 'JOIN institution_authentication ia ON ia.institution_id = t.id JOIN user_authentication ua ON ua.institution_authentication_id = ia.id';
            $userCriteria->condition = 'ua.user_id = :user_id';
            $userCriteria->params = [':user_id' => \Yii::app()->user->id];

            $criteria->mergeWith($userCriteria);

            return $this->findAll($criteria);
        }
    }

    public function getTenantedOr($default_to_id, $condition = '', $params = array(), $user_must_be_member = false)
    {
        return array_merge([$this->findByPk($default_to_id)], $this->getTenanted($condition, $params, $user_must_be_member));
    }

    public function getTenantedList($current_institution_only = true, $user_must_be_member = false, $return_as_json_array = false)
    {
        $result = array();

        if ($current_institution_only) {
            $current_institution = $this->getCurrent();
            $result[$current_institution->id] = $current_institution->name;
        } else {
            $rows = [];

            // Primary: MariaDB if available
            try {
                $cmd = Yii::app()->db->createCommand()
                    ->selectDistinct('i.id, i.name')
                    ->from('institution i')
                    ->join('institution_authentication ia', 'ia.institution_id = i.id');

                if ($user_must_be_member) {
                    $cmd->join('user_authentication ua', 'ua.institution_authentication_id = ia.id');
                    $cmd->where('ua.user_id = :user_id', [':user_id' => \Yii::app()->user->id]);
                }

                $rows = $cmd->order('i.name')->queryAll();
            } catch (Exception $e) {
                Yii::log('Falling back to Couchbase for institution list: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'application.institution');
            }

            // Fallback: Couchbase N1QL if MariaDB unavailable or empty
            if ((empty($rows)) && (Yii::app()->params['enable_couchbase_read'] ?? false)) {
                try {
                    $adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(\OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE);
                    $bucket = Yii::app()->couchbase->config['bucket'];
                    $scope = 'core';

                    $n1ql = "SELECT DISTINCT i.id, i.name FROM `{$bucket}`.`{$scope}`.`institution` i";
                    if ($user_must_be_member) {
                        $userId = (int)Yii::app()->user->id;
                        $n1ql .= " JOIN `{$bucket}`.`{$scope}`.`institution_authentication` ia ON ia.institution_id = i.id";
                        $n1ql .= " JOIN `{$bucket}`.`{$scope}`.`user_authentication` ua ON ua.institution_authentication_id = ia.id";
                        $n1ql .= " WHERE ua.user_id = {$userId}";
                    }
                    $n1ql .= " ORDER BY i.name";

                    $rows = $adapter->query($n1ql, []);
                } catch (Exception $e) {
                    Yii::log('Couchbase institution list query failed: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'application.institution');
                    $rows = [];
                }
            }

            if ($return_as_json_array) {
                $result = $rows;
            } else {
                foreach ($rows as $institution) {
                    // Rows may be associative arrays or objects depending on adapter
                    $id = is_array($institution) ? ($institution['id'] ?? null) : ($institution->id ?? null);
                    $name = is_array($institution) ? ($institution['name'] ?? null) : ($institution->name ?? null);
                    if ($id !== null && $name !== null) {
                        $result[$id] = $name;
                    }
                }
                natcasesort($result);
            }
        }

        return $result;
    }

    public function getCorrespondenceName()
    {
        return $this->name;
    }

    /**
     * Returns the name of the institution
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->short_name;
    }
}
