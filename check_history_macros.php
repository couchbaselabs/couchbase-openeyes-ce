<?php
// Check if history macros were saved to the database
require_once 'index.php';

// Query the database
$macros = Yii::app()->db->createCommand()
    ->select('*')
    ->from('ophciexamination_history_macro')
    ->order('id DESC')
    ->limit(10)
    ->queryAll();

echo "Found " . count($macros) . " history macros:\n";
foreach ($macros as $macro) {
    echo "ID: " . $macro['id'] . ", Name: " . $macro['name'] . ", Active: " . $macro['active'] . "\n";
}
?>
