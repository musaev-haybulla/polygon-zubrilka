<?php
/**
 * Обработчик добавления озвучки с выбором порядка сортировки
 */
declare(strict_types=1);

// Подключаем конфигурацию и классы
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/autoload.php';

// Настройка отображения ошибок для разработки
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: poem_list.php');
    exit;
}

// Валидация данных
$fragmentId = (int)($_POST['fragmentId'] ?? 0);
$audioTitle = trim($_POST['audioTitle'] ?? '');
$voiceType = (int)($_POST['voiceType'] ?? 0);
$sortOrder = (int)($_POST['sortOrder'] ?? 1);
$trimAudio = !empty($_POST['trimAudio']);

if ($fragmentId <= 0 || empty($audioTitle) || $sortOrder <= 0) {
    header('Location: poem_list.php?error=invalid_data');
    exit;
}

// Проверяем наличие файла
if (!isset($_FILES['audioFile']) || $_FILES['audioFile']['error'] !== UPLOAD_ERR_OK) {
    header('Location: poem_list.php?error=file_upload');
    exit;
}

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Проверяем существование фрагмента
    $stmt = $pdo->prepare("
        SELECT id FROM fragments 
        WHERE id = :fragment_id AND deleted_at IS NULL
    ");
    $stmt->execute(['fragment_id' => $fragmentId]);
    if (!$stmt->fetch()) {
        header('Location: poem_list.php?error=fragment_not_found');
        exit;
    }

    // Начинаем транзакцию
    $pdo->beginTransaction();

    // Сдвигаем существующие озвучки, если нужно
    if ($sortOrder > 1) {
        $stmt = $pdo->prepare("
            UPDATE audio_tracks 
            SET sort_order = sort_order + 1 
            WHERE fragment_id = :fragment_id 
            AND sort_order >= :sort_order 
            AND deleted_at IS NULL
        ");
        $stmt->execute([
            'fragment_id' => $fragmentId,
            'sort_order' => $sortOrder
        ]);
    } else {
        // Если добавляем первым, сдвигаем все существующие
        $stmt = $pdo->prepare("
            UPDATE audio_tracks 
            SET sort_order = sort_order + 1 
            WHERE fragment_id = :fragment_id 
            AND deleted_at IS NULL
        ");
        $stmt->execute(['fragment_id' => $fragmentId]);
    }

    // Сохраняем аудиофайл
    $uploadDir = __DIR__ . '/uploads/audio/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileExtension = pathinfo($_FILES['audioFile']['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('audio_') . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    if (!move_uploaded_file($_FILES['audioFile']['tmp_name'], $filePath)) {
        throw new Exception('Не удалось сохранить аудиофайл');
    }

    // Получаем длительность файла (базовый способ)
    $duration = 0;
    if (function_exists('shell_exec') && $fileExtension === 'mp3') {
        $output = shell_exec("ffprobe -i '$filePath' -show_entries format=duration -v quiet -of csv=\"p=0\"");
        if ($output !== null) {
            $duration = floatval(trim($output));
        }
    }

    // Вставляем запись в базу данных
    $stmt = $pdo->prepare("
        INSERT INTO audio_tracks 
        (fragment_id, file_path, duration, is_ai_generated, title, sort_order, status, is_default, created_at, updated_at) 
        VALUES 
        (:fragment_id, :file_path, :duration, :is_ai_generated, :title, :sort_order, :status, :is_default, NOW(), NOW())
    ");
    
    $stmt->execute([
        'fragment_id' => $fragmentId,
        'file_path' => 'uploads/audio/' . $fileName,
        'duration' => $duration,
        'is_ai_generated' => $voiceType,
        'title' => $audioTitle,
        'sort_order' => $sortOrder,
        'status' => $trimAudio ? 'draft' : 'active',
        'is_default' => 0
    ]);

    // Подтверждаем транзакцию
    $pdo->commit();

    // Если нужна обрезка, перенаправляем на следующий шаг
    if ($trimAudio) {
        $audioId = $pdo->lastInsertId();
        header("Location: add_audio_step2.php?id=$audioId");
    } else {
        header('Location: poem_list.php?success=audio_added');
    }
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Удаляем файл если он был загружен
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }
    
    error_log("Error in add_audio_step1.php: " . $e->getMessage());
    header('Location: poem_list.php?error=processing_failed');
    exit;
}