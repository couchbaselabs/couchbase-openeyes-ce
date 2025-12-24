<?php
/**
 * Add Couchbase Bridge Command
 * 
 * Automated script to add CouchbaseModelBridge trait to all models
 * that don't have it yet. This enables Couchbase integration for
 * the entire OpenEyes application.
 * 
 * Usage:
 *   yiic addcouchbasebridge scan          - Scan and list models without bridge
 *   yiic addcouchbasebridge add           - Add bridge to all models
 *   yiic addcouchbasebridge add --model=X - Add bridge to specific model
 *   yiic addcouchbasebridge addModule X   - Add bridge to all models in module X
 */

class AddCouchbaseBridgeCommand extends CConsoleCommand
{
    /**
     * Scope mapping for different model types
     */
    protected $scopeMapping = [
        // Patient-related -> clinical
        'Patient' => 'clinical',
        'Episode' => 'clinical',
        'Event' => 'clinical',
        'Worklist' => 'clinical',
        'Pathway' => 'clinical',
        
        // Admin-related -> admin
        'User' => 'admin',
        'Audit' => 'admin',
        'Setting' => 'admin',
        'Auth' => 'admin',
        'Sso' => 'admin',
        
        // Reference data -> reference
        'Disorder' => 'reference',
        'Medication' => 'reference',
        'Procedure' => 'reference',
        'Drug' => 'reference',
        'Allergy' => 'reference',
        'Anaesthetic' => 'reference',
        'Common' => 'reference',
        'Country' => 'reference',
        'Language' => 'reference',
        'Period' => 'reference',
        'Priority' => 'reference',
        'Risk' => 'reference',
        'Finding' => 'reference',
        'Practice' => 'reference',
        'Gp' => 'reference',
        'Contact' => 'reference',
        'Address' => 'reference',
        
        // Module-specific scopes
        'OphCiExamination' => 'examination',
        'Element_OphCiExamination' => 'examination',
        'OphTrOperationnote' => 'operationnote',
        'Element_OphTrOperationnote' => 'operationnote',
        'OphTrOperationbooking' => 'booking',
        'Element_OphTrOperationbooking' => 'booking',
        'OphCoCorrespondence' => 'correspondence',
        'Element_OphCoCorrespondence' => 'correspondence',
        'Letter' => 'correspondence',
        'OphTrConsent' => 'consent',
        'Element_OphTrConsent' => 'consent',
        'OphDrPrescription' => 'prescription',
        'Element_OphDrPrescription' => 'prescription',
        'OphTrLaser' => 'laser',
        'Element_OphTrLaser' => 'laser',
        'OphInBiometry' => 'biometry',
        'Element_OphInBiometry' => 'biometry',
        'OphCoCvi' => 'cvi',
        'Element_OphCoCvi' => 'cvi',
        'OphTrIntravitrealinjection' => 'injection',
        'Element_OphTrIntravitrealinjection' => 'injection',
        'OphInVisualfields' => 'visualfields',
        'Element_OphInVisualfields' => 'visualfields',
        'OphCoMessaging' => 'messaging',
        'Element_OphCoMessaging' => 'messaging',
        'OphCoTherapyapplication' => 'therapy',
        'Element_OphCoTherapyapplication' => 'therapy',
        'OphTrOperationchecklists' => 'checklists',
        'Element_OphTrOperationchecklists' => 'checklists',
        'OphInLabResults' => 'labresults',
        'Element_OphInLabResults' => 'labresults',
        'OphDrPGDPSD' => 'pgdpsd',
        'Element_DrugAdministration' => 'pgdpsd',
        'OphGeneric' => 'generic',
        'Genetics' => 'genetics',
        'OphInDna' => 'genetics',
        'OphInGenetic' => 'genetics',
        'PatientTicketing' => 'ticketing',
        'Queue' => 'ticketing',
        'Ticket' => 'ticketing',
        'OETrial' => 'trial',
        'Trial' => 'trial',
        'OECaseSearch' => 'search',
        'CaseSearch' => 'search',
    ];
    
