<?php
/**
 * Test OPCS Code dual-write functionality
 */

class TestOpcsCodeDualWriteCommand extends CConsoleCommand
{
    public function actionIndex()
    {
        echo "Testing OPCS Code dual-write...\n\n";
        
        $timestamp = time();
        $opcs = new OPCSCode();
        $opcs->name = "Z99.{$timestamp}";
        $opcs->description = "Test OPCS Code for Dual-Write Verification";
        $opcs->active = 1;
        
        echo "Creating test OPCS code...\n";
        if ($opcs->save()) {
            $id = $opcs->id;
            echo "✓ Saved to MariaDB (ID: {$id})\n";
            echo "  Name: {$opcs->name}\n";
            echo "  Description: {$opcs->description}\n";
            
            // Wait a moment for async sync
            sleep(1);
            
            // Check Couchbase
            echo "\nChecking Couchbase...\n";
            try {
                $cb = Yii::app()->couchbase;
                $collection = $cb->getCollection('reference', 'opcs_code');
                $doc = $collection->get("opcs_code::{$id}");
                
                echo "✓ FOUND in Couchbase!\n";
                $content = $doc->content();
                echo "  Name: {$content['name']}\n";
                echo "  Description: {$content['description']}\n";
                echo "  Type: {$content['_type']}\n";
                echo "  Active: " . ($content['active'] ? 'true' : 'false') . "\n";
                
                // Clean up
                echo "\nCleaning up test data...\n";
                $opcs->delete();
                echo "✓ Test OPCS code deleted from MariaDB\n";
                
                // Verify deleted from Couchbase
                sleep(1);
                try {
                    $collection->get("opcs_code::{$id}");
                    echo "⚠ Still in Couchbase (delete hook may not have worked)\n";
                    return 1;
                } catch (Exception $e) {
                    echo "✓ Deleted from Couchbase\n";
                }
                
                echo "\n======================================\n";
                echo "✓ Dual-Write Test PASSED\n";
                echo "======================================\n";
                return 0;
                
            } catch (Exception $e) {
                echo "✗ NOT found in Couchbase\n";
                echo "  Error: {$e->getMessage()}\n";
                
                // Still clean up MariaDB
                $opcs->delete();
                return 1;
            }
        } else {
            echo "✗ Failed to save to MariaDB\n";
            print_r($opcs->errors);
            return 1;
        }
    }
}
