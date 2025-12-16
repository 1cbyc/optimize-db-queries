# Quick Reference - Production Use Cases

## Emergency Scenarios

### Site is Slow - Need to Fix NOW
```bash
php optimize.php detect-n1 /var/log/mysql/slow.log
php optimize.php optimize "SLOW_QUERY"
php optimize.php apply-indexes table_name --dry-run
php optimize.php apply-indexes table_name
```

### Database is Overloaded
```bash
php optimize.php stats
php optimize.php analyze-table largest_table
php optimize.php generate-indexes largest_table
```

## Regular Maintenance

### Weekly Performance Review
```bash
php optimize.php history --limit=50
php optimize.php report weekly-queries.sql weekly-report.html
```

### Monthly Database Audit
```bash
php optimize.php stats > monthly-audit.txt
for table in users orders products; do
    php optimize.php analyze-table $table
done
```

## CI/CD Integration

### Pre-Deployment Check
```bash
php optimize.php batch new-feature/*.sql
php optimize.php report new-queries.sql pr-review.html
```

### Automated Performance Gate
```bash
php optimize.php analyze queries.sql | grep "Poor performance" && exit 1
```

## Common Tasks

### Find N+1 Problems
```bash
php optimize.php detect-n1 app/queries.log
```

### Compare Query Versions
```bash
php optimize.php compare "OLD_QUERY" "NEW_QUERY"
```

### Generate Indexes for Table
```bash
php optimize.php generate-indexes users
php optimize.php apply-indexes users --dry-run
php optimize.php apply-indexes users
```

### Export Performance Data
```bash
php optimize.php report queries.sql data.json --format=json
php optimize.php report queries.sql data.csv --format=csv
```

---

**Full documentation:** See [DOCUMENTATION.md](DOCUMENTATION.md) for detailed scenarios.
