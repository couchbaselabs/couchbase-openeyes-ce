<?php

/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2020
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2020, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

use OE\factories\models\traits\HasFactory;
use OE\Models\Traits\CouchbaseModelBridge;

/**
 * This is the model class for table "user_authentication".
 *
 * The followings are the available columns in table 'user_authentication':
 * @property integer $id
 * @property integer $institution_authentication_id
 * @property integer $user_id
 * @property string $username
 * @property string $password_hash
 * @property string $password_salt
 * @property datetime $password_softlocked_until
 * @property datetime $password_last_changed_date
 * @property int $password_failed_tries
 * @property string $password_status
 * @property datetime $last_successful_login_date
 * @property bool $active
 * @property string $last_modified_user_id
 * @property string $last_modified_date
 * @property string $created_user_id
 * @property string $created_date
 *
 * The followings are the available model relations:

 * @property User $user
 * @property User $createdUser
 * @property User $lastModifiedUser
 * @property InstitutionAuthentication $institutionAuthentication
 */
class UserAuthentication extends BaseActiveRecordVersioned
{
    use HasFactory;
    use CouchbaseModelBridge;

    public $password;
    public $password_repeat;
    private $old_attributes;

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'user_authentication';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        $password_restrictions = PasswordUtils::getPasswordRestrictions();

        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['institution_authentication_id, user_id, username', 'required'],
            ['username', 'length', 'max' => 40],
            ['username', 'checkWhetherUserNameIsTaken'],
            ['last_modified_user_id, created_user_id', 'length', 'max' => 10],
            ['active, password_status, institution_authentication_id, password_softlocked_until, last_modified_date, created_date', 'safe'],
            // The following rule is used by search().
            ['id, user_id, username, last_modified_user_id, last_modified_date, created_user_id, created_date, active', 'safe', 'on' => 'search'],

