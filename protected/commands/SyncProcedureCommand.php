<?php
/**
 * Manually sync a procedure to Couchbase
 */

class SyncProcedureCommand extends CConsoleCommand
{
    public function actionIndex($id)
    {
        echo "Manually syncing Procedure ID {$id} to Couchbase...\n";
        
        $procedure = Procedure::model()->findByPk($id);
        if (!$procedure) {
            echo "ERROR: Procedure not found in MariaDB\n";
            return 1;
        }
        
        echo "Found procedure: {$procedure->term}\n";
        echo "Calling syncToCouchbase()...\n";
        
        try {
            $result = $procedure->syncToCouchbase();
            if ($result) {
                echo "✓ Successfully synced to Couchbase!\n";
                
                // Verify
                sleep(1);
                $cb = Yii::app()->couchbase;
                $collection = $cb->getCollection('reference', 'procedure');
                try {
                    $doc = $collection->get('procedure::' . $id);
                    echo "✓ Verified in Couchbase\n";
                    $content = $doc->content();
                    echo "  Term: {$content['term']}\n";
                    echo "  Type: {$content['_type']}\n";
                } catch (Exception $e) {
                    echo "⚠ Could not verify: {$e->getMessage()}\n";
                }
            } else {
                echo "✗ Sync returned false\n";
            }
        } catch (Exception $e) {
            echo "ERROR: {$e->getMessage()}\n";
            echo "Stack trace:\n{$e->getTraceAsString()}\n";
            return 1;
        }
        
        return 0;
    }
}
