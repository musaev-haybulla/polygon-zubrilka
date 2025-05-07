<?php
declare(strict_types=1);
require 'config.php';

/** Простое логирование */
function logMsg(string $msg): void {
    $time = date('Y-m-d H:i:s');
    file_put_contents(__DIR__ . '/poem_import.log', "[$time] $msg\n", FILE_APPEND);
}

$pdo    = getPdo();
$userId = (int)($_SESSION['user_id'] ?? 0);
logMsg("Начало обработки. UserID={$userId}");

// Сбор данных
$title      = trim($_POST['title']       ?? '');
$grade      = trim($_POST['grade_level'] ?? '');
$poemText   = trim($_POST['poem_text']   ?? '');
$rawAuthors = $_POST['author_ids']       ?? [];
$rawAuthors = array_filter(array_map('trim', (array)$rawAuthors), fn($v)=>$v!=='');
logMsg("Вход: title='{$title}', grade='{$grade}', rawAuthors=[".implode(',', $rawAuthors)."]");

// Валидация
$allowedGrades = ['primary','middle','secondary'];
if (
    $title === '' ||
    $poemText === '' ||
    !in_array($grade, $allowedGrades, true) ||
    empty($rawAuthors)
) {
    logMsg("Ошибка валидации");
    die('Неверные данные.');
}

// Разбиение на строфы
$lines = preg_split('/\R/u', $poemText);
$blocks = [];
$tmp    = [];
foreach ($lines as $ln) {
    if (trim($ln) === '') {
        if ($tmp) { $blocks[] = $tmp; $tmp = []; }
    } else {
        $tmp[] = $ln;
    }
}
if ($tmp) { $blocks[] = $tmp; }
logMsg('Блоков: '.count($blocks));

// Обработка авторов: собираем IDs, создаём новых при необходимости
$authorIds = [];
foreach ($rawAuthors as $entry) {
    if (ctype_digit($entry)) {
        $authorIds[] = (int)$entry;
        logMsg("Существующий author_id={$entry}");
    } else {
        $parts = preg_split('/\s+/u', $entry, -1, PREG_SPLIT_NO_EMPTY);
        $first = array_shift($parts);
        $last  = array_pop($parts) ?: '';
        $middle= $parts ? implode(' ', $parts) : null;
        logMsg("Создание автора '{$entry}'");
        $stmtA = $pdo->prepare(
            "INSERT INTO authors
               (first_name,middle_name,last_name,created_at,updated_at)
             VALUES
               (:f,:m,:l,NOW(),NOW())"
        );
        $stmtA->execute([':f'=>$first,':m'=>$middle,':l'=>$last]);
        $newId = (int)$pdo->lastInsertId();
        $authorIds[] = $newId;
        logMsg("Автор создан ID={$newId}");
    }
}

try {
    $pdo->beginTransaction();
    logMsg("BEGIN TRANSACTION");

    // Вставка поэмы
    $stmtP = $pdo->prepare(
        "INSERT INTO poems
           (owner_id,title,grade_level,is_divided,status,created_at,updated_at)
         VALUES
           (:o,:t,:g,0,'draft',NOW(),NOW())"
    );
    $stmtP->execute([':o'=>$userId,':t'=>$title,':g'=>$grade]);
    $poemId = (int)$pdo->lastInsertId();
    logMsg("Inserted poem_id={$poemId}");

    // Вставка фрагмента
    $stmtF = $pdo->prepare(
        "INSERT INTO fragments
           (poem_id,owner_id,sort_order,status,created_at,updated_at)
         VALUES
           (:p,:o,1,'draft',NOW(),NOW())"
    );
    $stmtF->execute([':p'=>$poemId,':o'=>$userId]);
    $fragId = (int)$pdo->lastInsertId();
    logMsg("Inserted fragment_id={$fragId}");

    // Связь поэм и авторов
    $link = $pdo->prepare(
        "INSERT INTO poem_authors (poem_id,author_id) VALUES (:p,:a)"
    );
    foreach (array_unique($authorIds) as $aid) {
        $link->execute([':p'=>$poemId,':a'=>$aid]);
        logMsg("Linked poem_id={$poemId} with author_id={$aid}");
    }

    // Удаление старых строк
    $del = $pdo->prepare("DELETE FROM `lines` WHERE `fragment_id` = :f");
    $del->execute([':f'=>$fragId]);
    logMsg("Old lines deleted for fragment_id={$fragId}");

    // Вставка строк
    $insL = $pdo->prepare(
        "INSERT INTO `lines`
           (`fragment_id`,`line_number`,`text`,`end_line`,`created_at`,`updated_at`)
         VALUES
           (:f,:n,:txt,:e,NOW(),NOW())"
    );
    $num = 1;
    foreach ($blocks as $blk) {
        $cnt = count($blk);
        foreach ($blk as $i => $txt) {
            $insL->execute([
                ':f'   => $fragId,
                ':n'   => $num,
                ':txt' => trim($txt),
                ':e'   => ($i===$cnt-1) ? 1 : 0,
            ]);
            logMsg("Inserted line #{$num}");
            $num++;
        }
    }

    $pdo->commit();
    logMsg("COMMIT");

    // Редирект на форму с flash
    header('Location: add_simple_poem.php?success=1');
    exit;

} catch (Throwable $e) {
    $pdo->rollBack();
    logMsg("ROLLBACK: " . $e->getMessage());
    die('Ошибка: ' . $e->getMessage());
}
