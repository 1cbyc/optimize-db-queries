-- Example queries for testing the optimization tool
-- These demonstrate common performance issues

-- Example 1: Query that might need an index
SELECT * FROM users WHERE email = 'user@example.com';

-- Example 2: Query with JOIN that needs optimization
SELECT u.name, u.email, o.order_date, o.total
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
WHERE u.country = 'USA'
ORDER BY o.order_date DESC;

-- Example 3: Query with function in WHERE clause (prevents index usage)
SELECT * FROM products 
WHERE YEAR(created_at) = 2024
ORDER BY name;

-- Example 4: Query with LIKE and leading wildcard (inefficient)
SELECT * FROM users WHERE name LIKE '%john%';

-- Example 5: Query without LIMIT (may return too many rows)
SELECT * FROM orders WHERE status = 'pending';

-- Example 6: Pagination query with OFFSET
SELECT * FROM products 
ORDER BY created_at DESC
LIMIT 10 OFFSET 20;

-- Example 7: Query with OR condition (may prevent index usage)
SELECT * FROM users WHERE status = 'active' OR status = 'pending';

-- Example 8: Complex query with multiple JOINs
SELECT u.id, u.name, u.email, 
       o.id as order_id, o.total, o.created_at,
       p.name as product_name, p.price
FROM users u
INNER JOIN orders o ON u.id = o.user_id
INNER JOIN order_items oi ON o.id = oi.order_id
INNER JOIN products p ON oi.product_id = p.id
WHERE u.created_at > '2024-01-01'
ORDER BY o.created_at DESC;
