<?php
/**
 * Couchbase Cutover Manager
 * 
 * Manages gradual cutover from MariaDB to Couchbase with feature flags,
 * traffic routing, user targeting, and emergency controls.
 * 
 * Phase 16: Production Cutover
 */

class CouchbaseCutoverManager
{
    /**
     * @var array Configuration loaded from couchbase-cutover.php
     */
    protected $config;
    
    /**
     * @var CouchbaseCutoverManager Singleton instance
     */
    protected static $instance;
    
    /**
     * Constructor - loads configuration
     */
    public function __construct()
    {
        $configPath = Yii::app()->basePath . '/config/couchbase-cutover.php';
        
        if (!file_exists($configPath)) {
            throw new Exception("Couchbase cutover configuration not found: {$configPath}");
        }
        
        $this->config = require($configPath);
    }
    
    /**
     * Get singleton instance
     * 
     * @return CouchbaseCutoverManager
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Determine if Couchbase should be used for this request
     * 
     * @param string $modelClass Model class name (e.g., 'Patient', 'Episode')
     * @param string $operation Operation type ('read' or 'write')
     * @return bool True if Couchbase should be used
     */
    public function shouldUseCouchbase($modelClass, $operation = 'read')
    {
        // 1. EMERGENCY DISABLE CHECK (highest priority)
        if ($this->config['emergency_disable']) {
            $this->logDecision($modelClass, $operation, false, 'emergency_disable');
            Yii::log(
                "Couchbase disabled: {$this->config['emergency_disable_reason']}", 
                'warning',
                'couchbase.cutover'
            );
            return false;
        }
        
        // 2. MASTER SWITCH CHECK
        if (!$this->config['enabled']) {
            $this->logDecision($modelClass, $operation, false, 'master_disabled');
            return false;
        }
        
        // 3. WRITE OPERATION HANDLING
        if ($operation === 'write') {
            $useForWrite = in_array(
                $this->config['write_mode'], 
                ['dual_write', 'couchbase_primary']
            );
            $this->logDecision($modelClass, $operation, $useForWrite, 'write_mode_' . $this->config['write_mode']);
            return $useForWrite;
        }
        
        // 4. MODEL-SPECIFIC CONFIGURATION
        if (isset($this->config['models'][$modelClass])) {
            $modelConfig = $this->config['models'][$modelClass];
            
            // Check read source
            if ($modelConfig['read_source'] === 'mariadb') {
                $this->logDecision($modelClass, $operation, false, 'model_override_mariadb');
                return false;
            }
            
            if ($modelConfig['read_source'] === 'couchbase') {
                $percentage = $modelConfig['percentage'] ?? 100;
                $useIt = $this->checkPercentage($percentage);
                $this->logDecision($modelClass, $operation, $useIt, "model_override_couchbase_{$percentage}%");
                return $useIt;
            }
        }
        
        // 5. USER TARGETING
        if ($this->config['user_targeting']['enabled']) {
            // Internal users
            if ($this->config['user_targeting']['internal_users'] && $this->isInternalUser()) {
                $this->logDecision($modelClass, $operation, true, 'internal_user');
                return true;
            }
            
            // Beta users
            if ($this->isBetaUser()) {
                $this->logDecision($modelClass, $operation, true, 'beta_user');
                return true;
            }
            
            // Excluded users
            if ($this->isExcludedUser()) {
                $this->logDecision($modelClass, $operation, false, 'excluded_user');
                return false;
            }
        }
        
        // 6. SITE TARGETING
        if ($this->config['site_targeting']['enabled']) {
            // Excluded sites
            if ($this->isExcludedSite()) {
                $this->logDecision($modelClass, $operation, false, 'excluded_site');
                return false;
            }
            
            // Enabled sites
            if (!empty($this->config['site_targeting']['enabled_site_ids'])) {
                $useIt = $this->isEnabledSite();
                $this->logDecision($modelClass, $operation, $useIt, 'site_targeting');
                return $useIt;
            }
        }
        
        // 7. GLOBAL READ SOURCE
        if ($this->config['read_source'] === 'mariadb') {
            $this->logDecision($modelClass, $operation, false, 'global_mariadb');
            return false;
        }
        
        if ($this->config['read_source'] === 'couchbase') {
            $this->logDecision($modelClass, $operation, true, 'global_couchbase');
            return true;
        }
        
        // 8. DEFAULT: PERCENTAGE-BASED ROUTING (hybrid mode)
        $percentage = $this->config['couchbase_read_percentage'];
        $useIt = $this->checkPercentage($percentage);
        $this->logDecision($modelClass, $operation, $useIt, "percentage_{$percentage}%");
        return $useIt;
    }
    
    /**
     * Check if request falls within percentage threshold
     * Uses consistent hashing based on session ID for stable routing
     * 
     * @param int $percentage Percentage (0-100)
     * @return bool
     */
    protected function checkPercentage($percentage)
    {
        // Always use if 100%
        if ($percentage >= 100) {
            return true;
        }
        
        // Never use if 0%
        if ($percentage <= 0) {
            return false;
        }
        
        // Use session ID for consistent routing (same user always gets same result)
        $sessionId = session_id();
        if (!$sessionId) {
            // If no session, use a consistent fallback
            $sessionId = 'default-session';
        }
        
        // Hash session ID to get consistent bucket (0-99)
        $hash = crc32($sessionId);
        $bucket = abs($hash % 100);
        
        // Use Couchbase if bucket is within percentage
        return $bucket < $percentage;
    }
    
