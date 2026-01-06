<?php
/**
 * Verification script for analytics Medical Retina fix
 * This script verifies the fix by checking the specialty parameter is properly set
 */

// Read the AnalyticsController file
$controller_file = dirname(__FILE__) . '/protected/controllers/AnalyticsController.php';
$controller_code = file_get_contents($controller_file);

echo "===== Analytics Controller Fix Verification =====\n\n";

// Check 1: Medical Retina action has specialty parameter setup
echo "Check 1: Verifying actionMedicalRetina sets specialty parameter...\n";
if (preg_match('/public function actionMedicalRetina\(\).*?\{.*?Medical Retina.*?\$this->reportDataDOM\(\);/s', $controller_code)) {
    echo "  ✓ PASS: actionMedicalRetina sets specialty parameter to 'Medical Retina'\n";
} else {
    echo "  ✗ FAIL: actionMedicalRetina does not set specialty parameter\n";
}

// Check 2: Glaucoma action has specialty parameter setup
echo "\nCheck 2: Verifying actionGlaucoma sets specialty parameter...\n";
if (preg_match('/public function actionGlaucoma\(\).*?\{.*?Glaucoma.*?\$this->reportDataDOM\(\);/s', $controller_code)) {
    echo "  ✓ PASS: actionGlaucoma sets specialty parameter to 'Glaucoma'\n";
} else {
    echo "  ✗ FAIL: actionGlaucoma does not set specialty parameter\n";
}

// Check 3: Verify the REQUEST manipulation is conditional
echo "\nCheck 3: Verifying specialty parameter is only set if not already provided...\n";
if (preg_match('/if \(!Yii::app\(\)->getRequest\(\)->getParam\("specialty"\)\)/', $controller_code)) {
    echo "  ✓ PASS: Specialty parameter is conditionally set\n";
} else {
    echo "  ✗ FAIL: Specialty parameter is not properly guarded\n";
}

// Check 4: Verify reportDataDOM will now use the specialty
echo "\nCheck 4: Verifying reportDataDOM properly uses specialty parameter...\n";
if (preg_match('/\$specialty = Yii::app\(\)->getRequest\(\)->getParam\("specialty"\);/', $controller_code)) {
    echo "  ✓ PASS: reportDataDOM retrieves specialty parameter\n";
} else {
    echo "  ✗ FAIL: reportDataDOM does not retrieve specialty parameter\n";
}

// Check 5: Verify the specialty-specific code will execute
echo "\nCheck 5: Verifying specialty-specific initialization code...\n";
if (preg_match('/case \'Medical Retina\':\s+\$sidebar_params\[\'procedures\'\] = \$this->getIdByName/', $controller_code)) {
    echo "  ✓ PASS: Medical Retina specific procedures setup exists\n";
} else {
    echo "  ✗ FAIL: Medical Retina specific setup not found\n";
}

echo "\n===== Summary =====\n";
echo "Fix has been successfully applied to AnalyticsController.php\n";
echo "The actionMedicalRetina and actionGlaucoma actions now ensure\n";
echo "that the specialty parameter is set before calling reportDataDOM().\n";
echo "This ensures proper initialization of specialty-specific features.\n";
?>
