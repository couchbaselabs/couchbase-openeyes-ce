# Phase 6: Query Migration (SQL to N1QL) - Agent Executable Specification

**Version**: 1.0.0  
**Date**: December 22, 2025  
**Status**: READY FOR IMPLEMENTATION  
**Estimated Duration**: 3-4 weeks  
**Total Tasks**: 28 tasks across 9 sections

---

## Executive Summary

This specification provides step-by-step instructions for migrating SQL queries to Couchbase N1QL (SQL++). This includes creating query analysis tools, building N1QL query builders, translating common query patterns, and updating reports/APIs to support Couchbase.

---

## Prerequisites

### Required Phase Completions
- [x] Phase 1: Infrastructure & Couchbase Setup (COMPLETED)
- [x] Phase 2: Abstract Database Layer (COMPLETED)
- [x] Phase 3: Data Modeling & Schema Translation (COMPLETED)
- [x] Phase 4: Core Model Migration (COMPLETED)
- [x] Phase 5: Module Model Migration (COMPLETED)

### Required Running Services
```bash
# Verify Couchbase is running
docker compose -f docker-compose.couchbase.yml ps
# Expected: openeyes-couchbase running and healthy

# Verify OpenEyes containers
docker compose -f .devcontainer/docker-compose.yml ps
# Expected: web (port 7777), db (port 3333) both healthy
```

### Required Files from Previous Phases
| File | Purpose | Location |
|------|---------|----------|
| `CouchbaseConnection.php` | Couchbase connection component | `protected/components/CouchbaseConnection.php` |
| `DatabaseAdapterFactory.php` | Adapter factory | `protected/components/database/DatabaseAdapterFactory.php` |
| `CouchbaseAdapter.php` | Couchbase adapter | `protected/components/database/CouchbaseAdapter.php` |
| `ExaminationDocument.php` | Examination document model | `protected/modules/OphCiExamination/models/couchbase/ExaminationDocument.php` |
| `OperationDocument.php` | Operation document model | `protected/modules/OphTrOperationbooking/models/couchbase/OperationDocument.php` |
| `LetterDocument.php` | Letter document model | `protected/modules/OphCoCorrespondence/models/couchbase/LetterDocument.php` |
| `module-indexes.n1ql` | Index definitions | `protected/scripts/couchbase/module-indexes.n1ql` |
| `couchbase-collections.php` | Collection config | `protected/config/couchbase-collections.php` |

### Directory Structure to Create
```
protected/
├── components/
│   ├── database/
│   │   ├── N1qlQueryBuilder.php           # NEW - Query builder
│   │   └── QueryMigrationHelper.php       # NEW - Migration helper
│   └── reports/
│       ├── CouchbasePatientSearch.php     # NEW - Patient search
│       ├── CouchbaseEpisodeReport.php     # NEW - Episode reports
│       └── CouchbaseEventReport.php       # NEW - Event reports
├── commands/
│   ├── QueryAnalysisCommand.php           # NEW - Query analyzer
│   ├── QueryBenchmarkCommand.php          # NEW - Performance benchmark
│   └── QueryMigrationVerifyCommand.php    # NEW - Verification command
├── config/
│   └── couchbase-collection-map.php       # NEW - Table mapping
├── modules/
│   ├── OphCiExamination/components/
│   │   └── CouchbaseExaminationSearch.php # NEW
│   ├── OphTrOperationbooking/components/
│   │   ├── CouchbaseWaitingList.php       # NEW
│   │   └── CouchbaseTheatreSchedule.php   # NEW
│   └── OphCoCorrespondence/components/
│       └── CouchbaseLetterSearch.php      # NEW
├── scripts/couchbase/
│   └── analyze-queries.php                # NEW - Analysis script
└── tests/
    ├── unit/components/
    │   ├── database/N1qlQueryBuilderTest.php
    │   └── reports/CouchbasePatientSearchTest.php
    └── integration/
        └── CouchbaseQueryIntegrationTest.php
```

---

## Section 1: Query Inventory & Analysis (Tasks 1-3)

### Task 1.1: Create Query Analyzer Script

**File**: `/protected/scripts/couchbase/analyze-queries.php`

```php
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
            
            foreach ($grouped[$complexity] as $i => $q) {
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
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0])) {
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
```

**Acceptance Criteria**:
- [ ] Script scans all PHP files in protected/ directory
- [ ] Identifies createCommand, findBySql, findAllBySql, raw queries
- [ ] Categorizes queries by complexity (simple/moderate/complex)
- [ ] Extracts table names from queries
- [ ] Generates markdown and JSON reports

---

### Task 1.2: Create Query Analysis Command

**File**: `/protected/commands/QueryAnalysisCommand.php`

```php
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
        
        $outputDir = $output ?: Yii::getPathOfAlias('application.docs.migration-mariadb-to-couchbase');
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
```

**Acceptance Criteria**:
- [ ] Command runs without errors
- [ ] Summary action shows statistics
- [ ] Tables action shows table usage
- [ ] forTable action filters correctly
- [ ] Reports generated in correct directory

---

### Task 1.3: Run Analysis and Document Results

**Command**:
```bash
cd /Users/asahu/Desktop/OpenEyes/openeyes
php protected/yiic.php queryanalysis run
```

**Expected Output**: Report at `/docs/migration-mariadb-to-couchbase/query-migration-report.md`

---

## Section 2: N1QL Query Builder (Tasks 4-6)

