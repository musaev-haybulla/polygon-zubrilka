<?php
declare(strict_types=1);

session_start();

// Настройки БД
const DB_HOST    = '127.0.0.1';
const DB_NAME    = 'polygon-zubrilka-test';
const DB_USER    = 'root';
const DB_PASS    = 'root';
const DB_CHARSET = 'utf8mb4';

/**
 * Возвращает PDO-соединение к базе данных
 */
function getPdo(): PDO
{
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    return new PDO($dsn, DB_USER, DB_PASS, $opts);
}

// Для примера: ID текущего пользователя хранится в сессии
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}