    /**
     * Check if current user is internal (admin/staff)
     * 
     * @return bool
     */
    protected function isInternalUser()
    {
        $user = Yii::app()->user;
        
        if (!$user || $user->isGuest) {
            return false;
        }
        
        // Check for admin or staff role
        return $user->checkAccess('admin') || $user->checkAccess('staff');
    }
    
    /**
     * Check if current user is a beta tester
     * 
     * @return bool
     */
    protected function isBetaUser()
    {
        $user = Yii::app()->user;
        
        if (!$user || $user->isGuest) {
            return false;
        }
        
        $betaUserIds = $this->config['user_targeting']['beta_user_ids'] ?? [];
        return in_array($user->id, $betaUserIds);
    }
    
    /**
     * Check if current user is excluded
     * 
     * @return bool
     */
    protected function isExcludedUser()
    {
        $user = Yii::app()->user;
        
        if (!$user || $user->isGuest) {
            return false;
        }
        
        $excludedUserIds = $this->config['user_targeting']['excluded_user_ids'] ?? [];
        return in_array($user->id, $excludedUserIds);
    }
    
    /**
     * Check if current site is excluded
     * 
     * @return bool
     */
    protected function isExcludedSite()
    {
        $siteId = Yii::app()->session->get('selected_site_id');
        
        if (!$siteId) {
            return false;
        }
        
        $excludedSiteIds = $this->config['site_targeting']['excluded_site_ids'] ?? [];
        return in_array($siteId, $excludedSiteIds);
    }
    
    /**
     * Check if current site is enabled
     * 
     * @return bool
     */
    protected function isEnabledSite()
    {
        $siteId = Yii::app()->session->get('selected_site_id');
        
        if (!$siteId) {
            return false;
        }
        
        $enabledSiteIds = $this->config['site_targeting']['enabled_site_ids'] ?? [];
        return in_array($siteId, $enabledSiteIds);
    }
    
    /**
     * Enable emergency disable mode
     * 
     * @param string $reason Reason for emergency disable
     * @return bool Success
     */
    public function emergencyDisable($reason)
    {
        $this->config['emergency_disable'] = true;
        $this->config['emergency_disable_reason'] = $reason;
        $this->config['emergency_disable_timestamp'] = time();
        
        // Persist to config file
        $this->saveConfig();
        
        // Log alert
        Yii::log(
            "EMERGENCY DISABLE ACTIVATED: {$reason}",
            'error',
            'couchbase.cutover'
        );
        
        // Could trigger additional alerts here (email, Slack, PagerDuty, etc.)
        
        return true;
    }
    
    /**
     * Clear emergency disable mode
     * 
     * @return bool Success
     */
    public function clearEmergencyDisable()
    {
        $this->config['emergency_disable'] = false;
        $this->config['emergency_disable_reason'] = '';
        $this->config['emergency_disable_timestamp'] = null;
        
        $this->saveConfig();
        
        Yii::log(
            "Emergency disable cleared",
            'info',
            'couchbase.cutover'
        );
        
        return true;
    }
    
    /**
     * Update traffic percentage
     * 
     * @param int $percentage Percentage (0-100)
     * @return bool Success
     */
    public function setTrafficPercentage($percentage)
    {
        $percentage = max(0, min(100, (int)$percentage));
        $this->config['couchbase_read_percentage'] = $percentage;
        
        $this->saveConfig();
        
        Yii::log(
            "Traffic percentage set to: {$percentage}%",
            'info',
            'couchbase.cutover'
        );
        
        return true;
    }
    
    /**
     * Get current configuration
     * 
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
    
    /**
     * Update configuration
     * 
     * @param array $updates Configuration updates
     * @return bool Success
     */
    public function updateConfig(array $updates)
    {
        $this->config = array_merge($this->config, $updates);
        return $this->saveConfig();
    }
    
    /**
     * Save configuration to file
     * 
     * @return bool Success
     */
    protected function saveConfig()
    {
        $configPath = Yii::app()->basePath . '/config/couchbase-cutover.php';
        
        try {
            $content = "<?php\n";
            $content .= "/**\n";
            $content .= " * Couchbase Cutover Configuration\n";
            $content .= " * Auto-updated by CouchbaseCutoverManager\n";
            $content .= " * Last updated: " . date('Y-m-d H:i:s') . "\n";
            $content .= " */\n\n";
            $content .= "return " . var_export($this->config, true) . ";\n";
            
            return file_put_contents($configPath, $content) !== false;
        } catch (Exception $e) {
            Yii::log(
                "Failed to save cutover config: {$e->getMessage()}",
                'error',
                'couchbase.cutover'
            );
            return false;
        }
    }
    
    /**
     * Log routing decision (if logging enabled)
     * 
     * @param string $modelClass Model class
     * @param string $operation Operation type
     * @param bool $useCouchbase Decision result
     * @param string $reason Decision reason
     */
    protected function logDecision($modelClass, $operation, $useCouchbase, $reason)
    {
        if (!($this->config['log_routing_decisions'] ?? false)) {
            return;
        }
        
        $database = $useCouchbase ? 'Couchbase' : 'MariaDB';
        
        Yii::log(
            "Routing decision: {$modelClass}::{$operation} -> {$database} (reason: {$reason})",
            'info',
            'couchbase.cutover.routing'
        );
    }
    
    /**
     * Reset singleton instance (useful for testing)
     */
    public static function resetInstance()
    {
        self::$instance = null;
    }
}
