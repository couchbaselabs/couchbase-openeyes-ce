<?php
/**
 * Couchbase Rollback Command
 * 
 * Emergency rollback procedures for reverting from Couchbase to MariaDB.
 * Provides instant and gradual rollback options.
 * 
 * Phase 16: Production Cutover
 */

class CouchbaseRollbackCommand extends CConsoleCommand
{
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic couchbaserollback <action> [options]

DESCRIPTION
  Emergency rollback procedures for Couchbase cutover.

ACTIONS
  instant          Emergency instant rollback (disables Couchbase immediately)
  gradual          Gradual rollback over time
  verify           Verify rollback status
  reenable         Re-enable Couchbase after rollback

INSTANT ROLLBACK
  yiic couchbaserollback instant [reason]
  
  Arguments:
    reason         Reason for rollback (required)
  
  Example:
    yiic couchbaserollback instant "High error rate detected"

GRADUAL ROLLBACK  
  yiic couchbaserollback gradual --targetPercentage=<n> --steps=<n> --intervalMinutes=<n>
  
  Options:
    --targetPercentage    Target percentage (default: 0)
    --steps               Number of steps (default: 5)
    --intervalMinutes     Minutes between steps (default: 5)
  
  Example:
    yiic couchbaserollback gradual --targetPercentage=0 --steps=5 --intervalMinutes=5

VERIFY ROLLBACK
  yiic couchbaserollback verify
  
  Checks:
    - Emergency disable status
    - Traffic routing configuration
    - MariaDB connectivity
    - Current traffic percentage

RE-ENABLE
  yiic couchbaserollback reenable --percentage=<n>
  
  Options:
    --percentage    Starting percentage (default: 10)
  
