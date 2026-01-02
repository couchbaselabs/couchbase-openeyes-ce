<?php
/**
 * Test command to verify dual-write is working for Phase 11 models
 * 
 * Usage: php protected/yiic testdualwrite
 */

class TestDualWriteCommand extends CConsoleCommand
{
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic testdualwrite

DESCRIPTION
  Tests dual-write functionality for Phase 11 clinical reference models.
  Creates test records, verifies they exist in both MariaDB and Couchbase,
  then cleans them up.

EOD;
    }

    public function actionIndex()
    {
        echo "======================================\n";
        echo "Phase 11 Dual-Write Test\n";
        echo "======================================\n\n";

        $cb = Yii::app()->couchbase;
        if (!$cb) {
            echo "✗ ERROR: Couchbase adapter not configured\n";
            return 1;
        }

        $dualWriteEnabled = Yii::app()->params['enable_dual_write'] ?? false;
        echo "Dual-write config: " . ($dualWriteEnabled ? "ENABLED" : "DISABLED") . "\n";
        echo "Couchbase read config: " . (Yii::app()->params['enable_couchbase_read'] ?? false ? "ENABLED" : "DISABLED") . "\n\n";

        $this->testBenefit($cb);
        echo "\n";
        $this->testComplication($cb);

        echo "\n======================================\n";
        echo "Dual-Write Test Complete\n";
        echo "======================================\n";

        return 0;
    }

    protected function testBenefit($cb)
    {
        echo "Testing Benefit dual-write...\n";
        
        $benefit = new Benefit();
        $benefit->name = 'Test Benefit ' . time();
        $benefit->active = 1;

        try {
            if ($benefit->save()) {
                $id = $benefit->id;
                echo "  ✓ Benefit saved to MariaDB (ID: {$id})\n";
                
                // Give Couchbase a moment to process
                usleep(500000); // 0.5 seconds
                
                // Check if it's in Couchbase
                try {
                    $doc = $cb->get('reference', 'benefit', 'benefit::' . $id);
                    if ($doc) {
                        echo "  ✓ Benefit found in Couchbase\n";
                        echo "    - Name: {$doc['name']}\n";
                        echo "    - Type: {$doc['_type']}\n";
                        echo "    - Modified: {$doc['_modified']}\n";
                    } else {
                        echo "  ✗ Benefit NOT found in Couchbase\n";
                    }
                } catch (Exception $e) {
                    echo "  ✗ Error checking Couchbase: {$e->getMessage()}\n";
                }
                
                // Clean up
                $benefit->delete();
                echo "  ✓ Test benefit cleaned up from MariaDB\n";
                
                // Verify deletion from Couchbase
                try {
                    $doc = $cb->get('reference', 'benefit', 'benefit::' . $id);
                    if (!$doc) {
                        echo "  ✓ Benefit deleted from Couchbase\n";
                    } else {
                        echo "  ⚠ Benefit still in Couchbase after delete\n";
                    }
                } catch (Exception $e) {
                    echo "  ✓ Benefit deleted from Couchbase (not found)\n";
                }
            } else {
                echo "  ✗ Failed to save benefit: " . print_r($benefit->errors, true) . "\n";
            }
        } catch (Exception $e) {
            echo "  ✗ Exception: {$e->getMessage()}\n";
            echo "  Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    protected function testComplication($cb)
    {
        echo "Testing Complication dual-write...\n";
        
        $complication = new Complication();
        $complication->name = 'Test Complication ' . time();
        $complication->active = 1;

        try {
            if ($complication->save()) {
                $id = $complication->id;
                echo "  ✓ Complication saved to MariaDB (ID: {$id})\n";
                
                // Give Couchbase a moment to process
                usleep(500000); // 0.5 seconds
                
                // Check if it's in Couchbase
                try {
                    $doc = $cb->get('reference', 'complication', 'complication::' . $id);
                    if ($doc) {
                        echo "  ✓ Complication found in Couchbase\n";
                        echo "    - Name: {$doc['name']}\n";
                        echo "    - Type: {$doc['_type']}\n";
                    } else {
                        echo "  ✗ Complication NOT found in Couchbase\n";
                    }
                } catch (Exception $e) {
                    echo "  ✗ Error checking Couchbase: {$e->getMessage()}\n";
                }
                
                // Clean up
                $complication->delete();
                echo "  ✓ Test complication cleaned up\n";
            } else {
                echo "  ✗ Failed to save complication\n";
            }
        } catch (Exception $e) {
            echo "  ✗ Exception: {$e->getMessage()}\n";
        }
    }
}
