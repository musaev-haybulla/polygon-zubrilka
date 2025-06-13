<?php
/**
 * Получение фрагментов стихотворения по ID
 */
declare(strict_types=1);

// Подключаем конфигурацию
require __DIR__ . '/config/config.php';

// Настройка заголовков ответа
header('Content-Type: application/json; charset=UTF-8');

// Настройка отображения ошибок для разработки
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

$poemId = (int)($_GET['poem_id'] ?? 0);

if ($poemId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid poem ID']);
    exit;
}

try {
    // Получаем подключение к БД
    $pdo = getPdo();
    
    // Подготавливаем и выполняем запрос
    $stmt = $pdo->prepare(
        "SELECT `f`.`id`, `f`.`label`, `f`.`structure_info`, `f`.`sort_order`, `f`.`grade_level`, `l`.`text` AS `first_line`
         FROM `fragments` AS `f`
         LEFT JOIN `lines` AS `l` ON (`l`.`fragment_id` = `f`.`id` AND `l`.`line_number` = 1)
         WHERE `f`.`poem_id` = ?
         ORDER BY `f`.`sort_order`"
    );
    
    $stmt->execute([$poemId]);
    $fragments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Возвращаем результат
    echo json_encode([
        'success' => true,
        'data' => $fragments
    ]);
    
} catch (PDOException $e) {
    // Возвращаем ошибку
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка при получении фрагментов'
    ]);
}
