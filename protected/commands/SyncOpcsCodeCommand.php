<?php
/**
 * Sync OPCS codes to Couchbase
 */

class SyncOpcsCodeCommand extends CConsoleCommand
{
    public function actionIndex()
    {
        echo "Syncing existing OPCS codes to Couchbase...\n\n";
        
        $opcsCodes = OPCSCode::model()->findAll();
        $success = 0;
        $failed = 0;
        
        foreach ($opcsCodes as $opcs) {
            echo "Syncing OPCS Code ID {$opcs->id}: {$opcs->name} - {$opcs->description}\n";
            
            try {
                $result = $opcs->syncToCouchbase();
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

    public function actionVerify()
    {
        echo "Verifying OPCS codes in Couchbase...\n\n";
        
        $cb = Yii::app()->couchbase;
        $collection = $cb->getCollection('reference', 'opcs_code');
        $opcsCodes = OPCSCode::model()->findAll();
        
        $found = 0;
        $notFound = 0;
        
        foreach ($opcsCodes as $opcs) {
            echo "OPCS Code ID {$opcs->id}: {$opcs->name}\n";
            
            try {
                $doc = $collection->get("opcs_code::{$opcs->id}");
                echo "  ✓ Found in Couchbase\n";
                $content = $doc->content();
                echo "    Name: {$content['name']}\n";
                echo "    Description: {$content['description']}\n";
                $found++;
            } catch (Exception $e) {
                echo "  ✗ NOT in Couchbase: {$e->getMessage()}\n";
                $notFound++;
            }
        }
        
        echo "\n======================================\n";
        echo "Summary: {$found} found, {$notFound} not found\n";
        echo "======================================\n";
        
        return $notFound > 0 ? 1 : 0;
    }
}
