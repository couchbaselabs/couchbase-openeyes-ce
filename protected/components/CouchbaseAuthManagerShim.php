<?php

/**
 * CouchbaseAuthManagerShim
 *
 * Fallback auth manager that uses Couchbase-backed AuthAssignment when MariaDB is unavailable.
 * It extends CDbAuthManager to keep interface compatibility but bypasses DB on failure.
 */
class CouchbaseAuthManagerShim extends CDbAuthManager
{
    /**
     * Override checkAccess to use Couchbase assignments when DB is unavailable.
     */
    public function checkAccess($itemName, $userId, $params = array())
    {
        try {
            $db = $this->getDbConnection();
            if ($db === null || ($db instanceof OEDbConnection && !$db->isConnectionAvailable())) {
                return $this->checkAccessViaCouchbase($itemName, $userId, $params);
            }

            return parent::checkAccess($itemName, $userId, $params);
        } catch (Exception $e) {
            Yii::log("AuthManager fallback to Couchbase: " . $e->getMessage(), CLogger::LEVEL_WARNING);
            return $this->checkAccessViaCouchbase($itemName, $userId, $params);
        }
    }

    /**
     * Directly check AuthAssignment in Couchbase.
     */
    protected function checkAccessViaCouchbase($itemName, $userId, $params = array())
    {
        static $cbAuth = null;

        try {
            if ($cbAuth === null) {
                $cbAuth = new CouchbaseAuthManager();
                $cbAuth->itemTable = $this->itemTable;
                $cbAuth->assignmentTable = $this->assignmentTable;
                $cbAuth->itemChildTable = $this->itemChildTable;
                $cbAuth->init();
            }

            return $cbAuth->checkAccess($itemName, $userId, $params);
        } catch (Exception $e) {
            Yii::log("AuthManager Couchbase check failed: " . $e->getMessage(), CLogger::LEVEL_WARNING);

            try {
                // Cast userId to string since authassignment stores userid as string
                return AuthAssignment::model()->exists('itemname = :item AND userid = :uid', [':item' => $itemName, ':uid' => (string)$userId]);
            } catch (Exception $nested) {
                Yii::log("AuthManager Couchbase fallback failed: " . $nested->getMessage(), CLogger::LEVEL_ERROR);
                return false;
            }
        }
    }

    /**
     * Override assign to use Couchbase when MariaDB is unavailable.
     * @param string $itemName
     * @param mixed $userId
     * @param string|null $bizRule
     * @param mixed $data
     * @return CAuthAssignment
     */
    public function assign($itemName, $userId, $bizRule = null, $data = null)
    {
        try {
            $db = $this->getDbConnection();
            if ($db === null || ($db instanceof OEDbConnection && !$db->isConnectionAvailable())) {
                return $this->assignViaCouchbase($itemName, $userId, $bizRule, $data);
            }

            return parent::assign($itemName, $userId, $bizRule, $data);
        } catch (Exception $e) {
            Yii::log("AuthManager assign fallback to Couchbase: " . $e->getMessage(), CLogger::LEVEL_WARNING);
            return $this->assignViaCouchbase($itemName, $userId, $bizRule, $data);
        }
    }