  Example:
    yiic couchbaserollback reenable --percentage=10

EOD;
    }
    
    /**
     * Instant emergency rollback
     * Disables Couchbase reads immediately, routes all traffic to MariaDB
     * 
     * @param string $reason Reason for rollback
     */
    public function actionInstant($reason = 'Emergency rollback')
    {
        echo "===========================================\n";
        echo "INSTANT ROLLBACK\n";
        echo "===========================================\n\n";
        
        if (empty($reason)) {
            echo "Error: Reason is required\n";
            echo "Usage: yiic couchbaserollback instant \"reason\"\n";
            return 1;
        }
        
        echo "Reason: {$reason}\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Confirm action
        echo "This will immediately disable all Couchbase reads and writes.\n";
        echo "All traffic will be routed to MariaDB.\n\n";
        echo "Type 'ROLLBACK' to confirm: ";
        
        $confirmation = trim(fgets(STDIN));
        if ($confirmation !== 'ROLLBACK') {
            echo "Aborted.\n";
            return 1;
        }
        
        echo "\nExecuting rollback...\n";
        
        // Get cutover manager
        try {
            $manager = CouchbaseCutoverManager::getInstance();
        } catch (Exception $e) {
            echo "✗ Failed to load cutover manager: {$e->getMessage()}\n";
            return 1;
        }
        
        // Enable emergency disable
        echo "1. Disabling Couchbase reads...\n";
        $manager->emergencyDisable($reason);
        echo "   ✓ Couchbase disabled\n\n";
        
        echo "2. Verifying MariaDB connectivity...\n";
        try {
            $count = Patient::model()->count();
            echo "   ✓ MariaDB OK ({$count} patients)\n\n";
        } catch (Exception $e) {
            echo "   ✗ MariaDB ERROR: {$e->getMessage()}\n";
            echo "   WARNING: MariaDB may not be accessible!\n\n";
        }
        
        echo "3. Clearing application cache...\n";
        try {
            if (Yii::app()->cache) {
                Yii::app()->cache->flush();
                echo "   ✓ Cache cleared\n\n";
            } else {
                echo "   - No cache component configured\n\n";
            }
        } catch (Exception $e) {
            echo "   ✗ Cache clear failed: {$e->getMessage()}\n\n";
        }
        
        echo "===========================================\n";
        echo "ROLLBACK COMPLETE\n";
        echo "===========================================\n\n";
        echo "✓ All traffic now routed to MariaDB\n";
        echo "✓ Couchbase operations disabled\n\n";
        echo "Reason: {$reason}\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
        
        echo "Next steps:\n";
        echo "1. Monitor application for stability\n";
        echo "2. Investigate Couchbase issues\n";
        echo "3. Run: yiic couchbaserollback verify\n";
        echo "4. When ready to re-enable: yiic couchbaserollback reenable --percentage=10\n\n";
        
        return 0;
    }
    
    /**
     * Gradual rollback
     * Reduces Couchbase traffic percentage over time
     * 
     * @param int $targetPercentage Target percentage (default: 0)
     * @param int $steps Number of steps (default: 5)
     * @param int $intervalMinutes Minutes between steps (default: 5)
     */
    public function actionGradual($targetPercentage = 0, $steps = 5, $intervalMinutes = 5)
    {
        echo "===========================================\n";
        echo "GRADUAL ROLLBACK\n";
        echo "===========================================\n\n";
        
        $manager = CouchbaseCutoverManager::getInstance();
        $config = $manager->getConfig();
        $currentPercentage = $config['couchbase_read_percentage'];
        
        echo "Current traffic: {$currentPercentage}%\n";
        echo "Target traffic: {$targetPercentage}%\n";
        echo "Steps: {$steps}\n";
        echo "Interval: {$intervalMinutes} minutes\n\n";
        
        if ($currentPercentage <= $targetPercentage) {
            echo "Current percentage is already at or below target.\n";
            return 0;
        }
        
        // Confirm action
        $totalTime = $steps * $intervalMinutes;
        echo "This will reduce traffic over {$totalTime} minutes.\n";
        echo "Type 'YES' to confirm: ";
        
        $confirmation = trim(fgets(STDIN));
        if ($confirmation !== 'YES') {
            echo "Aborted.\n";
            return 1;
        }
        
        echo "\nStarting gradual rollback...\n\n";
        
        $step = ($currentPercentage - $targetPercentage) / $steps;
        
        for ($i = 1; $i <= $steps; $i++) {
            $newPercentage = max(
                $targetPercentage,
                $currentPercentage - ($step * $i)
            );
            $newPercentage = round($newPercentage);
            
            echo "--- Step {$i}/{$steps} ---\n";
            echo "Setting traffic to {$newPercentage}%...\n";
            
            $manager->setTrafficPercentage($newPercentage);
            echo "✓ Traffic updated\n";
            
            // Quick health check
            try {
                $count = Patient::model()->count();
                echo "✓ MariaDB healthy ({$count} patients)\n";
            } catch (Exception $e) {
                echo "✗ MariaDB error: {$e->getMessage()}\n";
            }
            
            if ($i < $steps) {
                echo "Waiting {$intervalMinutes} minutes...\n";
                sleep($intervalMinutes * 60);
                echo "\n";
            }
        }
        
        echo "\n===========================================\n";
        echo "GRADUAL ROLLBACK COMPLETE\n";
        echo "===========================================\n\n";
        echo "Current traffic: {$targetPercentage}%\n";
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
        
        if ($targetPercentage === 0) {
            echo "All traffic now on MariaDB.\n";
            echo "Consider running 'instant' rollback to fully disable Couchbase.\n";
        }
        
        return 0;
    }
    
    /**
     * Verify rollback status
     * Checks current configuration and connectivity
     */
    public function actionVerify()
    {
        echo "===========================================\n";
        echo "ROLLBACK STATUS VERIFICATION\n";
        echo "===========================================\n\n";
        
        // Get configuration
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $config = $manager->getConfig();
        } catch (Exception $e) {
            echo "✗ Failed to load configuration: {$e->getMessage()}\n";
            return 1;
        }
        
        // Display configuration
        echo "Configuration:\n";
        echo "  Enabled: " . ($config['enabled'] ? 'YES' : 'NO') . "\n";
        echo "  Emergency Disable: " . ($config['emergency_disable'] ? 'YES' : 'NO') . "\n";
        
        if ($config['emergency_disable']) {
            echo "    Reason: {$config['emergency_disable_reason']}\n";
            if ($config['emergency_disable_timestamp']) {
                echo "    Time: " . date('Y-m-d H:i:s', $config['emergency_disable_timestamp']) . "\n";
            }
        }
        
        echo "  Read Source: {$config['read_source']}\n";
        echo "  Write Mode: {$config['write_mode']}\n";
        echo "  Traffic %: {$config['couchbase_read_percentage']}%\n";
        echo "  Fallback Enabled: " . ($config['fallback_enabled'] ? 'YES' : 'NO') . "\n";
        echo "\n";
        
        // Emergency status
        if ($config['emergency_disable']) {
            echo "⚠ COUCHBASE IS DISABLED (EMERGENCY MODE)\n\n";
        } else if ($config['couchbase_read_percentage'] === 0) {
            echo "✓ All traffic on MariaDB (0% to Couchbase)\n\n";
        } else {
            echo "ℹ Traffic split: {$config['couchbase_read_percentage']}% Couchbase, ";
            echo (100 - $config['couchbase_read_percentage']) . "% MariaDB\n\n";
        }
        
        // Test MariaDB
        echo "Testing MariaDB connectivity:\n";
        try {
            $start = microtime(true);
            $count = Patient::model()->count();
            $duration = round((microtime(true) - $start) * 1000, 2);
            echo "  ✓ MariaDB OK\n";
            echo "    Patients: {$count}\n";
            echo "    Latency: {$duration}ms\n";
        } catch (Exception $e) {
            echo "  ✗ MariaDB ERROR: {$e->getMessage()}\n";
            return 1;
        }
        
        echo "\n";
        
        // Test Couchbase
        echo "Testing Couchbase connectivity:\n";
        try {
            if (!Yii::app()->hasComponent('couchbase')) {
                echo "  - Couchbase component not configured\n";
            } else {
                $start = microtime(true);
                $adapter = Yii::app()->couchbase;
                $adapter->query("SELECT 1");
                $duration = round((microtime(true) - $start) * 1000, 2);
                echo "  ✓ Couchbase OK\n";
                echo "    Latency: {$duration}ms\n";
            }
        } catch (Exception $e) {
            echo "  ✗ Couchbase ERROR: {$e->getMessage()}\n";
        }
        
        echo "\n";
        
        // Summary
        echo "===========================================\n";
        echo "SUMMARY\n";
        echo "===========================================\n";
        
        if ($config['emergency_disable']) {
            echo "Status: EMERGENCY ROLLBACK ACTIVE\n";
            echo "Action: All traffic on MariaDB\n";
        } else if ($config['couchbase_read_percentage'] === 0) {
            echo "Status: FULL ROLLBACK COMPLETE\n";
            echo "Action: All traffic on MariaDB\n";
        } else {
            echo "Status: PARTIAL ROLLBACK\n";
            echo "Action: {$config['couchbase_read_percentage']}% traffic to Couchbase\n";
        }
        
        return 0;
    }
    
    /**
     * Re-enable Couchbase after rollback
     * 
     * @param int $percentage Starting percentage (default: 10)
     */
    public function actionReenable($percentage = 10)
    {
        echo "===========================================\n";
        echo "RE-ENABLE COUCHBASE\n";
        echo "===========================================\n\n";
        
        echo "This will re-enable Couchbase at {$percentage}% traffic.\n\n";
        
        $manager = CouchbaseCutoverManager::getInstance();
        $config = $manager->getConfig();
        
        // Check if Couchbase is healthy
        echo "Checking Couchbase health...\n";
        try {
            if (!Yii::app()->hasComponent('couchbase')) {
                echo "✗ Couchbase component not configured\n";
                return 1;
            }
            
            $adapter = Yii::app()->couchbase;
            $start = microtime(true);
            $adapter->query("SELECT 1");
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            echo "✓ Couchbase is healthy\n";
            echo "  Latency: {$duration}ms\n\n";
            
            if ($duration > 100) {
                echo "⚠ Warning: High latency detected ({$duration}ms)\n";
                echo "Consider investigating before re-enabling.\n\n";
            }
        } catch (Exception $e) {
            echo "✗ Couchbase is unhealthy: {$e->getMessage()}\n";
            echo "Cannot re-enable until Couchbase is healthy.\n";
            return 1;
        }
        
        // Confirm
        echo "Type 'YES' to re-enable Couchbase at {$percentage}%: ";
        $confirmation = trim(fgets(STDIN));
        if ($confirmation !== 'YES') {
            echo "Aborted.\n";
            return 1;
        }
        
        echo "\nRe-enabling Couchbase...\n";
        
        // Clear emergency disable
        if ($config['emergency_disable']) {
            echo "1. Clearing emergency disable...\n";
            $manager->clearEmergencyDisable();
            echo "   ✓ Emergency disable cleared\n\n";
        }
        
        // Set traffic percentage
        echo "2. Setting traffic to {$percentage}%...\n";
        $manager->setTrafficPercentage($percentage);
        echo "   ✓ Traffic percentage updated\n\n";
        
        // Clear cache
        echo "3. Clearing application cache...\n";
        try {
            if (Yii::app()->cache) {
                Yii::app()->cache->flush();
                echo "   ✓ Cache cleared\n\n";
            }
        } catch (Exception $e) {
            echo "   ✗ Cache clear failed: {$e->getMessage()}\n\n";
        }
        
        echo "===========================================\n";
        echo "RE-ENABLE COMPLETE\n";
        echo "===========================================\n\n";
        echo "✓ Couchbase re-enabled at {$percentage}%\n";
        echo "✓ Emergency disable cleared\n\n";
        
        echo "Next steps:\n";
        echo "1. Monitor error rates and performance\n";
        echo "2. Gradually increase percentage if stable\n";
        echo "3. Run: yiic couchbaserollback verify\n\n";
        
        return 0;
    }
}
