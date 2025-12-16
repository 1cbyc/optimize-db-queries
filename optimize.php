#!/usr/bin/env php
<?php
/**
 * Database Query Optimization Tool
 * 
 * Analyzes and optimizes database queries for better performance
 * 
 * Usage:
 *   php optimize.php analyze <query_file.sql>
 *   php optimize.php explain "SELECT * FROM users WHERE name = 'John'"
 *   php optimize.php suggest-indexes <table_name>
 *   php optimize.php slow-queries
 */

class DatabaseOptimizer {
    private $pdo;
    private $config;
    
    public function __construct() {
        $this->loadConfig();
        $this->connect();
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
     * Analyze a SQL query and provide optimization suggestions
     */
    public function analyzeQuery($query) {
        echo "\n=== Query Analysis ===\n\n";
        echo "Original Query:\n";
        echo $query . "\n\n";
        
        // Get EXPLAIN plan
        $this->explainQuery($query);
        
        // Check for common issues
        $this->checkCommonIssues($query);
        
        // Suggest optimizations
        $this->suggestOptimizations($query);
    }
    
    /**
     * Show EXPLAIN plan for a query
     */
    public function explainQuery($query) {
        try {
            $explainQuery = "EXPLAIN " . $query;
            $stmt = $this->pdo->prepare($explainQuery);
            $stmt->execute();
            $plan = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "=== EXPLAIN Plan ===\n";
            if (empty($plan)) {
                echo "No execution plan available.\n\n";
                return;
            }
            
            // Display table format
            $headers = array_keys($plan[0]);
            printf("%-15s", "id");
            foreach ($headers as $header) {
                if ($header !== 'id') {
                    printf("%-20s", $header);
                }
            }
            echo "\n" . str_repeat("-", 120) . "\n";
            
            foreach ($plan as $row) {
                printf("%-15s", $row['id'] ?? '');
                foreach ($headers as $header) {
                    if ($header !== 'id') {
                        printf("%-20s", substr($row[$header] ?? '', 0, 18));
                    }
                }
                echo "\n";
            }
            echo "\n";
            
            // Analyze the plan
            $this->analyzeExplainPlan($plan);
            
        } catch (PDOException $e) {
            echo "Error analyzing query: " . $e->getMessage() . "\n\n";
        }
    }
    
    /**
     * Analyze EXPLAIN plan and provide insights
     */
    private function analyzeExplainPlan($plan) {
        $warnings = [];
        $suggestions = [];
        
        foreach ($plan as $row) {
            $type = strtoupper($row['type'] ?? '');
            $key = $row['key'] ?? '';
            $rows = $row['rows'] ?? 0;
            $extra = $row['Extra'] ?? '';
            
            // Check for full table scans
            if ($type === 'ALL' && empty($key)) {
                $warnings[] = "⚠️  Full table scan detected on table '{$row['table']}' - Consider adding an index";
            }
            
            // Check for high row estimates
            if ($rows > 10000) {
                $warnings[] = "⚠️  High row estimate ({$rows}) - Query may be slow";
            }
            
            // Check for filesort
            if (stripos($extra, 'filesort') !== false) {
                $warnings[] = "⚠️  Filesort detected - Consider adding an index for ORDER BY";
            }
            
            // Check for temporary tables
            if (stripos($extra, 'Using temporary') !== false) {
                $warnings[] = "⚠️  Temporary table detected - May indicate inefficient GROUP BY or ORDER BY";
            }
        }
        
        if (!empty($warnings)) {
            echo "=== Performance Warnings ===\n";
            foreach ($warnings as $warning) {
                echo $warning . "\n";
            }
            echo "\n";
        }
    }
    
    /**
     * Check for common query issues
     */
    private function checkCommonIssues($query) {
        $issues = [];
        $queryUpper = strtoupper($query);
        
        // Check for SELECT *
        if (preg_match('/SELECT\s+\*\s+FROM/i', $query)) {
            $issues[] = "⚠️  Using SELECT * - Consider selecting only needed columns";
        }
        
        // Check for missing WHERE clause on large tables
        if (preg_match('/SELECT.*FROM\s+(\w+)(?!.*WHERE)/i', $query, $matches)) {
            $issues[] = "⚠️  No WHERE clause - May return too many rows";
        }
        
        // Check for LIKE with leading wildcard
        if (preg_match('/LIKE\s+[\'"]%[^\'"]+/i', $query)) {
            $issues[] = "⚠️  LIKE with leading wildcard - Cannot use index efficiently";
        }
        
        // Check for functions in WHERE clause
        if (preg_match('/WHERE.*\b(YEAR|MONTH|DAY|DATE|UPPER|LOWER|SUBSTRING)\s*\(/i', $query)) {
            $issues[] = "⚠️  Functions in WHERE clause - May prevent index usage";
        }
        
        // Check for OR conditions
        if (preg_match('/WHERE.*\bOR\b/i', $query)) {
            $issues[] = "⚠️  OR conditions - May prevent index usage, consider UNION";
        }
        
        if (!empty($issues)) {
            echo "=== Common Issues ===\n";
            foreach ($issues as $issue) {
                echo $issue . "\n";
            }
            echo "\n";
        }
    }
    
    /**
     * Suggest query optimizations
     */
    private function suggestOptimizations($query) {
        $suggestions = [];
        $queryUpper = strtoupper($query);
        
        // Suggest indexes for WHERE clauses
        if (preg_match('/WHERE\s+(\w+)\s*=/i', $query, $matches)) {
            $column = $matches[1];
            $suggestions[] = "💡 Consider adding an index on column: {$column}";
        }
        
        // Suggest indexes for JOINs
        if (preg_match('/JOIN\s+\w+\s+ON\s+\w+\.(\w+)\s*=\s*\w+\.(\w+)/i', $query, $matches)) {
            $suggestions[] = "💡 Ensure indexes exist on JOIN columns: {$matches[1]} and {$matches[2]}";
        }
        
        // Suggest LIMIT for large result sets
        if (!preg_match('/LIMIT\s+\d+/i', $query) && stripos($query, 'SELECT') !== false) {
            $suggestions[] = "💡 Consider adding LIMIT to restrict result set size";
        }
        
        // Suggest pagination
        if (preg_match('/OFFSET\s+\d+/i', $query) && !preg_match('/LIMIT\s+\d+/i', $query)) {
            $suggestions[] = "💡 Using OFFSET without LIMIT - Consider using cursor-based pagination for large datasets";
        }
        
        if (!empty($suggestions)) {
            echo "=== Optimization Suggestions ===\n";
            foreach ($suggestions as $suggestion) {
                echo $suggestion . "\n";
            }
            echo "\n";
        }
    }
    
    /**
     * Suggest indexes for a table
     */
    public function suggestIndexes($tableName) {
        echo "\n=== Index Suggestions for table: {$tableName} ===\n\n";
        
        try {
            // Get table structure
            $stmt = $this->pdo->query("DESCRIBE `{$tableName}`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get existing indexes
            $stmt = $this->pdo->query("SHOW INDEXES FROM `{$tableName}`");
            $existingIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $indexedColumns = [];
            foreach ($existingIndexes as $index) {
                $indexedColumns[] = $index['Column_name'];
            }
            
            echo "Table Structure:\n";
            printf("%-20s %-15s %-10s %-10s\n", "Column", "Type", "Null", "Key");
            echo str_repeat("-", 60) . "\n";
            
            $suggestions = [];
            foreach ($columns as $col) {
                $name = $col['Field'];
                $type = $col['Type'];
                $null = $col['Null'];
                $key = $col['Key'] ?: '-';
                
                printf("%-20s %-15s %-10s %-10s\n", 
                    substr($name, 0, 18), 
                    substr($type, 0, 13), 
                    $null, 
                    $key
                );
                
                // Suggest indexes for common patterns
                if (!in_array($name, $indexedColumns)) {
                    // Primary key candidates
                    if (stripos($name, 'id') !== false && stripos($type, 'int') !== false) {
                        $suggestions[] = "PRIMARY KEY candidate: {$name}";
                    }
                    // Foreign key candidates
                    if (preg_match('/_id$/', $name)) {
                        $suggestions[] = "INDEX candidate (foreign key): {$name}";
                    }
                    // Common search columns
                    if (in_array(strtolower($name), ['name', 'email', 'username', 'status', 'created_at', 'updated_at'])) {
                        $suggestions[] = "INDEX candidate (common search): {$name}";
                    }
                }
            }
            
            echo "\n";
            
            if (!empty($existingIndexes)) {
                echo "Existing Indexes:\n";
                foreach ($existingIndexes as $index) {
                    echo "  - {$index['Key_name']} on {$index['Column_name']}\n";
                }
                echo "\n";
            }
            
            if (!empty($suggestions)) {
                echo "=== Suggested Indexes ===\n";
                foreach ($suggestions as $suggestion) {
                    echo "💡 {$suggestion}\n";
                }
            } else {
                echo "✓ Table appears to have appropriate indexes.\n";
            }
            
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Show slow queries from MySQL slow query log
     */
    public function showSlowQueries() {
        echo "\n=== Slow Query Analysis ===\n\n";
        
        try {
            // Check if slow query log is enabled
            $stmt = $this->pdo->query("SHOW VARIABLES LIKE 'slow_query_log'");
            $slowLogEnabled = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($slowLogEnabled && $slowLogEnabled['Value'] === 'OFF') {
                echo "⚠️  Slow query log is disabled. Enable it with:\n";
                echo "   SET GLOBAL slow_query_log = 'ON';\n";
                echo "   SET GLOBAL long_query_time = 2;\n\n";
            }
            
            // Try to get slow queries from performance_schema (MySQL 5.6+)
            try {
                $stmt = $this->pdo->query("
                    SELECT 
                        sql_text,
                        exec_count,
                        avg_timer_wait/1000000000000 as avg_time_sec,
                        sum_timer_wait/1000000000000 as total_time_sec
                    FROM performance_schema.events_statements_summary_by_digest
                    WHERE avg_timer_wait > 1000000000000
                    ORDER BY avg_timer_wait DESC
                    LIMIT 10
                ");
                
                $slowQueries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($slowQueries)) {
                    echo "Top 10 Slowest Queries:\n";
                    echo str_repeat("-", 100) . "\n";
                    foreach ($slowQueries as $i => $query) {
                        echo "\n" . ($i + 1) . ". Average Time: " . number_format($query['avg_time_sec'], 4) . "s\n";
                        echo "   Executions: {$query['exec_count']}\n";
                        echo "   Total Time: " . number_format($query['total_time_sec'], 4) . "s\n";
                        echo "   Query: " . substr($query['sql_text'], 0, 200) . "...\n";
                    }
                } else {
                    echo "No slow queries found in performance_schema.\n";
                }
            } catch (PDOException $e) {
                echo "Performance schema not available. Using alternative method.\n";
                echo "Enable slow query log for better analysis.\n";
            }
            
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Analyze queries from a file
     */
    public function analyzeFile($filePath) {
        if (!file_exists($filePath)) {
            die("Error: File not found: {$filePath}\n");
        }
        
        $content = file_get_contents($filePath);
        
        // Extract SQL queries (simple extraction)
        preg_match_all('/SELECT.*?;/is', $content, $matches);
        
        if (empty($matches[0])) {
            // Try to get the whole file as one query
            $queries = [trim($content)];
        } else {
            $queries = $matches[0];
        }
        
        foreach ($queries as $i => $query) {
            echo "\n" . str_repeat("=", 80) . "\n";
            echo "Query " . ($i + 1) . ":\n";
            echo str_repeat("=", 80) . "\n";
            $this->analyzeQuery($query);
        }
    }
}

// CLI Interface
if (php_sapi_name() === 'cli') {
    $optimizer = new DatabaseOptimizer();
    
    if ($argc < 2) {
        echo "Database Query Optimization Tool\n\n";
        echo "Usage:\n";
        echo "  php optimize.php analyze <query_file.sql>     - Analyze queries from a file\n";
        echo "  php optimize.php explain \"<SQL query>\"        - Show EXPLAIN plan for a query\n";
        echo "  php optimize.php suggest-indexes <table>     - Suggest indexes for a table\n";
        echo "  php optimize.php slow-queries                - Show slow queries\n";
        echo "\n";
        exit(1);
    }
    
    $command = $argv[1];
    
    switch ($command) {
        case 'analyze':
            if (!isset($argv[2])) {
                die("Error: Please provide a query file path.\n");
            }
            $optimizer->analyzeFile($argv[2]);
            break;
            
        case 'explain':
            if (!isset($argv[2])) {
                die("Error: Please provide a SQL query.\n");
            }
            $optimizer->explainQuery($argv[2]);
            break;
            
        case 'suggest-indexes':
            if (!isset($argv[2])) {
                die("Error: Please provide a table name.\n");
            }
            $optimizer->suggestIndexes($argv[2]);
            break;
            
        case 'slow-queries':
            $optimizer->showSlowQueries();
            break;
            
        default:
            die("Unknown command: {$command}\n");
    }
}

?>
