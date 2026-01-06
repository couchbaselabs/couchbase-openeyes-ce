<?php
// Test script to identify the report error

$yii = dirname(__FILE__) . '/framework/yiic.php';
require_once($yii);

// Bootstrap the app
$app = Yii::createWebApplication(dirname(__FILE__) . '/protected/config/main.php');

// Try to run the report
try {
    $report = new OphTrIntravitrealinjection_ReportInjections();
    $report->date_from = '2025-01-01';
    $report->date_to = '2026-01-06';
    $report->summary = '0';
    $report->pre_va = '0';
    $report->post_va = '0';
    
    echo "Running report...\n";
    $report->run();
    echo "Report completed successfully\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
