<?php
// Test script for DicomLogViewerController::actionSearch

// Set up Yii application
$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

require_once($yii);
$app = Yii::createWebApplication($config);

// Import the controller
Yii::import('application.controllers.DicomLogViewerController');

echo "Testing DicomLogViewerController::actionSearch...\n";
echo "============================================\n\n";

try {
    // Create a controller instance
    $controller = new DicomLogViewerController('dicomLogViewerController');
    
    // Check if the method exists
    if (method_exists($controller, 'actionSearch')) {
        echo "[✓] actionSearch method exists\n";
    } else {
        echo "[✗] actionSearch method does not exist\n";
    }
    
    // Check access rules
    $accessRules = $controller->accessRules();
    echo "\n[Access Rules]\n";
    echo json_encode($accessRules, JSON_PRETTY_PRINT) . "\n";
    
    // Check for the getData method
    if (method_exists($controller, 'getData')) {
        echo "\n[✓] getData method exists\n";
    } else {
        echo "\n[✗] getData method does not exist\n";
    }
    
    // Check for the getDicomFiles method
    if (method_exists($controller, 'getDicomFiles')) {
        echo "[✓] getDicomFiles method exists\n";
    } else {
        echo "[✗] getDicomFiles method does not exist\n";
    }
    
    echo "\n[Method signature analysis]\n";
    $reflection = new ReflectionMethod($controller, 'actionSearch');
    echo "Method: " . $reflection->getName() . "\n";
    echo "Parameters: " . count($reflection->getParameters()) . "\n";
    
    // Try to get source code
    $filename = $reflection->getFileName();
    $startLine = $reflection->getStartLine() - 1;
    $endLine = $reflection->getEndLine();
    $length = $endLine - $startLine;
    
    $source = file($filename);
    $methodSource = implode("", array_slice($source, $startLine, $length));
    
    echo "\nMethod source:\n";
    echo "---\n";
    echo $methodSource;
    echo "---\n";
    
    echo "\n[Analysis]\n";
    if (strpos($methodSource, 'Yii::app()->end()') !== false) {
        echo "[⚠] WARNING: Method contains Yii::app()->end() which terminates execution\n";
    }
    
    if (strpos($methodSource, 'renderJSON') !== false) {
        echo "[✓] Method uses renderJSON to return data\n";
    }
    
} catch (Exception $e) {
    echo "[✗] Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
?>
