<?php
/**
 * Скрипт переноса данных из MySQL в Meilisearch
 * Перенос базы стихов для детей
 */

declare(strict_types=1);

// Подключаем конфигурацию
require_once __DIR__ . '/config/config.php';

// Конфигурация Meilisearch
$meilisearchConfig = [
    'host'  => MEILISEARCH_HOST,
    'key'   => MEILISEARCH_KEY,
    'index' => MEILISEARCH_INDEX
];

// Получаем подключение к БД
try {
    $pdo = getPdo();
} catch (PDOException $e) {
    die("Ошибка подключения к MySQL: " . $e->getMessage());
}

// Функция для нормализации первой строки
function normalizeFirstLine($text) {
    if (empty($text)) return '';
    
    // Убираем основные знаки препинания
    $normalized = str_replace(['.', ',', '!', '?', ';', ':', '—', '–', '-', '«', '»', '"', '"', '(', ')', '…'], ' ', $text);
    
    // Приводим к нижнему регистру
    $normalized = mb_strtolower($normalized, 'UTF-8');
    
    // Убираем лишние пробелы
    $normalized = preg_replace('/\s+/', ' ', trim($normalized));
    
    return $normalized;
}

// Функция для генерации вариантов имен автора
function generateNameVariants($firstName, $middleName, $lastName) {
    $variants = [];
    
    if (!empty($firstName) && !empty($lastName)) {
        // А.С. Пушкин
        $firstInitial = mb_substr($firstName, 0, 1, 'UTF-8') . '.';
        $middleInitial = !empty($middleName) ? mb_substr($middleName, 0, 1, 'UTF-8') . '.' : '';
        
        if (!empty($middleInitial)) {
            $variants[] = $firstInitial . $middleInitial . ' ' . $lastName;
            $variants[] = $firstInitial . ' ' . $middleInitial . ' ' . $lastName;
            $variants[] = $lastName . ' ' . $firstInitial . $middleInitial;
        } else {
            $variants[] = $firstInitial . ' ' . $lastName;
            $variants[] = $lastName . ' ' . $firstInitial;
        }
        
        // Только фамилия
        $variants[] = $lastName;
    }
    
    return array_unique($variants);
}

// Функция для получения настроек CURL
function getCurlOptions($url, $method = 'GET', $data = null, $headers = []) {
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_VERBOSE => false
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        if ($data !== null) {
            $options[CURLOPT_POSTFIELDS] = $data;
        }
    } elseif ($method !== 'GET') {
        $options[CURLOPT_CUSTOMREQUEST] = $method;
    }

    if (!empty($headers)) {
        $options[CURLOPT_HTTPHEADER] = $headers;
    }

    return $options;
}

// Функция отправки данных в Meilisearch
function sendToMeilisearch($documents, $meilisearchConfig) {
    $url = $meilisearchConfig['host'] . '/indexes/' . $meilisearchConfig['index'] . '/documents';
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $meilisearchConfig['key']
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, getCurlOptions(
        $url,
        'POST',
        json_encode($documents, JSON_UNESCAPED_UNICODE),
        $headers
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception("Ошибка соединения с Meilisearch: $error");
    }
    
    if ($httpCode !== 202) {
        throw new Exception("Ошибка отправки в Meilisearch: HTTP $httpCode, Response: $response");
    }
    
    return json_decode($response, true);
}

// Очистка индекса
function clearIndex($meilisearchConfig) {
    $url = $meilisearchConfig['host'] . '/indexes/' . $meilisearchConfig['index'] . '/documents';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $meilisearchConfig['key']
        ]
    ]);
    
    curl_exec($ch);
    curl_close($ch);
    
    echo "Индекс очищен\n";
}

