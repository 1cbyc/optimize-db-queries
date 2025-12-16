# Database Query Optimization Tool - Usage Guide

## Setup

1. **Copy the configuration template:**
   ```bash
   cp config.example.php config.php
   ```

2. **Edit `config.php`** with your database credentials:
   ```php
   return [
       'database' => [
           'host' => 'localhost',
           'dbname' => 'your_database',
           'charset' => 'utf8mb4',
           'username' => 'your_username',
           'password' => 'your_password',
       ],
       // ... rest of config
   ];
   ```

## Usage

### 1. Analyze queries from a file

```bash
php optimize.php analyze example-queries.sql
```

This will analyze all queries in the file and provide:
- EXPLAIN plan for each query
- Performance warnings
- Common issues detected
- Optimization suggestions

### 2. Explain a single query

```bash
php optimize.php explain "SELECT * FROM users WHERE name = 'John'"
```

Shows the EXPLAIN plan and analysis for a specific query.

### 3. Get index suggestions for a table

```bash
php optimize.php suggest-indexes users
```

Analyzes a table structure and suggests:
- Missing indexes
- Primary key candidates
- Foreign key indexes
- Common search column indexes

### 4. View slow queries

```bash
php optimize.php slow-queries
```

Shows the top slowest queries from your database (requires performance_schema or slow query log).

## Features

### Query Analysis
- **EXPLAIN Plan**: Visual representation of how MySQL executes your query
- **Performance Warnings**: Detects full table scans, high row estimates, filesorts
- **Common Issues**: Identifies SELECT *, missing WHERE clauses, inefficient patterns
- **Optimization Suggestions**: Provides actionable recommendations

### Index Suggestions
- Analyzes table structure
- Identifies missing indexes
- Suggests indexes for foreign keys, common search columns, and primary keys

### Slow Query Detection
- Shows top slowest queries
- Displays execution counts and average times
- Helps identify queries that need optimization

## Common Optimization Tips

1. **Use Indexes**: Add indexes on columns used in WHERE, JOIN, and ORDER BY clauses
2. **Avoid SELECT ***: Only select columns you need
3. **Use LIMIT**: Always limit result sets when possible
4. **Avoid Functions in WHERE**: Functions prevent index usage
5. **Use Prepared Statements**: Always use parameter binding (already in examples)
6. **Optimize JOINs**: Ensure JOIN columns are indexed
7. **Avoid Leading Wildcards**: `LIKE '%text'` cannot use indexes efficiently

## Example Output

When you run `php optimize.php analyze example-queries.sql`, you'll see:

```
=== Query Analysis ===

Original Query:
SELECT * FROM users WHERE email = 'user@example.com'

=== EXPLAIN Plan ===
id             select_type     table           type            possible_keys   key             key_len         ref             rows            Extra           
------------------------------------------------------------------------------------------------------------------------
1              SIMPLE          users           ref             email_idx      email_idx       102             const           1               

=== Performance Warnings ===
⚠️  Using SELECT * - Consider selecting only needed columns

=== Optimization Suggestions ===
💡 Consider adding an index on column: email
```

## Requirements

- PHP 7.4 or higher
- PDO MySQL extension
- MySQL 5.6+ (for performance_schema features)
- Database credentials with appropriate permissions

