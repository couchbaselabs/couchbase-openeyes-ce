<?php
/**
 * CouchbaseSession - Session handler using Couchbase instead of MariaDB
 * 
 * This replaces CDbHttpSession to allow full MariaDB removal.
 * Sessions are stored in Couchbase with automatic expiration.
 */

class CouchbaseSession extends CHttpSession
{
    /**
     * @var string Couchbase scope for sessions
     */
    public $scope = 'admin';
    
    /**
     * @var string Collection name for sessions
     */
    public $collection = 'user_session';
    
    /**
     * @var int Session timeout in seconds
     */
    public $timeout = 1440; // 24 minutes default
    
    /**
     * Initialize the session handler
     */
    public function init()
    {
        // Ensure Couchbase connection is available
        if (!Yii::app()->hasComponent('couchbase')) {
            Yii::log('Couchbase not available for session storage, using default session handler', CLogger::LEVEL_WARNING);
            parent::init();
            return;
        }
        
        // Set custom session handlers
        session_set_save_handler(
            [$this, 'openSession'],
            [$this, 'closeSession'],
            [$this, 'readSession'],
            [$this, 'writeSession'],
            [$this, 'destroySession'],
            [$this, 'gcSession']
        );
        
        parent::init();
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
     * Get the session collection
     * @return \Couchbase\Collection
     */
    protected function getCollection()
    {
        return $this->getCouchbase()->bucket->scope($this->scope)->collection($this->collection);
    }
    
    /**
     * Open session - just return true (no connection pooling needed)
     * @param string $savePath
     * @param string $sessionName
     * @return bool
     */
    public function openSession($savePath, $sessionName)
    {
        return true;
    }
    
    /**
     * Close session
     * @return bool
     */
    public function closeSession()
    {
        return true;
    }
    
    /**
     * Read session data
     * @param string $id Session ID
     * @return string Session data or empty string
     */
    public function readSession($id)
    {
        try {
            $collection = $this->getCollection();
            $docId = 'session::' . $id;
            
            $result = $collection->get($docId);
            $doc = $result->content();
            
            // Check if session has expired
            if (isset($doc['expire']) && $doc['expire'] < time()) {
                $this->destroySession($id);
                return '';
            }
            
            return $doc['data'] ?? '';
        } catch (\Couchbase\Exception\DocumentNotFoundException $e) {
            return '';
        } catch (Exception $e) {
            Yii::log('Session read failed: ' . $e->getMessage(), CLogger::LEVEL_WARNING);
            return '';
        }
    }
    
    /**
     * Write session data
     * @param string $id Session ID
     * @param string $data Session data
     * @return bool
     */
    public function writeSession($id, $data)
    {
        try {
            $collection = $this->getCollection();
            $docId = 'session::' . $id;
            
            $doc = [
                '_type' => 'session',
                'id' => $id,
                'data' => $data,
                'expire' => time() + $this->timeout,
                'user_id' => $this->getUserId(),
                'created' => time(),
            ];
            
            // Use upsert with expiration
            $options = new \Couchbase\UpsertOptions();
            $options->expiry($this->timeout);
            
            $collection->upsert($docId, $doc, $options);
            
            return true;
        } catch (Exception $e) {
            Yii::log('Session write failed: ' . $e->getMessage(), CLogger::LEVEL_WARNING);
            return false;
        }
    }
    
    /**
     * Destroy session
     * @param string $id Session ID
     * @return bool
     */
    public function destroySession($id)
    {
        try {
            $collection = $this->getCollection();
            $docId = 'session::' . $id;
            
            $collection->remove($docId);
            return true;
        } catch (\Couchbase\Exception\DocumentNotFoundException $e) {
            // Already deleted
            return true;
        } catch (Exception $e) {
            Yii::log('Session destroy failed: ' . $e->getMessage(), CLogger::LEVEL_WARNING);
            return false;
        }
    }
    
    /**
     * Garbage collection - Couchbase handles this with TTL
     * @param int $maxLifetime
     * @return bool
     */
    public function gcSession($maxLifetime)
    {
        // Couchbase automatically expires documents with TTL
        // No manual garbage collection needed
        return true;
    }
    
    /**
     * Get current user ID if available
     * @return int|null
     */
    protected function getUserId()
    {
        if (Yii::app()->hasComponent('user') && !Yii::app()->user->isGuest) {
            return Yii::app()->user->id;
        }
        return null;
    }
    
    /**
     * Get active session count for a user
     * @param int $userId
     * @return int
     */
    public function getActiveSessionCount($userId)
    {
        try {
            $n1ql = "SELECT COUNT(*) as cnt FROM `openeyes`.`{$this->scope}`.`{$this->collection}` 
                     WHERE user_id = \$userId AND expire > \$now";
            
            $options = new \Couchbase\QueryOptions();
            $options->namedParameters([
                'userId' => $userId,
                'now' => time(),
            ]);
            
            $result = $this->getCouchbase()->cluster->query($n1ql, $options);
            $rows = $result->rows();
            
            return $rows[0]['cnt'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Regenerate session ID
     * @param bool $deleteOldSession
     * @return bool
     */
    public function regenerateID($deleteOldSession = false)
    {
        $oldId = session_id();
        
        if (parent::regenerateID($deleteOldSession)) {
            if ($deleteOldSession && $oldId) {
                $this->destroySession($oldId);
            }
            return true;
        }
        
        return false;
    }
}
