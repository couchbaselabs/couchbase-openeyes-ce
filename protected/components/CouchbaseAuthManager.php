<?php
/**
 * CouchbaseAuthManager - Auth manager using Couchbase instead of MariaDB
 * 
 * This replaces CDbAuthManager to allow full MariaDB removal.
 * Auth data (authitem, authassignment, authitemchild) is stored in Couchbase.
 */

class CouchbaseAuthManager extends CAuthManager
{
    /**
     * @var string the name of the auth item collection
     */
    public $itemTable = 'authitem';
    
    /**
     * @var string the name of the auth assignment collection
     */
    public $assignmentTable = 'authassignment';
    
    /**
     * @var string the name of the auth item child collection
     */
    public $itemChildTable = 'authitemchild';
    
    /**
     * @var string Couchbase scope for auth tables
     */
    public $scope = 'admin';
    
    /**
     * @var array Cached auth items
     */
    private $_items = [];
    
    /**
     * @var array Cached item children
     */
    private $_children = [];
    
    /**
     * @var array Rulesets for business rules
     */
    private $rulesets = [];
    
    /**
     * @var array Cached user assignments
     */
    private $user_assignments = [];
    
    public const ADMIN_ROLE_NAME = "admin";

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->registerRuleset('core', new AuthRules());

