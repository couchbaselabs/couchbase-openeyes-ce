<?php

use OE\factories\ModelFactory;
use OE\factories\exceptions\FactoryNotFoundException;

$projectDir = dirname(__DIR__);
$logDir = $projectDir . '/logs';
@mkdir($logDir, 0777, true);

$ts = date('Ymd_His');
$outCsv = $logDir . "/{$ts}_model_factory_verify.csv";

require_once $projectDir . '/framework/yii.php';
$config = $projectDir . '/protected/config/main.php';
Yii::createWebApplication($config);

function iterModelFiles(string $projectDir): Generator
{
    $roots = [
        $projectDir . '/protected/models',
        $projectDir . '/protected/modules',
    ];

    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (!str_ends_with($path, '.php')) {
                continue;
            }
            if (!preg_match('#/models/[^/]+\.php$#', $path)) {
                continue;
            }
            if (str_contains($path, '/traits/')) {
                continue;
            }
            yield $path;
        }
    }
}

function extractFqcn(string $filePath): ?string
{
    $src = file_get_contents($filePath);
    if ($src === false) {
        return null;
    }

    $namespace = null;
    if (preg_match('/^\s*namespace\s+([^;]+);/m', $src, $m)) {
        $namespace = trim($m[1]);
    }

    if (!preg_match('/^\s*(?:abstract\s+)?class\s+([A-Za-z0-9_]+)/m', $src, $m)) {
        return null;
    }

    $class = $m[1];
    if ($namespace) {
        return $namespace . '\\' . $class;
    }
    return $class;
}

$fh = fopen($outCsv, 'w');
if (!$fh) {
    fwrite(STDERR, "Unable to open output file: $outCsv\n");
    exit(1);
}

fputcsv($fh, [
    'model_class',
    'factory_type',
    'factory_class',
    'make',
    'create',
    'result',
    'error',
]);

$seen = [];
$failures = 0;

foreach (iterModelFiles($projectDir) as $filePath) {
    $modelClass = extractFqcn($filePath);
    if (!$modelClass) {
        continue;
    }
    if (isset($seen[$modelClass])) {
        continue;
    }
    $seen[$modelClass] = true;

    // Skip common non-model base classes
    if (preg_match('/^(Base|Common|Abstract)/', basename(str_replace('\\', '/', $modelClass)))) {
        continue;
    }

    $factoryType = 'GENERIC';
    $factoryClass = 'OE\\factories\\models\\GenericActiveRecordFactory';
    try {
        $factoryClass = ModelFactory::resolveFactoryName($modelClass);
        $factoryType = 'DEDICATED';
    } catch (FactoryNotFoundException $e) {
    }

    $make = 'FAIL';
    $create = 'FAIL';
    $result = 'FAIL';
    $error = '';

    try {
        if (!class_exists($modelClass, true)) {
            throw new RuntimeException('Class not loadable');
        }
        if (!is_subclass_of($modelClass, 'CModel')) {
            // Not a Yii model; skip
            fputcsv($fh, [$modelClass, $factoryType, $factoryClass, 'SKIP', 'SKIP', 'SKIP', 'Not a CModel']);
            continue;
        }

        $factory = ModelFactory::factoryFor($modelClass);

        $instance = $factory->make();
        $make = 'PASS';

        $created = $factory->create();
        $create = 'PASS';

        // Best-effort cleanup
        if ($created instanceof CActiveRecord) {
            try {
                $created->delete();
            } catch (Throwable $t) {
            }
        }

        $result = 'PASS';
    } catch (Throwable $t) {
        $error = $t->getMessage();
        $failures++;
    }

    fputcsv($fh, [$modelClass, $factoryType, $factoryClass, $make, $create, $result, $error]);
}

fclose($fh);

echo "Wrote: {$outCsv}\n";
echo "Models checked: " . count($seen) . "\n";
echo "Failures: {$failures}\n";

exit($failures > 0 ? 1 : 0);
