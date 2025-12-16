#!/usr/bin/env php
<?php
/**
 * Professional Database Query Optimization Tool
 * 
 * A comprehensive tool for analyzing, profiling, and optimizing database queries
 * 
 * Features:
 * - Query profiling with actual execution time measurement
 * - Query rewriting with optimized SQL generation
 * - Index analysis with CREATE INDEX statement generation
 * - Performance comparison (before/after)
 * - Query pattern detection (N+1, repeated queries)
 * - Comprehensive reporting
 * 
 * Usage:
 *   php optimize.php profile "SELECT * FROM users WHERE id = 1"
 *   php optimize.php optimize "SELECT * FROM users WHERE name LIKE '%john%'"
 *   php optimize.php analyze-table users
 *   php optimize.php compare "SELECT * FROM users" "SELECT id, name FROM users WHERE id = 1"
 *   php optimize.php generate-indexes users
 *   php optimize.php report example-queries.sql report.html
 */

class DatabaseOptimizer {
    private $pdo;
    private $config;
    private $queryHistory = [];
    private $resultsDir;
    
    public function __construct() {
        $this->loadConfig();
        $this->connect();
        $this->resultsDir = __DIR__ . '/results';
        if (!is_dir($this->resultsDir)) {
            mkdir($this->resultsDir, 0755, true);
        }
    }
    
    private function loadConfig() {
        $configFile = __DIR__ . '/config.php';
        if (!file_exists($configFile)) {
            $exampleFile = __DIR__ . '/config.example.php';
            if (file_exists($exampleFile)) {
                die("ERROR: config.php not found. Please copy config.example.php to config.php and update your credentials.\n");
            }
            die("ERROR: No configuration file found.\n");
        }
        $this->config = require $configFile;
    }
    
