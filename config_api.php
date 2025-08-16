<?php
/**
 * API для получения конфигурации клиентской стороны
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config/config.php';

try {
    // Формируем конфигурацию для клиентской стороны
    $config = [
        'meilisearch' => [
            'host' => MEILISEARCH_HOST_EXTERNAL,  // Для браузера используем localhost
            'key' => MEILISEARCH_KEY,
            'index' => MEILISEARCH_INDEX
        ],
        'search' => [
            'results_per_page' => SEARCH_RESULTS_PER_PAGE,
            'debounce_ms' => SEARCH_DEBOUNCE_MS
        ]
    ];
    
    // Возвращаем конфигурацию в формате JSON
    echo json_encode($config, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Ошибка получения конфигурации',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}