// Основная логика переноса
try {
    echo "Начинаем перенос данных...\n";
    
    // Очищаем индекс
    clearIndex($meilisearchConfig);
    
    $documents = [];
    
    // 1. Переносим авторов
    echo "Переносим авторов...\n";
    $authorsQuery = "
        SELECT id, first_name, middle_name, last_name 
        FROM authors 
        WHERE deleted_at IS NULL
    ";
    
    $authors = $pdo->query($authorsQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($authors as $author) {
        $fullName = trim(implode(' ', array_filter([
            $author['first_name'],
            $author['middle_name'],
            $author['last_name']
        ])));
        
        $variants = generateNameVariants(
            $author['first_name'],
            $author['middle_name'],
            $author['last_name']
        );
        
        $documents[] = [
            'id' => 'author_' . $author['id'],
            'type' => 'author',
            'name' => $fullName,
            'name_variants' => $variants
        ];
    }
    
    echo "Найдено авторов: " . count($authors) . "\n";
    
    // 2. Переносим стихи и фрагменты
    echo "Переносим стихи и фрагменты...\n";
    
    // Сначала получаем основные данные
    $poemsQuery = "
        SELECT 
            p.`id` as poem_id,
            p.`title`,
            p.`is_divided`,
            p.`year_written`,
            a.`id` as author_id,
            TRIM(CONCAT_WS(' ', a.`first_name`, a.`middle_name`, a.`last_name`)) as author_name,
            f.`id` as fragment_id,
            f.`label` as fragment_label,
            f.`grade_level`
        FROM `poems` p
        LEFT JOIN `poem_authors` pa ON p.`id` = pa.`poem_id`
        LEFT JOIN `authors` a ON pa.`author_id` = a.`id`  
        LEFT JOIN `fragments` f ON p.`id` = f.`poem_id`
        WHERE p.`deleted_at` IS NULL 
        AND (f.`deleted_at` IS NULL OR f.`id` IS NULL)
        ORDER BY p.`id`, f.`id`
    ";
    
    $results = $pdo->query($poemsQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    // Собираем все fragment_id для батчевого получения строк
    $fragmentIds = [];
    foreach ($results as $row) {
        if ($row['fragment_id']) {
            $fragmentIds[] = $row['fragment_id'];
        }
    }
    
    // Получаем все строки одним запросом
    $allLines = [];
    if (!empty($fragmentIds)) {
        $placeholders = str_repeat('?,', count($fragmentIds) - 1) . '?';
        $allLinesQuery = "
            SELECT fragment_id, text, line_number 
            FROM `lines` 
            WHERE fragment_id IN ($placeholders) AND deleted_at IS NULL 
            ORDER BY fragment_id, line_number
        ";
        $stmt = $pdo->prepare($allLinesQuery);
        $stmt->execute($fragmentIds);
        $linesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Группируем строки по fragment_id
        foreach ($linesData as $line) {
            $allLines[$line['fragment_id']][] = $line['text'];
        }
    }
    
    // Заполняем тексты в результатах
    foreach ($results as &$row) {
        if ($row['fragment_id'] && isset($allLines[$row['fragment_id']])) {
            $lines = $allLines[$row['fragment_id']];
            $row['full_text'] = implode("\n", $lines);
            $row['first_line'] = isset($lines[0]) ? $lines[0] : '';
        } else {
            $row['full_text'] = '';
            $row['first_line'] = '';
        }
    }
    
    // Предварительно создаем индекс авторов для быстрого поиска
    $authorsIndex = [];
    foreach ($authors as $author) {
        $authorsIndex[$author['id']] = $author;
    }
    
    $processedPoems = [];
    
    foreach ($results as $row) {
        $poemId = $row['poem_id'];
        $authorName = $row['author_name'];
        $authorVariants = [];
        
        // Получаем варианты имени автора из индекса
        if ($row['author_id'] && isset($authorsIndex[$row['author_id']])) {
            $author = $authorsIndex[$row['author_id']];
            $authorVariants = generateNameVariants(
                $author['first_name'],
                $author['middle_name'],
                $author['last_name']
            );
        }
        
        // Для крупных произведений создаем отдельный документ (только один раз)
        if ($row['is_divided'] && !isset($processedPoems[$poemId])) {
            $firstLine = $row['first_line'] ?: '';
            
            $documents[] = [
                'id' => 'poem_large_' . $poemId,
                'type' => 'poem_large',
                'title' => $row['title'],
                'first_line' => $firstLine,
                'first_line_normalized' => normalizeFirstLine($firstLine),
                'author_id' => $row['author_id'],
                'author_name' => $authorName,
                'author_name_variants' => $authorVariants,
                'year_written' => $row['year_written']
            ];
            
            $processedPoems[$poemId] = true;
        }
        
        // Создаем документ для фрагмента (или цельного стиха)
        if ($row['fragment_id'] && $row['full_text']) {
            $firstLine = $row['first_line'] ?: '';
            $isStandalone = !$row['is_divided'];
            
            $fragmentDoc = [
                'id' => 'poem_small_' . $row['fragment_id'],
                'type' => 'poem_small',
                'title' => $row['title'],
                'first_line' => $firstLine,
                'first_line_normalized' => normalizeFirstLine($firstLine),
                'author_id' => $row['author_id'],
                'author_name' => $authorName,
                'author_name_variants' => $authorVariants,
                'full_text' => $row['full_text'],
                'is_standalone' => $isStandalone,
                'grade_level' => $row['grade_level'],
                'year_written' => $row['year_written']
            ];
            
            // Для фрагментов крупных произведений добавляем связь с родителем
            if (!$isStandalone) {
                $fragmentDoc['parent_poem_id'] = $poemId;
                $fragmentDoc['parent_poem_title'] = $row['title'];
                if ($row['fragment_label']) {
                    $fragmentDoc['fragment_label'] = $row['fragment_label'];
                }
            }
            
            $documents[] = $fragmentDoc;
        }
    }
    
    echo "Найдено документов для переноса: " . count($documents) . "\n";
    
    // Отправляем данные в Meilisearch батчами по 100 документов
    $batchSize = 100;
    $batches = array_chunk($documents, $batchSize);
    
    foreach ($batches as $i => $batch) {
        echo "Отправляем батч " . ($i + 1) . " из " . count($batches) . " (" . count($batch) . " документов)...\n";
        $response = sendToMeilisearch($batch, $meilisearchConfig);
        echo "Задача: " . $response['taskUid'] . "\n";
        
        // Небольшая пауза между батчами
        sleep(1);
    }
    
    echo "\nПеренос завершен!\n";
    echo "Всего документов: " . count($documents) . "\n";
    echo "- Авторов: " . count($authors) . "\n";
    echo "- Стихов и фрагментов: " . (count($documents) - count($authors)) . "\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
?>