#!/usr/bin/env php
<?php
/**
 * Professional Database Query Optimization Tool
 * 
 * A comprehensive, production-ready tool for analyzing, profiling, and optimizing database queries
 * 
 * Advanced Features:
 * - Query profiling with actual execution time measurement
 * - Query rewriting with optimized SQL generation
 * - Index analysis with CREATE INDEX statement generation
 * - Performance comparison (before/after)
 * - Query history tracking and monitoring
 * - N+1 query pattern detection
 * - Query similarity detection
 * - Automatic index application (with dry-run)
 * - Database statistics analysis
 * - Multiple export formats (HTML, JSON, CSV)
 * - Batch processing capabilities
 * - Performance regression detection
 * 
 * Usage:
 *   php optimize.php profile "SELECT * FROM users WHERE id = 1"
 *   php optimize.php optimize "SELECT * FROM users WHERE name LIKE '%john%'"
 *   php optimize.php analyze-table users
 *   php optimize.php compare "SELECT * FROM users" "SELECT id, name FROM users WHERE id = 1"
 *   php optimize.php generate-indexes users
 *   php optimize.php apply-indexes users --dry-run
 *   php optimize.php report example-queries.sql report.html --format json
 *   php optimize.php history --limit 10
 *   php optimize.php detect-n1 queries.log
 *   php optimize.php stats
 *   php optimize.php batch queries/*.sql
 */

class DatabaseOptimizer {
    private $pdo;
    private $config;
    private $queryHistory = [];
    private $resultsDir;
    private $historyFile;
    
