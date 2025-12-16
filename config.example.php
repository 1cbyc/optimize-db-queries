<?php
/**
 * Database Configuration Template
 * 
 * Copy this file to config.php and update with your database credentials
 */

return [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'your_database',
        'charset' => 'utf8mb4',
        'username' => 'your_username',
        'password' => 'your_password',
        'port' => 3306, // Optional, defaults to 3306
    ],
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Return associative arrays
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'", // Set charset
    ],
    'profiling' => [
        'default_iterations' => 5, // Number of times to run query for profiling
        'slow_query_threshold' => 200, // Milliseconds - queries slower than this are flagged
    ],
];