            // conditional rules only for local authentications:
            [
                'username',
                'conditionalMatchValidator',
                'pattern' => '/^[\w|\.\-_\+@]+$/',
                'message' => 'Only letters, numbers and underscores are allowed for usernames.',
            ],
            [
                'password',
                'conditionalLengthValidator',
                'min' => $password_restrictions['min_length'],
                'tooShort' => $password_restrictions['min_length_message'],
                'max' => $password_restrictions['max_length'],
                'tooLong' => $password_restrictions['max_length_message'],
            ],
            [
                'password',
                'conditionalMatchValidator',
                'pattern' => $password_restrictions['strength_regex'],
                'message' => $password_restrictions['strength_message'],
            ],
            ['password_salt', 'conditionalLengthValidator', 'max' => 10,],
            ['password, password_repeat, password_hash, password_salt', 'conditionalSafeValidator',],
        ];
    }

    public function conditionalMatchValidator($attribute, $params)
    {
        if ($this->isLocalAuth()) {
            $validator = CValidator::createValidator('match', $this, $attribute, $params);
            $validator->validate($this);
        }
    }

    public function conditionalLengthValidator($attribute, $params)
    {
        if ($this->isLocalAuth()) {
            $validator = CValidator::createValidator('length', $this, $attribute, $params);
            $validator->validate($this);
        }
    }

    public function conditionalSafeValidator($attribute, $params)
    {
        if ($this->isLocalAuth()) {
            $validator = CValidator::createValidator('safe', $this, $attribute, $params);
            $validator->validate($this);
        }
    }

    public function checkWhetherUserNameIsTaken($attribute, $params)
    {
        if ($this->institutionAuthentication) {
            $existing_user_auth = self::model()->with([
                'institutionAuthentication' => [
                    'select' => false,
                    'condition' => 'institutionAuthentication.institution_id = :institution_id',
                    'params' => [
                        ':institution_id' => $this->institutionAuthentication->institution_id,
                    ],
                ]
            ])->findByAttributes(
                [
                    'username' => $this->username,
                ],
                'user_id != :user_id',
                [':user_id' => $this->user_id]
            );
            if ($existing_user_auth) {
                $this->addError($attribute, "The given username is already taken by user: {$existing_user_auth->user->fullName}.");
            }
        }
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        return [
            'user' => [self::BELONGS_TO, 'User', 'user_id'],
            'institutionAuthentication' => [self::BELONGS_TO, 'InstitutionAuthentication', 'institution_authentication_id'],
            'createdUser' => [self::BELONGS_TO, 'User', 'created_user_id'],
            'lastModifiedUser' => [self::BELONGS_TO, 'User', 'last_modified_user_id'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'institution_authentication_id' => 'Institution Authentication',
            'user_id' => 'User',
            'username' => 'Login ID',
            'password_status' => 'Status',
            'password' => 'Password',
            'password_repeat' => 'Confirm Password',
            'last_modified_user_id' => 'Last Modified User',
            'last_modified_date' => 'Last Modified Date',
            'created_user_id' => 'Created User',
            'created_date' => 'Created Date',
        ];
    }

    public function handlePassword()
    {
        if (!$this->isLocalAuth()) {
            $this->password = null;
            $this->password_repeat = null;
        } else {
            if (!empty($this->password)) {
                if ($this->password != $this->password_repeat) {
                    $this->addError('password_repeat', 'Password confirmation must match exactly');
                } else {
                    // Password set and matches
                    $this->password_last_changed_date = date('Y-m-d H:i:s');
                    $this->password_failed_tries = 0;
                    $this->password_salt = PasswordUtils::randomSalt();
                }
            } elseif ($this->getIsNewRecord()) {
                $this->addError('password', 'Password is required');
            }
        }
    }

    public function beforeValidate()
    {
        $this->username = trim($this->username);
        $this->handlePassword();
        return parent::beforeValidate();
    }

    public function setPasswordHash()
    {
        if ($this->isLocalAuth() && !empty($this->password)) {
            $this->password_salt = null;
            $this->password_hash = PasswordUtils::hashPassword($this->password, null);
        }
    }

    public function afterValidate()
    {
        parent::afterValidate();
        $this->setPasswordHash();
        if (isset($this->old_attributes['password_status']) && $this->old_attributes['password_status'] != $this->password_status) {
            if ($this->password_status == 'current') {
                $this->password_last_changed_date = date('Y-m-d H:i:s');
                $this->password_failed_tries = 0;
            }
        }
    }

    public function afterFind()
    {
        $this->old_attributes = $this->attributes;
        return parent::afterFind();
    }

    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }

    public function verifyPassword($password)
    {
        if (!$this->password_salt) {
            $hash = $this->password_hash;
            // Normalize bcrypt prefix for PHP if stored as 2b/2y interchangeably
            if (strpos($hash, '$2y$') === 0) {
                $hash = '$2y$' . substr($hash, 4);
            } elseif (strpos($hash, '$2b$') === 0) {
                $hash = '$2y$' . substr($hash, 4);
            }

            if (!password_verify($password, $hash)) {
                return false;
            }

            // If MariaDB is unavailable, don't attempt to persist counters
            if ($this->isDbUnavailable()) {
                return true;
            }

            try {
                $this->password_failed_tries = 0;
                $this->saveAttributes(['password_failed_tries']);
            } catch (\Exception $e) {
                // Ignore persistence failure in Couchbase-only mode
            }
            return true;
        }
        if (PasswordUtils::hashPassword($password, $this->password_salt) === $this->password_hash) {
            // Regenerate the hash using the new method.
            $this->password_salt = null;
            $this->password_hash = PasswordUtils::hashPassword($password, null);
            if (!$this->isDbUnavailable()) {
                if (!$this->saveAttributes(array('password_hash','password_salt'))) {
                    $this->audit('login', 'auto-encrypt-password-failed', "user_authentication_id = {$this->id}, with error :" . var_export($this->getErrors(), true));
                    return false;
                }
                $this->audit('login', 'auto-encrypt-password', "user_authentication_id = {$this->id}");
                try {
                    $this->password_failed_tries = 0;
                    $this->saveAttributes(['password_failed_tries']);
                } catch (\Exception $e) {
                    // Ignore persistence failure
                }
            }

            return password_verify($password, $this->password_hash);
        }
        return false;
    }

    /**
     * Determine if the primary DB is unavailable (MariaDB removed)
     * @return bool
     */
    private function isDbUnavailable()
    {
        try {
            $db = \Yii::app()->db;
            if ($db instanceof \OEDbConnection) {
                return !$db->isConnectionAvailable();
            }
            try {
                $pdo = $db->getPdoInstance();
                return ($pdo === null);
            } catch (\Throwable $e) {
                return true;
            }
        } catch (\Throwable $e) {
            return true;
        }
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
        $criteria = new CDbCriteria();

        $criteria->compare('id', $this->id);
        $criteria->compare('user_id', $this->user_id);
        $criteria->compare('username', $this->username, true);
        $criteria->compare('last_modified_user_id', $this->last_modified_user_id, true);
        $criteria->compare('last_modified_date', $this->last_modified_date, true);
        $criteria->compare('created_user_id', $this->created_user_id, true);
        $criteria->compare('created_date', $this->created_date, true);

        return new CActiveDataProvider($this, [
            'criteria' => $criteria,
        ]);
    }

    public function isLocalAuth()
    {
        $instAuth = $this->institutionAuthentication ?? $this->getRelated('institutionAuthentication', false);
        return $instAuth ? ($instAuth->user_authentication_method == 'LOCAL') : true;
    }

    public function isLDAPAuth()
    {
        $instAuth = $this->institutionAuthentication ?? $this->getRelated('institutionAuthentication', false);
        return $instAuth ? ($instAuth->user_authentication_method == 'LDAP') : true;
    }

    public function isSsoAuth()
    {
        $instAuth = $this->institutionAuthentication ?? $this->getRelated('institutionAuthentication', false);
        return $instAuth ? ($instAuth->user_authentication_method == 'SSO') : true;
    }

    public static function fromAttributes($attributes)
    {
        $user_auth = !empty($attributes['id']) ? self::model()->findByPk($attributes['id']) : new self();
        $user_auth->setAttributes($attributes);
        if (isset($user_auth->originalAttributes['institution_authentication_id']) && $user_auth->institution_authentication_id != $user_auth->originalAttributes['institution_authentication_id']) {
            $user_auth->password_last_changed_date = date('Y-m-d H:i:s');
            $user_auth->password_failed_tries = 0;
            if (isset($user_auth->institutionAuthentication) && $user_auth->institutionAuthentication->user_authentication_method == "LDAP") {
                $user_auth->password_status = 'current';
            }
        }
        return $user_auth;
    }

    public static function findAvailableAuthentications($username, $institution_id, $site_id)
    {
        $user_authentications = self::model()->findAllByAttributes(['username' => $username, 'active' => 1]);
        if (count($user_authentications) == 0) {
            $inactive_user_auths = array_map(
                function ($user_auth) use ($institution_id, $site_id) {
                    return $user_auth->institutionAuthentication->match($institution_id, $site_id);
                },
                self::model()->findAllByAttributes(['username' => $username, 'active' => 0])
            );
            $error = (
                !empty($inactive_user_auths) &&
                array_reduce(
                    $inactive_user_auths,
                    function ($total, $match) {
                        return $total | $match;
                    }
                )) ?
                "User has been deactivated, please contact an admin." :
                "Invalid login.";

            return [[], $error];
        }

        $matches = [];

        foreach ($user_authentications as $user_authentication) {
            if (!isset($user_authentication->institution_authentication_id)) {
                return [[ InstitutionAuthentication::PERMISSIVE_MATCH => [ $user_authentication ] ], "success"];
            }
            $inst_auth = $user_authentication->institutionAuthentication;
            if (!$inst_auth && $user_authentication->institution_authentication_id) {
                // Couchbase hydration may not auto-load relation; load manually
                $inst_auth = InstitutionAuthentication::model()->findByPk($user_authentication->institution_authentication_id);
            }
            if (!$inst_auth) {
                // If we cannot load, treat as permissive to avoid blocking login
                $matches[InstitutionAuthentication::PERMISSIVE_MATCH][] = $user_authentication;
                continue;
            }

            $match_type = $inst_auth->match($institution_id, $site_id);

            if ($match_type > InstitutionAuthentication::NO_MATCH) {
                $matches[$match_type][] = $user_authentication;
            }
        }

        if (empty($matches) && !empty($user_authentications)) {
            // Couchbase-only mode: allow permissive match when institution/site lookups are unavailable
            $matches[InstitutionAuthentication::PERMISSIVE_MATCH] = $user_authentications;
        }

        $error = empty($matches) ? "Invalid login" : "success";

        return [$matches, $error];
    }

    /**
     * Couchbase scope for this model
     * @return string
     */
    public function couchbaseScope()
    {
        return 'admin';
    }

    /**
     * Couchbase collection for this model
     * @return string
     */
    public function couchbaseCollection()
    {
        return 'user_authentication';
    }

    public static function userHasExactMatch($user, $institution_id, $site_id)
    {
        $user_authentications = UserAuthentication::model()->findAllByAttributes([ 'user_id' => $user->id, 'active' => 1 ]);
        foreach ($user_authentications as $user_authentication) {
            $inst_auth = $user_authentication->institutionAuthentication;
            if ($inst_auth && $inst_auth->active && $inst_auth->institution_id == $institution_id && $inst_auth->site_id == $site_id) {
                return true;
            }
        }
        return false;
    }

    public function findOrCreateSSOAuthentication($user_id, $username, $institution_id, $site_id)
    {
         // Find institution Authentication that matches user
         $institution_authentication = InstitutionAuthentication::model()
            ->findByAttributes([
                'user_authentication_method' => 'SSO',
                'institution_id' => $institution_id,
                'site_id' => $site_id
            ]);

        if (!$institution_authentication) {
            $this->audit('login', 'login-failed', "No matching Institution Authentication available for: Method = SSO, Institution ID = $institution_id, Site ID= $site_id", true);
            throw new RuntimeException('Unable to save User. Matching Institution Authentication not found');
        }

        $attrs = [
            'user_id' => $user_id,
            'username' =>  $username,
            'institution_authentication_id' => $institution_authentication->id,
        ];

        $user_auth = $this->findAllByAttributes($attrs);

        if (count($user_auth) > 1) {
            throw new Exception('More than 1 user authentication matched the search. This is an enexpected situation');
        }

        if (count($user_auth) === 1) {
            return $user_auth[0];
        }

        $new_auth = new self();
        $new_auth->setAttributes($attrs);

        # If UserAuthentication record doesn't already exist, create one
        if (!$new_auth->save()) {
            Audit::add('login', 'login-failed', "Cannot create user_authentication with username: $username in institution: {$institution_authentication->institution->name}", true);
            throw new Exception('Unable to save User: ' . print_r($new_auth->getErrors(), true));
        }

        return $new_auth;
    }

    public function setSSOUserAuthentication($user_id, $username, $institution_id, $site_id)
    {
        $this->findOrCreateSSOAuthentication($user_id, $username, $institution_id, $site_id);
    }
}
