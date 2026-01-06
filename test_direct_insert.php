<?php
// Test direct database insert
require_once 'index.php';

$db = Yii::app()->db;

// Test 1: Check database connection
echo "Test 1: Database Connection\n";
try {
    $result = $db->createCommand("SELECT 1")->queryScalar();
    echo "  Database connection: OK\n";
} catch (Exception $e) {
    echo "  Database connection: FAILED - " . $e->getMessage() . "\n";
}

// Test 2: Check table exists
echo "\nTest 2: Check Table\n";
try {
    $result = $db->createCommand("SELECT COUNT(*) FROM ophciexamination_history_macro")->queryScalar();
    echo "  Table exists: YES, Record count: " . $result . "\n";
} catch (Exception $e) {
    echo "  Table check: FAILED - " . $e->getMessage() . "\n";
}

// Test 3: Direct insert
echo "\nTest 3: Direct Insert\n";
try {
    $testName = "DirectInsert_" . time();
    $cmd = $db->createCommand();
    $cmd->insert('ophciexamination_history_macro', [
        'name' => $testName,
        'body' => 'Test body',
        'active' => 1,
        'display_order' => 1,
    ]);
    echo "  Direct insert: OK\n";
    
    // Verify insert
    $count = $db->createCommand("SELECT COUNT(*) FROM ophciexamination_history_macro WHERE name = :name")->bindParam(':name', $testName)->queryScalar();
    echo "  Verify count: " . $count . "\n";
} catch (Exception $e) {
    echo "  Direct insert: FAILED - " . $e->getMessage() . "\n";
}

// Test 4: Check isSqlAvailable for HistoryMacro
echo "\nTest 4: Check isSqlAvailable\n";
try {
    $model = new OEModule\OphCiExamination\models\HistoryMacro();
    if (method_exists($model, 'isSqlAvailable')) {
        $available = $model->isSqlAvailable();
        echo "  isSqlAvailable: " . ($available ? 'true' : 'false') . "\n";
    } else {
        echo "  isSqlAvailable method not found\n";
    }
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}
?>