    protected $processedCount = 0;
    protected $skippedCount = 0;
    protected $errorCount = 0;
    
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic addcouchbasebridge <action> [options]

ACTIONS
  scan              Scan and list all models without CouchbaseModelBridge
  add               Add bridge to all models (with confirmation)
  addModel <name>   Add bridge to a specific model file
  addModule <name>  Add bridge to all models in a module
  status            Show migration status

OPTIONS
  --dryRun          Show what would be done without making changes
  --verbose         Show detailed output
  --force           Skip confirmation prompts

EXAMPLES
  yiic addcouchbasebridge scan
  yiic addcouchbasebridge add --dryRun
  yiic addcouchbasebridge addModule OphTrLaser
  yiic addcouchbasebridge addModel PatientIdentifier

EOD;
    }
    
    /**
     * Scan all models and report status
     */
    public function actionScan()
    {
        echo "===========================================\n";
        echo "SCANNING ALL MODELS\n";
        echo "===========================================\n\n";
        
        $coreModels = $this->getCoreModels();
        $moduleModels = $this->getModuleModels();
        
        $coreWithBridge = 0;
        $coreWithoutBridge = 0;
        $moduleWithBridge = 0;
        $moduleWithoutBridge = 0;
        
        echo "CORE MODELS:\n";
        echo str_repeat('-', 40) . "\n";
        
        foreach ($coreModels as $model) {
            $hasBridge = $this->hasCouchbaseBridge($model['path']);
            if ($hasBridge) {
                $coreWithBridge++;
            } else {
                $coreWithoutBridge++;
                echo "  [ ] {$model['name']}\n";
            }
        }
        
        echo "\nMODULE MODELS (by module):\n";
        echo str_repeat('-', 40) . "\n";
        
        $moduleStats = [];
        foreach ($moduleModels as $model) {
            $moduleName = $model['module'];
            if (!isset($moduleStats[$moduleName])) {
                $moduleStats[$moduleName] = ['with' => 0, 'without' => 0, 'models' => []];
            }
            
            $hasBridge = $this->hasCouchbaseBridge($model['path']);
            if ($hasBridge) {
                $moduleStats[$moduleName]['with']++;
                $moduleWithBridge++;
            } else {
                $moduleStats[$moduleName]['without']++;
                $moduleWithoutBridge++;
                $moduleStats[$moduleName]['models'][] = $model['name'];
            }
        }
        
        foreach ($moduleStats as $module => $stats) {
            $total = $stats['with'] + $stats['without'];
            echo "\n  [{$module}] {$stats['without']}/{$total} need migration\n";
        }
        
        echo "\n===========================================\n";
        echo "SUMMARY\n";
        echo "===========================================\n";
        echo "Core Models:   {$coreWithBridge} migrated, {$coreWithoutBridge} remaining\n";
        echo "Module Models: {$moduleWithBridge} migrated, {$moduleWithoutBridge} remaining\n";
        echo "TOTAL:         " . ($coreWithBridge + $moduleWithBridge) . " migrated, ";
        echo ($coreWithoutBridge + $moduleWithoutBridge) . " remaining\n";
        
        return 0;
    }
    
    /**
     * Add bridge to all models
     */
    public function actionAdd($dryRun = false, $verbose = false, $force = false)
    {
        echo "===========================================\n";
        echo "ADD COUCHBASE BRIDGE TO ALL MODELS\n";
        echo "===========================================\n\n";
        
        if ($dryRun) {
            echo "[DRY RUN MODE - No changes will be made]\n\n";
        }
        
        $coreModels = $this->getCoreModels();
        $moduleModels = $this->getModuleModels();
        
        $toProcess = [];
        
        foreach ($coreModels as $model) {
            if (!$this->hasCouchbaseBridge($model['path'])) {
                $toProcess[] = $model;
            }
        }
        
        foreach ($moduleModels as $model) {
            if (!$this->hasCouchbaseBridge($model['path'])) {
                $toProcess[] = $model;
            }
        }
        
        $count = count($toProcess);
        echo "Found {$count} models without CouchbaseModelBridge\n\n";
        
        if ($count === 0) {
            echo "All models already have CouchbaseModelBridge!\n";
            return 0;
        }
        
        if (!$force && !$dryRun) {
            echo "This will modify {$count} model files.\n";
            echo "Type 'YES' to continue: ";
            $confirm = trim(fgets(STDIN));
            if ($confirm !== 'YES') {
                echo "Aborted.\n";
                return 1;
            }
        }
        
        echo "\nProcessing models...\n\n";
        
        foreach ($toProcess as $model) {
            $this->addBridgeToModel($model, $dryRun, $verbose);
        }
        
        echo "\n===========================================\n";
        echo "COMPLETE\n";
        echo "===========================================\n";
        echo "Processed: {$this->processedCount}\n";
        echo "Skipped:   {$this->skippedCount}\n";
        echo "Errors:    {$this->errorCount}\n";
        
        return $this->errorCount > 0 ? 1 : 0;
    }
    
    /**
     * Add bridge to a specific model
     */
    public function actionAddModel($model, $dryRun = false, $verbose = true)
    {
        echo "Adding CouchbaseModelBridge to: {$model}\n\n";
        
        // Find the model file
        $paths = [
            Yii::app()->basePath . "/models/{$model}.php",
        ];
        
        // Also check modules
        $modules = glob(Yii::app()->basePath . "/modules/*/models/{$model}.php");
        $paths = array_merge($paths, $modules);
        
        $found = false;
        foreach ($paths as $path) {
            if (file_exists($path)) {
                $modelInfo = [
                    'name' => $model,
                    'path' => $path,
                    'module' => $this->getModuleFromPath($path),
                ];
                $this->addBridgeToModel($modelInfo, $dryRun, $verbose);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "Model not found: {$model}\n";
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Add bridge to all models in a module
     */
    public function actionAddModule($module, $dryRun = false, $verbose = false)
    {
        echo "===========================================\n";
        echo "ADD BRIDGE TO MODULE: {$module}\n";
        echo "===========================================\n\n";
        
        $modulePath = Yii::app()->basePath . "/modules/{$module}/models";
        
        if (!is_dir($modulePath)) {
            echo "Module not found: {$module}\n";
            return 1;
        }
        
        $files = glob("{$modulePath}/*.php");
        $toProcess = [];
        
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (strpos($name, 'Test') !== false) continue;
            
            if (!$this->hasCouchbaseBridge($file)) {
                $toProcess[] = [
                    'name' => $name,
                    'path' => $file,
                    'module' => $module,
                ];
            }
        }
        
        $count = count($toProcess);
        echo "Found {$count} models without CouchbaseModelBridge in {$module}\n\n";
        
        if ($count === 0) {
            echo "All models in {$module} already have CouchbaseModelBridge!\n";
            return 0;
        }
        
        if ($dryRun) {
            echo "[DRY RUN MODE]\n\n";
        }
        
        foreach ($toProcess as $model) {
            $this->addBridgeToModel($model, $dryRun, $verbose);
        }
        
        echo "\nProcessed: {$this->processedCount}, Errors: {$this->errorCount}\n";
        
        return $this->errorCount > 0 ? 1 : 0;
    }
    
    /**
     * Show migration status
     */
    public function actionStatus()
    {
        $this->actionScan();
    }
    
    /**
     * Add CouchbaseModelBridge to a model file
     */
    protected function addBridgeToModel($model, $dryRun, $verbose)
    {
        $path = $model['path'];
        $name = $model['name'];
        
        if (!file_exists($path)) {
            echo "  ✗ {$name}: File not found\n";
            $this->errorCount++;
            return false;
        }
        
        $content = file_get_contents($path);
        
        // Skip if already has bridge
        if (strpos($content, 'CouchbaseModelBridge') !== false) {
            if ($verbose) echo "  - {$name}: Already has bridge\n";
            $this->skippedCount++;
            return true;
        }
        
        // Skip abstract classes and interfaces
        if (preg_match('/abstract\s+class|interface\s+\w+/', $content)) {
            if ($verbose) echo "  - {$name}: Abstract/Interface, skipping\n";
            $this->skippedCount++;
            return true;
        }
        
        // Skip non-model files
        if (!preg_match('/extends\s+(BaseActiveRecord|CActiveRecord|BaseEventTypeElement|SplitEventTypeElement|BaseActiveRecordVersioned)/', $content)) {
            if ($verbose) echo "  - {$name}: Not a model class, skipping\n";
            $this->skippedCount++;
            return true;
        }
        
        // Determine scope and collection
        $scope = $this->determineScope($name, $model['module'] ?? null);
        $collection = $this->determineCollection($name);
        
        // Add the use statement for the trait
        $useStatement = "use OE\\Models\\Traits\\CouchbaseModelBridge;\n";
        
        // Add trait usage inside class
        $traitUsage = "\n    use CouchbaseModelBridge;\n";
        
        // Add scope and collection methods
        $methods = $this->generateMethods($scope, $collection);
        
        // Modify the content
        $newContent = $content;
        
        // Add use statement after namespace or at the top
        if (preg_match('/^<\?php\s*\n/', $newContent)) {
            // Add after opening PHP tag
            $newContent = preg_replace(
                '/^(<\?php\s*\n)/',
                "$1{$useStatement}",
                $newContent
            );
        }
        
        // Add trait usage after class opening brace
        $newContent = preg_replace(
            '/(class\s+' . preg_quote($name, '/') . '\s+extends\s+\w+[^{]*\{)/',
            "$1{$traitUsage}",
            $newContent
        );
        
        // Add methods before the last closing brace
        $lastBracePos = strrpos($newContent, '}');
        if ($lastBracePos !== false) {
            $newContent = substr($newContent, 0, $lastBracePos) . $methods . "\n}\n";
        }
        
        if ($dryRun) {
            echo "  [DRY] {$name} -> {$scope}.{$collection}\n";
            $this->processedCount++;
            return true;
        }
        
        // Write the modified content
        if (file_put_contents($path, $newContent) !== false) {
            echo "  ✓ {$name} -> {$scope}.{$collection}\n";
            $this->processedCount++;
            return true;
        } else {
            echo "  ✗ {$name}: Failed to write file\n";
            $this->errorCount++;
            return false;
        }
    }
    
    /**
     * Determine the Couchbase scope for a model
     */
    protected function determineScope($modelName, $module = null)
    {
        // Check module-based scope first
        if ($module) {
            foreach ($this->scopeMapping as $prefix => $scope) {
                if (strpos($module, $prefix) === 0 || strpos($module, str_replace('_', '', $prefix)) === 0) {
                    return $scope;
                }
            }
        }
        
        // Check model name prefixes
        foreach ($this->scopeMapping as $prefix => $scope) {
            if (strpos($modelName, $prefix) === 0) {
                return $scope;
            }
        }
        
        // Default scope based on common patterns
        if (preg_match('/^Element_/', $modelName)) {
            return 'clinical';
        }
        
        return 'reference';
    }
    
    /**
     * Determine the Couchbase collection name for a model
     */
    protected function determineCollection($modelName)
    {
        // Convert CamelCase to snake_case
        $collection = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName));
        
        // Clean up double underscores
        $collection = preg_replace('/_+/', '_', $collection);
        
        return $collection;
    }
    
    /**
     * Generate the couchbaseScope and couchbaseCollection methods
     */
    protected function generateMethods($scope, $collection)
    {
        return <<<EOD

    /**
     * Get the Couchbase scope for this model
     * @return string
     */
    public function couchbaseScope()
    {
        return '{$scope}';
    }

    /**
     * Get the Couchbase collection name for this model
     * @return string
     */
    public function couchbaseCollection()
    {
        return '{$collection}';
    }
EOD;
    }
    
    /**
     * Check if a file has CouchbaseModelBridge
     */
    protected function hasCouchbaseBridge($path)
    {
        if (!file_exists($path)) return false;
        $content = file_get_contents($path);
        return strpos($content, 'CouchbaseModelBridge') !== false;
    }
    
    /**
     * Get all core models
     */
    protected function getCoreModels()
    {
        $models = [];
        $path = Yii::app()->basePath . '/models';
        
        foreach (glob("{$path}/*.php") as $file) {
            $name = basename($file, '.php');
            
            // Skip base classes and tests
            if (strpos($name, 'Base') === 0) continue;
            if (strpos($name, 'Test') !== false) continue;
            
            $models[] = [
                'name' => $name,
                'path' => $file,
                'module' => null,
            ];
        }
        
        return $models;
    }
    
    /**
     * Get all module models
     */
    protected function getModuleModels()
    {
        $models = [];
        $modulesPath = Yii::app()->basePath . '/modules';
        
        foreach (glob("{$modulesPath}/*/models/*.php") as $file) {
            $name = basename($file, '.php');
            
            // Skip tests
            if (strpos($name, 'Test') !== false) continue;
            
            // Get module name from path
            preg_match('/modules\/([^\/]+)\/models/', $file, $matches);
            $module = $matches[1] ?? 'unknown';
            
            $models[] = [
                'name' => $name,
                'path' => $file,
                'module' => $module,
            ];
        }
        
        return $models;
    }
    
    /**
     * Get module name from file path
     */
    protected function getModuleFromPath($path)
    {
        if (preg_match('/modules\/([^\/]+)\/models/', $path, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
