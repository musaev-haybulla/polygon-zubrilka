<?php
/**
 * Получение фрагментов стихотворения по ID
 */
declare(strict_types=1);

// Подключаем конфигурацию и классы
require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

use App\ResponseHelper;
use App\DatabaseHelper;
// Настройка заголовков ответа
ResponseHelper::setApiHeaders();

$poemId = (int)($_GET['poem_id'] ?? 0);

if ($poemId <= 0) {
    ResponseHelper::validationError('Invalid poem ID');
}

try {
    // Используем DatabaseHelper для получения фрагментов
    $fragments = DatabaseHelper::getFragmentsByPoemId($poemId);
    
    // Возвращаем результат
    ResponseHelper::success($fragments);
    
} catch (PDOException $e) {
    // Возвращаем ошибку
    ResponseHelper::databaseError('Ошибка при получении фрагментов');
}
