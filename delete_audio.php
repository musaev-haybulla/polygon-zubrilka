<?php
declare(strict_types=1);

header('Content-Type: application/json');

require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/autoload.php';

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

    // 1. Находим путь к файлу
    $stmt = $pdo->prepare("SELECT file_path FROM audio_tracks WHERE id = :id");
    $stmt->execute([':id' => $audioId]);
    $filePath = $stmt->fetchColumn();

    if ($filePath) {
        // 2. Удаляем файл с диска
        $fullPath = __DIR__ . '/' . $filePath;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    // 3. Выполняем "мягкое удаление" (soft delete), устанавливая deleted_at
    // Предполагается, что связанные данные (например, лайки) обрабатываются в БД каскадно или триггерами
    $stmt = $pdo->prepare("UPDATE audio_tracks SET deleted_at = NOW() WHERE id = :id");
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
