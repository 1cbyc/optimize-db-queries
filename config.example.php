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
    ],
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'",
    ],
];

