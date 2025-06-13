<?php
declare(strict_types=1);

// Подключаем конфигурационные файлы
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/search_config.php';
require_once __DIR__ . '/app_config.php';

// Инициализация сессии
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params(SESSION_LIFETIME, '/');
    session_start();
}

// Установка ID пользователя по умолчанию, если не установлен
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = DEFAULT_USER_ID;
}

/**
 * Возвращает PDO-соединение к базе данных
 * @return PDO
 * @throws PDOException Если не удалось подключиться к БД
 */
function getPdo(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );
    
    return new PDO($dsn, DB_USER, DB_PASS, PDO_OPTIONS);
}