<?php
declare(strict_types=1);

// Database configuration
const DB_HOST    = '127.0.0.1';
const DB_NAME    = 'polygon-zubrilka-test';
const DB_USER    = 'root';
const DB_PASS    = 'root';
const DB_CHARSET = 'utf8mb4';

// PDO options
const PDO_OPTIONS = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
