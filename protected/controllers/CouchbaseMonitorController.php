<?php
/**
 * Couchbase Monitor Controller
 * 
 * Monitoring dashboard for Couchbase cutover status, health, performance, and sync.
 * Provides real-time visibility into the migration process.
 * 
 * Phase 16: Production Cutover
 */

class CouchbaseMonitorController extends BaseController
{
    /**
     * Access control - restrict to admin users only
     */
    public function accessRules()
    {
        return [
            ['allow', 'roles' => ['admin']],
            ['deny', 'users' => ['*']],
        ];
    }
    
    /**
     * Main dashboard
     * 
     * Displays cutover status, health, performance metrics, and sync status
     */
    public function actionIndex()
    {
        $data = [
            'cutover' => $this->getCutoverStatus(),
            'health' => $this->getHealthStatus(),
            'performance' => $this->getPerformanceMetrics(),
            'errors' => $this->getRecentErrors(),
            'sync' => $this->getSyncStatus(),
            'timestamp' => time(),
        ];
        
        $this->render('index', $data);
    }
    
    /**
     * Get cutover status
     * 
     * @return array Cutover configuration and status
     */
    protected function getCutoverStatus()
    {
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $config = $manager->getConfig();
            
            // Determine current phase
            $phase = $this->determinePhase($config);
            
            return [
                'enabled' => $config['enabled'],
                'emergency_disable' => $config['emergency_disable'],
                'emergency_reason' => $config['emergency_disable_reason'] ?? '',
                'emergency_timestamp' => $config['emergency_disable_timestamp'] ?? null,
                'read_source' => $config['read_source'],
                'write_mode' => $config['write_mode'],
                'percentage' => $config['couchbase_read_percentage'],
                'fallback_enabled' => $config['fallback_enabled'],
                'phase' => $phase,
                'phase_name' => $this->getPhaseName($phase),
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'enabled' => false,
                'percentage' => 0,
            ];
        }
    }
    
    /**
     * Determine current cutover phase
     * 
     * @param array $config Configuration
     * @return int Phase number (1-5)
     */
    protected function determinePhase($config)
    {
        if ($config['emergency_disable']) {
            return 0; // Emergency
        }
        
        $percentage = $config['couchbase_read_percentage'];
        
        if ($percentage === 0) {
            return 1; // Dual-write only
        } else if ($percentage <= 10) {
            return 2; // Canary (10%)
        } else if ($percentage <= 50) {
            return 3; // Partial (50%)
        } else if ($percentage < 100) {
            return 4; // Majority (>50%, <100%)
        } else if ($percentage === 100 && $config['write_mode'] === 'dual_write') {
            return 4; // Full Couchbase with dual-write
        } else {
            return 5; // Couchbase primary
        }
    }
    
    /**
     * Get phase name
     * 
     * @param int $phase Phase number
     * @return string Phase name
     */
    protected function getPhaseName($phase)
    {
        $phases = [
            0 => 'Emergency Rollback',
            1 => 'Phase 1: Dual-Write Only',
            2 => 'Phase 2: Canary (10% Reads)',
            3 => 'Phase 3: Partial (50% Reads)',
            4 => 'Phase 4: Majority (100% Reads)',
            5 => 'Phase 5: Couchbase Primary',
        ];
        
        return $phases[$phase] ?? 'Unknown';
    }
    
    /**
     * Get health status for both databases
     * 
     * @return array Health status
     */
    protected function getHealthStatus()
    {
        $status = [
            'mariadb' => ['status' => 'unknown', 'latency' => 0],
            'couchbase' => ['status' => 'unknown', 'latency' => 0],
        ];
        
        // Check MariaDB
        try {
            $start = microtime(true);
            Yii::app()->db->createCommand("SELECT 1")->queryScalar();
            $latency = (microtime(true) - $start) * 1000;
            
            $status['mariadb'] = [
                'status' => 'healthy',
                'latency' => round($latency, 2),
                'status_class' => $this->getStatusClass($latency),
            ];
        } catch (Exception $e) {
            $status['mariadb'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
                'status_class' => 'danger',
            ];
        }
        
        // Check Couchbase
        try {
            if (!Yii::app()->hasComponent('couchbase')) {
                $status['couchbase'] = [
                    'status' => 'not_configured',
                    'status_class' => 'warning',
                ];
            } else {
                $start = microtime(true);
                Yii::app()->couchbase->query("SELECT 1");
                $latency = (microtime(true) - $start) * 1000;
                
                $status['couchbase'] = [
                    'status' => 'healthy',
                    'latency' => round($latency, 2),
                    'status_class' => $this->getStatusClass($latency),
                ];
            }
        } catch (Exception $e) {
            $status['couchbase'] = [
                'status' => 'error',
                'error' => $e->getMessage(),
                'status_class' => 'danger',
            ];
        }
        
        return $status;
    }
    
    /**
     * Get status class based on latency
     * 
     * @param float $latency Latency in milliseconds
     * @return string Bootstrap class (success/warning/danger)
     */
    protected function getStatusClass($latency)
    {
        if ($latency < 50) {
            return 'success'; // Green
        } else if ($latency < 200) {
            return 'warning'; // Yellow
        } else {
            return 'danger'; // Red
        }
    }
    
    /**
     * Get performance metrics
     * 
     * @return array Performance metrics
     */
    protected function getPerformanceMetrics()
    {
        // Check if performance monitor is available
        if (!Yii::app()->hasComponent('couchbaseMonitor')) {
            return [
                'available' => false,
                'message' => 'Performance monitor not configured',
            ];
        }
        
        try {
            $monitor = CouchbasePerformanceMonitor::getInstance();
            $summary = $monitor->getSummary();
            
            return [
                'available' => true,
                'summary' => $summary,
                'health' => $monitor->getHealthStatus(),
            ];
        } catch (Exception $e) {
            return [
                'available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Get recent errors from logs
     * 
     * @return array Recent errors
     */
    protected function getRecentErrors()
    {
        $errors = [];
        
        // Read application log
        $logFile = Yii::app()->basePath . '/runtime/application.log';
        
        if (!file_exists($logFile)) {
            return $errors;
        }
        
        try {
            // Read last 200 lines
            $lines = array_slice(file($logFile), -200);
            
            // Filter for Couchbase errors
            foreach ($lines as $line) {
                if (stripos($line, 'couchbase') !== false && 
                    (stripos($line, 'error') !== false || stripos($line, 'warning') !== false)) {
                    $errors[] = trim($line);
                }
            }
            
            // Return last 10 errors
            return array_slice($errors, -10);
        } catch (Exception $e) {
            return ['Error reading log file: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get sync status between MariaDB and Couchbase
     * 
     * @return array Sync status for key tables
     */
    protected function getSyncStatus()
    {
        $tables = [
            'patient' => ['model' => 'Patient', 'scope' => 'clinical'],
            'episode' => ['model' => 'Episode', 'scope' => 'clinical'],
            'event' => ['model' => 'Event', 'scope' => 'clinical'],
            'disorder' => ['model' => 'Disorder', 'scope' => 'reference'],
            'medication' => ['model' => 'Medication', 'scope' => 'reference'],
        ];
        
        $status = [];
        
        if (!Yii::app()->hasComponent('couchbase')) {
            return ['error' => 'Couchbase not configured'];
        }
        
        $adapter = Yii::app()->couchbase;
        
        foreach ($tables as $table => $config) {
            $modelClass = $config['model'];
            
            if (!class_exists($modelClass)) {
                continue;
            }
            
            try {
                // Get MariaDB count
                $mysqlCount = $modelClass::model()->count();
                
                // Get Couchbase count
                try {
                    $cbCount = $adapter->count($config['scope'], $table);
                } catch (Exception $e) {
                    $cbCount = 0;
                }
                
                $diff = $mysqlCount - $cbCount;
                $diffPercent = $mysqlCount > 0 ? ($diff / $mysqlCount) * 100 : 0;
                
                $status[$table] = [
                    'mariadb' => $mysqlCount,
                    'couchbase' => $cbCount,
                    'synced' => $diff === 0,
                    'diff' => $diff,
                    'diff_percent' => round($diffPercent, 2),
                    'status_class' => $diff === 0 ? 'success' : ($diffPercent < 1 ? 'warning' : 'danger'),
                ];
            } catch (Exception $e) {
                $status[$table] = [
                    'error' => $e->getMessage(),
                    'status_class' => 'danger',
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * API endpoint for metrics (JSON)
     * 
     * For integration with monitoring tools
     */
    public function actionMetrics()
    {
        header('Content-Type: application/json');
        
        $data = [
            'timestamp' => time(),
            'cutover' => $this->getCutoverStatus(),
            'health' => $this->getHealthStatus(),
            'performance' => $this->getPerformanceMetrics(),
            'sync' => $this->getSyncStatus(),
        ];
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        Yii::app()->end();
    }
    
    /**
     * Emergency disable action
     * 
     * POST endpoint to trigger emergency disable
     */
    public function actionEmergencyDisable()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(405, 'Method not allowed');
        }
        
        $reason = Yii::app()->request->getPost('reason', 'Manual emergency disable via dashboard');
        
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $manager->emergencyDisable($reason);
            
            Yii::app()->user->setFlash('success', 'Emergency disable activated. All traffic routed to MariaDB.');
        } catch (Exception $e) {
            Yii::app()->user->setFlash('error', 'Failed to activate emergency disable: ' . $e->getMessage());
        }
        
        $this->redirect(['index']);
    }
    
    /**
     * Clear emergency disable action
     */
    public function actionClearEmergency()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(405, 'Method not allowed');
        }
        
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $manager->clearEmergencyDisable();
            
            Yii::app()->user->setFlash('success', 'Emergency disable cleared.');
        } catch (Exception $e) {
            Yii::app()->user->setFlash('error', 'Failed to clear emergency disable: ' . $e->getMessage());
        }
        
        $this->redirect(['index']);
    }
    
    /**
     * Update traffic percentage action
     * 
     * POST endpoint to update Couchbase traffic percentage
     */
    public function actionSetTraffic()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(405, 'Method not allowed');
        }
        
        $percentage = (int)Yii::app()->request->getPost('percentage', 0);
        
        if ($percentage < 0 || $percentage > 100) {
            Yii::app()->user->setFlash('error', 'Invalid percentage. Must be between 0 and 100.');
            $this->redirect(['index']);
            return;
        }
        
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $manager->setTrafficPercentage($percentage);
            
            Yii::app()->user->setFlash('success', "Traffic percentage updated to {$percentage}%.");
        } catch (Exception $e) {
            Yii::app()->user->setFlash('error', 'Failed to update traffic percentage: ' . $e->getMessage());
        }
        
        $this->redirect(['index']);
    }
}
