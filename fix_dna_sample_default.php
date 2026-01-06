<?php
// This script fixes the Element_OphInDnasample_Sample element type to be marked as default

define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);
require_once('index.php');

try {
    // Check if Element_OphInDnasample_Sample exists
    $element_type = ElementType::model()->find("class_name = 'Element_OphInDnasample_Sample'");
    
    if (!$element_type) {
        echo "ERROR: Element_OphInDnasample_Sample not found in database!\n";
        exit(1);
    }
    
    echo "Found Element_OphInDnasample_Sample (ID: {$element_type->id})\n";
    echo "Current default value: " . ($element_type->default ? "1 (YES)" : "0 (NO)") . "\n";
    
    if (!$element_type->default) {
        // Update to set default=1
        $element_type->default = 1;
        if ($element_type->save()) {
            echo "SUCCESS: Element type updated to default=1\n";
        } else {
            echo "ERROR: Failed to update element type\n";
            print_r($element_type->getErrors());
            exit(1);
        }
    } else {
        echo "Element type is already marked as default\n";
    }
    
    // Verify the update
    $element_type_check = ElementType::model()->find("class_name = 'Element_OphInDnasample_Sample'");
    echo "Verification: default=" . ($element_type_check->default ? "1 (YES)" : "0 (NO)") . "\n";
    
    echo "\nFix completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    exit(1);
}
?>
