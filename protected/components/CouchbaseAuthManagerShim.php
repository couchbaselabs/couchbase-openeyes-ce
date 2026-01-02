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
            if ($db instanceof OEDbConnection && !$db->isConnectionAvailable()) {
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
                return AuthAssignment::model()->exists('itemname = :item AND userid = :uid', [':item' => $itemName, ':uid' => $userId]);
            } catch (Exception $nested) {
                Yii::log("AuthManager Couchbase fallback failed: " . $nested->getMessage(), CLogger::LEVEL_ERROR);
                return false;
            }
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
