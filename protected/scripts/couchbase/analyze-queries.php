<?php
/**
 * Analyze SQL queries in codebase for migration to N1QL
 * 
 * Usage: php protected/scripts/couchbase/analyze-queries.php
 */

class QueryAnalyzer
{
    private $patterns = [
        'createCommand' => '/->createCommand\s*\(\s*[\'"]([^"\']+)[\'"]/ms',
        'rawSql' => '/->(?:query|execute|queryAll|queryRow|queryColumn|queryScalar)\s*\(\s*[\'"]([^"\']+)[\'"]/ms',
        'findBySql' => '/::findBySql\s*\(\s*[\'"]([^"\']+)[\'"]/ms',
        'findAllBySql' => '/::findAllBySql\s*\(\s*[\'"]([^"\']+)[\'"]/ms',
        'commandText' => '/->setText\s*\(\s*[\'"]([^"\']+)[\'"]/ms',
    ];
    
    private $queries = [];
    private $excludeDirs = ['vendor', 'node_modules', '.git', 'runtime', 'assets'];
    
    public function analyze($directory)
    {
        $this->scanDirectory($directory);
        return $this->queries;
    }
    
    private function scanDirectory($directory)
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                function ($file, $key, $iterator) {
                    $filename = $file->getFilename();
                    if ($file->isDir()) {
                        return !in_array($filename, $this->excludeDirs);
                    }
                    return $file->getExtension() === 'php';
                }
            )
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $this->analyzeFile($file->getPathname());
            }
        }
    }
    
    private function analyzeFile($filepath)
    {
        $content = file_get_contents($filepath);
        $relativePath = str_replace(dirname(__DIR__, 2) . '/', '', $filepath);
        
        foreach ($this->patterns as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $sql = trim($match[0]);
                    $lineNumber = substr_count(substr($content, 0, $match[1]), "\n") + 1;
                    
                    $this->queries[] = [
                        'file' => $relativePath,
                        'line' => $lineNumber,
                        'type' => $type,
                        'sql' => $sql,
                        'complexity' => $this->assessComplexity($sql),
                        'tables' => $this->extractTables($sql),
                    ];
                }
            }
        }
    }
    
    private function assessComplexity($sql)
    {
        $score = 0;
        $sqlUpper = strtoupper($sql);
        
        // JOINs add complexity
        $score += substr_count($sqlUpper, ' JOIN ') * 2;
        $score += substr_count($sqlUpper, ' LEFT JOIN ') * 2;
        $score += substr_count($sqlUpper, ' RIGHT JOIN ') * 2;
        $score += substr_count($sqlUpper, ' INNER JOIN ') * 2;
        
        // Subqueries (nested SELECT)
        $score += (substr_count($sqlUpper, 'SELECT') - 1) * 3;
        
        // Aggregations
        $aggregations = ['GROUP BY', 'HAVING', 'UNION', 'DISTINCT', 'COUNT(', 'SUM(', 'AVG(', 'MAX(', 'MIN('];
        foreach ($aggregations as $agg) {
            $score += substr_count($sqlUpper, $agg);
        }
        
        // Complex functions
        $functions = ['COALESCE', 'IFNULL', 'CASE ', 'CONCAT', 'DATE_FORMAT', 'STR_TO_DATE'];
        foreach ($functions as $func) {
            $score += substr_count($sqlUpper, $func);
        }
        
        if ($score <= 2) return 'simple';
        if ($score <= 5) return 'moderate';
        return 'complex';
    }
    
    private function extractTables($sql)
    {
        $tables = [];
        
        // FROM clause
        if (preg_match_all('/FROM\s+[`]?(\w+)[`]?/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }
        
        // JOIN clauses
        if (preg_match_all('/JOIN\s+[`]?(\w+)[`]?/i', $sql, $matches)) {
            $tables = array_merge($tables, $matches[1]);
        }
        
        return array_unique($tables);
    }
    
    public function exportReport($filename)
    {
        $grouped = [
            'simple' => [],
            'moderate' => [],
            'complex' => [],
        ];
        
        foreach ($this->queries as $query) {
            $grouped[$query['complexity']][] = $query;
        }
        
        $report = "# SQL Query Migration Report\n\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "## Summary\n\n";
        $report .= "| Complexity | Count |\n";
        $report .= "|------------|-------|\n";
        $report .= "| Simple | " . count($grouped['simple']) . " |\n";
        $report .= "| Moderate | " . count($grouped['moderate']) . " |\n";
        $report .= "| Complex | " . count($grouped['complex']) . " |\n";
        $report .= "| **Total** | **" . count($this->queries) . "** |\n\n";
        
        // Tables summary
        $allTables = [];
        foreach ($this->queries as $q) {
            $allTables = array_merge($allTables, $q['tables']);
        }
        $tableCounts = array_count_values($allTables);
        arsort($tableCounts);
        
        $report .= "## Tables Referenced\n\n";
        $report .= "| Table | Query Count |\n";
        $report .= "|-------|-------------|\n";
        foreach (array_slice($tableCounts, 0, 20) as $table => $count) {
            $report .= "| {$table} | {$count} |\n";
        }
        $report .= "\n";
        
        // Detailed queries by complexity
        foreach (['complex', 'moderate', 'simple'] as $complexity) {
            if (empty($grouped[$complexity])) continue;
            
            $report .= "## " . ucfirst($complexity) . " Queries (" . count($grouped[$complexity]) . ")\n\n";
            
            foreach (array_slice($grouped[$complexity], 0, 10) as $i => $q) {
                $report .= "### " . ($i + 1) . ". {$q['file']}:{$q['line']}\n\n";
                $report .= "**Type**: {$q['type']}  \n";
                $report .= "**Tables**: " . implode(', ', $q['tables']) . "\n\n";
                $report .= "```sql\n" . wordwrap($q['sql'], 100) . "\n```\n\n";
            }
        }
        
        file_put_contents($filename, $report);
        return $filename;
    }
    
    public function exportJson($filename)
    {
        file_put_contents($filename, json_encode($this->queries, JSON_PRETTY_PRINT));
        return $filename;
    }
}

// Run if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    $projectRoot = dirname(__DIR__, 2);
    
    echo "Analyzing SQL queries in codebase...\n";
    
    $analyzer = new QueryAnalyzer();
    $analyzer->analyze($projectRoot . '/protected');
    
    $reportDir = $projectRoot . '/docs/migration-mariadb-to-couchbase';
    if (!is_dir($reportDir)) {
        mkdir($reportDir, 0755, true);
    }
    
    $mdFile = $analyzer->exportReport($reportDir . '/query-migration-report.md');
    $jsonFile = $analyzer->exportJson($reportDir . '/query-migration-report.json');
    
    echo "Reports generated:\n";
    echo "  - {$mdFile}\n";
    echo "  - {$jsonFile}\n";
}
