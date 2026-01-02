<?php
/**
 * Check if a specific procedure exists in both MariaDB and Couchbase
 */

class CheckProcedureCommand extends CConsoleCommand
{
    public function actionIndex($id)
    {
        echo "======================================\n";
        echo "Checking Procedure ID: {$id}\n";
        echo "======================================\n\n";

        // Check MariaDB
        echo "1. Checking MariaDB...\n";
        $procedure = Procedure::model()->findByPk($id);
        if ($procedure) {
            echo "   ✓ Found in MariaDB\n";
            echo "   - ID: {$procedure->id}\n";
            echo "   - Term: {$procedure->term}\n";
            echo "   - SNOMED: {$procedure->snomed_code}\n";
            echo "   - Created: {$procedure->created_date}\n";
        } else {
            echo "   ✗ NOT found in MariaDB\n";
            return 1;
        }

        echo "\n2. Checking Couchbase (reference.procedure)...\n";
        
        $cb = Yii::app()->couchbase;
        if (!$cb) {
            echo "   ✗ ERROR: Couchbase adapter not configured\n";
            return 1;
        }

        try {
            $collection = $cb->getCollection('reference', 'procedure');
            $key = 'procedure::' . $id;
            
            try {
                $doc = $collection->get($key);
                $content = $doc->content();
                
                echo "   ✓ Found in Couchbase\n";
                echo "   - Key: {$key}\n";
                if (isset($content['id'])) {
                    echo "   - ID: {$content['id']}\n";
                }
                echo "   - Term: {$content['term']}\n";
                echo "   - Type: {$content['_type']}\n";
                echo "   - Modified: " . ($content['_modified'] ?? 'N/A') . "\n";
                
                if (isset($content['opcs_codes'])) {
                    echo "   - OPCS Codes: " . count($content['opcs_codes']) . " codes\n";
                }
                if (isset($content['benefits'])) {
                    echo "   - Benefits: " . count($content['benefits']) . " benefits\n";
                }
                if (isset($content['complications'])) {
                    echo "   - Complications: " . count($content['complications']) . " complications\n";
                }
                
            } catch (\Couchbase\Exception\DocumentNotFoundException $e) {
                echo "   ✗ NOT found in Couchbase\n";
                echo "   - Expected key: {$key}\n";
                echo "   - This means dual-write did NOT sync to Couchbase\n";
                return 1;
            }
        } catch (Exception $e) {
            echo "   ✗ ERROR: {$e->getMessage()}\n";
            return 1;
        }

        echo "\n======================================\n";
        echo "✓ Procedure exists in BOTH databases\n";
        echo "======================================\n";

        return 0;
    }

    public function actionRecent($limit = 5)
    {
        echo "Recent Procedures in MariaDB:\n";
        echo "======================================\n";
        
        $procedures = Procedure::model()->findAll([
            'order' => 'id DESC',
            'limit' => $limit,
        ]);

        foreach ($procedures as $p) {
            echo "ID {$p->id}: {$p->term}\n";
            echo "  Created: {$p->created_date}\n";
            
            // Check if in Couchbase
            $cb = Yii::app()->couchbase;
            if ($cb) {
                try {
                    $collection = $cb->getCollection('reference', 'procedure');
                    $doc = $collection->get('procedure::' . $p->id);
                    echo "  Couchbase: ✓ YES\n";
                } catch (Exception $e) {
                    echo "  Couchbase: ✗ NO\n";
                }
            }
            echo "\n";
        }
    }
}