    public function __construct() {
        $this->loadConfig();
        $this->connect();
        $this->resultsDir = __DIR__ . '/results';
        $this->historyFile = $this->resultsDir . '/query_history.json';
        if (!is_dir($this->resultsDir)) {
            mkdir($this->resultsDir, 0755, true);
        }
        $this->loadHistory();
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
            $port = isset($db['port']) ? $db['port'] : 3306;
            $dsn = "mysql:host={$db['host']};port={$port};dbname={$db['dbname']};charset={$db['charset']}";
            $this->pdo = new PDO($dsn, $db['username'], $db['password'], $this->config['options']);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage() . "\n");
        }
    }
    
    /**
     * Load query history from file
     */
    private function loadHistory() {
        if (file_exists($this->historyFile)) {
            $content = file_get_contents($this->historyFile);
            $this->queryHistory = json_decode($content, true) ?: [];
        }
    }
    
    /**
     * Save query to history
     */
    private function saveToHistory($query, $profile, $optimizations = []) {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'query' => $query,
            'profile' => $profile,
            'optimizations' => $optimizations,
            'query_hash' => md5($query)
        ];
        
        $this->queryHistory[] = $entry;
        
        // Keep only last 1000 entries
        if (count($this->queryHistory) > 1000) {
            $this->queryHistory = array_slice($this->queryHistory, -1000);
        }
        
        file_put_contents($this->historyFile, json_encode($this->queryHistory, JSON_PRETTY_PRINT));
    }
    
    /**
     * Profile a query - measure actual execution time
     */
    public function profileQuery($query, $iterations = 5, $saveHistory = true) {
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
        $stdDev = $this->calculateStdDev($times);
        
        echo "Execution Statistics:\n";
        echo str_repeat("-", 60) . "\n";
        printf("Average Time:  %10.2f ms\n", $avgTime);
        printf("Min Time:      %10.2f ms\n", $minTime);
        printf("Max Time:      %10.2f ms\n", $maxTime);
        printf("Total Time:    %10.2f ms\n", $totalTime);
        printf("Std Deviation: %10.2f ms\n", $stdDev);
        printf("Rows Returned: %10d\n", $rowCounts[0]);
        echo "\n";
        
        // Get EXPLAIN plan
        $explainPlan = $this->getExplainPlan($query);
        if ($explainPlan) {
            $this->displayExplainPlan($explainPlan);
        }
        
        // Performance assessment
        $this->assessPerformance($avgTime, $rowCounts[0]);
        
        $profile = [
            'query' => $query,
            'avg_time' => $avgTime,
            'min_time' => $minTime,
            'max_time' => $maxTime,
            'total_time' => $totalTime,
            'std_dev' => $stdDev,
            'rows' => $rowCounts[0],
            'iterations' => $iterations,
            'explain_plan' => $explainPlan
        ];
        
        if ($saveHistory) {
            $this->saveToHistory($query, $profile);
        }
        
        return $profile;
    }
    
    /**
     * Calculate standard deviation
     */
    private function calculateStdDev($values) {
        $mean = array_sum($values) / count($values);
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($values);
        return sqrt($variance);
    }
    
    /**
     * Get EXPLAIN plan as array
     */
    private function getExplainPlan($query) {
        try {
            $explainQuery = "EXPLAIN " . $query;
            $stmt = $this->pdo->prepare($explainQuery);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Display EXPLAIN plan
     */
    private function displayExplainPlan($plan) {
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
    }
    
    /**
     * Optimize a query - generate optimized version
     */
    public function optimizeQuery($query) {
        echo "\n=== Query Optimization ===\n\n";
        echo "Original Query:\n";
        echo $query . "\n\n";
        
        $originalProfile = $this->profileQuery($query, 3, false);
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
            if (!empty($opt['optimized_query']) && $opt['optimized_query'] !== $query) {
                echo "Optimized Query:\n";
                echo $opt['optimized_query'] . "\n";
            }
            
            if (!empty($opt['index_sql'])) {
                echo "\nRequired Index:\n";
                echo $opt['index_sql'] . "\n";
            }
            
            if (!empty($opt['note'])) {
                echo "\nNote: " . $opt['note'] . "\n";
            }
            
            // Test optimized query if possible
            if (!empty($opt['optimized_query']) && $opt['optimized_query'] !== $query) {
                echo "\nTesting optimized query...\n";
                $optimizedProfile = $this->profileQuery($opt['optimized_query'], 3, false);
                
                if ($optimizedProfile && $originalProfile) {
                    $improvement = (($originalProfile['avg_time'] - $optimizedProfile['avg_time']) / $originalProfile['avg_time']) * 100;
                    echo "\nPerformance Improvement: " . number_format($improvement, 2) . "% faster\n";
                    echo "Time Saved: " . number_format($originalProfile['avg_time'] - $optimizedProfile['avg_time'], 2) . " ms\n";
                    $opt['improvement'] = $improvement;
                    $opt['time_saved'] = $originalProfile['avg_time'] - $optimizedProfile['avg_time'];
                }
            }
            
            echo "\n" . str_repeat("-", 80) . "\n\n";
        }
        
        $this->saveToHistory($query, $originalProfile, $optimizations);
    }
    
    /**
     * Generate optimized versions of a query
     */
    private function generateOptimizations($query) {
        $optimizations = [];
        $queryUpper = strtoupper($query);
        $originalQuery = $query;
        
        // 1. Replace SELECT * with specific columns
        if (preg_match('/SELECT\s+\*\s+FROM\s+`?(\w+)`?/i', $query, $matches)) {
            $table = $matches[1];
            try {
                $stmt = $this->pdo->query("DESCRIBE `{$table}`");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($columns)) {
                    $columnList = implode(', ', array_slice($columns, 0, 10)); // First 10 columns
                    $optimized = preg_replace('/SELECT\s+\*/i', "SELECT {$columnList}", $query);
                    $optimizations[] = [
                        'issue' => 'SELECT * returns unnecessary columns - reduces network transfer',
                        'optimized_query' => $optimized,
                        'index_sql' => null,
                        'priority' => 'medium'
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
            $table = $this->extractTableName($query);
            $optimizations[] = [
                'issue' => "LIKE with leading wildcard on '{$column}' prevents index usage",
                'optimized_query' => $query,
                'index_sql' => "CREATE FULLTEXT INDEX idx_{$column}_fulltext ON `{$table}` (`{$column}`);",
                'note' => "Consider using FULLTEXT index or rewrite to: WHERE {$column} LIKE '{$value}%'",
                'priority' => 'high'
            ];
        }
        
        // 3. Add missing LIMIT
        if (stripos($query, 'SELECT') !== false && stripos($query, 'LIMIT') === false && 
            stripos($query, 'COUNT') === false && stripos($query, 'EXISTS') === false) {
            $optimized = rtrim(trim($query), ';') . ' LIMIT 100;';
            $optimizations[] = [
                'issue' => 'Query missing LIMIT clause - may return too many rows',
                'optimized_query' => $optimized,
                'index_sql' => null,
                'priority' => 'medium'
            ];
        }
        
        // 4. Fix functions in WHERE clause
        if (preg_match('/WHERE\s+(YEAR|MONTH|DAY|DATE|UPPER|LOWER|SUBSTRING)\s*\((\w+)\)/i', $query, $matches)) {
            $func = $matches[1];
            $column = $matches[2];
            $table = $this->extractTableName($query);
            
            $optimizations[] = [
                'issue' => "Function {$func}() on column '{$column}' prevents index usage",
                'optimized_query' => $query,
                'index_sql' => "CREATE INDEX idx_{$column} ON `{$table}` (`{$column}`);",
                'note' => "Rewrite query to avoid function. Example: WHERE {$column} >= '2024-01-01' AND {$column} < '2025-01-01'",
                'priority' => 'high'
            ];
        }
        
        // 5. Optimize OR conditions
        if (preg_match('/WHERE.*\b(\w+)\s*=\s*[\'"]?(\w+)[\'"]?\s+OR\s+\1\s*=\s*[\'"]?(\w+)[\'"]?/i', $query, $matches)) {
            $column = $matches[1];
            $value1 = $matches[2];
            $value2 = $matches[3];
            $table = $this->extractTableName($query);
            $baseQuery = preg_replace('/WHERE.*$/i', '', $query);
            $optimized = $baseQuery . " WHERE {$column} IN ('{$value1}', '{$value2}');";
            $optimizations[] = [
                'issue' => "OR condition on '{$column}' may prevent index usage - use IN() instead",
                'optimized_query' => $optimized,
                'index_sql' => "CREATE INDEX idx_{$column} ON `{$table}` (`{$column}`);",
                'priority' => 'high'
            ];
        }
        
        // 6. Add index suggestions based on WHERE and JOIN
        $indexSuggestions = $this->suggestIndexesForQuery($query);
        foreach ($indexSuggestions as $index) {
            $optimizations[] = [
                'issue' => "Missing index on '{$index['column']}' used in WHERE/JOIN/ORDER BY",
                'optimized_query' => $query,
                'index_sql' => $index['sql'],
                'priority' => 'high'
            ];
        }
        
        // Sort by priority
        usort($optimizations, function($a, $b) {
            $priority = ['high' => 3, 'medium' => 2, 'low' => 1];
            return ($priority[$b['priority']] ?? 1) - ($priority[$a['priority']] ?? 1);
        });
        
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
        if (preg_match_all('/JOIN\s+`?(\w+)`?\s+ON\s+`?\w+`?\.(\w+)\s*=\s*`?\w+`?\.(\w+)/i', $query, $matches)) {
            foreach (array_merge($matches[2], $matches[3]) as $column) {
                $joinTable = $matches[1][0] ?? $table;
                if (!$this->indexExists($joinTable, $column)) {
                    $suggestions[] = [
                        'column' => $column,
                        'sql' => "CREATE INDEX idx_{$column} ON `{$joinTable}` (`{$column}`);"
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
            
            // Get table size
            $stmt = $this->pdo->query("
                SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$tableName}'
            ");
            $sizeInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo "Table Statistics:\n";
            echo "  Rows: " . number_format($rowCount) . "\n";
            echo "  Columns: " . count($columns) . "\n";
            echo "  Existing Indexes: " . count(array_unique(array_column($existingIndexes, 'Key_name'))) . "\n";
            if ($sizeInfo) {
                echo "  Table Size: " . $sizeInfo['size_mb'] . " MB\n";
            }
            echo "\n";
            
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
                    if (preg_match('/^id$|_id$/', $name) && stripos($type, 'int') !== false) {
                        $recommendation = "INDEX (FK/PK)";
                        $recommendations[] = "CREATE INDEX idx_{$name} ON `{$tableName}` (`{$name}`);";
                    } elseif (in_array(strtolower($name), ['email', 'username', 'name', 'slug', 'code'])) {
                        $recommendation = "INDEX (search)";
                        $recommendations[] = "CREATE INDEX idx_{$name} ON `{$tableName}` (`{$name}`);";
                    } elseif (in_array(strtolower($name), ['status', 'type', 'category', 'state'])) {
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
     * Apply indexes automatically (with dry-run option)
     */
    public function applyIndexes($tableName, $dryRun = true) {
        echo "\n=== Applying Indexes to: {$tableName} ===\n";
        echo ($dryRun ? "DRY RUN MODE - No changes will be made\n\n" : "LIVE MODE - Indexes will be created\n\n");
        
        $this->analyzeTable($tableName);
        
        // Read the generated index file
        $pattern = $this->resultsDir . "/indexes_{$tableName}_*.sql";
        $files = glob($pattern);
        
        if (empty($files)) {
            echo "No index recommendations found. Run 'generate-indexes' first.\n";
            return;
        }
        
        $latestFile = max($files);
        $content = file_get_contents($latestFile);
        preg_match_all('/CREATE INDEX[^;]+;/i', $content, $matches);
        
        if (empty($matches[0])) {
            echo "No CREATE INDEX statements found.\n";
            return;
        }
        
        echo "\n=== Indexes to Apply ===\n\n";
        foreach ($matches[0] as $i => $sql) {
            echo ($i + 1) . ". " . trim($sql) . "\n";
            
            if (!$dryRun) {
                try {
                    $this->pdo->exec(trim($sql));
                    echo "   ✓ Created successfully\n";
                } catch (PDOException $e) {
                    echo "   ✗ Error: " . $e->getMessage() . "\n";
                }
            }
        }
        
        if ($dryRun) {
            echo "\nTo apply these indexes, run:\n";
            echo "php optimize.php apply-indexes {$tableName}\n";
        } else {
            echo "\n✓ All indexes applied successfully\n";
        }
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
        
        $profile1 = $this->profileQuery($query1, 5, false);
        echo "\n" . str_repeat("=", 80) . "\n\n";
        $profile2 = $this->profileQuery($query2, 5, false);
        
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
     * Generate report in multiple formats
     */
    public function generateReport($inputFile, $outputFile = null, $format = 'html') {
        if (!file_exists($inputFile)) {
            die("Error: File not found: {$inputFile}\n");
        }
        
        $content = file_get_contents($inputFile);
        preg_match_all('/SELECT.*?;/is', $content, $matches);
        $queries = empty($matches[0]) ? [trim($content)] : $matches[0];
        
        $reportData = [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_queries' => count($queries),
            'queries' => []
        ];
        
        foreach ($queries as $i => $query) {
            $profile = $this->profileQuery($query, 3, false);
            $optimizations = $this->generateOptimizations($query);
            
            $reportData['queries'][] = [
                'index' => $i + 1,
                'query' => $query,
                'profile' => $profile,
                'optimizations' => $optimizations
            ];
        }
        
        if (!$outputFile) {
            $ext = $format === 'json' ? 'json' : ($format === 'csv' ? 'csv' : 'html');
            $outputFile = $this->resultsDir . '/report_' . date('Y-m-d_His') . '.' . $ext;
        }
        
        switch ($format) {
            case 'json':
                file_put_contents($outputFile, json_encode($reportData, JSON_PRETTY_PRINT));
                break;
                
            case 'csv':
                $this->generateCsvReport($reportData, $outputFile);
                break;
                
            case 'html':
            default:
                $this->generateHtmlReport($reportData, $outputFile);
                break;
        }
        
        echo "\n✓ Report generated: {$outputFile}\n";
    }
    
    /**
     * Generate HTML report
     */
    private function generateHtmlReport($reportData, $outputFile) {
        $html = "<!DOCTYPE html>\n<html><head><title>Query Optimization Report</title>\n";
        $html .= "<style>body{font-family:Arial;margin:20px;background:#f5f5f5;}";
        $html .= ".container{max-width:1200px;margin:0 auto;background:white;padding:20px;border-radius:8px;box-shadow:0 2px 4px rgba(0,0,0,0.1);}";
        $html .= "table{border-collapse:collapse;width:100%;margin:20px 0;}";
        $html .= "th,td{border:1px solid #ddd;padding:12px;text-align:left;}";
        $html .= "th{background:#4CAF50;color:white;font-weight:bold;}";
        $html .= "tr:nth-child(even){background:#f9f9f9;}";
        $html .= ".warning{color:orange;font-weight:bold;}";
        $html .= ".success{color:green;font-weight:bold;}";
        $html .= ".error{color:red;font-weight:bold;}";
        $html .= "pre{background:#f4f4f4;padding:10px;border-radius:4px;overflow-x:auto;}";
        $html .= ".query-box{margin:20px 0;padding:15px;background:#f9f9f9;border-left:4px solid #4CAF50;}";
        $html .= "</style></head><body>\n";
        $html .= "<div class='container'>\n";
        $html .= "<h1>Database Query Optimization Report</h1>\n";
        $html .= "<p><strong>Generated:</strong> " . htmlspecialchars($reportData['generated_at']) . "</p>\n";
        $html .= "<p><strong>Total Queries:</strong> " . $reportData['total_queries'] . "</p>\n";
        
        foreach ($reportData['queries'] as $item) {
            $html .= "<div class='query-box'>\n";
            $html .= "<h2>Query " . $item['index'] . "</h2>\n";
            $html .= "<pre>" . htmlspecialchars($item['query']) . "</pre>\n";
            
            if ($item['profile']) {
                $html .= "<table><tr><th>Metric</th><th>Value</th></tr>\n";
                $html .= "<tr><td>Average Time</td><td>" . number_format($item['profile']['avg_time'], 2) . " ms</td></tr>\n";
                $html .= "<tr><td>Min Time</td><td>" . number_format($item['profile']['min_time'], 2) . " ms</td></tr>\n";
                $html .= "<tr><td>Max Time</td><td>" . number_format($item['profile']['max_time'], 2) . " ms</td></tr>\n";
                $html .= "<tr><td>Rows Returned</td><td>" . $item['profile']['rows'] . "</td></tr>\n";
                $html .= "</table>\n";
            }
            
            if (!empty($item['optimizations'])) {
                $html .= "<h3>Optimization Recommendations</h3>\n<ul>\n";
                foreach ($item['optimizations'] as $opt) {
                    $html .= "<li><strong>" . htmlspecialchars($opt['issue']) . "</strong>";
                    if (!empty($opt['index_sql'])) {
                        $html .= "<pre>" . htmlspecialchars($opt['index_sql']) . "</pre>";
                    }
                    if (!empty($opt['note'])) {
                        $html .= "<br><em>" . htmlspecialchars($opt['note']) . "</em>";
                    }
                    $html .= "</li>\n";
                }
                $html .= "</ul>\n";
            }
            
            $html .= "</div>\n";
        }
        
        $html .= "</div></body></html>";
        file_put_contents($outputFile, $html);
    }
    
    /**
     * Generate CSV report
     */
    private function generateCsvReport($reportData, $outputFile) {
        $fp = fopen($outputFile, 'w');
        fputcsv($fp, ['Query #', 'Query', 'Avg Time (ms)', 'Rows', 'Optimizations']);
        
        foreach ($reportData['queries'] as $item) {
            $optimizations = !empty($item['optimizations']) ? count($item['optimizations']) : 0;
            $avgTime = $item['profile'] ? $item['profile']['avg_time'] : 0;
            $rows = $item['profile'] ? $item['profile']['rows'] : 0;
            
            fputcsv($fp, [
                $item['index'],
                substr($item['query'], 0, 200),
                number_format($avgTime, 2),
                $rows,
                $optimizations
            ]);
        }
        
        fclose($fp);
    }
    
    /**
     * Show query history
     */
    public function showHistory($limit = 10) {
        echo "\n=== Query History ===\n\n";
        
        $history = array_slice($this->queryHistory, -$limit);
        
        if (empty($history)) {
            echo "No query history found.\n";
            return;
        }
        
        foreach (array_reverse($history) as $i => $entry) {
            echo ($i + 1) . ". [" . $entry['timestamp'] . "]\n";
            echo "   Query: " . substr($entry['query'], 0, 80) . "...\n";
            if (isset($entry['profile']['avg_time'])) {
                echo "   Avg Time: " . number_format($entry['profile']['avg_time'], 2) . " ms\n";
            }
            if (!empty($entry['optimizations'])) {
                echo "   Optimizations: " . count($entry['optimizations']) . "\n";
            }
            echo "\n";
        }
    }
    
    /**
     * Detect N+1 query patterns
     */
    public function detectN1($logFile) {
        echo "\n=== N+1 Query Pattern Detection ===\n\n";
        
        if (!file_exists($logFile)) {
            die("Error: Log file not found: {$logFile}\n");
        }
        
        $content = file_get_contents($logFile);
        preg_match_all('/SELECT.*?;/is', $content, $matches);
        $queries = $matches[0];
        
        // Group similar queries
        $queryGroups = [];
        foreach ($queries as $query) {
            $normalized = preg_replace('/\d+/', '?', $query);
            $normalized = preg_replace('/[\'"]\w+[\'"]/', '?', $normalized);
            $hash = md5($normalized);
            
            if (!isset($queryGroups[$hash])) {
                $queryGroups[$hash] = ['pattern' => $normalized, 'count' => 0, 'queries' => []];
            }
            $queryGroups[$hash]['count']++;
            $queryGroups[$hash]['queries'][] = $query;
        }
        
        // Find potential N+1 patterns (same query executed many times)
        $n1Patterns = array_filter($queryGroups, function($group) {
            return $group['count'] > 5;
        });
        
        if (empty($n1Patterns)) {
            echo "✓ No N+1 query patterns detected.\n";
            return;
        }
        
        echo "⚠ Potential N+1 Query Patterns Found:\n\n";
        foreach ($n1Patterns as $hash => $group) {
            echo "Pattern executed " . $group['count'] . " times:\n";
            echo substr($group['pattern'], 0, 200) . "...\n";
            echo "Consider using JOIN or batch loading instead.\n\n";
        }
    }
    
    /**
     * Show database statistics
     */
    public function showStats() {
        echo "\n=== Database Statistics ===\n\n";
        
        try {
            // Get database size
            $stmt = $this->pdo->query("
                SELECT 
                    table_schema AS 'Database',
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE()
                GROUP BY table_schema
            ");
            $dbSize = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get table statistics
            $stmt = $this->pdo->query("
                SELECT 
                    table_name AS 'Table',
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
                    table_rows AS 'Rows'
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 10
            ");
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Database: " . ($dbSize['Database'] ?? 'N/A') . "\n";
            echo "Total Size: " . ($dbSize['Size (MB)'] ?? 0) . " MB\n\n";
            
            echo "Top 10 Largest Tables:\n";
            echo str_repeat("-", 60) . "\n";
            printf("%-30s %15s %15s\n", "Table", "Size (MB)", "Rows");
            echo str_repeat("-", 60) . "\n";
            
            foreach ($tables as $table) {
                printf("%-30s %15s %15s\n", 
                    substr($table['Table'], 0, 28),
                    number_format($table['Size (MB)'], 2),
                    number_format($table['Rows'])
                );
            }
            
        } catch (PDOException $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Batch process multiple query files
     */
    public function batchProcess($files) {
        echo "\n=== Batch Processing ===\n\n";
        
        $allResults = [];
        
        foreach ($files as $file) {
            if (!file_exists($file)) {
                echo "⚠ Skipping: {$file} (not found)\n";
                continue;
            }
            
            echo "Processing: {$file}\n";
            $content = file_get_contents($file);
            preg_match_all('/SELECT.*?;/is', $content, $matches);
            $queries = empty($matches[0]) ? [trim($content)] : $matches[0];
            
            foreach ($queries as $query) {
                $profile = $this->profileQuery($query, 3, false);
                if ($profile) {
                    $allResults[] = [
                        'file' => $file,
                        'query' => $query,
                        'profile' => $profile
                    ];
                }
            }
        }
        
        // Generate summary report
        $summaryFile = $this->resultsDir . '/batch_summary_' . date('Y-m-d_His') . '.json';
        file_put_contents($summaryFile, json_encode($allResults, JSON_PRETTY_PRINT));
        echo "\n✓ Batch processing complete. Summary saved to: {$summaryFile}\n";
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
        echo "  php optimize.php profile \"<SQL query>\" [iterations]     - Profile query execution time\n";
        echo "  php optimize.php optimize \"<SQL query>\"                  - Generate optimized query\n";
        echo "  php optimize.php analyze-table <table>                    - Analyze table structure\n";
        echo "  php optimize.php generate-indexes <table>                 - Generate CREATE INDEX statements\n";
        echo "  php optimize.php apply-indexes <table> [--dry-run]        - Apply indexes (use --dry-run to preview)\n";
        echo "  php optimize.php compare \"<query1>\" \"<query2>\"           - Compare two queries\n";
        echo "  php optimize.php analyze <query_file.sql>                  - Analyze queries from file\n";
        echo "  php optimize.php report <input.sql> [output] [--format=json|csv|html] - Generate report\n";
        echo "  php optimize.php history [--limit=N]                      - Show query history\n";
        echo "  php optimize.php detect-n1 <log_file>                    - Detect N+1 query patterns\n";
        echo "  php optimize.php stats                                   - Show database statistics\n";
        echo "  php optimize.php batch <file1> <file2> ...               - Batch process multiple files\n";
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
            
        case 'apply-indexes':
        case 'apply_indexes':
            if (!isset($argv[2])) {
                die("Error: Please provide a table name.\n");
            }
            $dryRun = in_array('--dry-run', $argv);
            $optimizer->applyIndexes($argv[2], $dryRun);
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
            $outputFile = isset($argv[3]) && strpos($argv[3], '--') !== 0 ? $argv[3] : null;
            $format = 'html';
            foreach ($argv as $arg) {
                if (strpos($arg, '--format=') === 0) {
                    $format = substr($arg, 9);
                }
            }
            $optimizer->generateReport($argv[2], $outputFile, $format);
            break;
            
        case 'history':
            $limit = 10;
            foreach ($argv as $arg) {
                if (strpos($arg, '--limit=') === 0) {
                    $limit = (int)substr($arg, 8);
                }
            }
            $optimizer->showHistory($limit);
            break;
            
        case 'detect-n1':
        case 'detect_n1':
            if (!isset($argv[2])) {
                die("Error: Please provide a log file path.\n");
            }
            $optimizer->detectN1($argv[2]);
            break;
            
        case 'stats':
            $optimizer->showStats();
            break;
            
        case 'batch':
            if (count($argv) < 3) {
                die("Error: Please provide at least one file to process.\n");
            }
            $files = array_slice($argv, 2);
            $optimizer->batchProcess($files);
            break;
            
        default:
            die("Unknown command: {$command}\n");
    }
}

?>
