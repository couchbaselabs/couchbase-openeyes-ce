<?php
/**
 * Command to analyze SQL queries for Couchbase migration
 * 
 * Usage:
 *   yiic queryanalysis run                    - Analyze and generate report
 *   yiic queryanalysis summary                - Show summary only
 *   yiic queryanalysis tables                 - Show table usage
 *   yiic queryanalysis forTable --table=patient - Show queries for specific table
 */

class QueryAnalysisCommand extends CConsoleCommand
{
    private $analyzer;
    
    public function init()
    {
        parent::init();
        require_once(Yii::getPathOfAlias('application.scripts.couchbase') . '/analyze-queries.php');
        $this->analyzer = new QueryAnalyzer();
    }
    
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic queryanalysis <action> [options]

ACTIONS
  run         - Run full analysis and generate reports
  summary     - Show summary statistics only
  tables      - Show table usage statistics
  forTable    - Show queries for a specific table

OPTIONS
  --table=<name>     Table name for forTable action
  --complexity=<level>  Filter by complexity (simple/moderate/complex)
  --output=<path>    Custom output path for reports

EXAMPLES
  yiic queryanalysis run
  yiic queryanalysis summary
  yiic queryanalysis tables
  yiic queryanalysis forTable --table=patient
  yiic queryanalysis run --complexity=complex
EOD;
    }
    
    public function actionRun($complexity = null, $output = null)
    {
        echo "=== SQL Query Analysis for Couchbase Migration ===\n\n";
        
        $queries = $this->analyzer->analyze(Yii::getPathOfAlias('application'));
        
        if ($complexity) {
            $queries = array_filter($queries, function($q) use ($complexity) {
                return $q['complexity'] === $complexity;
            });
        }
        
        $outputDir = $output ?: Yii::getPathOfAlias('application') . '/../docs/migration-mariadb-to-couchbase';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $mdFile = $this->analyzer->exportReport($outputDir . '/query-migration-report.md');
        $jsonFile = $this->analyzer->exportJson($outputDir . '/query-migration-report.json');
        
        $this->showSummary($queries);
        
        echo "\nReports generated:\n";
        echo "  - {$mdFile}\n";
        echo "  - {$jsonFile}\n";
    }
    
    public function actionSummary()
    {
        $queries = $this->analyzer->analyze(Yii::getPathOfAlias('application'));
        $this->showSummary($queries);
    }
    
    public function actionTables()
    {
        $queries = $this->analyzer->analyze(Yii::getPathOfAlias('application'));
        
        $tableCounts = [];
        foreach ($queries as $q) {
            foreach ($q['tables'] as $table) {
                $tableCounts[$table] = ($tableCounts[$table] ?? 0) + 1;
            }
        }
        arsort($tableCounts);
        
        echo "=== Table Usage in SQL Queries ===\n\n";
        echo str_pad("Table", 40) . str_pad("Query Count", 15) . "\n";
        echo str_repeat("-", 55) . "\n";
        
        foreach ($tableCounts as $table => $count) {
            echo str_pad($table, 40) . str_pad($count, 15) . "\n";
        }
    }
    
    public function actionForTable($table = null)
    {
        if (!$table) {
            echo "Error: --table parameter required\n";
            return 1;
        }
        
        $queries = $this->analyzer->analyze(Yii::getPathOfAlias('application'));
        
        $filtered = array_filter($queries, function($q) use ($table) {
            return in_array($table, $q['tables']);
        });
        
        echo "=== Queries Involving Table: {$table} ===\n\n";
        echo "Found " . count($filtered) . " queries\n\n";
        
        foreach ($filtered as $q) {
            echo "File: {$q['file']}:{$q['line']}\n";
            echo "Type: {$q['type']}\n";
            echo "Complexity: {$q['complexity']}\n";
            echo "SQL:\n{$q['sql']}\n";
            echo str_repeat("-", 60) . "\n\n";
        }
    }
    
    private function showSummary($queries)
    {
        $grouped = ['simple' => 0, 'moderate' => 0, 'complex' => 0];
        foreach ($queries as $q) {
            $grouped[$q['complexity']]++;
        }
        
        echo "Query Analysis Summary\n";
        echo str_repeat("=", 40) . "\n";
        echo str_pad("Complexity", 15) . str_pad("Count", 10) . "\n";
        echo str_repeat("-", 25) . "\n";
        echo str_pad("Simple", 15) . str_pad($grouped['simple'], 10) . "\n";
        echo str_pad("Moderate", 15) . str_pad($grouped['moderate'], 10) . "\n";
        echo str_pad("Complex", 15) . str_pad($grouped['complex'], 10) . "\n";
        echo str_repeat("-", 25) . "\n";
        echo str_pad("Total", 15) . str_pad(count($queries), 10) . "\n";
    }
}