        if (isset(Yii::app()->params['additional_rulesets'])) {
            foreach (Yii::app()->params['additional_rulesets'] as $r) {
                $this->registerRuleset($r['namespace'], new $r['class']());
            }
        }
    }

    /**
     * Initialize the component
     */
    public function init()
    {
        parent::init();
        $this->loadItems();
    }

    /**
     * No-op save - Couchbase writes are executed immediately per operation.
     * Required to satisfy IAuthManager contract.
     */
    public function save()
    {
        return true;
    }
    
    /**
     * Get the Couchbase connection
     * @return CouchbaseConnection
     */
    protected function getCouchbase()
    {
        return Yii::app()->couchbase;
    }
    
    /**
     * Get a collection
     * @param string $name Collection name
     * @return \Couchbase\Collection
     */
    protected function getCollection($name)
    {
        return $this->getCouchbase()->bucket->scope($this->scope)->collection($name);
    }
    
    /**
     * Execute a N1QL query
     * @param string $n1ql Query
     * @param array $params Parameters
     * @return array Results
     */
    protected function query($n1ql, $params = [])
    {
        $options = new \Couchbase\QueryOptions();
        if (!empty($params)) {
            $options->namedParameters($params);
        }
        
        try {
            $result = $this->getCouchbase()->cluster->query($n1ql, $options);
            return $result->rows();
        } catch (Exception $e) {
            Yii::log("CouchbaseAuthManager query failed: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Load all auth items into cache
     */
    protected function loadItems()
    {
        if (!empty($this->_items)) {
            return;
        }
        
        $n1ql = "SELECT * FROM `openeyes`.`{$this->scope}`.`{$this->itemTable}`";
        $rows = $this->query($n1ql);
        
        foreach ($rows as $row) {
            $data = isset($row[$this->itemTable]) ? $row[$this->itemTable] : $row;
            $this->_items[$data['name']] = new CAuthItem(
                $this,
                $data['name'],
                $data['type'],
                $data['description'] ?? '',
                $data['bizrule'] ?? null,
                $data['data'] ?? null
            );
        }
        
        // Load children relationships
        $n1ql = "SELECT * FROM `openeyes`.`{$this->scope}`.`{$this->itemChildTable}`";
        $rows = $this->query($n1ql);
        
        foreach ($rows as $row) {
            $data = isset($row[$this->itemChildTable]) ? $row[$this->itemChildTable] : $row;
            $parent = $data['parent'];
            $child = $data['child'];
            
            if (!isset($this->_children[$parent])) {
                $this->_children[$parent] = [];
            }
            $this->_children[$parent][$child] = isset($this->_items[$child]) ? $this->_items[$child] : null;
        }
    }
    
    /**
     * Create an auth item
     * @param string $name
     * @param int $type
     * @param string $description
     * @param string $bizRule
     * @param mixed $data
     * @return CAuthItem
     */
    public function createAuthItem($name, $type, $description = '', $bizRule = null, $data = null)
    {
        $doc = [
            'name' => $name,
            'type' => $type,
            'description' => $description,
            'bizrule' => $bizRule,
            'data' => $data !== null ? serialize($data) : null,
            '_type' => $this->itemTable,
        ];
        
        try {
            $collection = $this->getCollection($this->itemTable);
            $collection->upsert($this->itemTable . '::' . $name, $doc);
        } catch (Exception $e) {
            Yii::log("Failed to create auth item: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            throw $e;
        }
        
        $item = new CAuthItem($this, $name, $type, $description, $bizRule, $data);
        $this->_items[$name] = $item;
        
        return $item;
    }
    
    /**
     * Remove an auth item
     * @param string $name
     * @return bool
     */
    public function removeAuthItem($name)
    {
        try {
            $collection = $this->getCollection($this->itemTable);
            $collection->remove($this->itemTable . '::' . $name);
            unset($this->_items[$name]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get an auth item by name
     * @param string $name
     * @return CAuthItem|null
     */
    public function getAuthItem($name)
    {
        $this->loadItems();
        return isset($this->_items[$name]) ? $this->_items[$name] : null;
    }
    
    /**
     * Get all auth items of a type
     * @param int|null $type
     * @param int|null $userId
     * @return array
     */
    public function getAuthItems($type = null, $userId = null)
    {
        $this->loadItems();
        
        if ($type === null && $userId === null) {
            return $this->_items;
        }
        
        $items = [];
        
        if ($userId !== null) {
            $assignments = $this->getAuthAssignments($userId);
            foreach ($assignments as $assignment) {
                $name = $assignment->itemName;
                if (isset($this->_items[$name])) {
                    $item = $this->_items[$name];
                    if ($type === null || $item->type == $type) {
                        $items[$name] = $item;
                    }
                }
            }
        } else {
            foreach ($this->_items as $name => $item) {
                if ($item->type == $type) {
                    $items[$name] = $item;
                }
            }
        }
        
        return $items;
    }
    
    /**
     * Save an auth item
     * @param CAuthItem $item
     * @param string|null $oldName
     */
    public function saveAuthItem($item, $oldName = null)
    {
        $doc = [
            'name' => $item->name,
            'type' => $item->type,
            'description' => $item->description,
            'bizrule' => $item->bizRule,
            'data' => $item->data !== null ? serialize($item->data) : null,
            '_type' => $this->itemTable,
        ];
        
        try {
            $collection = $this->getCollection($this->itemTable);
            
            if ($oldName !== null && $oldName !== $item->name) {
                $collection->remove($this->itemTable . '::' . $oldName);
                unset($this->_items[$oldName]);
            }
            
            $collection->upsert($this->itemTable . '::' . $item->name, $doc);
            $this->_items[$item->name] = $item;
        } catch (Exception $e) {
            Yii::log("Failed to save auth item: " . $e->getMessage(), CLogger::LEVEL_ERROR);
        }
    }
    
    /**
     * Add child item to parent
     * @param string $itemName
     * @param string $childName
     * @return bool
     */
    public function addItemChild($itemName, $childName)
    {
        $doc = [
            'parent' => $itemName,
            'child' => $childName,
            '_type' => $this->itemChildTable,
        ];
        
        try {
            $collection = $this->getCollection($this->itemChildTable);
            $collection->upsert($this->itemChildTable . '::' . $itemName . '::' . $childName, $doc);
            
            if (!isset($this->_children[$itemName])) {
                $this->_children[$itemName] = [];
            }
            $this->_children[$itemName][$childName] = $this->getAuthItem($childName);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Remove child item from parent
     * @param string $itemName
     * @param string $childName
     * @return bool
     */
    public function removeItemChild($itemName, $childName)
    {
        try {
            $collection = $this->getCollection($this->itemChildTable);
            $collection->remove($this->itemChildTable . '::' . $itemName . '::' . $childName);
            
            if (isset($this->_children[$itemName][$childName])) {
                unset($this->_children[$itemName][$childName]);
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if item has child
     * @param string $itemName
     * @param string $childName
     * @return bool
     */
    public function hasItemChild($itemName, $childName)
    {
        $this->loadItems();
        return isset($this->_children[$itemName][$childName]);
    }
    
    /**
     * Get children of an item
     * @param string $itemName
     * @return array
     */
    public function getItemChildren($itemName)
    {
        $this->loadItems();
        return isset($this->_children[$itemName]) ? $this->_children[$itemName] : [];
    }
    
    /**
     * Assign an auth item to a user
     * @param string $itemName
     * @param mixed $userId
     * @param string|null $bizRule
     * @param mixed $data
     * @return CAuthAssignment
     */
    public function assign($itemName, $userId, $bizRule = null, $data = null)
    {
        $doc = [
            'itemname' => $itemName,
            'userid' => (string)$userId,
            'bizrule' => $bizRule,
            'data' => $data !== null ? serialize($data) : null,
            '_type' => $this->assignmentTable,
        ];
        
        try {
            $collection = $this->getCollection($this->assignmentTable);
            $collection->upsert($this->assignmentTable . '::' . $userId . '::' . $itemName, $doc);
        } catch (Exception $e) {
            Yii::log("Failed to assign auth item: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            throw $e;
        }
        
        $assignment = new CAuthAssignment($this, $itemName, $userId, $bizRule, $data);
        
        // Clear cache
        unset($this->user_assignments[$userId]);
        
        return $assignment;
    }
    
    /**
     * Revoke an auth item from a user
     * @param string $itemName
     * @param mixed $userId
     * @return bool
     */
    public function revoke($itemName, $userId)
    {
        try {
            $collection = $this->getCollection($this->assignmentTable);
            $collection->remove($this->assignmentTable . '::' . $userId . '::' . $itemName);
            
            // Clear cache
            unset($this->user_assignments[$userId]);
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if user is assigned an item
     * @param string $itemName
     * @param mixed $userId
     * @return bool
     */
    public function isAssigned($itemName, $userId)
    {
        $assignments = $this->getAuthAssignments($userId);
        return isset($assignments[$itemName]);
    }
    
    /**
     * Get auth assignment
     * @param string $itemName
     * @param mixed $userId
     * @return CAuthAssignment|null
     */
    public function getAuthAssignment($itemName, $userId)
    {
        $assignments = $this->getAuthAssignments($userId);
        return isset($assignments[$itemName]) ? $assignments[$itemName] : null;
    }
    
    /**
     * Get all auth assignments for a user
     * @param mixed $userId
     * @return array
     */
    public function getAuthAssignments($userId)
    {
        if (isset($this->user_assignments[$userId])) {
            return $this->user_assignments[$userId];
        }
        
        $n1ql = "SELECT * FROM `openeyes`.`{$this->scope}`.`{$this->assignmentTable}` WHERE userid = \$userId";
        $rows = $this->query($n1ql, ['userId' => (string)$userId]);
        
        $assignments = [];
        foreach ($rows as $row) {
            $data = isset($row[$this->assignmentTable]) ? $row[$this->assignmentTable] : $row;
            $itemName = $data['itemname'];
            $assignments[$itemName] = new CAuthAssignment(
                $this,
                $itemName,
                $userId,
                $data['bizrule'] ?? null,
                $data['data'] ? unserialize($data['data']) : null
            );
        }
        
        $this->user_assignments[$userId] = $assignments;
        return $assignments;
    }
    
    /**
     * Save auth assignment
     * @param CAuthAssignment $assignment
     */
    public function saveAuthAssignment($assignment)
    {
        $doc = [
            'itemname' => $assignment->itemName,
            'userid' => (string)$assignment->userId,
            'bizrule' => $assignment->bizRule,
            'data' => $assignment->data !== null ? serialize($assignment->data) : null,
            '_type' => $this->assignmentTable,
        ];
        
        try {
            $collection = $this->getCollection($this->assignmentTable);
            $collection->upsert($this->assignmentTable . '::' . $assignment->userId . '::' . $assignment->itemName, $doc);
            
            // Clear cache
            unset($this->user_assignments[$assignment->userId]);
        } catch (Exception $e) {
            Yii::log("Failed to save auth assignment: " . $e->getMessage(), CLogger::LEVEL_ERROR);
        }
    }
    
    /**
     * Check access
     * @param string $itemName
     * @param mixed $userId
     * @param array $params
     * @return bool
     */
    public function checkAccess($itemName, $userId, $params = [])
    {
        $assignments = $this->getAuthAssignments($userId);
        return $this->checkAccessRecursive($itemName, $userId, $params, $assignments);
    }
    
    /**
     * Recursive access check
     */
    protected function checkAccessRecursive($itemName, $userId, $params, $assignments)
    {
        $item = $this->getAuthItem($itemName);
        
        if ($item === null) {
            return false;
        }
        
        Yii::trace('Checking permission "' . $item->name . '"', 'system.web.auth.CAuthManager');
        
        $paramsWithUser = is_array($params) ? $params : [];
        $paramsWithUser['userId'] = $userId;

        if (!$this->executeBizRule($item->bizRule, $paramsWithUser, $item->data)) {
            return false;
        }
        
        if (isset($assignments[$itemName])) {
            $assignment = $assignments[$itemName];
            if ($this->executeBizRule($assignment->bizRule, $paramsWithUser, $assignment->data)) {
                return true;
            }
        }
        
        // Check parent items
        $this->loadItems();
        foreach ($this->_children as $parentName => $children) {
            if (isset($children[$itemName])) {
                if ($this->checkAccessRecursive($parentName, $userId, $params, $assignments)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Execute business rule
     * @param string $bizRule
     * @param array $params
     * @param mixed $data
     * @return bool
     */
    public function executeBizRule($bizRule, $params, $data)
    {
        if (!$bizRule) {
            return true;
        }

        $bits = explode('.', $bizRule, 2);

        if (count($bits) == 1) {
            $namespace = 'core';
            $rule = $bizRule;
        } else {
            $namespace = $bits[0];
            $rule = $bits[1];
        }

        if (!isset($this->rulesets[$namespace])) {
            throw new Exception("Unknown ruleset '{$namespace}' for business rule '{$bizRule}'");
        }

        $ruleSet = $this->rulesets[$namespace];

        if (!method_exists($ruleSet, $rule)) {
            throw new Exception("Undefined business rule: '{$bizRule}'");
        }

        $params = is_array($params) ? $params : [];
        $userId = $params['userId'] ?? $params['user_id'] ?? null;

        // Build arguments based on rule signature to avoid type errors
        $method = new ReflectionMethod($ruleSet, $rule);
        $arguments = [];
        foreach ($method->getParameters() as $index => $parameter) {
            if ($index === 0) {
                // First parameter is always $data
                $arguments[] = $data;
                continue;
            }

            $name = $parameter->getName();

            if (array_key_exists($name, $params)) {
                $arguments[] = $params[$name];
                continue;
            }

            if (in_array($name, ['userId', 'user_id'], true)) {
                $arguments[] = $userId;
                continue;
            }

            // Fallback to default or null
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                $arguments[] = null;
            }
        }

        return $method->invokeArgs($ruleSet, $arguments);
    }
    
    /**
     * Register a ruleset
     * @param string $namespace
     * @param object $ruleset
     */
    public function registerRuleset($namespace, $ruleset)
    {
        $this->rulesets[$namespace] = $ruleset;
    }
    
    /**
     * Get roles (type = 2)
     * @param mixed|null $userId
     * @return array
     */
    public function getRoles($userId = null)
    {
        return $this->getAuthItems(CAuthItem::TYPE_ROLE, $userId);
    }
    
    /**
     * Get tasks (type = 1)
     * @param mixed|null $userId
     * @return array
     */
    public function getTasks($userId = null)
    {
        return $this->getAuthItems(CAuthItem::TYPE_TASK, $userId);
    }
    
    /**
     * Get operations (type = 0)
     * @param mixed|null $userId
     * @return array
     */
    public function getOperations($userId = null)
    {
        return $this->getAuthItems(CAuthItem::TYPE_OPERATION, $userId);
    }
    
    /**
     * Clear all auth data
     */
    public function clearAll()
    {
        $this->_items = [];
        $this->_children = [];
        $this->user_assignments = [];
    }
    
    /**
     * Clear auth assignments
     */
    public function clearAuthAssignments()
    {
        $this->user_assignments = [];
    }
    
    /**
     * Check if user has a specific role
     * @param int $user_id
     * @param string $target_role
     * @return bool
     */
    public function hasRole($user_id, $target_role): bool
    {
        if (!$user_id) {
            throw new InvalidArgumentException('Cannot check if user has role when no user supplied.');
        }
        if (!$target_role) {
            throw new InvalidArgumentException('Cannot check if user has role when no target role supplied.');
        }

        foreach ($this->getRoles($user_id) as $role) {
            if ($target_role === $role->name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get assignable roles for a user
     * @param int $user_id
     * @return array
     */
    public function getAssignableRoles($user_id)
    {
        $allRoles = $this->getRoles();
        
        if ($this->hasRole($user_id, self::ADMIN_ROLE_NAME)) {
            return $allRoles;
        } else {
            return array_filter($allRoles, function ($role) {
                return $role->name !== self::ADMIN_ROLE_NAME;
            });
        }
    }

    /**
     * Set or update assignment
     * @param string $itemName
     * @param mixed $userId
     * @param string|null $bizRule
     * @param mixed $data
     * @return CAuthAssignment
     */
    public function setOrUpdateAssignment(string $itemName, $userId, ?string $bizRule = null, $data = null): CAuthAssignment
    {
        $auth = $this->getAuthAssignment($itemName, $userId);

        if (!$auth) {
            return $this->assign($itemName, $userId, $bizRule, $data);
        }

        if ($auth->bizRule !== $bizRule) {
            throw new Exception('The supplied bizRule does not match the existing bizRule');
        }

        $auth->data = $data;
        $this->saveAuthAssignment($auth);

        return $auth;
    }
}
