<?php
/**
 * Поисковый API для стихотворений
 */
declare(strict_types=1);

// Подключаем конфигурацию и классы
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/autoload.php';

// Настройка заголовков
ResponseHelper::setApiHeaders();
ResponseHelper::setCorsHeaders();

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Получение и валидация поискового запроса
$query = trim($_GET['query'] ?? $_GET['q'] ?? '');
if (strlen($query) < 2) {
    ResponseHelper::validationError('Слишком короткий поисковый запрос');
}

try {
    // Выполняем поиск через сервис
    $searchService = new SearchService();
    $results = $searchService->performFullSearch($query);
    
    // Возвращаем результаты в том же формате, что и раньше
    ResponseHelper::json($results);
    
} catch (PDOException $e) {
    ResponseHelper::databaseError('Произошла ошибка при выполнении поиска');
} catch (Exception $e) {
    ResponseHelper::error('Произошла ошибка при выполнении поиска');
}