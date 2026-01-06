<?php
// Check application logs for debug messages
$logFile = dirname(__FILE__) . '/logs/application.log';

if (!file_exists($logFile)) {
    die("Log file not found: $logFile\n");
}

// Read the last 100 lines
$lines = array_slice(file($logFile), -100);

echo "Last debug entries:\n";
foreach ($lines as $line) {
    if (strpos($line, 'application.debug') !== false || strpos($line, 'HistoryMacro') !== false) {
        echo $line;
    }
}
?>
