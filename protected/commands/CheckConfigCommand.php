<?php
/**
 * Check Couchbase configuration
 */

class CheckConfigCommand extends CConsoleCommand
{
    public function actionIndex()
    {
        echo "Couchbase Configuration Check\n";
        echo "========================================\n\n";
        
        // Check config
        $dualWrite = Yii::app()->params['enable_dual_write'] ?? 'NOT SET';
        $couchbaseRead = Yii::app()->params['enable_couchbase_read'] ?? 'NOT SET';
        
        echo "Config Values:\n";
        echo "  enable_dual_write: " . var_export($dualWrite, true) . "\n";
        echo "  enable_couchbase_read: " . var_export($couchbaseRead, true) . "\n\n";
        
        // Check if Procedure has the traits
        echo "Procedure Model Analysis:\n";
        $reflection = new ReflectionClass('Procedure');
        $traits = $reflection->getTraitNames();
        echo "  Uses CouchbaseModelBridge: " . (in_array('OE\\Models\\Traits\\CouchbaseModelBridge', $traits) ? 'YES' : 'NO') . "\n";
        
        // Check if afterSave exists
        $methods = $reflection->getMethods();
        $hasAfterSave = false;
        foreach ($methods as $method) {
            if ($method->getName() === 'afterSave' && $method->getDeclaringClass()->getName() === 'Procedure') {
                $hasAfterSave = true;
                break;
            }
        }
        echo "  Has afterSave() in Procedure class: " . ($hasAfterSave ? 'YES' : 'NO') . "\n\n";
        
        // Check Couchbase connection
        echo "Couchbase Connection:\n";
        try {
            $cb = Yii::app()->couchbase;
            if ($cb) {
                echo "  Adapter: " . get_class($cb) . "\n";
                echo "  Connected: YES\n";
                
                // Try to get a collection
                try {
                    $collection = $cb->getCollection('reference', 'procedure');
                    echo "  Can access reference.procedure: YES\n";
                } catch (Exception $e) {
                    echo "  Can access reference.procedure: NO - {$e->getMessage()}\n";
                }
            } else {
                echo "  Adapter: NOT CONFIGURED\n";
            }
        } catch (Exception $e) {
            echo "  Error: {$e->getMessage()}\n";
        }
        
        return 0;
    }
}
