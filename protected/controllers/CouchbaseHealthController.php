<?php
/**
 * Couchbase health check controller
 * 
 * Provides a health check endpoint for monitoring Couchbase connectivity.
 * Returns JSON with connection status, latency, and diagnostic information.
 * 
 * Endpoints:
 *   GET /couchbaseHealth - Full health check
 *   GET /couchbaseHealth/ping - Simple ping check
 */

class CouchbaseHealthController extends CController
{
    /**
     * Disable layout for API responses
     */
    public $layout = false;
    
    /**
     * Allow access without authentication for health checks
     */
    public function filters()
    {
        return [];
    }
    
    /**
     * Full health check
     * GET /couchbaseHealth
     */
    public function actionIndex()
    {
        $this->sendJsonResponse($this->performHealthCheck());
    }
    
    /**
     * Simple ping check
     * GET /couchbaseHealth/ping
     */
    public function actionPing()
    {
        try {
            $pingResult = Yii::app()->couchbase->ping();
            $this->sendJsonResponse([
                'status' => $pingResult['status'],
                'timestamp' => date('c'),
            ], $pingResult['status'] === 'healthy' ? 200 : 503);
        } catch (Exception $e) {
            $this->sendJsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => date('c'),
            ], 503);
        }
    }
    
    /**
     * Perform comprehensive health check
     * @return array Health check results
     */
    private function performHealthCheck(): array
    {
        $status = [
            'service' => 'couchbase',
            'status' => 'unknown',
            'timestamp' => date('c'),
            'checks' => [],
        ];
        
        $httpCode = 200;
        
        // Check 1: Configuration
        try {
            $config = Yii::app()->couchbase->config;
            $status['checks']['configuration'] = [
                'status' => 'pass',
                'host' => $config['connection']['host'],
                'bucket' => $config['bucket'],
            ];
        } catch (Exception $e) {
            $status['checks']['configuration'] = [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
            $httpCode = 503;
        }
        
        // Check 2: Connectivity
        try {
            $pingResult = Yii::app()->couchbase->ping();
            $status['checks']['connectivity'] = [
                'status' => $pingResult['status'] === 'healthy' ? 'pass' : 'fail',
                'latency_ms' => $pingResult['latency_ms'],
            ];
            if ($pingResult['status'] !== 'healthy') {
                $httpCode = 503;
            }
        } catch (Exception $e) {
            $status['checks']['connectivity'] = [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
            $httpCode = 503;
        }
        
        // Check 3: Query capability
        try {
            $start = microtime(true);
            $result = Yii::app()->couchbase->query('SELECT 1 as test');
            $latency = round((microtime(true) - $start) * 1000, 2);
            
            $status['checks']['query'] = [
                'status' => 'pass',
                'latency_ms' => $latency,
            ];
        } catch (Exception $e) {
            $status['checks']['query'] = [
                'status' => 'fail',
                'error' => $e->getMessage(),
            ];
            $httpCode = 503;
        }
        
        // Determine overall status
        $failedChecks = array_filter($status['checks'], function($check) {
            return $check['status'] === 'fail';
        });
        
        $status['status'] = empty($failedChecks) ? 'healthy' : 'unhealthy';
        
        // Set HTTP code
        http_response_code($httpCode);
        
        return $status;
    }
    
    /**
     * Send JSON response
     * @param array $data Response data
     * @param int $httpCode HTTP status code
     */
    private function sendJsonResponse(array $data, int $httpCode = 200)
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        Yii::app()->end();
    }
}
