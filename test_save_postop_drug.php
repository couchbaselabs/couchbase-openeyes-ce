<?php
// Simple test script to debug saving PostopDrug
define('YII_DEBUG', true);
require_once('index.php');

try {
    echo "Creating new PostopDrug...\n";
    $drug = new OphTrOperationnote_PostopDrug();
    echo "Setting attributes...\n";
    $drug->name = "Test Drug From Script";
    echo "Drug name: " . $drug->name . "\n";
    echo "Drug attributes: " . json_encode($drug->getAttributes()) . "\n";
    
    echo "Validating...\n";
    $isValid = $drug->validate();
    echo "Valid: " . ($isValid ? "true" : "false") . "\n";
    if (!$isValid) {
        echo "Errors: " . json_encode($drug->getErrors()) . "\n";
    }
    
    echo "Attempting to save...\n";
    $result = $drug->save();
    echo "Save result: " . ($result ? "true" : "false") . "\n";
    
    if ($result) {
        echo "Success! New drug ID: " . $drug->id . "\n";
    } else {
        echo "Save failed! Errors: " . json_encode($drug->getErrors()) . "\n";
        echo "Drug attributes after failed save: " . json_encode($drug->getAttributes()) . "\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
