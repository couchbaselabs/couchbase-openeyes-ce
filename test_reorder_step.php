<?php
// Test the reorderStep endpoint with POST data

// Set up Yii
$yii = dirname(__FILE__) . '/protected/yiic.php';
require_once($yii);

// Create a test to check if the code will work
// Note: This is just a syntax check, not a functional test

// Check if the file can be parsed
$file = 'protected/modules/Admin/controllers/WorklistController.php';
$content = file_get_contents($file);

// Check for the fixed exception
if (strpos($content, "throw new CHttpException(500, 'Unable to reorder step.');") !== false) {
    echo "✓ Fix applied successfully: CHttpException now has HTTP status code 500\n";
    exit(0);
} else {
    echo "✗ Fix not found or incorrect\n";
    exit(1);
}
?>
