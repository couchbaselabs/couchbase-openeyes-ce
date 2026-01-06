<?php
// Check the table schema for ophciexamination_history_macro
require_once 'index.php';

// Get table columns
$columns = Yii::app()->db->createCommand("DESCRIBE ophciexamination_history_macro")->queryAll();

echo "Table: ophciexamination_history_macro\n";
echo "Columns:\n";
foreach ($columns as $col) {
    echo "  " . $col['Field'] . " - " . $col['Type'] . " (" . $col['Null'] . ", " . $col['Key'] . ", " . $col['Default'] . ")\n";
}
?>