### Task 2.1: Create N1QL Query Builder

**File**: `/protected/components/database/N1qlQueryBuilder.php`

```php
<?php
/**
 * Fluent N1QL Query Builder for Couchbase
 * 
 * Provides a chainable interface for building N1QL queries with
 * proper parameter binding and Couchbase-specific features.
 * 
 * Usage:
 *   $builder = new N1qlQueryBuilder('openeyes');
 *   $results = $builder
 *       ->from('core', 'patient')
 *       ->select('hos_num, nhs_num, dob')
 *       ->where('hos_num = $hosNum', ['hosNum' => '12345'])
 *       ->orderBy('last_name')
 *       ->limit(10)
 *       ->execute();
 */

namespace OE\Database;

class N1qlQueryBuilder
{
    private $bucket;
    private $scope;
    private $collection;
    private $alias;
    private $select = ['*'];
    private $joins = [];
    private $where = [];
    private $params = [];
    private $orderBy = [];
    private $groupBy = [];
    private $having = [];
    private $limit;
    private $offset;
    private $useKeys = null;
    private $includeMetaId = true;
    
    /**
     * @param string $bucket Couchbase bucket name
     */
    public function __construct(string $bucket = 'openeyes')
    {
        $this->bucket = $bucket;
    }
    
    /**
     * Set the collection to query from
     * @param string $scope Scope name
     * @param string $collection Collection name
     * @param string|null $alias Optional alias
     * @return self
     */
    public function from(string $scope, string $collection, ?string $alias = null): self
    {
        $this->scope = $scope;
        $this->collection = $collection;
        $this->alias = $alias;
        return $this;
    }
    
    /**
     * Set SELECT columns
     * @param string|array $columns Columns to select
     * @return self
     */
    public function select($columns): self
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $this->select = $columns;
        return $this;
    }
    
    /**
     * Add SELECT columns
     * @param string|array $columns Additional columns
     * @return self
     */
    public function addSelect($columns): self
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $this->select = array_merge($this->select, $columns);
        return $this;
    }
    
    /**
     * Disable automatic META().id inclusion
     * @return self
     */
    public function withoutMetaId(): self
    {
        $this->includeMetaId = false;
        return $this;
    }
    
    /**
     * Add WHERE condition (AND)
     * @param string $condition Condition string
     * @param array $params Parameters for the condition
     * @return self
     */
    public function where(string $condition, array $params = []): self
    {
        $this->where[] = ['AND', $condition];
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    /**
     * Alias for where() - adds AND condition
     * @param string $condition Condition string
     * @param array $params Parameters
     * @return self
     */
    public function andWhere(string $condition, array $params = []): self
    {
        return $this->where($condition, $params);
    }
    
    /**
     * Add OR WHERE condition
     * @param string $condition Condition string
     * @param array $params Parameters
     * @return self
     */
    public function orWhere(string $condition, array $params = []): self
    {
        $this->where[] = ['OR', $condition];
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    /**
     * Add IN condition
     * @param string $column Column name
     * @param array $values Array of values
     * @param string $paramPrefix Parameter name prefix
     * @return self
     */
    public function whereIn(string $column, array $values, string $paramPrefix = 'in'): self
    {
        if (empty($values)) {
            $this->where[] = ['AND', '1 = 0']; // Always false
            return $this;
        }
        
        $placeholders = [];
        foreach ($values as $i => $value) {
            $paramName = $paramPrefix . $i;
            $placeholders[] = '$' . $paramName;
            $this->params[$paramName] = $value;
        }
        
        $this->where[] = ['AND', "{$column} IN [" . implode(', ', $placeholders) . "]"];
        return $this;
    }
    
    /**
     * Add BETWEEN condition
     * @param string $column Column name
     * @param mixed $start Start value
     * @param mixed $end End value
     * @param string $paramPrefix Parameter prefix
     * @return self
     */
    public function whereBetween(string $column, $start, $end, string $paramPrefix = 'between'): self
    {
        $startParam = $paramPrefix . 'Start';
        $endParam = $paramPrefix . 'End';
        
        $this->where[] = ['AND', "{$column} BETWEEN \${$startParam} AND \${$endParam}"];
        $this->params[$startParam] = $start;
        $this->params[$endParam] = $end;
        
        return $this;
    }
    
    /**
     * Add LIKE condition
     * @param string $column Column name
     * @param string $pattern LIKE pattern
     * @param string $paramName Parameter name
     * @return self
     */
    public function whereLike(string $column, string $pattern, string $paramName = 'like'): self
    {
        $this->where[] = ['AND', "{$column} LIKE \${$paramName}"];
        $this->params[$paramName] = $pattern;
        return $this;
    }
    
    /**
     * Add case-insensitive LIKE condition
     * @param string $column Column name
     * @param string $pattern LIKE pattern
     * @param string $paramName Parameter name
     * @return self
     */
    public function whereILike(string $column, string $pattern, string $paramName = 'ilike'): self
    {
        $this->where[] = ['AND', "LOWER({$column}) LIKE LOWER(\${$paramName})"];
        $this->params[$paramName] = $pattern;
        return $this;
    }
    
    /**
     * Add IS NULL condition
     * @param string $column Column name
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->where[] = ['AND', "{$column} IS NULL"];
        return $this;
    }
    
    /**
     * Add IS NOT NULL condition
     * @param string $column Column name
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->where[] = ['AND', "{$column} IS NOT NULL"];
        return $this;
    }
    
    /**
     * Add ANY/SATISFIES condition for array queries
     * @param string $arrayPath Path to array field
     * @param string $itemVar Variable name for array item
     * @param string $condition Condition using itemVar
     * @param array $params Parameters for condition
     * @return self
     */
    public function whereAny(string $arrayPath, string $itemVar, string $condition, array $params = []): self
    {
        $this->where[] = ['AND', "ANY {$itemVar} IN {$arrayPath} SATISFIES {$condition} END"];
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    /**
     * Add JOIN clause
     * @param string $scope Target scope
     * @param string $collection Target collection
     * @param string $on JOIN condition
     * @param string|null $alias Alias for joined collection
     * @return self
     */
    public function join(string $scope, string $collection, string $on, ?string $alias = null): self
    {
        $this->joins[] = [
            'type' => 'JOIN',
            'scope' => $scope,
            'collection' => $collection,
            'alias' => $alias,
            'on' => $on,
        ];
        return $this;
    }
    
    /**
     * Add LEFT JOIN clause
     * @param string $scope Target scope
     * @param string $collection Target collection
     * @param string $on JOIN condition
     * @param string|null $alias Alias
     * @return self
     */
    public function leftJoin(string $scope, string $collection, string $on, ?string $alias = null): self
    {
        $this->joins[] = [
            'type' => 'LEFT JOIN',
            'scope' => $scope,
            'collection' => $collection,
            'alias' => $alias,
            'on' => $on,
        ];
        return $this;
    }
    
    /**
     * Add NEST clause (Couchbase-specific - embeds joined docs as array)
     * @param string $scope Target scope
     * @param string $collection Target collection
     * @param string $as Alias for nested array
     * @param string $on NEST condition
     * @return self
     */
    public function nest(string $scope, string $collection, string $as, string $on): self
    {
        $this->joins[] = [
            'type' => 'NEST',
            'scope' => $scope,
            'collection' => $collection,
            'alias' => $as,
            'on' => $on,
        ];
        return $this;
    }
    
    /**
     * Add UNNEST clause (expand array into rows)
     * @param string $arrayPath Path to array field
     * @param string $as Alias for unnested items
     * @return self
     */
    public function unnest(string $arrayPath, string $as): self
    {
        $this->joins[] = [
            'type' => 'UNNEST',
            'path' => $arrayPath,
            'alias' => $as,
        ];
        return $this;
    }
    
    /**
     * Add ORDER BY clause
     * @param string $column Column to order by
     * @param string $direction ASC or DESC
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }
        $this->orderBy[] = "{$column} {$direction}";
        return $this;
    }
    
    /**
     * Add GROUP BY clause
     * @param string|array $columns Columns to group by
     * @return self
     */
    public function groupBy($columns): self
    {
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $this->groupBy = array_merge($this->groupBy, $columns);
        return $this;
    }
    
    /**
     * Add HAVING clause
     * @param string $condition HAVING condition
     * @param array $params Parameters
     * @return self
     */
    public function having(string $condition, array $params = []): self
    {
        $this->having[] = $condition;
        $this->params = array_merge($this->params, $params);
        return $this;
    }
    
    /**
     * Set LIMIT
     * @param int $limit Maximum rows
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Set OFFSET
     * @param int $offset Rows to skip
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * Set USE KEYS for direct key lookup
     * @param string|array $keys Document key(s)
     * @return self
     */
    public function useKeys($keys): self
    {
        $this->useKeys = is_array($keys) ? $keys : [$keys];
        return $this;
    }
    
    /**
     * Build the N1QL query string
     * @return string
     */
    public function build(): string
    {
        $parts = [];
        
        // SELECT clause
        $selectStr = implode(', ', $this->select);
        if ($this->includeMetaId && strpos($selectStr, 'META()') === false && $selectStr !== '*') {
            $selectStr = "META().id AS _id, {$selectStr}";
        } elseif ($selectStr === '*' && $this->includeMetaId) {
            $selectStr = "META().id AS _id, *";
        }
        $parts[] = "SELECT {$selectStr}";
        
        // FROM clause
        $keyspace = "`{$this->bucket}`.`{$this->scope}`.`{$this->collection}`";
        if ($this->alias) {
            $keyspace .= " AS {$this->alias}";
        }
        $parts[] = "FROM {$keyspace}";
        
        // USE KEYS
        if ($this->useKeys !== null) {
            $keys = array_map(function($k) { return "'{$k}'"; }, $this->useKeys);
            $parts[] = "USE KEYS [" . implode(', ', $keys) . "]";
        }
        
        // JOINs
        foreach ($this->joins as $join) {
            if ($join['type'] === 'UNNEST') {
                $parts[] = "UNNEST {$join['path']} AS {$join['alias']}";
            } else {
                $target = "`{$this->bucket}`.`{$join['scope']}`.`{$join['collection']}`";
                if ($join['alias']) {
                    $target .= " AS {$join['alias']}";
                }
                $parts[] = "{$join['type']} {$target} ON {$join['on']}";
            }
        }
        
        // WHERE clause
        if (!empty($this->where)) {
            $conditions = [];
            foreach ($this->where as $i => $w) {
                if ($i === 0) {
                    $conditions[] = $w[1];
                } else {
                    $conditions[] = $w[0] . ' ' . $w[1];
                }
            }
            $parts[] = "WHERE " . implode(' ', $conditions);
        }
        
        // GROUP BY
        if (!empty($this->groupBy)) {
            $parts[] = "GROUP BY " . implode(', ', $this->groupBy);
        }
        
        // HAVING
        if (!empty($this->having)) {
            $parts[] = "HAVING " . implode(' AND ', $this->having);
        }
        
        // ORDER BY
        if (!empty($this->orderBy)) {
            $parts[] = "ORDER BY " . implode(', ', $this->orderBy);
        }
        
        // LIMIT
        if ($this->limit !== null) {
            $parts[] = "LIMIT {$this->limit}";
        }
        
        // OFFSET
        if ($this->offset !== null) {
            $parts[] = "OFFSET {$this->offset}";
        }
        
        return implode("\n", $parts);
    }
    
    /**
     * Get bound parameters
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }
    
    /**
     * Execute the query and return results
     * @return array
     */
    public function execute(): array
    {
        $connection = \Yii::app()->couchbase;
        $result = $connection->query($this->build(), $this->params);
        return $result->rows();
    }
    
    /**
     * Execute and return single row
     * @return array|null
     */
    public function one(): ?array
    {
        $this->limit(1);
        $results = $this->execute();
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Execute and return scalar value
     * @param string $column Column to return
     * @return mixed|null
     */
    public function scalar(string $column = null)
    {
        $row = $this->one();
        if ($row === null) {
            return null;
        }
        if ($column) {
            return $row[$column] ?? null;
        }
        return reset($row);
    }
    
    /**
     * Execute COUNT query
     * @return int
     */
    public function count(): int
    {
        $countBuilder = clone $this;
        $countBuilder->select = ['COUNT(*) AS cnt'];
        $countBuilder->orderBy = [];
        $countBuilder->limit = null;
        $countBuilder->offset = null;
        $countBuilder->includeMetaId = false;
        
        $result = $countBuilder->one();
        return (int)($result['cnt'] ?? 0);
    }
    
    /**
     * Reset builder for reuse
     * @return self
     */
    public function reset(): self
    {
        $this->scope = null;
        $this->collection = null;
        $this->alias = null;
        $this->select = ['*'];
        $this->joins = [];
        $this->where = [];
        $this->params = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->having = [];
        $this->limit = null;
        $this->offset = null;
        $this->useKeys = null;
        $this->includeMetaId = true;
        return $this;
    }
    
    /**
     * Get query string for debugging
     * @return string
     */
    public function __toString(): string
    {
        return $this->build();
    }
}
```

