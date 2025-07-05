<?php
/**
 * Автодополнение для авторов
 */
declare(strict_types=1);

// Подключаем конфигурацию и классы
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/autoload.php';

// Настройка заголовков ответа
ResponseHelper::setApiHeaders();

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) {
    ResponseHelper::json([]);
}

try {
    // Используем DatabaseHelper для поиска авторов
    $result = DatabaseHelper::searchAuthors($q);
    ResponseHelper::json($result);
    
} catch (PDOException $e) {
    ResponseHelper::databaseError('Ошибка при выполнении запроса');
}