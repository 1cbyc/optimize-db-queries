# Quick Start Guide

Get started with the Database Query Optimization Tool in 3 steps:

## Step 1: Configure Database

```bash
cp config.example.php config.php
```

Edit `config.php` and add your database credentials.

## Step 2: Test Connection

Try profiling a simple query:

```bash
php optimize.php profile "SELECT 1"
```

If this works, you're ready to go!

## Step 3: Optimize Your Queries

### Profile a query to see its performance:
```bash
php optimize.php profile "SELECT * FROM your_table WHERE id = 1"
```

### Get optimization suggestions:
```bash
php optimize.php optimize "SELECT * FROM your_table WHERE name LIKE '%search%'"
```

### Analyze a table for missing indexes:
```bash
php optimize.php analyze-table your_table
```

### Generate index creation statements:
```bash
php optimize.php generate-indexes your_table
```

## Common Use Cases

### "I have a slow query"
```bash
# 1. Profile it
php optimize.php profile "YOUR_SLOW_QUERY"

# 2. Optimize it
php optimize.php optimize "YOUR_SLOW_QUERY"

# 3. Apply suggested indexes
php optimize.php generate-indexes table_name
```

### "I want to analyze all queries in a file"
```bash
php optimize.php analyze queries.sql
```

### "I need a report for my team"
```bash
php optimize.php report queries.sql team-report.html
```

### "I want to compare two query versions"
```bash
php optimize.php compare "OLD_QUERY" "NEW_QUERY"
```

## Next Steps

- Read `USAGE.md` for detailed documentation
- Check `example-queries.sql` for sample queries
- Review generated files in `results/` directory

## Need Help?

- Check database connection: Verify credentials in `config.php`
- Check permissions: Ensure database user has SELECT and EXPLAIN permissions
- Check PHP version: Requires PHP 7.4+

