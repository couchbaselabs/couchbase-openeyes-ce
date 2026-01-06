<?php
// Clear Yii schema cache

require_once 'index.php';

// Clear the schema cache
$db = Yii::app()->db;
$db->getSchema()->refresh();

// Also clear any runtime cache
$cacheDir = dirname(__FILE__) . '/protected/runtime/cache';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "✓ Runtime cache cleared\n";
}

// Clear file-based cache directory
if (Yii::app()->hasComponent('cache')) {
    Yii::app()->cache->flush();
    echo "✓ Cache component flushed\n";
}

echo "✓ Schema cache refreshed\n";
echo "✓ Cache clearing completed!\n";
