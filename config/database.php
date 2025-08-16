<?php
declare(strict_types=1);

// Database configuration
const DB_HOST    = 'db';
const DB_NAME    = 'app';
const DB_USER    = 'app';
const DB_PASS    = 'secret';
const DB_CHARSET = 'utf8mb4';

// PDO options
const PDO_OPTIONS = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
