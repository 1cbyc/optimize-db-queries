# Professional Database Query Optimization Tool

A comprehensive tool for analyzing, profiling, and optimizing database queries with real performance measurements and actionable recommendations.

> 📖 **New:** See [DOCUMENTATION.md](DOCUMENTATION.md) for real-world scenarios and workflows.

## Features

- **Query Profiling**: Measure actual execution time with multiple iterations
- **Query Optimization**: Generate optimized SQL versions automatically
- **Index Analysis**: Analyze tables and generate CREATE INDEX statements
- **Performance Comparison**: Compare before/after query performance
- **HTML Reports**: Generate detailed optimization reports
- **Pattern Detection**: Identify common performance issues

## Setup

1. **Copy the configuration template:**
   ```bash
   cp config.example.php config.php
   ```

2. **Edit `config.php`** with your database credentials

3. **Ensure PHP PDO MySQL extension is installed:**
   ```bash
   php -m | grep pdo_mysql
   ```

## Commands

### 1. Profile Query Execution Time

Measure the actual execution time of a query:

```bash
php optimize.php profile "SELECT * FROM users WHERE id = 1"
php optimize.php profile "SELECT * FROM users WHERE email = 'user@example.com'" 10
```

**Output includes:**
- Average, min, max execution times
- Number of rows returned
- EXPLAIN plan
- Performance assessment

### 2. Optimize a Query

Generate optimized versions of a query with performance comparisons:

```bash
php optimize.php optimize "SELECT * FROM users WHERE name LIKE '%john%'"
```

**Provides:**
- Original query analysis
- Optimized query suggestions
- Required CREATE INDEX statements
- Performance improvement percentage
- Time saved in milliseconds

### 3. Analyze Table Structure

Analyze a table and get index recommendations:

```bash
php optimize.php analyze-table users
```

**Shows:**
- Table statistics (row count, columns, indexes)
- Column analysis with recommendations
- Missing index suggestions
- Saves CREATE INDEX statements to file

### 4. Generate Index Statements

Generate ready-to-use CREATE INDEX statements:

```bash
php optimize.php generate-indexes orders
```

Creates a SQL file with all recommended indexes in `results/` directory.

### 5. Compare Two Queries

Compare performance of two queries side-by-side:

```bash
php optimize.php compare "SELECT * FROM users" "SELECT id, name FROM users WHERE id = 1"
```

**Shows:**
- Execution time for both queries
- Performance improvement percentage
- Time saved

### 6. Analyze Queries from File

Analyze multiple queries from a SQL file:

```bash
php optimize.php analyze example-queries.sql
```

Processes all queries in the file and provides optimization recommendations for each.

### 7. Generate HTML Report

Create a comprehensive HTML report:

```bash
php optimize.php report example-queries.sql report.html
```

Generates a detailed HTML report with:
- All queries analyzed
- Performance metrics
- Optimization recommendations
- Index suggestions

## Example Workflow

### Step 1: Profile a Slow Query

```bash
php optimize.php profile "SELECT * FROM orders WHERE customer_id = 123"
```

**Output:**
```
=== Query Profiling ===

Query: SELECT * FROM orders WHERE customer_id = 123
Iterations: 5

Execution Statistics:
------------------------------------------------------------
Average Time:     245.32 ms
Min Time:         198.45 ms
Max Time:         312.18 ms
Total Time:      1226.60 ms
Rows Returned:        15

⚠ Poor performance (> 200ms) - Consider optimization
```

### Step 2: Optimize the Query

```bash
php optimize.php optimize "SELECT * FROM orders WHERE customer_id = 123"
```

**Output:**
```
=== Optimization Recommendations ===

Recommendation 1: Missing index on 'customer_id' used in WHERE/JOIN
Optimized Query:
SELECT * FROM orders WHERE customer_id = 123

Required Index:
CREATE INDEX idx_customer_id ON `orders` (`customer_id`);

Testing optimized query...
Performance Improvement: 87.45% faster
Time Saved: 214.56 ms
```

### Step 3: Generate Indexes

```bash
php optimize.php generate-indexes orders
```

Creates `results/indexes_orders_2024-01-15_143022.sql` with:
```sql
CREATE INDEX idx_customer_id ON `orders` (`customer_id`);
CREATE INDEX idx_status ON `orders` (`status`);
CREATE INDEX idx_created_at ON `orders` (`created_at`);
```

### Step 4: Compare Before/After

After creating indexes, compare performance:

```bash
php optimize.php compare "SELECT * FROM orders WHERE customer_id = 123" "SELECT order_id, total FROM orders WHERE customer_id = 123"
```

## Common Optimizations

The tool automatically detects and suggests fixes for:

1. **SELECT *** → Replace with specific columns
2. **LIKE with leading wildcard** → Suggest FULLTEXT index or rewrite
3. **Missing LIMIT** → Add LIMIT clause
4. **Functions in WHERE** → Rewrite to avoid functions
5. **OR conditions** → Suggest UNION or proper indexing
6. **Missing indexes** → Generate CREATE INDEX statements
7. **Inefficient JOINs** → Suggest indexes on JOIN columns
8. **Large result sets** → Suggest pagination

## Output Files

All generated files are saved in the `results/` directory:

- `indexes_<table>_<timestamp>.sql` - Index recommendations
- `report_<timestamp>.html` - HTML optimization reports

## Performance Thresholds

- **Excellent**: < 10ms
- **Good**: < 50ms
- **Acceptable**: < 200ms
- **Poor**: > 200ms (optimization recommended)

## Requirements

- PHP 7.4 or higher
- PDO MySQL extension
- MySQL 5.6+ (for performance features)
- Database credentials with SELECT, EXPLAIN permissions

## Tips for Best Results

1. **Profile first**: Always profile queries to get baseline performance
2. **Test optimizations**: Use the compare command to verify improvements
3. **Apply indexes**: Generate and apply recommended indexes
4. **Review reports**: Use HTML reports for documentation and sharing
5. **Iterate**: Optimize, test, measure, repeat

## Troubleshooting

**Connection errors:**
- Verify database credentials in `config.php`
- Check database server is running
- Verify network connectivity

**Permission errors:**
- Ensure database user has SELECT and EXPLAIN permissions
- For index generation, may need CREATE INDEX permissions

**Slow profiling:**
- Reduce iterations: `php optimize.php profile "QUERY" 3`
- Use smaller test datasets
- Profile during off-peak hours

## Advanced Usage

### Batch Processing

```bash
# Process multiple files
for file in queries/*.sql; do
    php optimize.php analyze "$file"
done
```

### Automated Reporting

```bash
# Generate daily reports
php optimize.php report production-queries.sql reports/daily-$(date +%Y%m%d).html
```

### Integration with CI/CD

```bash
# Check for slow queries in CI pipeline
php optimize.php analyze test-queries.sql | grep "Poor performance"
```