    private function connect() {
        try {
            $db = $this->config['database'];
            $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset={$db['charset']}";
            $this->pdo = new PDO($dsn, $db['username'], $db['password'], $this->config['options']);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage() . "\n");
        }
    }
    
    /**
     * Profile a query - measure actual execution time
     */
    public function profileQuery($query, $iterations = 5) {
        echo "\n=== Query Profiling ===\n\n";
        echo "Query: " . substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '') . "\n";
        echo "Iterations: {$iterations}\n\n";
        
        // Warm up
        try {
            $this->pdo->exec($query);
        } catch (PDOException $e) {
            echo "ERROR: Query execution failed - " . $e->getMessage() . "\n";
            return null;
        }
        
        $times = [];
        $rowCounts = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            try {
                $stmt = $this->pdo->query($query);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $rowCount = count($results);
            } catch (PDOException $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
                return null;
            }
            $end = microtime(true);
            
            $executionTime = ($end - $start) * 1000; // Convert to milliseconds
            $times[] = $executionTime;
            $rowCounts[] = $rowCount;
        }
        
        $avgTime = array_sum($times) / count($times);
        $minTime = min($times);
        $maxTime = max($times);
        $totalTime = array_sum($times);
        
        echo "Execution Statistics:\n";
        echo str_repeat("-", 60) . "\n";
        printf("Average Time:  %10.2f ms\n", $avgTime);
        printf("Min Time:      %10.2f ms\n", $minTime);
        printf("Max Time:      %10.2f ms\n", $maxTime);
        printf("Total Time:    %10.2f ms\n", $totalTime);
        printf("Rows Returned: %10d\n", $rowCounts[0]);
        echo "\n";
        
        // Get EXPLAIN plan
        $this->explainQuery($query);
        
        // Performance assessment
        $this->assessPerformance($avgTime, $rowCounts[0]);
        
        return [
            'query' => $query,
            'avg_time' => $avgTime,
            'min_time' => $minTime,
            'max_time' => $maxTime,
            'total_time' => $totalTime,
            'rows' => $rowCounts[0],
            'iterations' => $iterations
        ];
    }
    
    /**
     * Optimize a query - generate optimized version
     */
    public function optimizeQuery($query) {
        echo "\n=== Query Optimization ===\n\n";
        echo "Original Query:\n";
        echo $query . "\n\n";
        
        $originalProfile = $this->profileQuery($query, 3);
        if (!$originalProfile) {
            return;
        }
        
        $optimizations = $this->generateOptimizations($query);
        
        if (empty($optimizations)) {
            echo "✓ Query appears to be well optimized.\n\n";
            return;
        }
        
        echo "=== Optimization Recommendations ===\n\n";
        
        foreach ($optimizations as $i => $opt) {
            echo "Recommendation " . ($i + 1) . ": " . $opt['issue'] . "\n";
            echo "Optimized Query:\n";
            echo $opt['optimized_query'] . "\n";
            
            if (!empty($opt['index_sql'])) {
                echo "\nRequired Index:\n";
                echo $opt['index_sql'] . "\n";
            }
            
            // Test optimized query if possible
            if (!empty($opt['optimized_query']) && $opt['optimized_query'] !== $query) {
                echo "\nTesting optimized query...\n";
                $optimizedProfile = $this->profileQuery($opt['optimized_query'], 3);
                
                if ($optimizedProfile && $originalProfile) {
                    $improvement = (($originalProfile['avg_time'] - $optimizedProfile['avg_time']) / $originalProfile['avg_time']) * 100;
                    echo "\nPerformance Improvement: " . number_format($improvement, 2) . "% faster\n";
                    echo "Time Saved: " . number_format($originalProfile['avg_time'] - $optimizedProfile['avg_time'], 2) . " ms\n";
                }
            }
            
            echo "\n" . str_repeat("-", 80) . "\n\n";
        }
    }
    
    /**
     * Generate optimized versions of a query
     */
    private function generateOptimizations($query) {
        $optimizations = [];
        $queryUpper = strtoupper($query);
        $originalQuery = $query;
        
        // 1. Replace SELECT * with specific columns
        if (preg_match('/SELECT\s+\*\s+FROM\s+(\w+)/i', $query, $matches)) {
            $table = $matches[1];
            try {
                $stmt = $this->pdo->query("DESCRIBE `{$table}`");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($columns)) {
                    $columnList = implode(', ', array_slice($columns, 0, 5)); // First 5 columns
                    $optimized = preg_replace('/SELECT\s+\*/i', "SELECT {$columnList}", $query);
                    $optimizations[] = [
                        'issue' => 'SELECT * returns unnecessary columns',
                        'optimized_query' => $optimized,
                        'index_sql' => null
                    ];
                }
            } catch (PDOException $e) {
                // Table doesn't exist or error
            }
        }
        
        // 2. Fix LIKE with leading wildcard
        if (preg_match('/WHERE\s+(\w+)\s+LIKE\s+[\'"]%([^\'"]+)%[\'"]/i', $query, $matches)) {
            $column = $matches[1];
            $value = $matches[2];
            $optimized = preg_replace(
                '/WHERE\s+' . preg_quote($column, '/') . '\s+LIKE\s+[\'"]%' . preg_quote($value, '/') . '%[\'"]/i',
                "WHERE {$column} LIKE '%{$value}%'",
                $query
            );
            $optimizations[] = [
                'issue' => "LIKE with leading wildcard on '{$column}' prevents index usage",
                'optimized_query' => $optimized,
                'index_sql' => "CREATE INDEX idx_{$column}_like ON " . $this->extractTableName($query) . " ({$column});",
                'note' => 'Consider using FULLTEXT index for better LIKE performance'
            ];
        }
        
        // 3. Add missing LIMIT
        if (stripos($query, 'SELECT') !== false && stripos($query, 'LIMIT') === false && stripos($query, 'COUNT') === false) {
            $optimized = rtrim(trim($query), ';') . ' LIMIT 100;';
            $optimizations[] = [
                'issue' => 'Query missing LIMIT clause - may return too many rows',
                'optimized_query' => $optimized,
                'index_sql' => null
            ];
        }
        
        // 4. Fix functions in WHERE clause
        if (preg_match('/WHERE\s+(YEAR|MONTH|DAY|DATE|UPPER|LOWER)\s*\((\w+)\)/i', $query, $matches)) {
            $func = $matches[1];
            $column = $matches[2];
            $table = $this->extractTableName($query);
            
            $optimizations[] = [
                'issue' => "Function {$func}() on column '{$column}' prevents index usage",
                'optimized_query' => $query, // Keep original, but suggest index
                'index_sql' => "CREATE INDEX idx_{$column}_func ON {$table} ({$column});",
                'note' => "Rewrite query to avoid function: WHERE {$column} >= '2024-01-01' AND {$column} < '2025-01-01'"
            ];
        }
        
        // 5. Optimize OR conditions with UNION
        if (preg_match('/WHERE.*\b(\w+)\s*=\s*[\'"]?(\w+)[\'"]?\s+OR\s+\1\s*=\s*[\'"]?(\w+)[\'"]?/i', $query, $matches)) {
            $column = $matches[1];
            $value1 = $matches[2];
            $value2 = $matches[3];
            $baseQuery = preg_replace('/WHERE.*$/i', '', $query);
            $optimized = $baseQuery . " WHERE {$column} = '{$value1}' UNION " . $baseQuery . " WHERE {$column} = '{$value2}';";
            $optimizations[] = [
                'issue' => "OR condition on '{$column}' may prevent index usage",
                'optimized_query' => $optimized,
                'index_sql' => "CREATE INDEX idx_{$column} ON " . $this->extractTableName($query) . " ({$column});"
            ];
        }
        
        // 6. Add index suggestions based on WHERE and JOIN
        $indexSuggestions = $this->suggestIndexesForQuery($query);
        foreach ($indexSuggestions as $index) {
            $optimizations[] = [
                'issue' => "Missing index on '{$index['column']}' used in WHERE/JOIN",
                'optimized_query' => $query,
                'index_sql' => $index['sql']
            ];
        }
        
        return $optimizations;
    }
    
    /**
     * Extract table name from query
     */
    private function extractTableName($query) {
        if (preg_match('/FROM\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        if (preg_match('/UPDATE\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        if (preg_match('/INSERT\s+INTO\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        return 'table_name';
    }
    
    /**
     * Suggest indexes for a query
     */
    private function suggestIndexesForQuery($query) {
        $suggestions = [];
        $table = $this->extractTableName($query);
        
        // WHERE clause columns
        if (preg_match_all('/WHERE\s+(\w+)\s*[=<>!]/i', $query, $matches)) {
            foreach ($matches[1] as $column) {
                if (!$this->indexExists($table, $column)) {
                    $suggestions[] = [
                        'column' => $column,
                        'sql' => "CREATE INDEX idx_{$column} ON `{$table}` (`{$column}`);"
                    ];
                }
            }
        }
        
        // JOIN columns
        if (preg_match_all('/JOIN\s+\w+\s+ON\s+\w+\.(\w+)\s*=\s*\w+\.(\w+)/i', $query, $matches)) {
            foreach (array_merge($matches[1], $matches[2]) as $column) {
                if (!$this->indexExists($table, $column)) {
                    $suggestions[] = [
                        'column' => $column,
                        'sql' => "CREATE INDEX idx_{$column} ON `{$table}` (`{$column}`);"
                    ];
                }
            }
        }
        
        // ORDER BY columns
        if (preg_match('/ORDER\s+BY\s+(\w+)/i', $query, $matches)) {
            $column = $matches[1];
            if (!$this->indexExists($table, $column)) {
                $suggestions[] = [
                    'column' => $column,
                    'sql' => "CREATE INDEX idx_{$column}_order ON `{$table}` (`{$column}`);"
                ];
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Check if index exists on column
     */
    private function indexExists($table, $column) {
        try {
            $stmt = $this->pdo->query("SHOW INDEXES FROM `{$table}` WHERE Column_name = '{$column}'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Analyze table structure and generate index recommendations
     */
    public function analyzeTable($tableName) {
        echo "\n=== Table Analysis: {$tableName} ===\n\n";
        
        try {
            // Get table structure
            $stmt = $this->pdo->query("DESCRIBE `{$tableName}`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get existing indexes
            $stmt = $this->pdo->query("SHOW INDEXES FROM `{$tableName}`");
            $existingIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get table stats
            $stmt = $this->pdo->query("SELECT COUNT(*) as row_count FROM `{$tableName}`");
            $rowCount = $stmt->fetch(PDO::FETCH_ASSOC)['row_count'];
            
            echo "Table Statistics:\n";
            echo "  Rows: " . number_format($rowCount) . "\n";
            echo "  Columns: " . count($columns) . "\n";
            echo "  Existing Indexes: " . count(array_unique(array_column($existingIndexes, 'Key_name'))) . "\n\n";
            
            echo "Column Analysis:\n";
            echo str_repeat("-", 80) . "\n";
            printf("%-20s %-15s %-10s %-10s %-20s\n", "Column", "Type", "Null", "Key", "Recommendation");
            echo str_repeat("-", 80) . "\n";
            
            $indexedColumns = array_unique(array_column($existingIndexes, 'Column_name'));
            $recommendations = [];
            
            foreach ($columns as $col) {
                $name = $col['Field'];
                $type = $col['Type'];
                $null = $col['Null'];
                $key = $col['Key'] ?: '-';
                $recommendation = '-';
                
                // Generate recommendations
                if (!in_array($name, $indexedColumns)) {
                    if (preg_match('/_id$/', $name) && stripos($type, 'int') !== false) {
                        $recommendation = "INDEX (FK)";
                        $recommendations[] = "CREATE INDEX idx_{$name} ON `{$tableName}` (`{$name}`);";
                    } elseif (in_array(strtolower($name), ['email', 'username', 'name', 'slug'])) {
                        $recommendation = "INDEX (search)";
                        $recommendations[] = "CREATE INDEX idx_{$name} ON `{$tableName}` (`{$name}`);";
                    } elseif (in_array(strtolower($name), ['status', 'type', 'category'])) {
                        $recommendation = "INDEX (filter)";
                        $recommendations[] = "CREATE INDEX idx_{$name} ON `{$tableName}` (`{$name}`);";
                    } elseif (preg_match('/_(at|date|time)$/', $name)) {
                        $recommendation = "INDEX (date)";
                        $recommendations[] = "CREATE INDEX idx_{$name} ON `{$tableName}` (`{$name}`);";
                    }
                }
                
                printf("%-20s %-15s %-10s %-10s %-20s\n", 
                    substr($name, 0, 18), 
                    substr($type, 0, 13), 
                    $null, 
                    $key,
                    $recommendation
                );
            }
            
            echo "\n";
            
            if (!empty($recommendations)) {
                echo "=== Recommended Indexes ===\n\n";
                foreach ($recommendations as $i => $sql) {
                    echo ($i + 1) . ". " . $sql . "\n";
                }
                
                // Save to file
                $indexFile = $this->resultsDir . "/indexes_{$tableName}_" . date('Y-m-d_His') . ".sql";
                file_put_contents($indexFile, "-- Index recommendations for table: {$tableName}\n");
                file_put_contents($indexFile, "-- Generated: " . date('Y-m-d H:i:s') . "\n\n", FILE_APPEND);
                foreach ($recommendations as $sql) {
                    file_put_contents($indexFile, $sql . "\n", FILE_APPEND);
                }
                echo "\n✓ Index recommendations saved to: {$indexFile}\n";
            } else {
                echo "✓ Table appears to have appropriate indexes.\n";
            }
            
        } catch (PDOException $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Generate CREATE INDEX statements for a table
     */
    public function generateIndexes($tableName) {
        $this->analyzeTable($tableName);
    }
    
    /**
     * Compare two queries
     */
    public function compareQueries($query1, $query2) {
        echo "\n=== Query Comparison ===\n\n";
        
        echo "Query 1 (Original):\n";
        echo $query1 . "\n\n";
        
        echo "Query 2 (Optimized):\n";
        echo $query2 . "\n\n";
        
        $profile1 = $this->profileQuery($query1, 5);
        echo "\n" . str_repeat("=", 80) . "\n\n";
        $profile2 = $this->profileQuery($query2, 5);
        
        if ($profile1 && $profile2) {
            echo "\n=== Comparison Results ===\n\n";
            $improvement = (($profile1['avg_time'] - $profile2['avg_time']) / $profile1['avg_time']) * 100;
            $timeSaved = $profile1['avg_time'] - $profile2['avg_time'];
            
            printf("Query 1 Average Time: %10.2f ms\n", $profile1['avg_time']);
            printf("Query 2 Average Time: %10.2f ms\n", $profile2['avg_time']);
            printf("Improvement:          %10.2f%%\n", $improvement);
            printf("Time Saved:           %10.2f ms\n", $timeSaved);
            
            if ($improvement > 0) {
                echo "\n✓ Query 2 is " . number_format($improvement, 2) . "% faster\n";
            } else {
                echo "\n⚠ Query 1 is " . number_format(abs($improvement), 2) . "% faster\n";
            }
        }
    }
    
    /**
     * Show EXPLAIN plan
     */
    public function explainQuery($query) {
        try {
            $explainQuery = "EXPLAIN " . $query;
            $stmt = $this->pdo->prepare($explainQuery);
            $stmt->execute();
            $plan = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($plan)) {
                return;
            }
            
            echo "EXPLAIN Plan:\n";
            $headers = array_keys($plan[0]);
            printf("%-5s", "id");
            foreach ($headers as $header) {
                if ($header !== 'id') {
                    printf("%-20s", substr($header, 0, 18));
                }
            }
            echo "\n" . str_repeat("-", 120) . "\n";
            
            foreach ($plan as $row) {
                printf("%-5s", $row['id'] ?? '');
                foreach ($headers as $header) {
                    if ($header !== 'id') {
                        $value = $row[$header] ?? '';
                        printf("%-20s", substr($value, 0, 18));
                    }
                }
                echo "\n";
            }
            echo "\n";
            
        } catch (PDOException $e) {
            // Silent fail for explain
        }
    }
    
    /**
     * Assess performance
     */
    private function assessPerformance($avgTime, $rowCount) {
        echo "Performance Assessment:\n";
        echo str_repeat("-", 60) . "\n";
        
        if ($avgTime < 10) {
            echo "✓ Excellent performance (< 10ms)\n";
        } elseif ($avgTime < 50) {
            echo "✓ Good performance (< 50ms)\n";
        } elseif ($avgTime < 200) {
            echo "⚠ Acceptable performance (< 200ms)\n";
        } else {
            echo "⚠ Poor performance (> 200ms) - Consider optimization\n";
        }
        
        if ($rowCount > 10000 && $avgTime > 100) {
            echo "⚠ Large result set ({$rowCount} rows) with slow execution\n";
        }
        
        echo "\n";
    }
    
    /**
     * Generate HTML report
     */
    public function generateReport($inputFile, $outputFile = null) {
        if (!file_exists($inputFile)) {
            die("Error: File not found: {$inputFile}\n");
        }
        
        $content = file_get_contents($inputFile);
        preg_match_all('/SELECT.*?;/is', $content, $matches);
        $queries = empty($matches[0]) ? [trim($content)] : $matches[0];
        
        if (!$outputFile) {
            $outputFile = $this->resultsDir . '/report_' . date('Y-m-d_His') . '.html';
        }
        
        $html = "<!DOCTYPE html>\n<html><head><title>Query Optimization Report</title>\n";
        $html .= "<style>body{font-family:Arial;margin:20px;}table{border-collapse:collapse;width:100%;}";
        $html .= "th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#4CAF50;color:white;}";
        $html .= ".warning{color:orange;}.success{color:green;}.error{color:red;}</style></head><body>\n";
        $html .= "<h1>Database Query Optimization Report</h1>\n";
        $html .= "<p>Generated: " . date('Y-m-d H:i:s') . "</p>\n";
        
        foreach ($queries as $i => $query) {
            $html .= "<h2>Query " . ($i + 1) . "</h2>\n";
            $html .= "<pre>" . htmlspecialchars($query) . "</pre>\n";
            
            $profile = $this->profileQuery($query, 3);
            if ($profile) {
                $html .= "<table><tr><th>Metric</th><th>Value</th></tr>\n";
                $html .= "<tr><td>Average Time</td><td>" . number_format($profile['avg_time'], 2) . " ms</td></tr>\n";
                $html .= "<tr><td>Rows Returned</td><td>" . $profile['rows'] . "</td></tr>\n";
                $html .= "</table>\n";
            }
            
            $optimizations = $this->generateOptimizations($query);
            if (!empty($optimizations)) {
                $html .= "<h3>Optimization Recommendations</h3>\n<ul>\n";
                foreach ($optimizations as $opt) {
                    $html .= "<li>" . htmlspecialchars($opt['issue']) . "</li>\n";
                    if (!empty($opt['index_sql'])) {
                        $html .= "<pre>" . htmlspecialchars($opt['index_sql']) . "</pre>\n";
                    }
                }
                $html .= "</ul>\n";
            }
        }
        
        $html .= "</body></html>";
        file_put_contents($outputFile, $html);
        echo "\n✓ Report generated: {$outputFile}\n";
    }
    
    /**
     * Analyze queries from file
     */
    public function analyzeFile($filePath) {
        if (!file_exists($filePath)) {
            die("Error: File not found: {$filePath}\n");
        }
        
        $content = file_get_contents($filePath);
        preg_match_all('/SELECT.*?;/is', $content, $matches);
        $queries = empty($matches[0]) ? [trim($content)] : $matches[0];
        
        foreach ($queries as $i => $query) {
            echo "\n" . str_repeat("=", 80) . "\n";
            echo "Query " . ($i + 1) . "\n";
            echo str_repeat("=", 80) . "\n";
            $this->optimizeQuery($query);
        }
    }
}

// CLI Interface
if (php_sapi_name() === 'cli') {
    $optimizer = new DatabaseOptimizer();
    
    if ($argc < 2) {
        echo "Professional Database Query Optimization Tool\n\n";
        echo "Usage:\n";
        echo "  php optimize.php profile \"<SQL query>\" [iterations]  - Profile query execution time\n";
        echo "  php optimize.php optimize \"<SQL query>\"              - Generate optimized query\n";
        echo "  php optimize.php analyze-table <table>                - Analyze table structure\n";
        echo "  php optimize.php generate-indexes <table>             - Generate CREATE INDEX statements\n";
        echo "  php optimize.php compare \"<query1>\" \"<query2>\"       - Compare two queries\n";
        echo "  php optimize.php analyze <query_file.sql>             - Analyze queries from file\n";
        echo "  php optimize.php report <input.sql> [output.html]    - Generate HTML report\n";
        echo "\n";
        exit(1);
    }
    
    $command = $argv[1];
    
    switch ($command) {
        case 'profile':
            if (!isset($argv[2])) {
                die("Error: Please provide a SQL query.\n");
            }
            $iterations = isset($argv[3]) ? (int)$argv[3] : 5;
            $optimizer->profileQuery($argv[2], $iterations);
            break;
            
        case 'optimize':
            if (!isset($argv[2])) {
                die("Error: Please provide a SQL query.\n");
            }
            $optimizer->optimizeQuery($argv[2]);
            break;
            
        case 'analyze-table':
        case 'analyze_table':
            if (!isset($argv[2])) {
                die("Error: Please provide a table name.\n");
            }
            $optimizer->analyzeTable($argv[2]);
            break;
            
        case 'generate-indexes':
        case 'generate_indexes':
            if (!isset($argv[2])) {
                die("Error: Please provide a table name.\n");
            }
            $optimizer->generateIndexes($argv[2]);
            break;
            
        case 'compare':
            if (!isset($argv[3])) {
                die("Error: Please provide two queries to compare.\n");
            }
            $optimizer->compareQueries($argv[2], $argv[3]);
            break;
            
        case 'analyze':
            if (!isset($argv[2])) {
                die("Error: Please provide a query file path.\n");
            }
            $optimizer->analyzeFile($argv[2]);
            break;
            
        case 'report':
            if (!isset($argv[2])) {
                die("Error: Please provide an input file.\n");
            }
            $outputFile = isset($argv[3]) ? $argv[3] : null;
            $optimizer->generateReport($argv[2], $outputFile);
            break;
            
        default:
            die("Unknown command: {$command}\n");
    }
}

?>
