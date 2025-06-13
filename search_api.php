<?php
/**
 * API для поиска стихотворений
 */
declare(strict_types=1);

// Включаем буферизацию вывода
ob_start();

// Подключаем конфигурацию
require __DIR__ . '/config/config.php';

// Настройка заголовков ответа
header('Content-Type: application/json; charset=UTF-8');

// Настройка отображения ошибок для разработки
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Получаем поисковый запрос
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

header('Cache-Control: no-cache, must-revalidate');

// Проверяем, что запрос не пустой
if (empty($query) || strlen($query) < 2) {
    echo json_encode(['success' => false, 'error' => 'Слишком короткий поисковый запрос']);
    exit;
}

// Подготавливаем поисковую строку для SQL LIKE
$searchTerm = '%' . $query . '%';

// Функция для получения первых двух строк стихотворения
function getFirstTwoLines($pdo, $poemId) {
    // Находим первый фрагмент стихотворения (с минимальным sort_order)
    $fragmentSql = "SELECT id FROM `fragments` 
                  WHERE poem_id = :poem_id 
                  AND deleted_at IS NULL 
                  ORDER BY sort_order ASC 
                  LIMIT 1";
    $stmt = $pdo->prepare($fragmentSql);
    $stmt->execute(['poem_id' => $poemId]);
    $fragmentId = $stmt->fetchColumn();
    
    if (!$fragmentId) {
        return ['<текст отсутствует>', ''];
    }
    
    // Теперь получаем первые две строки этого фрагмента
    $sql = "SELECT l.text FROM `lines` l
           WHERE l.fragment_id = :fragment_id
           AND l.line_number IN (1, 2)
           AND l.deleted_at IS NULL
           ORDER BY l.line_number
           LIMIT 2";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['fragment_id' => $fragmentId]);
    $lines = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return array_pad($lines, 2, ''); // Гарантируем, что всегда вернём массив из 2 элементов
}

