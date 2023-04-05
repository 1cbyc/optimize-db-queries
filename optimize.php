<?php

// Establish a database connection
$dsn = "mysql:host=localhost;dbname=mydatabase;charset=utf8mb4";
$username = "myusername";
$password = "mypassword";
$options = [
    PDO::ATTR_EMULATE_PREPARES   => false, // turn off emulation mode for "real" prepared statements
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // enable exceptions for errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // set default fetch mode to associative arrays
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'", // set the default character set to utf8mb4
];
$pdo = new PDO($dsn, $username, $password, $options);

// Example 1: Use indexes to speed up queries
// Create an index on the "name" column of the "users" table
$pdo->exec("CREATE INDEX users_name_index ON users (name)");

// Use the index in a query to search for users by name
$name = "John";
$stmt = $pdo->prepare("SELECT * FROM users WHERE name = ?");
$stmt->execute([$name]);
$users = $stmt->fetchAll();

// Example 2: Optimize queries with JOINs
// Use a LEFT JOIN to combine data from the "users" and "orders" tables
$stmt = $pdo->prepare("
    SELECT users.name, orders.order_date
    FROM users
    LEFT JOIN orders ON users.id = orders.user_id
    WHERE users.country = ?
");
$stmt->execute(["USA"]);
$results = $stmt->fetchAll();

// Example 3: Use LIMIT and OFFSET to paginate results
$page = 2;
$limit = 10;
$offset = ($page - 1) * $limit;
$stmt = $pdo->prepare("SELECT * FROM products LIMIT ? OFFSET ?");
$stmt->execute([$limit, $offset]);
$products = $stmt->fetchAll();

// Example 4: Use parameter binding to prevent SQL injection attacks
$name = "John";
$stmt = $pdo->prepare("SELECT * FROM users WHERE name = ?");
$stmt->execute([$name]);
$user = $stmt->fetch();

// Close the database connection
$pdo = null;

?>
