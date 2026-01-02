<?php
/**
 * ConvertDbCallsCommand
 * 
 * Converts Yii::app()->db calls to Yii::app()->cbdb for Couchbase migration.
 * This is part of Phase 4 of the MariaDB removal plan.
 */

class ConvertDbCallsCommand extends CConsoleCommand
{
    public $dryRun = false;
    public $verbose = false;
    
    private $stats = [
        'files_scanned' => 0,
        'files_modified' => 0,
        'replacements' => 0,
        'skipped' => 0,
    ];
    
    // Files/directories to skip (framework internals, migrations, tests)
    private $skipPatterns = [
        '/migrations/',      // Skip migration files
        '/tests/',           // Skip test files
        '/vendor/',          // Skip vendor files
        'OEMigration.php',   // Skip migration base class
        'OEDbConnection.php', // Skip DB connection class
        'CouchbaseDbConnection.php', // Skip our new class
        'ConvertDbCallsCommand.php', // Skip this file
    ];
    
    // Directories to process
    private $targetDirs = [
        'controllers',
        'models', 
        'components',
        'behaviors',
        'helpers',
        'services',
        'widgets',
        'modules',
    ];

    public function getHelp()
    {
        return <<<HELP
USAGE
  yiic convertdbcalls <action> [options]

DESCRIPTION
  Converts Yii::app()->db calls to Yii::app()->cbdb for Couchbase migration.

ACTIONS
  scan      Scan and report files that need conversion
  convert   Convert Yii::app()->db to Yii::app()->cbdb
  revert    Revert Yii::app()->cbdb back to Yii::app()->db

OPTIONS
  --dryRun    Show what would be done without making changes
  --verbose   Show detailed output

EXAMPLES
  yiic convertdbcalls scan
  yiic convertdbcalls convert --dryRun
  yiic convertdbcalls convert --verbose

HELP;
    }

    public function actionScan()
    {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "SCANNING FOR Yii::app()->db CALLS\n";
        echo str_repeat("=", 70) . "\n\n";
        
        $basePath = Yii::getPathOfAlias('application');
        $files = $this->findFilesToConvert($basePath);
        
        $byCategory = [];
        foreach ($files as $file => $count) {
            $category = $this->categorizeFile($file);
            if (!isset($byCategory[$category])) {
                $byCategory[$category] = [];
            }
            $byCategory[$category][$file] = $count;
        }
        
        foreach ($byCategory as $category => $categoryFiles) {
            echo "\n" . strtoupper($category) . " (" . count($categoryFiles) . " files):\n";
            echo str_repeat("-", 60) . "\n";
            foreach ($categoryFiles as $file => $count) {
                $shortPath = str_replace($basePath . '/', '', $file);
                echo sprintf("  %-50s %3d calls\n", $shortPath, $count);
            }
        }
        
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "SUMMARY\n";
        echo str_repeat("=", 70) . "\n";
        echo "Total files to convert: " . count($files) . "\n";
        echo "Total db calls: " . array_sum($files) . "\n";
        echo str_repeat("=", 70) . "\n\n";
    }

    public function actionConvert()
    {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "CONVERTING Yii::app()->db TO Yii::app()->cbdb\n";
        echo str_repeat("=", 70) . "\n\n";
        
        if ($this->dryRun) {
            echo "*** DRY RUN MODE - No files will be modified ***\n\n";
        }
        
        $basePath = Yii::getPathOfAlias('application');
        $files = $this->findFilesToConvert($basePath);
        
        foreach ($files as $file => $count) {
            $this->convertFile($file);
        }
        
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "CONVERSION SUMMARY\n";
        echo str_repeat("=", 70) . "\n";
        echo "Files scanned: " . $this->stats['files_scanned'] . "\n";
        echo "Files modified: " . $this->stats['files_modified'] . "\n";
        echo "Total replacements: " . $this->stats['replacements'] . "\n";
        echo "Skipped: " . $this->stats['skipped'] . "\n";
        echo str_repeat("=", 70) . "\n\n";
    }

