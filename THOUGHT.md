
Emergency fix:
```bash
php optimize.php detect-n1 slow.log
php optimize.php optimize "PROBLEM_QUERY"
php optimize.php apply-indexes table --dry-run
```

Weekly review:
```bash
php optimize.php history --limit=50
php optimize.php report queries.sql report.html
```

Pre-deployment:
```bash
php optimize.php batch new-feature/*.sql
php optimize.php report new-queries.sql pr-review.html
```