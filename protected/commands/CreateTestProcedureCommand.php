<?php
/**
 * Create a test procedure to verify dual-write
 */

class CreateTestProcedureCommand extends CConsoleCommand
{
    public function actionIndex()
    {
        echo "Creating test procedure to verify dual-write...\n\n";
        
        $timestamp = time();
        $procedure = new Procedure();
        $procedure->term = "Test Procedure DW {$timestamp}";
        $procedure->short_format = "Test {$timestamp}";
        $procedure->default_duration = 30;
        $procedure->snomed_code = "99999{$timestamp}";
        $procedure->snomed_term = "Test procedure for dual-write";
        $procedure->active = 1;
        
        echo "Attempting to save...\n";
        if ($procedure->save()) {
            $id = $procedure->id;
            echo "✓ Saved to MariaDB (ID: {$id})\n";
            
            // Wait a moment
            sleep(2);
            
            // Check Couchbase
            echo "\nChecking Couchbase...\n";
            try {
                $cb = Yii::app()->couchbase;
                $collection = $cb->getCollection('reference', 'procedure');
                $doc = $collection->get("procedure::{$id}");
                
                echo "✓ FOUND in Couchbase!\n";
                $content = $doc->content();
                echo "  Term: {$content['term']}\n";
                echo "  Type: {$content['_type']}\n";
                echo "  SNOMED: {$content['snomed_code']}\n";
                
                // Clean up
                echo "\nCleaning up...\n";
                $procedure->delete();
                echo "✓ Test procedure deleted\n";
                
            } catch (Exception $e) {
                echo "✗ NOT found in Couchbase\n";
                echo "  Error: {$e->getMessage()}\n";
                
                // Still clean up MariaDB
                $procedure->delete();
            }
        } else {
            echo "✗ Failed to save to MariaDB\n";
            print_r($procedure->errors);
            return 1;
        }
        
        return 0;
    }
}
