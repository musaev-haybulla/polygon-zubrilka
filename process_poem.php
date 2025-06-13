<?php
declare(strict_types=1);

// Подключаем конфигурацию
require __DIR__ . '/config/config.php';

// Настройка отображения ошибок для разработки
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

/**
 * Логирование сообщений в файл
 * @param string $msg Сообщение для логирования
 */
function logMsg(string $msg): void {
    $time = date('Y-m-d H:i:s');
    $logFile = LOGS_DIR . '/poem_import.log';
    
    // Создаем директорию для логов, если её нет
    if (!is_dir(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }
    
    file_put_contents($logFile, "[$time] $msg\n", FILE_APPEND);
}

$pdo    = getPdo();
$userId = (int)($_SESSION['user_id'] ?? 0);
logMsg("Начало обработки. UserID={$userId}");

// Сбор данных
$title       = trim($_POST['title']        ?? '');
$grade       = trim($_POST['grade_level']  ?? '');
$poemText    = trim($_POST['poem_text']    ?? '');
$rawAuthors  = $_POST['author_ids']        ?? [];
$fragmentId  = (int)($_POST['fragment_id'] ?? 0);
$poemId      = (int)($_POST['poem_id']     ?? 0);
$label       = trim($_POST['label']        ?? '');
$structure   = trim($_POST['structure_info'] ?? '');
$sortOrder   = (int)($_POST['sort_order']  ?? 0);

// Валидация
$allowedGrades = ['primary', 'middle', 'secondary'];
$errors = [];

// Проверяем, что текст фрагмента не пустой
if ($poemText === '') {
    $errors[] = 'Текст фрагмента не может быть пустым';
}

// Проверяем, что poemId валидный
if ($poemId < 0) {
    $errors[] = 'Неверный идентификатор стихотворения';
}

// Если создаем новое стихотворение, проверяем обязательные поля
if ($poemId === 0) {
    if ($title === '') {
        $errors[] = 'Название стихотворения не может быть пустым';
    }
    if (!in_array($grade, $allowedGrades, true)) {
        $errors[] = 'Неверно указан грейд';
    }
}

// Проверяем sort_order
if ($sortOrder < 0) {
    $errors[] = 'Неверный порядок сортировки';
}

if (!empty($errors)) {
    logMsg("Ошибка валидации: " . implode(", ", $errors));
    die('Неверные данные. ' . implode(" ", $errors));
}

// Обработка авторов
$authorIds = [];
$rawAuthors = array_filter(array_map('trim', (array)$rawAuthors), fn($v) => $v !== '');
foreach ($rawAuthors as $entry) {
    if (ctype_digit($entry)) {
        $authorIds[] = (int)$entry;
    } else {
        $parts = preg_split('/\s+/u', $entry, -1, PREG_SPLIT_NO_EMPTY);
        $first = array_shift($parts) ?: '';
        $last  = count($parts) > 0 ? array_pop($parts) : '';
        $middle = $parts ? implode(' ', $parts) : null;

        if ($first === '') continue;

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO authors (first_name, middle_name, last_name, created_at, updated_at)
                 VALUES (:f, :m, :l, NOW(), NOW())"
            );
            $stmt->execute([':f' => $first, ':m' => $middle, ':l' => $last]);
            $authorIds[] = (int)$pdo->lastInsertId();
        } catch (PDOException $e) {
            logMsg("Ошибка создания автора '{$entry}': " . $e->getMessage());
        }
    }
}
$authorIds = array_unique($authorIds);

