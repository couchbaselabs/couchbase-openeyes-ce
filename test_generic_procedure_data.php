<?php
// Test script to debug Generic Procedure Data list issue
chdir(dirname(__FILE__));
require_once 'protected/config/main-local.php';
Yii::app()->theme = 'openeyes';

try {
    // Check total count
    $count = OphTrOperationNote_Generic_Procedure_Data::model()->count();
    echo "Total records in table: $count\n";
    
    // Check with criteria
    $criteria = new CDbCriteria();
    $criteria->with = 'procedure';
    $criteria->order = 'term asc';
    
    $countWithCriteria = OphTrOperationNote_Generic_Procedure_Data::model()->count($criteria);
    echo "Records with criteria (with procedure relation): $countWithCriteria\n";
    
    // Try findAll with criteria
    $models = OphTrOperationNote_Generic_Procedure_Data::model()->findAll($criteria);
    echo "findAll result count: " . count($models) . "\n";
    
    if (count($models) > 0) {
        echo "\nFirst record details:\n";
        $first = $models[0];
        echo "ID: " . $first->id . "\n";
        echo "proc_id: " . $first->proc_id . "\n";
        echo "default_text: " . substr($first->default_text, 0, 50) . "\n";
        
        // Check procedure relation
        echo "Procedure relation loaded: " . (isset($first->procedure) ? "YES" : "NO") . "\n";
        if (isset($first->procedure) && $first->procedure) {
            echo "Procedure term: " . $first->procedure->term . "\n";
        } else {
            echo "Procedure is null or not loaded\n";
        }
    } else {
        echo "\nNo models found!\n";
        
        // Try without eager loading
        $criteria2 = new CDbCriteria();
        $criteria2->order = 'id asc';
        $modelsNoEager = OphTrOperationNote_Generic_Procedure_Data::model()->findAll($criteria2);
        echo "findAll without eager loading: " . count($modelsNoEager) . "\n";
        
        if (count($modelsNoEager) > 0) {
            echo "First record without eager loading:\n";
            $first2 = $modelsNoEager[0];
            echo "ID: " . $first2->id . "\n";
            echo "proc_id: " . $first2->proc_id . "\n";
            
            // Try to manually load procedure
            if ($first2->proc_id) {
                $proc = Procedure::model()->findByPk($first2->proc_id);
                if ($proc) {
                    echo "Procedure found: " . $proc->term . "\n";
                } else {
                    echo "Procedure NOT found for proc_id: " . $first2->proc_id . "\n";
                }
            } else {
                echo "proc_id is null/empty\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
