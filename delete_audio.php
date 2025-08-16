<?php
declare(strict_types=1);

header('Content-Type: application/json');

require __DIR__ . '/config/config.php';
require __DIR__ . '/vendor/autoload.php';

use App\AudioFileHelper;


// Получаем данные из запроса
$input = json_decode(file_get_contents('php://input'), true);
$audioId = $input['id'] ?? null;

if (!$audioId) {
    echo json_encode(['success' => false, 'error' => 'Не передан ID озвучки']);
    exit;
}

$pdo = getPdo();

try {
    $pdo->beginTransaction();

    // Находим данные о файлах
    $stmt = $pdo->prepare("SELECT filename, original_filename, fragment_id FROM tracks WHERE id = :id");
    $stmt->execute([':id' => $audioId]);
    $audioData = $stmt->fetch();

    if (!$audioData) {
        echo json_encode(['success' => false, 'error' => 'Запись не найдена или уже удалена']);
        exit;
    }

    // Решение: Hard delete - полностью удаляем из БД и файлы
    // Это проще для управления и не создает мусор в файловой системе
    
    // 1. Удаляем текущий файл с диска
    if ($audioData['filename']) {
        AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $audioData['filename']);
    }
    
    // 2. Удаляем оригинальный файл если есть
    if ($audioData['original_filename']) {
        AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $audioData['original_filename']);
    }

    // 3. Удаляем связанные данные (таймкоды разметки)
    $stmt = $pdo->prepare("DELETE FROM timings WHERE track_id = :id");
    $stmt->execute([':id' => $audioId]);

    // 4. Выполняем полное удаление записи из БД
    $stmt = $pdo->prepare("DELETE FROM tracks WHERE id = :id");
    $stmt->execute([':id' => $audioId]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $pdo->rollBack();
    if (APP_ENV === 'development') {
        echo json_encode(['success' => false, 'error' => 'Ошибка при удалении: ' . $e->getMessage()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Произошла ошибка на сервере.']);
    }
}