**Acceptance Criteria**:
- [ ] Fluent interface works correctly
- [ ] SELECT with META().id auto-inclusion
- [ ] WHERE with AND/OR logic
- [ ] JOINs, LEFT JOINs work
- [ ] NEST and UNNEST for Couchbase arrays
- [ ] ANY/SATISFIES for array element queries
- [ ] ORDER BY, GROUP BY, HAVING
- [ ] LIMIT and OFFSET pagination
- [ ] Parameter binding with named params
- [ ] execute(), one(), scalar(), count() methods work

---

### Task 2.2: Create Query Builder Unit Tests

**File**: `/protected/tests/unit/components/database/N1qlQueryBuilderTest.php`

```php
<?php
/**
 * Unit tests for N1qlQueryBuilder
 */

class N1qlQueryBuilderTest extends CTestCase
{
    public function testBasicSelect()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->build();
        
        $this->assertStringContainsString('SELECT META().id AS _id, *', $query);
        $this->assertStringContainsString('FROM `openeyes`.`core`.`patient`', $query);
    }
    
    public function testSelectSpecificColumns()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->select('hos_num, nhs_num, dob')
            ->build();
        
        $this->assertStringContainsString('SELECT META().id AS _id, hos_num, nhs_num, dob', $query);
    }
    
    public function testWhereCondition()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->where('hos_num = $hosNum', ['hosNum' => '12345'])
            ->build();
        
        $this->assertStringContainsString('WHERE hos_num = $hosNum', $query);
        $this->assertEquals(['hosNum' => '12345'], $builder->getParams());
    }
    
    public function testMultipleWhereConditions()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->where('hos_num = $hosNum', ['hosNum' => '12345'])
            ->andWhere('active = $active', ['active' => true])
            ->build();
        
        $this->assertStringContainsString('WHERE hos_num = $hosNum AND active = $active', $query);
    }
    
    public function testOrWhere()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->where('hos_num = $hosNum', ['hosNum' => '12345'])
            ->orWhere('nhs_num = $nhsNum', ['nhsNum' => '9876543210'])
            ->build();
        
        $this->assertStringContainsString('WHERE hos_num = $hosNum OR nhs_num = $nhsNum', $query);
    }
    
    public function testWhereIn()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->whereIn('id', [1, 2, 3])
            ->build();
        
        $this->assertStringContainsString('WHERE id IN [$in0, $in1, $in2]', $query);
        $this->assertEquals(['in0' => 1, 'in1' => 2, 'in2' => 3], $builder->getParams());
    }
    
    public function testWhereBetween()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->whereBetween('dob', '1980-01-01', '1989-12-31')
            ->build();
        
        $this->assertStringContainsString('WHERE dob BETWEEN $betweenStart AND $betweenEnd', $query);
    }
    
    public function testJoin()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'episode', 'e')
            ->join('core', 'patient', 'e.patient_id = META(p).id', 'p')
            ->select('e.*, p.hos_num')
            ->build();
        
        $this->assertStringContainsString('JOIN `openeyes`.`core`.`patient` AS p ON e.patient_id = META(p).id', $query);
    }
    
    public function testNest()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient', 'p')
            ->nest('core', 'episode', 'episodes', 'e.patient_id = META(p).id')
            ->build();
        
        $this->assertStringContainsString('NEST `openeyes`.`core`.`episode` AS episodes ON e.patient_id = META(p).id', $query);
    }
    
    public function testUnnest()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('clinical', 'examination', 'e')
            ->unnest('e.elements.VisualAcuity.left_readings', 'r')
            ->select('e.event_id, r.*')
            ->build();
        
        $this->assertStringContainsString('UNNEST e.elements.VisualAcuity.left_readings AS r', $query);
    }
    
    public function testWhereAny()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('clinical', 'examination')
            ->whereAny('elements.VisualAcuity.left_readings', 'r', 'r.value < $threshold', ['threshold' => 0.5])
            ->build();
        
        $this->assertStringContainsString('ANY r IN elements.VisualAcuity.left_readings SATISFIES r.value < $threshold END', $query);
    }
    
    public function testOrderBy()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->orderBy('last_name')
            ->orderBy('first_name', 'DESC')
            ->build();
        
        $this->assertStringContainsString('ORDER BY last_name ASC, first_name DESC', $query);
    }
    
    public function testGroupByAndHaving()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'episode')
            ->select('firm_id, COUNT(*) as cnt')
            ->groupBy('firm_id')
            ->having('COUNT(*) > $minCount', ['minCount' => 10])
            ->build();
        
        $this->assertStringContainsString('GROUP BY firm_id', $query);
        $this->assertStringContainsString('HAVING COUNT(*) > $minCount', $query);
    }
    
    public function testLimitAndOffset()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->limit(20)
            ->offset(40)
            ->build();
        
        $this->assertStringContainsString('LIMIT 20', $query);
        $this->assertStringContainsString('OFFSET 40', $query);
    }
    
    public function testUseKeys()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->useKeys(['patient::1', 'patient::2'])
            ->build();
        
        $this->assertStringContainsString("USE KEYS ['patient::1', 'patient::2']", $query);
    }
    
    public function testCount()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        
        // Can't actually execute without Couchbase, but we can test the query building
        $builder->from('core', 'patient')
            ->where('active = $active', ['active' => true]);
        
        // Clone and check that count query is built correctly
        $countBuilder = clone $builder;
        $countBuilder->select = ['COUNT(*) AS cnt'];
        $countBuilder->orderBy = [];
        $countBuilder->includeMetaId = false;
        $query = $countBuilder->build();
        
        $this->assertStringContainsString('SELECT COUNT(*) AS cnt', $query);
        $this->assertStringNotContainsString('META().id', $query);
    }
    
    public function testReset()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $builder->from('core', 'patient')
            ->where('id = $id', ['id' => 1])
            ->limit(10);
        
        $builder->reset();
        
        $this->assertEmpty($builder->getParams());
    }
    
    public function testWhereNull()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->whereNull('date_of_death')
            ->build();
        
        $this->assertStringContainsString('WHERE date_of_death IS NULL', $query);
    }
    
    public function testWhereNotNull()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->whereNotNull('nhs_num')
            ->build();
        
        $this->assertStringContainsString('WHERE nhs_num IS NOT NULL', $query);
    }
    
    public function testWhereILike()
    {
        $builder = new \OE\Database\N1qlQueryBuilder('openeyes');
        $query = $builder
            ->from('core', 'patient')
            ->whereILike('contact.last_name', 'smi%')
            ->build();
        
        $this->assertStringContainsString('LOWER(contact.last_name) LIKE LOWER($ilike)', $query);
    }
}
```