    /**
     * Assign a role via Couchbase.
     */
    protected function assignViaCouchbase($itemName, $userId, $bizRule = null, $data = null)
    {
        static $cbAuth = null;

        try {
            if ($cbAuth === null) {
                $cbAuth = new CouchbaseAuthManager();
                $cbAuth->itemTable = $this->itemTable;
                $cbAuth->assignmentTable = $this->assignmentTable;
                $cbAuth->itemChildTable = $this->itemChildTable;
                $cbAuth->init();
            }

            return $cbAuth->assign($itemName, $userId, $bizRule, $data);
        } catch (Exception $e) {
            Yii::log("AuthManager Couchbase assign failed: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            throw $e;
        }
    }

    /**
     * Override revoke to use Couchbase when MariaDB is unavailable.
     * @param string $itemName
     * @param mixed $userId
     * @return bool
     */
    public function revoke($itemName, $userId)
    {
        try {
            $db = $this->getDbConnection();
            if ($db === null || ($db instanceof OEDbConnection && !$db->isConnectionAvailable())) {
                return $this->revokeViaCouchbase($itemName, $userId);
            }

            return parent::revoke($itemName, $userId);
        } catch (Exception $e) {
            Yii::log("AuthManager revoke fallback to Couchbase: " . $e->getMessage(), CLogger::LEVEL_WARNING);
            return $this->revokeViaCouchbase($itemName, $userId);
        }
    }

    /**
     * Revoke a role via Couchbase.
     */
    protected function revokeViaCouchbase($itemName, $userId)
    {
        static $cbAuth = null;

        try {
            if ($cbAuth === null) {
                $cbAuth = new CouchbaseAuthManager();
                $cbAuth->itemTable = $this->itemTable;
                $cbAuth->assignmentTable = $this->assignmentTable;
                $cbAuth->itemChildTable = $this->itemChildTable;
                $cbAuth->init();
            }

            return $cbAuth->revoke($itemName, $userId);
        } catch (Exception $e) {
            Yii::log("AuthManager Couchbase revoke failed: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            throw $e;
        }
    }

    /**
     * Override getRoles to use Couchbase when MariaDB is unavailable.
     * @param mixed $userId
     * @return array
     */
    public function getRoles($userId = null)
    {
        Yii::log("CouchbaseAuthManagerShim::getRoles() called for user $userId", CLogger::LEVEL_INFO, 'application.couchbase.routing');
        
        // First check if MariaDB is available using the app's db connection
        $mariaDbAvailable = $this->isMariaDbAvailable();
        
        Yii::log("CouchbaseAuthManagerShim::getRoles() - MariaDB available: " . ($mariaDbAvailable ? 'yes' : 'no'), CLogger::LEVEL_INFO, 'application.couchbase.routing');
        
        if (!$mariaDbAvailable) {
            Yii::log("AuthManager getRoles: MariaDB unavailable, using Couchbase for user $userId", CLogger::LEVEL_INFO, 'application.couchbase.routing');
            return $this->getRolesViaCouchbase($userId);
        }

        try {
            return parent::getRoles($userId);
        } catch (Exception $e) {
            Yii::log("AuthManager getRoles fallback to Couchbase: " . $e->getMessage(), CLogger::LEVEL_WARNING);
            return $this->getRolesViaCouchbase($userId);
        }
    }
    
    /**
     * Check if MariaDB is available (mirrors logic from CouchbaseModelBridge)
     * @return bool
     */
    protected function isMariaDbAvailable()
    {
        try {
            $db = Yii::app()->db;
            if ($db instanceof OEDbConnection) {
                return $db->isConnectionAvailable();
            }
            // For regular CDbConnection, try to get PDO
            try {
                $pdo = $db->getPdoInstance();
                return ($pdo !== null);
            } catch (Exception $e) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get roles via Couchbase.
     * Returns CAuthItem objects for roles assigned to the user.
     */
    protected function getRolesViaCouchbase($userId = null)
    {
        try {
            Yii::log("getRolesViaCouchbase: Querying roles for user $userId", CLogger::LEVEL_INFO, 'application.couchbase.routing');
            
            // Query authassignment directly for this user
            // Try both string and integer userid since storage format may vary
            $roles = [];
            
            // First try with string userid (standard format)
            $assignments = AuthAssignment::model()->findAllByAttributes(['userid' => (string)$userId]);
            Yii::log("getRolesViaCouchbase: findAllByAttributes with string userid returned " . count($assignments) . " results", CLogger::LEVEL_INFO, 'application.couchbase.routing');
            
            // If no results, try with integer userid
            if (empty($assignments) && is_numeric($userId)) {
                Yii::log("getRolesViaCouchbase: No results with string userid, trying integer", CLogger::LEVEL_INFO, 'application.couchbase.routing');
                $assignments = AuthAssignment::model()->findAllByAttributes(['userid' => (int)$userId]);
                Yii::log("getRolesViaCouchbase: findAllByAttributes with int userid returned " . count($assignments) . " results", CLogger::LEVEL_INFO, 'application.couchbase.routing');
            }
            
            // If still no results, try a more flexible N1QL query using the REST client
            if (empty($assignments)) {
                Yii::log("getRolesViaCouchbase: No results from findAllByAttributes, trying direct N1QL via REST", CLogger::LEVEL_INFO, 'application.couchbase.routing');
                try {
                    $restClient = Yii::app()->couchbaseRest;
                    if ($restClient) {
                        // Query using TO_STRING for type-insensitive comparison
                        // Note: Parameter must be passed as string value to match string storage in Couchbase
                        $n1ql = "SELECT META(a).id AS _doc_key, a.* FROM `openeyes`.`admin`.`authassignment` a WHERE a.userid = \$userid";
                        $userIdStr = (string)$userId;
                        Yii::log("getRolesViaCouchbase: Executing REST N1QL: $n1ql with userid='$userIdStr'", CLogger::LEVEL_INFO, 'application.couchbase.routing');
                        $results = $restClient->query($n1ql, ['userid' => $userIdStr]);
                        Yii::log("getRolesViaCouchbase: Direct N1QL returned " . count($results) . " results", CLogger::LEVEL_INFO, 'application.couchbase.routing');
                        
                        if (!empty($results)) {
                            foreach ($results as $row) {
                                // Results come wrapped in collection name
                                $data = $row['a'] ?? $row;
                                $assignment = new AuthAssignment();
                                $assignment->itemname = $data['itemname'] ?? null;
                                $assignment->userid = $data['userid'] ?? null;
                                $assignment->bizrule = $data['bizrule'] ?? null;
                                $assignment->data = $data['data'] ?? null;
                                if ($assignment->itemname) {
                                    $assignments[] = $assignment;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    Yii::log("getRolesViaCouchbase: Direct N1QL query failed: " . $e->getMessage(), CLogger::LEVEL_WARNING, 'application.couchbase.routing');
                }
            }
            
            Yii::log("getRolesViaCouchbase: Found " . count($assignments) . " total assignments for user $userId", CLogger::LEVEL_INFO, 'application.couchbase.routing');
            
            foreach ($assignments as $assignment) {
                // Look up the auth item to get the type
                $authItem = AuthItem::model()->findByAttributes(['name' => $assignment->itemname]);
                
                // Only include items that are roles (type = 2)
                if ($authItem && $authItem->type == CAuthItem::TYPE_ROLE) {
                    $role = new CAuthItem(
                        $this, 
                        $assignment->itemname, 
                        CAuthItem::TYPE_ROLE,
                        $authItem->description ?? '',
                        $authItem->bizrule ?? null,
                        $authItem->data ?? null
                    );
                    $roles[$assignment->itemname] = $role;
                    Yii::log("getRolesViaCouchbase: Added role {$assignment->itemname} (authitem found)", CLogger::LEVEL_INFO, 'application.couchbase.routing');
                } elseif (!$authItem) {
                    // If no authitem found, still create a role object (for backward compatibility)
                    $role = new CAuthItem($this, $assignment->itemname, CAuthItem::TYPE_ROLE);
                    $roles[$assignment->itemname] = $role;
                    Yii::log("getRolesViaCouchbase: Added role {$assignment->itemname} (no authitem found)", CLogger::LEVEL_INFO, 'application.couchbase.routing');
                }
            }
            
            Yii::log("getRolesViaCouchbase: Returning " . count($roles) . " roles for user $userId", CLogger::LEVEL_INFO, 'application.couchbase.routing');
            return $roles;
        } catch (Exception $e) {
            Yii::log("AuthManager Couchbase getRoles failed: " . $e->getMessage(), CLogger::LEVEL_ERROR, 'application.couchbase.routing');
            return [];
        }
    }

    /**
     * Set or update an assignment with business rule and data.
     * This method is used by the Team model to assign tasks with team ID data.
     */
    public function setOrUpdateAssignment($itemName, $userId, $bizRule = null, $data = null)
    {
        try {
            // Get or create the assignment record
            $assignment = AuthAssignment::model()->findByAttributes([
                'itemname' => $itemName,
                'userid' => $userId,
            ]);

            if (!$assignment) {
                $assignment = new AuthAssignment();
                $assignment->itemname = $itemName;
                $assignment->userid = $userId;
            }

            // Update the assignment with business rule and data
            $assignment->bizrule = $bizRule;
            if ($data !== null) {
                $assignment->data = serialize($data);
            }

            if (!$assignment->save()) {
                Yii::log("Failed to save AuthAssignment: " . implode(", ", $assignment->getErrors()), CLogger::LEVEL_ERROR);
            }

            return $assignment;
        } catch (Exception $e) {
            Yii::log("AuthManager setOrUpdateAssignment failed: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            throw $e;
        }
    }

    /**
     * Compatibility helper for admin UI: returns assignable roles.
     */
    public function getAssignableRoles($userId)
    {
        try {
            // Primary: roles from authitem
            $roles = AuthItem::model()->findAll('type = :type', [':type' => CAuthItem::TYPE_ROLE]);
            if ($roles && count($roles)) {
                return $roles;
            }

            // Secondary: distinct role names from authassignment
            $assignments = AuthAssignment::model()->findAll(array(
                'select' => 'DISTINCT itemname',
            ));
            $fallback = [];
            foreach ($assignments as $a) {
                $obj = new stdClass();
                $obj->name = $a->itemname;
                $fallback[] = $obj;
            }
            if (count($fallback)) {
                return $fallback;
            }

            // Final fallback: seed a minimal admin/login role list
            $seed = [];
            foreach (['admin', 'OprnInstitutionAdmin', 'OprnLogin', 'OprnViewClinical'] as $r) {
                $obj = new stdClass();
                $obj->name = $r;
                $seed[] = $obj;
            }
            return $seed;
        } catch (Exception $e) {
            Yii::log("AuthManager getAssignableRoles fallback failed: " . $e->getMessage(), CLogger::LEVEL_WARNING);
            return [];
        }
    }
}
