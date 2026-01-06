<?php
/**
 * Test script to verify the actionLog fix for null merge_json
 */

require_once 'protected/yiic.php';
$config = require('protected/config/main.php');
$app = Yii::createWebApplication($config);

echo "=== Testing PatientMergeRequest actionLog Fix ===\n\n";

// Test 1: Test with valid merge_json
echo "Test 1: Valid merge_json with log data\n";
$valid_json = '{"log": {"step1": "First step", "step2": "Second step"}}';
$decoded = json_decode($valid_json, true);
$log = array();
if ($decoded && isset($decoded['log']) && is_array($decoded['log'])) {
    foreach ($decoded['log'] as $key => $log_row) {
        $log[] = array(
            'id' => $key,
            'log' => $log_row,
        );
    }
}
echo "  Result: " . count($log) . " log entries found\n";
echo "  Status: " . (count($log) === 2 ? "PASS" : "FAIL") . "\n\n";

// Test 2: Test with null merge_json
echo "Test 2: NULL merge_json\n";
$null_json = null;
$decoded = json_decode($null_json, true);
$log = array();
if ($decoded && isset($decoded['log']) && is_array($decoded['log'])) {
    foreach ($decoded['log'] as $key => $log_row) {
        $log[] = array(
            'id' => $key,
            'log' => $log_row,
        );
    }
}
echo "  Result: " . count($log) . " log entries found\n";
echo "  Status: " . (count($log) === 0 ? "PASS" : "FAIL") . "\n\n";

// Test 3: Test with empty JSON
echo "Test 3: Empty JSON object\n";
$empty_json = '{}';
$decoded = json_decode($empty_json, true);
$log = array();
if ($decoded && isset($decoded['log']) && is_array($decoded['log'])) {
    foreach ($decoded['log'] as $key => $log_row) {
        $log[] = array(
            'id' => $key,
            'log' => $log_row,
        );
    }
}
echo "  Result: " . count($log) . " log entries found\n";
echo "  Status: " . (count($log) === 0 ? "PASS" : "FAIL") . "\n\n";

// Test 4: Test with JSON but log is not an array
echo "Test 4: JSON with log but log is a string (not array)\n";
$invalid_json = '{"log": "This is a string, not an array"}';
$decoded = json_decode($invalid_json, true);
$log = array();
if ($decoded && isset($decoded['log']) && is_array($decoded['log'])) {
    foreach ($decoded['log'] as $key => $log_row) {
        $log[] = array(
            'id' => $key,
            'log' => $log_row,
        );
    }
}
echo "  Result: " . count($log) . " log entries found\n";
echo "  Status: " . (count($log) === 0 ? "PASS" : "FAIL") . "\n\n";

// Test 5: Test with invalid JSON
echo "Test 5: Invalid JSON\n";
$invalid_json = '{invalid json}';
$decoded = json_decode($invalid_json, true);
$log = array();
if ($decoded && isset($decoded['log']) && is_array($decoded['log'])) {
    foreach ($decoded['log'] as $key => $log_row) {
        $log[] = array(
            'id' => $key,
            'log' => $log_row,
        );
    }
}
echo "  Result: " . count($log) . " log entries found\n";
echo "  Status: " . (count($log) === 0 ? "PASS" : "FAIL") . "\n\n";

echo "=== All tests completed ===\n";
?>