try {
    $pdo = getPdo();
    $results = []; // Общий массив результатов
    
    // Создаем массивы для разных типов поиска
    // Массивы для хранения результатов по разным типам поиска
    $poemResults = [];
    $firstLineResults = [];
    $fragmentResults = [];
    
    // Наборы для отслеживания стихотворений и фрагментов, которые уже добавлены
    $addedPoemIds = [];
    $addedFragmentIds = [];
    
    // 1. Поиск по названию стихотворения
    $sql = "SELECT p.id, p.title, p.year_written,
           GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS author
           FROM `poems` p
           LEFT JOIN `poem_authors` pa ON p.id = pa.poem_id
           LEFT JOIN `authors` a ON pa.author_id = a.id
           WHERE p.title LIKE :query AND p.deleted_at IS NULL
           GROUP BY p.id
           LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['query' => $searchTerm]);
    
    foreach ($stmt->fetchAll() as $row) {
        $addedPoemIds[] = $row['id'];

        // Динамическая обработка фрагментов
        $fragStmt = $pdo->prepare("SELECT f.id, f.label FROM `fragments` f WHERE f.poem_id = :poem_id AND f.deleted_at IS NULL ORDER BY f.sort_order ASC");
        $fragStmt->execute(['poem_id' => $row['id']]);
        $fragments = $fragStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($fragments) > 1) {
            $allFragments = [];
            foreach ($fragments as $frag) {
                $lineStmt = $pdo->prepare("SELECT l.text FROM `lines` l WHERE l.fragment_id = :fragment_id AND l.line_number IN (1, 2) AND l.deleted_at IS NULL ORDER BY l.line_number ASC LIMIT 2");
                $lineStmt->execute(['fragment_id' => $frag['id']]);
                $fragLines = $lineStmt->fetchAll(PDO::FETCH_COLUMN);
                $fragLines = array_pad($fragLines, 2, '');
                $allFragments[] = [
                    'fragment_id' => $frag['id'],
                    'label' => $frag['label'],
                    'lines' => $fragLines
                ];
            }
            $poemResults[] = [
                'type' => 'poem',
                'id' => $row['id'],
                'title' => $row['title'],
                'year_written' => $row['year_written'],
                'author' => $row['author'] ? $row['author'] : 'Не указан',
                'fragments' => $allFragments
            ];
        } else {
            $firstLines = getFirstTwoLines($pdo, $row['id']);
            $poemResults[] = [
                'type' => 'poem',
                'id' => $row['id'],
                'title' => $row['title'],
                'year_written' => $row['year_written'],
                'author' => $row['author'] ? $row['author'] : 'Не указан',
                'lines' => $firstLines
            ];
        }
    }
    
    // 2. Поиск по первой строке
    $sql = "SELECT l.id AS line_id, l.text, f.id AS fragment_id, p.id AS poem_id, p.title AS poem_title, p.year_written,
           GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS author
           FROM `lines` l
           JOIN `fragments` f ON l.fragment_id = f.id
           JOIN `poems` p ON f.poem_id = p.id
           LEFT JOIN `poem_authors` pa ON p.id = pa.poem_id
           LEFT JOIN `authors` a ON pa.author_id = a.id
           WHERE l.line_number = 1 
           AND l.text LIKE :query
           AND l.deleted_at IS NULL
           AND f.deleted_at IS NULL
           AND p.deleted_at IS NULL
           GROUP BY p.id
           LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['query' => $searchTerm]);
    
    foreach ($stmt->fetchAll() as $row) {
        // Пропускаем стихотворения, которые уже нашли по названию
        if (in_array($row['poem_id'], $addedPoemIds)) {
            continue;
        }
        
        // Добавляем ID в набор
        $addedPoemIds[] = $row['poem_id'];
        
        // Получаем первые две строки
        $firstLines = getFirstTwoLines($pdo, $row['poem_id']);
        
        $firstLineResults[] = [
            'type' => 'first_line',
            'poem_id' => $row['poem_id'],
            'poem_title' => $row['poem_title'],
            'text' => $row['text'],
            'year_written' => $row['year_written'],
            'author' => $row['author'] ? $row['author'] : 'Не указан',
            'lines' => $firstLines
        ];
    }
    
    // 3. Поиск по метке фрагмента
    $sql = "SELECT f.id, f.label, p.id AS poem_id, p.title AS poem_title,
           GROUP_CONCAT(DISTINCT CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') AS author
           FROM `fragments` f 
           JOIN `poems` p ON f.poem_id = p.id
           LEFT JOIN `poem_authors` pa ON p.id = pa.poem_id
           LEFT JOIN `authors` a ON pa.author_id = a.id 
           WHERE f.label LIKE :query AND f.deleted_at IS NULL AND p.deleted_at IS NULL
           GROUP BY f.id
           LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['query' => $searchTerm]);
    
    foreach ($stmt->fetchAll() as $row) {
        // Пропускаем фрагменты, которые уже добавлены
        if (in_array($row['id'], $addedFragmentIds)) {
            continue;
        }
        
        // Добавляем фрагмент в набор
        $addedFragmentIds[] = $row['id'];
        
        // Получаем строки фрагмента
        $fragmentLinesSql = "SELECT l.text FROM `lines` l
                            WHERE l.fragment_id = :fragment_id
                            AND l.line_number <= 2
                            AND l.deleted_at IS NULL
                            ORDER BY l.line_number
                            LIMIT 2";
        $lineStmt = $pdo->prepare($fragmentLinesSql);
        $lineStmt->execute(['fragment_id' => $row['id']]);
        $fragmentLines = $lineStmt->fetchAll(PDO::FETCH_COLUMN);
        $fragmentLines = array_pad($fragmentLines, 2, '');
        
        $fragmentResults[] = [
            'type' => 'fragment',
            'id' => $row['id'],
            'poem_id' => $row['poem_id'],
            'poem_title' => $row['poem_title'],
            'label' => $row['label'],
            'author' => $row['author'] ? $row['author'] : 'Не указан',
            'lines' => $fragmentLines
        ];
    }
    
    // Объединяем результаты в правильном порядке, избегая дубликатов
    $results = array_merge($poemResults, $firstLineResults, $fragmentResults);
    
    // Возвращаем результаты с поддержкой кириллицы
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    // В случае ошибки возвращаем пустой массив
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'Произошла ошибка при выполнении поиска'], JSON_UNESCAPED_UNICODE);
}
