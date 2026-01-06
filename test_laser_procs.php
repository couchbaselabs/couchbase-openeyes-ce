<?php
chdir(dirname(__FILE__));
require_once('protected/yiic.php');
$app = Yii::createWebApplication('protected/config/main.php');

// Check database for laser procedures
echo "=== Checking OphTrLaser_LaserProcedure table ===\n";

// Using Couchbase
try {
    $cmd = Yii::app()->cbdb->createCommand();
    $result = $cmd->select('*')->from('ophtrlaser_laserprocedure')->queryAll();
    echo "Couchbase ophtrlaser_laserprocedure count: " . count($result) . "\n";
    var_dump($result);
} catch (Exception $e) {
    echo "Couchbase error: " . $e->getMessage() . "\n";
}

// Using SQLite
try {
    $cmd = Yii::app()->db->createCommand();
    $result = $cmd->select('*')->from('ophtrlaser_laserprocedure')->queryAll();
    echo "SQLite ophtrlaser_laserprocedure count: " . count($result) . "\n";
    var_dump($result);
} catch (Exception $e) {
    echo "SQLite error: " . $e->getMessage() . "\n";
}

// Using Model
try {
    $models = OphTrLaser_LaserProcedure::model()->findAll();
    echo "Model count: " . count($models) . "\n";
    foreach ($models as $m) {
        echo "ID: " . $m->id . ", Procedure ID: " . $m->procedure_id . "\n";
        echo "  Procedure object: " . ($m->procedure ? 'exists' : 'null') . "\n";
        if ($m->procedure) {
            echo "    Term: " . $m->procedure->term . "\n";
        }
    }
} catch (Exception $e) {
    echo "Model error: " . $e->getMessage() . "\n";
}
