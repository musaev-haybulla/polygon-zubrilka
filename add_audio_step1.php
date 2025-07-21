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

// Определяем режим работы
$editMode = !empty($_POST['editMode']) && $_POST['editMode'] === '1';
$audioId = $editMode ? (int)($_POST['audioId'] ?? 0) : 0;

// Валидация данных
$fragmentId = (int)($_POST['fragmentId'] ?? 0);
$audioTitle = trim($_POST['audioTitle'] ?? '');
$voiceType = (int)($_POST['voiceType'] ?? 0);
$sortOrder = $editMode ? 0 : (int)($_POST['sortOrder'] ?? 1); // Сортировка не нужна при редактировании
$trimAudio = !empty($_POST['trimAudio']);

if ($fragmentId <= 0 || empty($audioTitle)) {
    header('Location: poem_list.php?error=invalid_data');
    exit;
}

// Дополнительная валидация для режима редактирования
if ($editMode && $audioId <= 0) {
    header('Location: poem_list.php?error=invalid_audio_id');
    exit;
}

// Проверяем наличие файла (обязательно для добавления, опционально для редактирования)
$hasNewFile = isset($_FILES['audioFile']) && $_FILES['audioFile']['error'] === UPLOAD_ERR_OK;
if (!$editMode && !$hasNewFile) {
    header('Location: poem_list.php?error=file_upload');
    exit;
}

try {
    $pdo = getPdo();

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

    // Если режим редактирования, проверяем существование аудиозаписи
    if ($editMode) {
        $stmt = $pdo->prepare("
            SELECT id, file_path, original_file_path 
            FROM audio_tracks 
            WHERE id = :audio_id AND fragment_id = :fragment_id AND deleted_at IS NULL
        ");
        $stmt->execute(['audio_id' => $audioId, 'fragment_id' => $fragmentId]);
        $existingAudio = $stmt->fetch();
        
        if (!$existingAudio) {
            header('Location: poem_list.php?error=audio_not_found');
            exit;
        }
    }

    // Начинаем транзакцию
    $pdo->beginTransaction();

    if ($editMode) {
        // РЕЖИМ РЕДАКТИРОВАНИЯ
        $updateFields = [
            'title' => $audioTitle,
            'is_ai_generated' => $voiceType,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $params = [
            'audio_id' => $audioId,
            'title' => $audioTitle,
            'is_ai_generated' => $voiceType
        ];

        // Если загружен новый файл, обрабатываем его
        if ($hasNewFile) {
            // Сохраняем новый аудиофайл
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

            // Получаем длительность файла
            $duration = 0;
            if (function_exists('shell_exec') && $fileExtension === 'mp3') {
                $output = shell_exec("ffprobe -i '$filePath' -show_entries format=duration -v quiet -of csv=\"p=0\"");
                if ($output !== null) {
                    $duration = floatval(trim($output));
                }
            }

            // Удаляем старый файл
            $oldFilePath = __DIR__ . '/' . $existingAudio['file_path'];
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
            
            // Удаляем original файл если есть
            if ($existingAudio['original_file_path']) {
                $oldOriginalPath = __DIR__ . '/' . $existingAudio['original_file_path'];
                if (file_exists($oldOriginalPath)) {
                    unlink($oldOriginalPath);
                }
            }

            // Обновляем поля файла и сбрасываем workflow
            $updateFields['file_path'] = 'uploads/audio/' . $fileName;
            $updateFields['duration'] = $duration;
            $updateFields['original_file_path'] = null; // Сбрасываем обрезку
            
            $params['file_path'] = 'uploads/audio/' . $fileName;
            $params['duration'] = $duration;
        }

        // Обновляем запись
        $setClause = implode(', ', array_map(fn($field) => "$field = :$field", array_keys($updateFields)));
        $sql = "UPDATE audio_tracks SET $setClause WHERE id = :audio_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

    } else {
        // РЕЖИМ ДОБАВЛЕНИЯ (существующая логика)
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

        // Получаем длительность файла
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
    }

    // Подтверждаем транзакцию
    $pdo->commit();

    // Определяем куда перенаправлять
    if ($editMode) {
        header('Location: poem_list.php?success=audio_updated');
    } else {
        // Режим добавления
        if ($trimAudio) {
            $audioId = $pdo->lastInsertId();
            header("Location: add_audio_step2.php?id=$audioId");
        } else {
            header('Location: poem_list.php?success=audio_added');
        }
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