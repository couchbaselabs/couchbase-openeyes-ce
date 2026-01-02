<?php
/**
 * Manage allergies and test dual-write
 */

class AllergyCommand extends CConsoleCommand
{
    /**
     * Add a new allergy
     */
    public function actionAdd($name)
    {
        echo "Adding allergy: {$name}\n\n";
        
        // Check if already exists
        $existing = Allergy::model()->findByAttributes(['name' => $name]);
        if ($existing) {
            echo "✗ Allergy already exists with ID: {$existing->id}\n";
            return 1;
        }
        
        $allergy = new Allergy();
        $allergy->name = $name;
        $allergy->active = 1;
        
        if ($allergy->save()) {
            echo "✓ Saved to MariaDB (ID: {$allergy->id})\n";
            
            // Wait for sync
            sleep(1);
            
            // Check Couchbase
            echo "Checking Couchbase...\n";
            try {
                $cb = Yii::app()->couchbase;
                $collection = $cb->getCollection('reference', 'allergy');
                $doc = $collection->get("allergy::{$allergy->id}");
                
                echo "✓ Found in Couchbase!\n";
                $content = $doc->content();
                echo "  Name: {$content['name']}\n";
                echo "  Active: " . ($content['active'] ? 'true' : 'false') . "\n";
                echo "\n✓ Dual-write successful!\n";
                return 0;
            } catch (Exception $e) {
                echo "✗ NOT in Couchbase: {$e->getMessage()}\n";
                return 1;
            }
        } else {
            echo "✗ Failed to save to MariaDB\n";
            print_r($allergy->errors);
            return 1;
        }
    }

    /**
     * List all allergies
     */
    public function actionList()
    {
        echo "Allergies in MariaDB:\n";
        echo "======================================\n";
        
        $allergies = Allergy::model()->findAll(['order' => 'name']);
        
        foreach ($allergies as $allergy) {
            echo "ID {$allergy->id}: {$allergy->name}";
            echo " [" . ($allergy->active ? 'Active' : 'Inactive') . "]\n";
            
            // Check if in Couchbase
            try {
                $cb = Yii::app()->couchbase;
                $collection = $cb->getCollection('reference', 'allergy');
                $doc = $collection->get("allergy::{$allergy->id}");
                echo "  Couchbase: ✓ YES\n";
            } catch (Exception $e) {
                echo "  Couchbase: ✗ NO\n";
            }
        }
    }

    /**
     * Sync existing allergies to Couchbase
     */
    public function actionSync()
    {
        echo "Syncing allergies to Couchbase...\n\n";
        
        $allergies = Allergy::model()->findAll();
        $success = 0;
        $failed = 0;
        
        foreach ($allergies as $allergy) {
            echo "Syncing Allergy ID {$allergy->id}: {$allergy->name}\n";
            
            try {
                $result = $allergy->syncToCouchbase();
                if ($result) {
                    echo "  ✓ Synced successfully\n";
                    $success++;
                } else {
                    echo "  ✗ Sync returned false\n";
                    $failed++;
                }
            } catch (Exception $e) {
                echo "  ✗ Error: {$e->getMessage()}\n";
                $failed++;
            }
        }
        
        echo "\n======================================\n";
        echo "Summary: {$success} synced, {$failed} failed\n";
        echo "======================================\n";
        
        return $failed > 0 ? 1 : 0;
    }

    /**
     * Test dual-write functionality
     */
    public function actionTest()
    {
        echo "Testing Allergy dual-write...\n\n";
        
        $timestamp = time();
        $allergy = new Allergy();
        $allergy->name = "Test Allergy DW {$timestamp}";
        $allergy->active = 1;
        
        echo "Creating test allergy...\n";
        if ($allergy->save()) {
            $id = $allergy->id;
            echo "✓ Saved to MariaDB (ID: {$id})\n";
            
            sleep(1);
            
            echo "\nChecking Couchbase...\n";
            try {
                $cb = Yii::app()->couchbase;
                $collection = $cb->getCollection('reference', 'allergy');
                $doc = $collection->get("allergy::{$id}");
                
                echo "✓ FOUND in Couchbase!\n";
                $content = $doc->content();
                echo "  Name: {$content['name']}\n";
                echo "  Type: {$content['_type']}\n";
                
                // Clean up
                echo "\nCleaning up...\n";
                $allergy->delete();
                echo "✓ Deleted from MariaDB\n";
                
                sleep(1);
                try {
                    $collection->get("allergy::{$id}");
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
                echo "✗ NOT in Couchbase: {$e->getMessage()}\n";
                $allergy->delete();
                return 1;
            }
        } else {
            echo "✗ Failed to save\n";
            print_r($allergy->errors);
            return 1;
        }
    }
}
