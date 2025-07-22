<?php
/**
 * Обработчик добавления озвучки с выбором порядка сортировки
 */
declare(strict_types=1);

// Подключаем конфигурацию и классы
require __DIR__ . '/config/config.php';
require __DIR__ . '/classes/autoload.php';
require __DIR__ . '/classes/AudioFileHelper.php';

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

// Определяем подтверждение пользователя для перезаписи обработанных файлов
$confirmed = !empty($_POST['confirmed']) && $_POST['confirmed'] === '1';

// Валидация данных
$fragmentId = (int)($_POST['fragmentId'] ?? 0);
$audioTitle = trim($_POST['audioTitle'] ?? '');
$voiceType = (int)($_POST['voiceType'] ?? 0);
$sortOrder = (int)($_POST['sortOrder'] ?? 1); // Сортировка доступна в обоих режимах
$trimAudio = !empty($_POST['trimAudio']);

// Отладочное логирование
error_log("DEBUG - Edit mode: " . ($editMode ? 'YES' : 'NO'));
error_log("DEBUG - Audio ID: " . $audioId);
error_log("DEBUG - Title: " . $audioTitle);
error_log("DEBUG - Voice type: " . $voiceType);
error_log("DEBUG - POST data: " . print_r($_POST, true));

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
            SELECT id, filename 
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
        
        // Получаем текущий sort_order редактируемой озвучки
        $stmt = $pdo->prepare("SELECT sort_order FROM audio_tracks WHERE id = :audio_id");
        $stmt->execute(['audio_id' => $audioId]);
        $currentSortOrder = (int)$stmt->fetchColumn();
        
        // Если изменился порядок сортировки, используем AudioSorter для правильной перестановки
        if ($currentSortOrder !== $sortOrder) {
            $audioSorter = new AudioSorter();
            if (!$audioSorter->moveAudio($audioId, $fragmentId, $sortOrder)) {
                throw new Exception('Не удалось переставить озвучку');
            }
        }
        
        // Обновляем только метаданные, sort_order уже обработан AudioSorter
        $updateFields = [
            'title' => $audioTitle,
            'is_ai_generated' => $voiceType,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Параметры должны содержать все поля + audio_id
        $params = [
            'audio_id' => $audioId,
            'title' => $audioTitle,
            'is_ai_generated' => $voiceType,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Если загружен новый файл, обрабатываем его
        if ($hasNewFile) {
            // Проверяем есть ли обработка (обрезка или разметка) и нет ли подтверждения
            if (!$confirmed) {
                $stmt = $pdo->prepare("
                    SELECT filename, original_filename,
                           (SELECT COUNT(*) FROM audio_timings WHERE audio_track_id = :audio_id) as timings_count
                    FROM audio_tracks 
                    WHERE id = :audio_id
                ");
                $stmt->execute(['audio_id' => $audioId]);
                $currentAudio = $stmt->fetch();
                
                $hasProcessing = false;
                $processingTypes = [];
                
                // Проверяем была ли обрезка
                if ($currentAudio && $currentAudio['original_filename'] && 
                    $currentAudio['original_filename'] !== $currentAudio['filename']) {
                    $hasProcessing = true;
                    $processingTypes[] = 'обрезка';
                }
                
                // Проверяем была ли разметка
                if ($currentAudio && $currentAudio['timings_count'] > 0) {
                    $hasProcessing = true;
                    $processingTypes[] = 'разметка';
                }
                
                if ($hasProcessing) {
                    $processingText = implode(' и ', $processingTypes);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'warning' => "Загрузка нового файла удалит текущую {$processingText}. Продолжить?"
                    ]);
                    exit;
                }
            }
            // Загружаем конфигурацию для аудио
            $audioConfig = require_once __DIR__ . '/config/audio.php';
            
            // Генерируем новое имя файла
            $fileName = AudioFileHelper::generateFilename($audioTitle);
            $uploadDir = AudioFileHelper::ensureFragmentDirectory($fragmentId);
            $filePath = $uploadDir . $fileName;
            
            // Если подтверждена перезапись, удаляем старые файлы и разметку
            if ($confirmed) {
                $stmt = $pdo->prepare("SELECT filename, original_filename FROM audio_tracks WHERE id = ?");
                $stmt->execute([$audioId]);
                $oldFileData = $stmt->fetch();
                
                if ($oldFileData) {
                    // Удаляем текущий файл
                    if ($oldFileData['filename']) {
                        $oldFilePath = AudioFileHelper::getAbsoluteAudioPath($fragmentId, $oldFileData['filename']);
                        if (file_exists($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                    }
                    
                    // Удаляем оригинальный файл если есть
                    if ($oldFileData['original_filename']) {
                        $oldOriginalPath = AudioFileHelper::getAbsoluteAudioPath($fragmentId, $oldFileData['original_filename']);
                        if (file_exists($oldOriginalPath)) {
                            unlink($oldOriginalPath);
                        }
                    }
                    
                    // Удаляем разметку
                    $stmt = $pdo->prepare("DELETE FROM audio_timings WHERE audio_track_id = ?");
                    $stmt->execute([$audioId]);
                }
            }
            
            if (!move_uploaded_file($_FILES['audioFile']['tmp_name'], $filePath)) {
                throw new Exception('Не удалось сохранить аудиофайл');
            }

            // Получаем длительность файла
            $duration = 0;
            if (function_exists('shell_exec')) {
                $output = shell_exec("ffprobe -i '$filePath' -show_entries format=duration -v quiet -of csv=\"p=0\"");
                if ($output !== null) {
                    $duration = floatval(trim($output));
                }
            }

            // Удаляем старый файл если не была подтверждена перезапись
            if (!$confirmed && $existingAudio['filename']) {
                $oldFilePath = AudioFileHelper::getAbsoluteAudioPath($fragmentId, $existingAudio['filename']);
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            // Обновляем поля файла и сбрасываем workflow
            $updateFields['filename'] = $fileName;
            $updateFields['original_filename'] = null; // Сбрасываем обрезку
            $updateFields['duration'] = $duration;
            $updateFields['status'] = 'draft'; // Возвращаем в статус draft
            
            $params['filename'] = $fileName;
            $params['original_filename'] = null;
            $params['duration'] = $duration;
            $params['status'] = 'draft';
        }

        // Обновляем запись
        $setClause = implode(', ', array_map(fn($field) => "$field = :$field", array_keys($updateFields)));
        $sql = "UPDATE audio_tracks SET $setClause WHERE id = :audio_id";
        
        error_log("DEBUG - SQL: " . $sql);
        error_log("DEBUG - Params: " . print_r($params, true));
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        error_log("DEBUG - Update result: " . ($result ? 'SUCCESS' : 'FAILED'));
        error_log("DEBUG - Affected rows: " . $stmt->rowCount());

    } else {
        // РЕЖИМ ДОБАВЛЕНИЯ
        
        // Используем AudioSorter для правильной вставки
        $audioSorter = new AudioSorter();
        $sortOrder = $audioSorter->getInsertPosition($fragmentId, $sortOrder);

        // Загружаем конфигурацию для аудио
        $audioConfig = require_once __DIR__ . '/config/audio.php';
        
        // Генерируем имя файла
        $fileName = AudioFileHelper::generateFilename($audioTitle);
        
        // Создаем директорию и получаем полный путь
        $uploadDir = AudioFileHelper::ensureFragmentDirectory($fragmentId);
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($_FILES['audioFile']['tmp_name'], $filePath)) {
            throw new Exception('Не удалось сохранить аудиофайл');
        }

        // Получаем длительность файла
        $duration = 0;
        if (function_exists('shell_exec')) {
            $output = shell_exec("ffprobe -i '$filePath' -show_entries format=duration -v quiet -of csv=\"p=0\"");
            if ($output !== null) {
                $duration = floatval(trim($output));
            }
        }

        // Вставляем запись в базу данных
        $stmt = $pdo->prepare("
            INSERT INTO audio_tracks 
            (fragment_id, filename, duration, is_ai_generated, title, sort_order, status, is_default, created_at, updated_at) 
            VALUES 
            (:fragment_id, :filename, :duration, :is_ai_generated, :title, :sort_order, :status, :is_default, NOW(), NOW())
        ");
            
        $stmt->execute([
            'fragment_id' => $fragmentId,
            'filename' => $fileName,
            'duration' => $duration,
            'is_ai_generated' => $voiceType,
            'title' => $audioTitle,
            'sort_order' => $sortOrder,
            'status' => 'draft', // Всегда draft до завершения разметки
            'is_default' => 0
        ]);
        
        // Получаем ID созданной записи
        $audioId = $pdo->lastInsertId();
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