<?php
/**
 * Восстановление оригинального аудиофайла
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/autoload.php';
require __DIR__ . '/classes/AudioFileHelper.php';

// Получаем данные из запроса
$input = json_decode(file_get_contents('php://input'), true);
$audioId = (int)($input['audio_id'] ?? 0);

if (!$audioId) {
    echo json_encode(['success' => false, 'error' => 'Не передан ID озвучки']);
    exit;
}

try {
    $pdo = getPdo();
    $pdo->beginTransaction();

    // Получаем данные аудиозаписи
    $stmt = $pdo->prepare("
        SELECT at.*, f.id as fragment_id
        FROM audio_tracks at
        JOIN fragments f ON at.fragment_id = f.id  
        WHERE at.id = ?
    ");
    $stmt->execute([$audioId]);
    $audioData = $stmt->fetch();

    if (!$audioData || !$audioData['original_filename']) {
        throw new Exception('Оригинальный файл не найден или аудио не было обрезано');
    }

    // Получаем длительность оригинального файла
    $originalPath = AudioFileHelper::getAbsoluteAudioPath($audioData['fragment_id'], $audioData['original_filename']);
    
    if (!file_exists($originalPath)) {
        throw new Exception('Оригинальный файл не существует на диске');
    }

    // Получаем длительность оригинального файла
    $cmd = sprintf("%s -i '%s' -show_entries format=duration -v quiet -of csv=\"p=0\"", AudioFileHelper::getFFprobePath(), $originalPath);
    $output = shell_exec($cmd);
    $originalDuration = $output ? floatval(trim($output)) : 0;

    if ($originalDuration <= 0) {
        throw new Exception('Не удалось определить длительность оригинального файла');
    }

    // Удаляем обрезанный файл
    AudioFileHelper::deleteAudioFile($audioData['fragment_id'], $audioData['filename']);

    // Обновляем базу данных - возвращаем на оригинал
    $stmt = $pdo->prepare("
        UPDATE audio_tracks SET 
            filename = original_filename,
            original_filename = NULL,
            duration = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$originalDuration, $audioId]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error in restore_original_audio.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>