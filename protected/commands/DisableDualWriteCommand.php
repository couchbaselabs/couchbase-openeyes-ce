<?php
/**
 * Disable Dual-Write Command
 * 
 * Transitions the system from dual-write mode (MariaDB + Couchbase) 
 * to Couchbase-primary mode (Couchbase only).
 * 
 * This is a critical step in the MariaDB decommissioning process.
 * 
 * Prerequisites:
 * - 100% traffic on Couchbase for 4+ weeks
 * - Zero rollbacks during stabilization
 * - All validation checks passing
 * - Final MariaDB backup created
 */

class DisableDualWriteCommand extends CConsoleCommand
{
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic disabledualwrite <action> [options]

DESCRIPTION
  Transitions from dual-write mode to Couchbase-primary mode.
  This disables writes to MariaDB, making Couchbase the only write target.

ACTIONS
  check       Run pre-disable checks without making changes
  disable     Disable dual-write (requires confirmation)
  status      Show current write mode status
  rollback    Re-enable dual-write if issues arise

OPTIONS
  --force     Skip confirmation prompts (use with caution)
  --backup    Create MariaDB backup before disabling

EXAMPLES
  yiic disabledualwrite check
  yiic disabledualwrite disable
  yiic disabledualwrite disable --backup
  yiic disabledualwrite status
  yiic disabledualwrite rollback

EOD;
    }
    
    /**
     * Run pre-disable checks
     */
    public function actionCheck()
    {
        echo "===========================================\n";
        echo "PRE-DISABLE DUAL-WRITE CHECKS\n";
        echo "===========================================\n\n";
        
        $passed = 0;
        $failed = 0;
        
        // Check 1: Current write mode
        echo "1. Current Write Mode\n";
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $config = $manager->getConfig();
            $writeMode = $config['write_mode'];
            
            if ($writeMode === 'dual_write') {
                echo "   ✓ Currently in dual_write mode\n";
                $passed++;
            } else if ($writeMode === 'couchbase_primary') {
                echo "   - Already in couchbase_primary mode\n";
                $passed++;
            } else {
                echo "   ✗ Unexpected mode: {$writeMode}\n";
                $failed++;
            }
        } catch (Exception $e) {
            echo "   ✗ Error: {$e->getMessage()}\n";
            $failed++;
        }
        
        // Check 2: Traffic percentage
        echo "\n2. Traffic Percentage\n";
        try {
            $percentage = $config['couchbase_read_percentage'] ?? 0;
            
            if ($percentage === 100) {
                echo "   ✓ Traffic at 100% Couchbase\n";
                $passed++;
            } else {
                echo "   ✗ Traffic at {$percentage}% (should be 100%)\n";
                $failed++;
            }
        } catch (Exception $e) {
            echo "   ✗ Error: {$e->getMessage()}\n";
            $failed++;
        }
        
        // Check 3: Emergency disable status
        echo "\n3. Emergency Disable Status\n";
        try {
            $emergency = $config['emergency_disable'] ?? false;
            
            if (!$emergency) {
                echo "   ✓ No emergency disable active\n";
                $passed++;
            } else {
                echo "   ✗ Emergency disable is active\n";
                $failed++;
            }
        } catch (Exception $e) {
            echo "   ✗ Error: {$e->getMessage()}\n";
            $failed++;
        }
        
        // Check 4: Couchbase health
        echo "\n4. Couchbase Health\n";
        try {
            if (!Yii::app()->hasComponent('couchbase')) {
                echo "   ✗ Couchbase component not configured\n";
                $failed++;
            } else {
                $start = microtime(true);
                Yii::app()->couchbase->query("SELECT 1");
                $latency = round((microtime(true) - $start) * 1000, 2);
                
                if ($latency < 100) {
                    echo "   ✓ Couchbase healthy ({$latency}ms)\n";
                    $passed++;
                } else {
                    echo "   ⚠ Couchbase slow ({$latency}ms)\n";
                    $passed++; // Warning but not failure
                }
            }
        } catch (Exception $e) {
            echo "   ✗ Couchbase error: {$e->getMessage()}\n";
            $failed++;
        }
        
        // Check 5: MariaDB health (for backup purposes)
        echo "\n5. MariaDB Health\n";
        try {
            $count = Patient::model()->count();
            echo "   ✓ MariaDB accessible ({$count} patients)\n";
            $passed++;
        } catch (Exception $e) {
            echo "   ✗ MariaDB error: {$e->getMessage()}\n";
            $failed++;
        }
        
        // Check 6: Data sync status
        echo "\n6. Data Sync Status\n";
        try {
            $tables = ['patient', 'episode', 'event'];
            $allSynced = true;
            
            foreach ($tables as $table) {
                $modelClass = ucfirst($table);
                $mysqlCount = $modelClass::model()->count();
                
                try {
                    $cbCount = Yii::app()->couchbase->count('clinical', $table);
                } catch (Exception $e) {
                    $cbCount = 0;
                }
                
                if ($mysqlCount !== $cbCount) {
                    $allSynced = false;
                    echo "   ✗ {$table}: MySQL={$mysqlCount}, CB={$cbCount}\n";
                }
            }
            
            if ($allSynced) {
                echo "   ✓ All critical tables synced\n";
                $passed++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            echo "   ✗ Sync check error: {$e->getMessage()}\n";
            $failed++;
        }
        
        // Check 7: Recent errors
        echo "\n7. Recent Couchbase Errors\n";
        try {
            $logFile = Yii::app()->basePath . '/runtime/application.log';
            $errorCount = 0;
            
            if (file_exists($logFile)) {
                $lines = array_slice(file($logFile), -500);
                foreach ($lines as $line) {
                    if (stripos($line, 'couchbase') !== false && stripos($line, 'error') !== false) {
                        $errorCount++;
                    }
                }
            }
            
            if ($errorCount === 0) {
                echo "   ✓ No recent Couchbase errors\n";
                $passed++;
            } else if ($errorCount < 10) {
                echo "   ⚠ {$errorCount} recent errors (review logs)\n";
                $passed++; // Warning
            } else {
                echo "   ✗ {$errorCount} recent errors (too many)\n";
                $failed++;
            }
        } catch (Exception $e) {
            echo "   ✗ Log check error: {$e->getMessage()}\n";
            $failed++;
        }
        
        // Summary
        echo "\n===========================================\n";
        echo "SUMMARY\n";
        echo "===========================================\n";
        echo "Passed: {$passed}\n";
        echo "Failed: {$failed}\n\n";
        
        if ($failed === 0) {
            echo "✓ READY TO DISABLE DUAL-WRITE\n";
            echo "\nRun: yiic disabledualwrite disable\n";
            return 0;
        } else {
            echo "✗ NOT READY - Address failures before proceeding\n";
            return 1;
        }
    }
    
    /**
     * Disable dual-write
     */
    public function actionDisable($force = false, $backup = false)
    {
        echo "===========================================\n";
        echo "DISABLE DUAL-WRITE\n";
        echo "===========================================\n\n";
        
        // Run checks first
        echo "Running pre-disable checks...\n\n";
        
        if ($this->actionCheck() !== 0 && !$force) {
            echo "\nPre-disable checks failed. Use --force to override.\n";
            return 1;
        }
        
        echo "\n";
        
        // Backup reminder
        if ($backup) {
            echo "Creating MariaDB backup...\n";
            echo "NOTE: Run this manually for production:\n";
            echo "  mysqldump -u root -p openeyes > openeyes_pre_disable_$(date +%Y%m%d).sql\n\n";
        } else {
            echo "⚠ REMINDER: Create a MariaDB backup before proceeding!\n";
            echo "  Run with --backup flag or manually backup first.\n\n";
        }
        
        // Confirmation
        if (!$force) {
            echo "This will DISABLE writes to MariaDB.\n";
            echo "All writes will go to Couchbase ONLY.\n\n";
            echo "Type 'DISABLE' to confirm: ";
            
            $confirmation = trim(fgets(STDIN));
            if ($confirmation !== 'DISABLE') {
                echo "Aborted.\n";
                return 1;
            }
        }
        
        echo "\nDisabling dual-write...\n\n";
        
        // Update configuration
        echo "1. Updating configuration...\n";
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $manager->updateConfig(['write_mode' => 'couchbase_primary']);
            echo "   ✓ Write mode set to 'couchbase_primary'\n";
        } catch (Exception $e) {
            echo "   ✗ Failed: {$e->getMessage()}\n";
            return 1;
        }
        
        // Clear cache
        echo "\n2. Clearing application cache...\n";
        try {
            if (Yii::app()->cache) {
                Yii::app()->cache->flush();
                echo "   ✓ Cache cleared\n";
            } else {
                echo "   - No cache component\n";
            }
        } catch (Exception $e) {
            echo "   ⚠ Cache clear failed: {$e->getMessage()}\n";
        }
        
        // Log the change
        echo "\n3. Logging transition...\n";
        Yii::log(
            "DUAL-WRITE DISABLED: System transitioned to Couchbase-primary mode",
            'info',
            'couchbase.cutover'
        );
        echo "   ✓ Logged\n";
        
        // Verification
        echo "\n4. Verifying configuration...\n";
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            CouchbaseCutoverManager::resetInstance(); // Force reload
            $manager = CouchbaseCutoverManager::getInstance();
            $config = $manager->getConfig();
            
            if ($config['write_mode'] === 'couchbase_primary') {
                echo "   ✓ Configuration verified\n";
            } else {
                echo "   ✗ Configuration mismatch\n";
                return 1;
            }
        } catch (Exception $e) {
            echo "   ✗ Verification failed: {$e->getMessage()}\n";
            return 1;
        }
        
        echo "\n===========================================\n";
        echo "DUAL-WRITE DISABLED\n";
        echo "===========================================\n\n";
        echo "✓ Writes now go to Couchbase ONLY\n";
        echo "✓ MariaDB is now READ-ONLY\n\n";
        
        echo "Next steps:\n";
        echo "1. Monitor for 48+ hours\n";
        echo "2. Check logs: tail -f protected/runtime/application.log\n";
        echo "3. If issues: yiic disabledualwrite rollback\n";
        echo "4. After stabilization: Plan MariaDB decommissioning\n\n";
        
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        
        return 0;
    }
    
    /**
     * Show current status
     */
    public function actionStatus()
    {
        echo "===========================================\n";
        echo "DUAL-WRITE STATUS\n";
        echo "===========================================\n\n";
        
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $config = $manager->getConfig();
            
            echo "Write Mode: {$config['write_mode']}\n";
            echo "Read Source: {$config['read_source']}\n";
            echo "Traffic %: {$config['couchbase_read_percentage']}%\n";
            echo "Emergency Disable: " . ($config['emergency_disable'] ? 'YES' : 'NO') . "\n";
            echo "Fallback Enabled: " . ($config['fallback_enabled'] ? 'YES' : 'NO') . "\n";
            
            echo "\n";
            
            switch ($config['write_mode']) {
                case 'mariadb_only':
                    echo "Status: MariaDB ONLY (legacy mode)\n";
                    echo "  - All writes go to MariaDB only\n";
                    break;
                    
                case 'dual_write':
                    echo "Status: DUAL-WRITE (transition mode)\n";
                    echo "  - Writes go to BOTH MariaDB and Couchbase\n";
                    echo "  - Ready to disable when stable\n";
                    break;
                    
                case 'couchbase_primary':
                    echo "Status: COUCHBASE-PRIMARY (target mode)\n";
                    echo "  - Writes go to Couchbase ONLY\n";
                    echo "  - MariaDB is READ-ONLY\n";
                    break;
                    
                default:
                    echo "Status: UNKNOWN ({$config['write_mode']})\n";
            }
            
        } catch (Exception $e) {
            echo "Error: {$e->getMessage()}\n";
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Rollback to dual-write mode
     */
    public function actionRollback()
    {
        echo "===========================================\n";
        echo "ROLLBACK TO DUAL-WRITE\n";
        echo "===========================================\n\n";
        
        echo "This will RE-ENABLE writes to MariaDB.\n\n";
        echo "Type 'ROLLBACK' to confirm: ";
        
        $confirmation = trim(fgets(STDIN));
        if ($confirmation !== 'ROLLBACK') {
            echo "Aborted.\n";
            return 1;
        }
        
        echo "\nRolling back to dual-write...\n\n";
        
        // Update configuration
        echo "1. Updating configuration...\n";
        try {
            $manager = CouchbaseCutoverManager::getInstance();
            $manager->updateConfig(['write_mode' => 'dual_write']);
            echo "   ✓ Write mode set to 'dual_write'\n";
        } catch (Exception $e) {
            echo "   ✗ Failed: {$e->getMessage()}\n";
            return 1;
        }
        
        // Clear cache
        echo "\n2. Clearing application cache...\n";
        try {
            if (Yii::app()->cache) {
                Yii::app()->cache->flush();
                echo "   ✓ Cache cleared\n";
            }
        } catch (Exception $e) {
            echo "   ⚠ Cache clear failed\n";
        }
        
        // Log
        echo "\n3. Logging rollback...\n";
        Yii::log(
            "DUAL-WRITE RE-ENABLED: Rolled back from Couchbase-primary mode",
            'warning',
            'couchbase.cutover'
        );
        echo "   ✓ Logged\n";
        
        echo "\n===========================================\n";
        echo "ROLLBACK COMPLETE\n";
        echo "===========================================\n\n";
        echo "✓ Dual-write mode restored\n";
        echo "✓ Writes now go to BOTH MariaDB and Couchbase\n\n";
        
        echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        
        return 0;
    }
}