try {
    $pdo->beginTransaction();
    logMsg("BEGIN TRANSACTION");

    // Создаем новое стихотворение, если не указано существующее
    if ($poemId === 0) {
        $isDivided = isset($_POST['is_divided']) ? 1 : 0;
        $stmt = $pdo->prepare(
            "INSERT INTO poems 
             (owner_id, title, is_divided, status, created_at, updated_at)
             VALUES (:o, :t, :d, 'draft', NOW(), NOW())"
        );
        $stmt->execute([':o' => $userId, ':t' => $title, ':d' => $isDivided]);
        $poemId = (int)$pdo->lastInsertId();
        logMsg("Created new poem_id={$poemId}, is_divided={$isDivided}");
    }

    // Определяем sort_order для нового фрагмента
    if ($fragmentId === 0) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(MAX(sort_order), 0) + 1 
             FROM fragments 
             WHERE poem_id = :p"
        );
        $stmt->execute([':p' => $poemId]);
        $newSortOrder = (int)$stmt->fetchColumn();
        
        // Если указан sort_order, сдвигаем существующие записи
        if ($sortOrder > 0 && $sortOrder < $newSortOrder) {
            $stmt = $pdo->prepare(
                "UPDATE fragments 
                 SET sort_order = sort_order + 1 
                 WHERE poem_id = :p AND sort_order >= :s"
            );
            $stmt->execute([':p' => $poemId, ':s' => $sortOrder]);
            $newSortOrder = $sortOrder;
        }
    }

    // Создаем или обновляем фрагмент
    if ($fragmentId === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO fragments 
             (poem_id, owner_id, label, structure_info, sort_order, grade_level, status, created_at, updated_at)
             VALUES (:p, :o, :l, :s, :so, :g, 'draft', NOW(), NOW())"
        );
        $stmt->execute([
            ':p'  => $poemId,
            ':o'  => $userId,
            ':l'  => $label ?: null,
            ':s'  => $structure ?: null,
            ':so' => $newSortOrder,
            ':g'  => $grade
        ]);
        $fragmentId = (int)$pdo->lastInsertId();
        logMsg("Created new fragment_id={$fragmentId}");
    } else {
        $stmt = $pdo->prepare(
            "UPDATE fragments 
             SET label = :l, structure_info = :s, grade_level = :g, updated_at = NOW()
             WHERE id = :id AND owner_id = :o"
        );
        $stmt->execute([
            ':id' => $fragmentId,
            ':o'  => $userId,
            ':l'  => $label ?: null,
            ':s'  => $structure ?: null,
            ':g'  => $grade
        ]);
        logMsg("Updated fragment_id={$fragmentId}");
    }

    // Привязка авторов к стихотворению
    if (!empty($authorIds)) {
        // Удаляем старые привязки, если это обновление
        if ($fragmentId > 0) {
            $stmt = $pdo->prepare(
                "DELETE FROM poem_authors 
                 WHERE poem_id = ? AND author_id NOT IN (" . 
                 implode(',', array_fill(0, count($authorIds), '?')) . ")"
            );
            $stmt->execute(array_merge([$poemId], $authorIds));
        }

        // Добавляем новые привязки
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO poem_authors (poem_id, author_id) 
             VALUES (:p, :a)"
        );
        foreach ($authorIds as $aid) {
            $stmt->execute([':p' => $poemId, ':a' => $aid]);
        }
    }

    // Обработка строк фрагмента
    $lines = preg_split('/\R/u', $poemText);
    $blocks = [];
    $tmp = [];
    foreach ($lines as $ln) {
        if (trim($ln) === '') {
            if ($tmp) { $blocks[] = $tmp; $tmp = []; }
        } else {
            $tmp[] = trim($ln);
        }
    }
    if ($tmp) { $blocks[] = $tmp; }

    // Удаляем старые строки фрагмента
    $stmt = $pdo->prepare("DELETE FROM `lines` WHERE `fragment_id` = :f");
    $stmt->execute([':f' => $fragmentId]);

    // Вставляем новые строки
    $stmt = $pdo->prepare(
        "INSERT INTO `lines` 
         (`fragment_id`, `line_number`, `text`, `end_line`, `created_at`, `updated_at`)
         VALUES (:f, :n, :txt, :e, NOW(), NOW())"
    );

    $num = 1;
    foreach ($blocks as $blk) {
        $cnt = count($blk);
        foreach ($blk as $i => $txt) {
            $stmt->execute([
                ':f'   => $fragmentId,
                ':n'   => $num,
                ':txt' => $txt,
                ':e'   => ($i === $cnt - 1) ? 1 : 0
            ]);
            $num++;
        }
    }

    $pdo->commit();
    logMsg("COMMIT successful for poem_id={$poemId}, fragment_id={$fragmentId}");

    // Редирект обратно на форму с сообщением об успехе
    $redirect = isset($_POST['is_divided']) ? 'add_poem_fragment.php' : 'add_simple_poem.php';
    header("Location: {$redirect}?success=1");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logMsg("Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    die('Произошла ошибка при сохранении. Пожалуйста, попробуйте позже.');
}