**Acceptance Criteria**:
- [ ] All 20+ test methods pass
- [ ] Tests cover all query builder features
- [ ] Edge cases handled

---

### Task 2.3: Create Query Migration Helper

**File**: `/protected/components/database/QueryMigrationHelper.php`

```php
<?php
/**
 * Helper class for migrating SQL queries to N1QL
 */

namespace OE\Database;

class QueryMigrationHelper
{
    private $bucket = 'openeyes';
    private $collectionMap;
    
    public function __construct()
    {
        $mapFile = \Yii::getPathOfAlias('application.config') . '/couchbase-collection-map.php';
        if (file_exists($mapFile)) {
            $this->collectionMap = require($mapFile);
        } else {
            $this->collectionMap = $this->getDefaultMapping();
        }
    }
    
    /**
     * Get default table to scope/collection mapping
     * @return array
     */
    private function getDefaultMapping(): array
    {
        return [
            'core' => [
                'patient', 'contact', 'address', 'user', 'episode', 'event',
                'firm', 'site', 'institution', 'service_subspecialty_assignment',
            ],
            'clinical' => [
                'examination', 'diagnosis', 'allergy', 'medication', 'procedure',
            ],
            'booking' => [
                'operation', 'session', 'whiteboard', 'booking',
            ],
            'correspondence' => [
                'letter', 'message', 'document',
            ],
            'admin' => [
                'audit', 'setting', 'user_session',
            ],
            'reference' => [
                'specialty', 'subspecialty', 'disorder', 'drug', 'procedure_type',
            ],
        ];
    }
    
    /**
     * Get the scope for a given table
     * @param string $table Table name
     * @return string Scope name
     */
    public function getScopeForTable(string $table): string
    {
        $table = strtolower(preg_replace('/^(et_|ophtr|ophco|ophci)/', '', $table));
        
        foreach ($this->collectionMap as $scope => $tables) {
            if (in_array($table, $tables)) {
                return $scope;
            }
        }
        
        // Pattern-based mapping
        if (preg_match('/^et_ophciexamination_/', $table)) {
            return 'clinical';
        }
        if (preg_match('/^ophtr/', $table)) {
            return 'booking';
        }
        if (preg_match('/^ophco/', $table)) {
            return 'correspondence';
        }
        
        return 'core';
    }
    
    /**
     * Get the full keyspace for a table
     * @param string $table Table name
     * @return string Keyspace string
     */
    public function getKeyspace(string $table): string
    {
        $scope = $this->getScopeForTable($table);
        $collection = $this->getCollectionName($table);
        return "`{$this->bucket}`.`{$scope}`.`{$collection}`";
    }
    
    /**
     * Get collection name from table name
     * @param string $table MySQL table name
     * @return string Collection name
     */
    public function getCollectionName(string $table): string
    {
        // Remove common prefixes
        $collection = preg_replace('/^(et_ophciexamination_|et_|ophtr|ophco|ophci)/', '', $table);
        return strtolower($collection);
    }
    
    /**
     * Convert a simple SELECT query to N1QL
     * @param string $sql SQL query
     * @return string N1QL query
     */
    public function convertSimpleSelect(string $sql): string
    {
        $n1ql = $sql;
        
        // Extract table name and replace with keyspace
        if (preg_match('/FROM\s+[`]?(\w+)[`]?(\s+AS\s+(\w+))?/i', $sql, $matches)) {
            $table = $matches[1];
            $alias = $matches[3] ?? null;
            
            $keyspace = $this->getKeyspace($table);
            if ($alias) {
                $keyspace .= " AS {$alias}";
            }
            
            $n1ql = preg_replace(
                '/FROM\s+[`]?\w+[`]?(\s+AS\s+\w+)?/i',
                "FROM {$keyspace}",
                $n1ql
            );
        }
        
        // Add META().id to SELECT if selecting *
        if (preg_match('/SELECT\s+\*/i', $n1ql)) {
            $n1ql = preg_replace(
                '/SELECT\s+\*/i',
                'SELECT META().id AS _id, *',
                $n1ql
            );
        }
        
        // Convert MySQL-specific functions
        $n1ql = $this->convertFunctions($n1ql);
        
        // Convert parameter placeholders
        $n1ql = $this->convertParameters($n1ql);
        
        return $n1ql;
    }
    
    /**
     * Convert MySQL functions to N1QL equivalents
     * @param string $query Query string
     * @return string Converted query
     */
    public function convertFunctions(string $query): string
    {
        $conversions = [
            '/IFNULL\s*\(/i' => 'IFNULL(',
            '/COALESCE\s*\(/i' => 'IFNULL(',
            '/NOW\s*\(\)/i' => 'NOW_STR()',
            '/CURDATE\s*\(\)/i' => 'SUBSTR(NOW_STR(), 0, 10)',
            '/DATE_FORMAT\s*\(\s*([^,]+),\s*[\'"]%Y-%m-%d[\'"]\s*\)/i' => 'SUBSTR($1, 0, 10)',
            '/CONCAT\s*\(/i' => 'CONCAT(',
            '/GROUP_CONCAT\s*\(/i' => 'ARRAY_AGG(',
            '/DATEDIFF\s*\(\s*([^,]+),\s*([^)]+)\)/i' => 'DATE_DIFF_STR($1, $2, "day")',
        ];
        
        foreach ($conversions as $pattern => $replacement) {
            $query = preg_replace($pattern, $replacement, $query);
        }
        
        return $query;
    }
    
    /**
     * Convert MySQL parameter placeholders to N1QL named parameters
     * @param string $query Query string
     * @return string Converted query
     */
    public function convertParameters(string $query): string
    {
        // Convert :paramName to $paramName
        $query = preg_replace('/:(\w+)/', '\$$1', $query);
        
        // Convert ? placeholders to named params (p0, p1, etc.)
        $index = 0;
        $query = preg_replace_callback('/\?/', function() use (&$index) {
            return '$p' . ($index++);
        }, $query);
        
        return $query;
    }
    
    /**
     * Convert CDbCriteria to N1qlQueryBuilder
     * @param \CDbCriteria $criteria Yii criteria object
     * @param string $table Table name
     * @return N1qlQueryBuilder
     */
    public function criteriaToBuilder(\CDbCriteria $criteria, string $table): N1qlQueryBuilder
    {
        $builder = new N1qlQueryBuilder($this->bucket);
        
        $scope = $this->getScopeForTable($table);
        $collection = $this->getCollectionName($table);
        
        $builder->from($scope, $collection);
        
        // SELECT
        if ($criteria->select !== '*') {
            $builder->select($criteria->select);
        }
        
        // WHERE
        if (!empty($criteria->condition)) {
            $condition = $this->convertParameters($criteria->condition);
            $condition = $this->convertFunctions($condition);
            $builder->where($condition, $criteria->params ?: []);
        }
        
        // ORDER
        if (!empty($criteria->order)) {
            $orders = explode(',', $criteria->order);
            foreach ($orders as $order) {
                $parts = preg_split('/\s+/', trim($order));
                $column = $parts[0];
                $direction = $parts[1] ?? 'ASC';
                $builder->orderBy($column, $direction);
            }
        }
        
        // LIMIT
        if ($criteria->limit > 0) {
            $builder->limit($criteria->limit);
        }
        
        // OFFSET
        if ($criteria->offset > 0) {
            $builder->offset($criteria->offset);
        }
        
        return $builder;
    }
    
    /**
     * Create a new N1qlQueryBuilder for the given table
     * @param string $table Table name
     * @return N1qlQueryBuilder
     */
    public function createBuilder(string $table): N1qlQueryBuilder
    {
        $builder = new N1qlQueryBuilder($this->bucket);
        $scope = $this->getScopeForTable($table);
        $collection = $this->getCollectionName($table);
        
        return $builder->from($scope, $collection);
    }
}
```

**Acceptance Criteria**:
- [ ] Correct table to scope/collection mapping
- [ ] Simple SELECT conversion works
- [ ] MySQL functions converted to N1QL equivalents
- [ ] Parameter placeholders converted
- [ ] CDbCriteria conversion works

---

## Section 3: SQL to N1QL Translation Guide (Tasks 7-8)

### Task 3.1: Create Translation Guide Document

**File**: `/docs/migration-mariadb-to-couchbase/sql-to-n1ql-guide.md`

Create comprehensive documentation covering:
- Basic SELECT conversions
- JOIN translations
- Aggregation queries
- Subquery patterns
- Date/time functions
- String functions
- Array operations (Couchbase-specific)
- Pagination patterns

### Task 3.2: Create Collection Mapping Config

**File**: `/protected/config/couchbase-collection-map.php`

```php
<?php
/**
 * MySQL table to Couchbase scope/collection mapping
 */

return [
    'core' => [
        'patient',
        'contact', 
        'address',
        'user',
        'episode',
        'event',
        'firm',
        'site',
        'institution',
        'service_subspecialty_assignment',
        'specialty',
        'subspecialty',
    ],
    'clinical' => [
        'examination',
        'visual_acuity',
        'intraocular_pressure',
        'refraction',
        'diagnoses',
        'allergy',
        'medication',
    ],
    'booking' => [
        'operation',
        'session',
        'whiteboard',
        'booking',
        'theatre',
    ],
    'correspondence' => [
        'letter',
        'message',
        'document',
        'macro',
    ],
    'admin' => [
        'audit',
        'setting',
        'user_session',
        'auth_assignment',
    ],
    'reference' => [
        'disorder',
        'drug',
        'procedure_type',
        'common_ophthalmic_disorder',
    ],
];
```

---

## Section 4: Patient Search Migration (Tasks 9-11)

### Task 4.1: Create Couchbase Patient Search

**File**: `/protected/components/reports/CouchbasePatientSearch.php`

```php
<?php
/**
 * Patient search using Couchbase N1QL
 */

namespace OE\Reports;

use OE\Database\N1qlQueryBuilder;

class CouchbasePatientSearch
{
    /**
     * Search patients by various criteria
     * @param array $criteria Search criteria
     * @return array Search results
     */
    public function search(array $criteria): array
    {
        $builder = new N1qlQueryBuilder();
        $builder->from('core', 'patient')
            ->select('META().id AS _id, hos_num, nhs_num, dob, gender, date_of_death, is_deceased, contact, identifiers');
        
        // Hospital number - exact match
        if (!empty($criteria['hos_num'])) {
            $builder->where('hos_num = $hosNum', ['hosNum' => $criteria['hos_num']]);
        }
        
        // NHS number - exact match
        if (!empty($criteria['nhs_num'])) {
            $builder->where('nhs_num = $nhsNum', ['nhsNum' => $criteria['nhs_num']]);
        }
        
        // Last name - case-insensitive prefix match
        if (!empty($criteria['last_name'])) {
            $builder->whereILike('contact.last_name', $criteria['last_name'] . '%', 'lastName');
        }
        
        // First name - case-insensitive prefix match
        if (!empty($criteria['first_name'])) {
            $builder->whereILike('contact.first_name', $criteria['first_name'] . '%', 'firstName');
        }
        
        // Date of birth - exact match
        if (!empty($criteria['dob'])) {
            $builder->where('dob = $dob', ['dob' => $criteria['dob']]);
        }
        
        // Gender
        if (!empty($criteria['gender'])) {
            $builder->where('gender = $gender', ['gender' => $criteria['gender']]);
        }
        
        // Exclude deceased
        if (!empty($criteria['exclude_deceased'])) {
            $builder->whereNull('date_of_death');
        }
        
        // Default ordering
        $builder->orderBy('contact.last_name')
            ->orderBy('contact.first_name');
        
        // Pagination
        $limit = $criteria['limit'] ?? 50;
        $builder->limit($limit);
        
        if (!empty($criteria['offset'])) {
            $builder->offset($criteria['offset']);
        }
        
        return $builder->execute();
    }
    
    /**
     * Find patient by hospital number
     * @param string $hosNum Hospital number
     * @return array|null
     */
    public function findByHosNum(string $hosNum): ?array
    {
        $results = $this->search(['hos_num' => $hosNum, 'limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Find patient by NHS number
     * @param string $nhsNum NHS number
     * @return array|null
     */
    public function findByNhsNum(string $nhsNum): ?array
    {
        $results = $this->search(['nhs_num' => $nhsNum, 'limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Search patients born on a specific date
     * @param string $dob Date of birth (YYYY-MM-DD)
     * @param int $limit Max results
     * @return array
     */
    public function findByDob(string $dob, int $limit = 50): array
    {
        return $this->search(['dob' => $dob, 'limit' => $limit]);
    }
    
    /**
     * Full name search (both first and last name)
     * @param string $name Name to search
     * @param int $limit Max results
     * @return array
     */
    public function searchByName(string $name, int $limit = 50): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);
        
        $criteria = ['limit' => $limit];
        
        if (count($parts) === 1) {
            // Single term - search both first and last name
            $builder = new N1qlQueryBuilder();
            $builder->from('core', 'patient')
                ->select('META().id AS _id, hos_num, nhs_num, dob, gender, contact')
                ->where('(LOWER(contact.last_name) LIKE LOWER($term) OR LOWER(contact.first_name) LIKE LOWER($term))', 
                    ['term' => $parts[0] . '%'])
                ->orderBy('contact.last_name')
                ->orderBy('contact.first_name')
                ->limit($limit);
            
            return $builder->execute();
        }
        
        // Two terms - assume first_name last_name
        $criteria['first_name'] = $parts[0];
        $criteria['last_name'] = $parts[1];
        
        return $this->search($criteria);
    }
    
    /**
     * Get count of patients matching criteria
     * @param array $criteria Search criteria
     * @return int
     */
    public function count(array $criteria): int
    {
        $builder = new N1qlQueryBuilder();
        $builder->from('core', 'patient');
        
        if (!empty($criteria['hos_num'])) {
            $builder->where('hos_num = $hosNum', ['hosNum' => $criteria['hos_num']]);
        }
        if (!empty($criteria['nhs_num'])) {
            $builder->where('nhs_num = $nhsNum', ['nhsNum' => $criteria['nhs_num']]);
        }
        if (!empty($criteria['last_name'])) {
            $builder->whereILike('contact.last_name', $criteria['last_name'] . '%', 'lastName');
        }
        
        return $builder->count();
    }
}
```

**Acceptance Criteria**:
- [ ] Search by hos_num works
- [ ] Search by nhs_num works
- [ ] Name search (case-insensitive) works
- [ ] DOB search works
- [ ] Pagination works
- [ ] Count method works

---

### Task 4.2: Update PatientService (Modify existing file)

Add Couchbase search method to `/protected/services/PatientService.php`

### Task 4.3: Create Patient Search Tests

**File**: `/protected/tests/unit/components/reports/CouchbasePatientSearchTest.php`

---

## Section 5-8: Module Query Migrations

(Detailed implementations for Episode, Event, Examination, Operation Booking, and Correspondence modules follow similar patterns to Section 4)

---

## Section 9: Feature Flags & Testing (Tasks 24-28)

### Task 9.1: Add Query Feature Flags

**File**: `/protected/config/core/common.php` (MODIFY)

Add to params array:
```php
// Phase 6: Query Migration Feature Flags
'enable_couchbase_queries' => strtolower(getenv('ENABLE_COUCHBASE_QUERIES') ?: '') === 'true',
'couchbase_query_collections' => [], // Collections using Couchbase queries
```

### Task 9.2: Create Query Benchmark Command

**File**: `/protected/commands/QueryBenchmarkCommand.php`

### Task 9.3: Create Integration Tests

**File**: `/protected/tests/integration/CouchbaseQueryIntegrationTest.php`

### Task 9.4: Create Query Migration Verification Command

**File**: `/protected/commands/QueryMigrationVerifyCommand.php`

### Task 9.5: Create Implementation Summary

**File**: `/docs/migration-mariadb-to-couchbase/PHASE-06-IMPLEMENTATION-SUMMARY.md`

---

## Testing Criteria

### Query Correctness
- [ ] All migrated queries return same results as SQL equivalents
- [ ] NULL handling matches MySQL behavior
- [ ] Date/time comparisons work correctly
- [ ] Case sensitivity handled appropriately

### Performance Benchmarks
- [ ] Simple queries: < 50ms average
- [ ] Complex queries: < 500ms average
- [ ] Search queries: < 100ms average
- [ ] Aggregation queries: < 200ms average

### Integration Tests
- [ ] Feature flags work correctly
- [ ] Graceful fallback to MariaDB when Couchbase unavailable
- [ ] No breaking changes to existing functionality
- [ ] Error handling doesn't expose system details

---

## Rollback Plan

1. **Immediate Rollback**:
   ```bash
   export ENABLE_COUCHBASE_QUERIES=false
   ```

2. **All queries automatically fall back to MariaDB**

3. **No data changes required**

4. **Zero downtime rollback**

---

## Definition of Done

- [ ] Query analyzer identifies all SQL queries
- [ ] N1QL Query Builder functional with all features
- [ ] All unit tests passing (>80% coverage)
- [ ] Translation guide documentation complete
- [ ] Patient search queries migrated
- [ ] Episode/Event queries migrated
- [ ] Examination module queries migrated
- [ ] Operation booking queries migrated
- [ ] Correspondence queries migrated
- [ ] All feature flags implemented
- [ ] Performance benchmarks completed and documented
- [ ] Integration tests passing
- [ ] Documentation complete
- [ ] Code reviewed and approved

---

## Estimated Totals

| Metric | Value |
|--------|-------|
| New Files | 18 |
| Modified Files | 3 |
| Lines of Code | ~2,500 |
| Unit Tests | ~50 |
| Integration Tests | ~15 |

---

*Phase 6 Sign-off Required:*
- [ ] Technical Lead
- [ ] QA Lead

*Estimated Duration: 3-4 weeks*
