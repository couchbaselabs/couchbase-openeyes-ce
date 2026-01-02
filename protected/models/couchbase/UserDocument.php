<?php
/**
 * User document model for direct Couchbase operations
 * NOTE: Does NOT contain password/authentication data
 */

class UserDocument extends CouchbaseActiveRecord
{
    public function documentType()
    {
        return 'user';
    }
    
    public function scope()
    {
        return 'core';
    }
    
    public function collectionName()
    {
        return 'user';
    }
    
    public function rules()
    {
        return [
            [['username', 'first_name', 'last_name'], 'required'],
            ['username', 'length', 'max' => 40],
            ['email', 'email'],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'username' => 'Username',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'active' => 'Active',
        ];
    }
    
    /**
     * Find user by username
     * @param string $username Username
     * @return UserDocument|null
     */
    public static function findByUsername($username)
    {
        $results = static::findAllByAttributes(['username' => $username], ['limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find active users
     * @param int $limit Maximum results
     * @return array Array of UserDocument
     */
    public static function findActiveUsers($limit = 100)
    {
        return static::findAllByAttributes(
            ['active' => true],
            ['limit' => $limit, 'order' => 'last_name ASC, first_name ASC']
        );
    }
    
    /**
     * Find users by role
     * @param string $role Role name
     * @return array Array of UserDocument
     */
    public static function findByRole($role)
    {
        $conn = Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META().id as _key, u.* 
                  FROM `{$bucket}`.`core`.`user` u 
                  WHERE u._type = 'user' 
                  AND \$role IN u.roles";
        
        try {
            $results = $conn->query($query, ['role' => $role]);
            
            $models = [];
            foreach ($results as $row) {
                $model = new static();
                $data = is_object($row) ? (array)$row : $row;
                if (isset($data['u'])) {
                    $data = array_merge($data, (array)$data['u']);
                    unset($data['u']);
                }
                $model->setAttributes($data);
                $model->setPrimaryKey(isset($data['_mysql_id']) ? $data['_mysql_id'] : null);
                $model->setIsNewRecord(false);
                $models[] = $model;
            }
            
            return $models;
        } catch (\Exception $e) {
            Yii::log("UserDocument search error: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Get full name
     * @return string
     */
    public function getFullName()
    {
        if (!empty($this->full_name)) {
            return $this->full_name;
        }
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
    /**
     * Check if user has a specific role
     * @param string $role Role name
     * @return bool
     */
    public function hasRole($role)
    {
        return is_array($this->roles) && in_array($role, $this->roles);
    }
    
    /**
     * Check if user is active
     * @return bool
     */
    public function isActive()
    {
        return !empty($this->active);
    }
    
    /**
     * Get corresponding MariaDB User model
     * NOTE: Use this for authentication operations
     * @return User|null
     */
    public function getMariaDbModel()
    {
        $pk = $this->getPrimaryKey();
        return $pk ? User::model()->findByPk($pk) : null;
    }
}