    public function actionRevert()
    {
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "REVERTING Yii::app()->cbdb TO Yii::app()->db\n";
        echo str_repeat("=", 70) . "\n\n";
        
        if ($this->dryRun) {
            echo "*** DRY RUN MODE - No files will be modified ***\n\n";
        }
        
        $basePath = Yii::getPathOfAlias('application');
        $files = $this->findFilesWithCbdb($basePath);
        
        foreach ($files as $file => $count) {
            $this->revertFile($file);
        }
        
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "REVERT SUMMARY\n";
        echo str_repeat("=", 70) . "\n";
        echo "Files scanned: " . $this->stats['files_scanned'] . "\n";
        echo "Files modified: " . $this->stats['files_modified'] . "\n";
        echo "Total replacements: " . $this->stats['replacements'] . "\n";
        echo str_repeat("=", 70) . "\n\n";
    }

    private function findFilesToConvert($basePath)
    {
        $files = [];
        
        foreach ($this->targetDirs as $dir) {
            $fullPath = $basePath . '/' . $dir;
            if (is_dir($fullPath)) {
                $this->scanDirectory($fullPath, $files);
            }
        }
        
        return $files;
    }

    private function findFilesWithCbdb($basePath)
    {
        $files = [];
        
        foreach ($this->targetDirs as $dir) {
            $fullPath = $basePath . '/' . $dir;
            if (is_dir($fullPath)) {
                $this->scanDirectoryForCbdb($fullPath, $files);
            }
        }
        
        return $files;
    }

    private function scanDirectory($dir, &$files)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $path = $file->getPathname();
                
                // Check if should skip
                if ($this->shouldSkip($path)) {
                    continue;
                }
                
                $content = file_get_contents($path);
                $count = preg_match_all('/Yii::app\(\)->db\b/', $content);
                
                if ($count > 0) {
                    $files[$path] = $count;
                }
            }
        }
    }

    private function scanDirectoryForCbdb($dir, &$files)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $path = $file->getPathname();
                $content = file_get_contents($path);
                $count = preg_match_all('/Yii::app\(\)->cbdb\b/', $content);
                
                if ($count > 0) {
                    $files[$path] = $count;
                }
            }
        }
    }

    private function shouldSkip($path)
    {
        foreach ($this->skipPatterns as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private function categorizeFile($path)
    {
        if (strpos($path, '/controllers/') !== false) return 'controllers';
        if (strpos($path, '/models/') !== false) return 'models';
        if (strpos($path, '/components/') !== false) return 'components';
        if (strpos($path, '/behaviors/') !== false) return 'behaviors';
        if (strpos($path, '/helpers/') !== false) return 'helpers';
        if (strpos($path, '/services/') !== false) return 'services';
        if (strpos($path, '/widgets/') !== false) return 'widgets';
        if (strpos($path, '/modules/') !== false) return 'modules';
        return 'other';
    }

    private function convertFile($path)
    {
        $this->stats['files_scanned']++;
        
        $content = file_get_contents($path);
        $newContent = preg_replace(
            '/Yii::app\(\)->db\b/',
            'Yii::app()->cbdb',
            $content,
            -1,
            $count
        );
        
        if ($count > 0) {
            if ($this->verbose) {
                $basePath = Yii::getPathOfAlias('application');
                $shortPath = str_replace($basePath . '/', '', $path);
                echo "Converting: $shortPath ($count replacements)\n";
            }
            
            if (!$this->dryRun) {
                file_put_contents($path, $newContent);
            }
            
            $this->stats['files_modified']++;
            $this->stats['replacements'] += $count;
        }
    }

    private function revertFile($path)
    {
        $this->stats['files_scanned']++;
        
        $content = file_get_contents($path);
        $newContent = preg_replace(
            '/Yii::app\(\)->cbdb\b/',
            'Yii::app()->db',
            $content,
            -1,
            $count
        );
        
        if ($count > 0) {
            if ($this->verbose) {
                $basePath = Yii::getPathOfAlias('application');
                $shortPath = str_replace($basePath . '/', '', $path);
                echo "Reverting: $shortPath ($count replacements)\n";
            }
            
            if (!$this->dryRun) {
                file_put_contents($path, $newContent);
            }
            
            $this->stats['files_modified']++;
            $this->stats['replacements'] += $count;
        }
    }
}
