<?php
/**
 * Автодополнение для авторов
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

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getPdo();
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    exit;
}

// Используем MATCH...AGAINST для релевантности + LIKE для подстановки
$sql = "
  SELECT
    id,
    CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name,
    MATCH(first_name,middle_name,last_name)
      AGAINST(:query IN NATURAL LANGUAGE MODE) AS score_ft
  FROM authors
  WHERE
    MATCH(first_name,middle_name,last_name) AGAINST(:query IN NATURAL LANGUAGE MODE)
    OR first_name   LIKE :like
    OR middle_name  LIKE :like
    OR last_name    LIKE :like
  ORDER BY score_ft DESC, full_name
  LIMIT 10
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':query' => $q,
        ':like'  => "%{$q}%"
    ]);
    
    $result = $stmt->fetchAll();
    echo json_encode($result);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка при выполнении запроса']);
}