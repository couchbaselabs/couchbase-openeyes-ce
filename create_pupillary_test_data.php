<?php
// Script to create test pupillary abnormality data

require_once('protected/yii.php');

try {
    // Check if connection is working
    $model = OEModule\OphCiExamination\models\OphCiExamination_PupillaryAbnormalities_Abnormality::model();
    
    // Check existing count
    $existing = $model->findAll();
    echo "Current abnormalities: " . count($existing) . "\n";
    
    // List of test abnormalities
    $abnormalities = [
        'Dilated',
        'Constricted',
        'Irregular',
        'Non-reactive',
        'Marcus Gunn',
    ];
    
    foreach ($abnormalities as $name) {
        $abnorm = new OEModule\OphCiExamination\models\OphCiExamination_PupillaryAbnormalities_Abnormality();
        $abnorm->name = $name;
        $abnorm->active = 1;
        $abnorm->display_order = 0;
        
        if ($abnorm->save()) {
            echo "Created: $name\n";
        } else {
            echo "Failed to create $name: " . implode(", ", $abnorm->getErrors()['name'] ?? []) . "\n";
        }
    }
    
    // Verify creation
    $final = $model->findAll();
    echo "\nFinal abnormalities: " . count($final) . "\n";
    foreach ($final as $abn) {
        echo "  - {$abn->id}: {$abn->name}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
