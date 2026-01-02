<?php

/**
 * Command to add CouchbaseModelBridge trait to all models that don't have it
 */
class AddCouchbaseBridgeCommand extends CConsoleCommand
{
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic addcouchbasebridge [--dry-run] [--module=<name>]

DESCRIPTION
  Adds the CouchbaseModelBridge trait to all models that don't have it.

OPTIONS
  --dry-run : Show what would be changed without making changes
  --module  : Only process a specific module (e.g., OphCiExamination)

EOD;
    }

    public function actionIndex($dryRun = false, $module = null)
    {
        $basePath = Yii::app()->basePath;
        $modelsToProcess = [];

        // Core models
        if (!$module) {
            $coreModels = glob($basePath . '/models/*.php');
            foreach ($coreModels as $file) {
                if ($this->needsBridge($file)) {
                    $modelsToProcess[] = $file;
                }
            }
        }

        // Module models
        $modulesPath = $basePath . '/modules';
        $modules = $module ? [$module] : array_map('basename', glob($modulesPath . '/*', GLOB_ONLYDIR));

        foreach ($modules as $mod) {
            $modelsDir = $modulesPath . '/' . $mod . '/models';
            if (!is_dir($modelsDir)) {
                continue;
            }
            $files = glob($modelsDir . '/*.php');
            foreach ($files as $file) {
                if ($this->needsBridge($file)) {
                    $modelsToProcess[] = $file;
                }
            }
        }

        echo "Found " . count($modelsToProcess) . " models without CouchbaseModelBridge\n\n";

        if ($dryRun) {
            echo "DRY RUN - no changes will be made\n\n";
        }

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($modelsToProcess as $file) {
            $result = $this->addBridgeToFile($file, $dryRun);
            $basename = basename($file);
            
            if ($result === true) {
                echo "[OK] $basename\n";
                $processed++;
            } elseif ($result === 'skipped') {
                echo "[SKIP] $basename - abstract class or interface\n";
                $skipped++;
            } else {
                echo "[ERROR] $basename - $result\n";
                $errors++;
            }
        }

        echo "\n=== Summary ===\n";
        echo "Processed: $processed\n";
        echo "Skipped: $skipped\n";
        echo "Errors: $errors\n";
    }

    private function needsBridge($file)
    {
        $content = file_get_contents($file);
        
        // Skip if already has either bridge
        if (strpos($content, 'CouchbaseModelBridge') !== false ||
            strpos($content, 'CouchbaseElementBridge') !== false) {
            return false;
        }
        
        // Skip base/abstract classes and interfaces
        $basename = basename($file);
        if (preg_match('/^Base[A-Z]/', $basename) && strpos($content, 'abstract class') !== false) {
            return false;
        }
        
        return true;
    }

    private function addBridgeToFile($file, $dryRun)
    {
        $content = file_get_contents($file);
        $originalContent = $content;
        
        // Check if it's an abstract class or interface
        if (preg_match('/\b(abstract\s+class|interface)\s+\w+/', $content)) {
            // Still add to abstract classes, but note it
        }
        
        // Determine if file has namespace
        $hasNamespace = preg_match('/^namespace\s+[^;]+;/m', $content);
        
        // Find the class declaration
        if (!preg_match('/class\s+(\w+)\s+extends\s+(\w+)/', $content, $matches)) {
            return "Could not find class declaration";
        }
        
        $className = $matches[1];
        $parentClass = $matches[2];
        
        // Determine the scope for couchbase based on file location
        $scope = 'core';
        if (strpos($file, '/modules/') !== false) {
            preg_match('/modules\/(\w+)\//', $file, $modMatch);
            if ($modMatch) {
                $moduleName = strtolower($modMatch[1]);
                // Map module to couchbase scope
                $scopeMap = [
                    'ophciexamination' => 'examination',
                    'ophcocorrespondence' => 'correspondence',
                    'ophtrlaser' => 'laser',
                    'ophtroperationnote' => 'operationnote',
                    'ophtroperationbooking' => 'operationbooking',
                    'ophtrconsent' => 'consent',
                    'ophcocvi' => 'cvi',
                    'ophdrprescription' => 'prescription',
                    'ophdrpgdpsd' => 'pgdpsd',
                    'ophinbiometry' => 'biometry',
                    'ophgeneric' => 'generic',
                    'ophcomessaging' => 'messaging',
                    'ophindnaextraction' => 'dnaextraction',
                    'ophinlabresults' => 'labresults',
                    'ophinvisualfields' => 'visualfields',
                    'ophtroperationchecklists' => 'operationchecklists',
                    'ophcotherapyapplication' => 'therapyapplication',
                    'patientticketing' => 'ticketing',
                    'genetics' => 'genetics',
                    'oetrial' => 'trial',
                    'oecasesearch' => 'casesearch',
                ];
                $scope = $scopeMap[$moduleName] ?? strtolower($modMatch[1]);
            }
        }
        
        if ($hasNamespace) {
            // For namespaced files, add the use statement inside the class
            $traitUse = "use \\OE\\Models\\Traits\\CouchbaseModelBridge;";
            
            // Find position after class opening brace
            $pattern = '/(class\s+' . preg_quote($className) . '[^{]*\{)/';
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_MATCH)) {
                $insertPos = $matches[0][1] + strlen($matches[0][0]);
                $content = substr($content, 0, $insertPos) . 
                           "\n    " . $traitUse . "\n" .
                           substr($content, $insertPos);
            } else {
                return "Could not find class opening brace";
            }
        } else {
            // For non-namespaced files, add use statement at top and inside class
            
            // Add use statement after <?php and any comments/license
            $useStatement = "use OE\\Models\\Traits\\CouchbaseModelBridge;\n";
            $traitUse = "use CouchbaseModelBridge;";
            
            // Find position after opening PHP tag and any initial comments
            // Look for the first class or use statement
            if (preg_match('/^(<\?php\s*(?:\/\*[\s\S]*?\*\/\s*)?)/m', $content, $matches)) {
                $afterPhpAndComments = strlen($matches[0]);
                
                // Check if there are already use statements
                if (preg_match('/\nuse\s+[^;]+;/', $content)) {
                    // Add after last use statement before class
                    $content = preg_replace(
                        '/((?:\nuse\s+[^;]+;)+)(\s*(?:\/\*[\s\S]*?\*\/)?\s*class\s)/m',
                        "$1\n" . $useStatement . "$2",
                        $content
                    );
                } else {
                    // Add before class declaration
                    $content = preg_replace(
                        '/((?:\/\*[\s\S]*?\*\/\s*)?)(\bclass\s)/m',
                        $useStatement . "\n$1$2",
                        $content,
                        1
                    );
                }
            }
            
            // Add trait use inside class
            $pattern = '/(class\s+' . preg_quote($className) . '[^{]*\{)/';
            if (preg_match($pattern, $content, $matches, PREG_OFFSET_MATCH)) {
                $insertPos = $matches[0][1] + strlen($matches[0][0]);
                $content = substr($content, 0, $insertPos) . 
                           "\n    " . $traitUse . "\n" .
                           substr($content, $insertPos);
            } else {
                return "Could not find class opening brace";
            }
        }
        
        if ($content === $originalContent) {
            return "No changes made";
        }
        
        if (!$dryRun) {
            file_put_contents($file, $content);
        }
        
        return true;
    }
}
