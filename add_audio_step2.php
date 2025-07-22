<?php
/**
 * Шаг 2: Обрезка аудиофайла
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

// Обработка POST-запроса для выполнения обрезки
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $audioId = (int)($_POST['audio_id'] ?? 0);
    $startTime = (float)($_POST['start_time'] ?? 0);
    $endTime = (float)($_POST['end_time'] ?? 0);
    
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
        
        if (!$audioData) {
            throw new Exception('Аудиозапись не найдена');
        }
        
        // Валидация временных меток
        if ($startTime < 0 || $endTime <= $startTime) {
            throw new Exception('Некорректные временные метки');
        }
        
        // Формируем пути к файлам
        $originalFilename = $audioData['filename'];
        $trimmedFilename = str_replace('.mp3', '-trimmed.mp3', $originalFilename);
        
        $originalPath = AudioFileHelper::getAbsoluteAudioPath($audioData['fragment_id'], $originalFilename);
        $trimmedPath = AudioFileHelper::getAbsoluteAudioPath($audioData['fragment_id'], $trimmedFilename);
        
        // Проверяем существование оригинального файла
        if (!file_exists($originalPath)) {
            throw new Exception('Исходный аудиофайл не найден');
        }
        
        // Выполняем обрезку через FFmpeg
        $cmd = sprintf(
            "%s -i '%s' -ss %.3f -to %.3f -c copy '%s' 2>&1",
            AudioFileHelper::getFFmpegPath(),
            $originalPath,
            $startTime,
            $endTime,
            $trimmedPath
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            // Удаляем частично созданный файл если есть
            if (file_exists($trimmedPath)) {
                unlink($trimmedPath);
            }
            throw new Exception('Ошибка при обрезке файла: ' . implode('\n', $output));
        }
        
        // Вычисляем новую длительность
        $newDuration = $endTime - $startTime;
        
        // Обновляем базу данных
        $stmt = $pdo->prepare("
            UPDATE audio_tracks SET 
                filename = ?,
                original_filename = ?,
                duration = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $trimmedFilename,
            $originalFilename,
            $newDuration,
            $audioId
        ]);
        
        $pdo->commit();
        
        // Перенаправляем обратно на список с сообщением об успехе
        header('Location: poem_list.php?success=audio_trimmed');
        exit;
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Удаляем файл если он был создан
        if (isset($trimmedPath) && file_exists($trimmedPath)) {
            unlink($trimmedPath);
        }
        
        error_log("Error in add_audio_step2.php: " . $e->getMessage());
        $error = $e->getMessage();
    }
}

// Получение данных для отображения
$audioId = (int)($_GET['id'] ?? 0);

if ($audioId <= 0) {
    header('Location: poem_list.php?error=invalid_audio_id');
    exit;
}

try {
    $pdo = getPdo();
    
    // Получаем данные аудиозаписи с информацией о фрагменте и стихотворении
    $stmt = $pdo->prepare("
        SELECT 
            at.*,
            f.label as fragment_label, 
            f.id as fragment_id,
            p.title as poem_title,
            p.id as poem_id
        FROM audio_tracks at
        JOIN fragments f ON at.fragment_id = f.id  
        JOIN poems p ON f.poem_id = p.id
        WHERE at.id = ?
    ");
    $stmt->execute([$audioId]);
    $audioData = $stmt->fetch();
    
    if (!$audioData) {
        header('Location: poem_list.php?error=audio_not_found');
        exit;
    }
    
    // Формируем путь к аудиофайлу для браузера
    $audioUrl = AudioFileHelper::getAudioPath($audioData['fragment_id'], $audioData['filename']);
    
    // Определяем заголовок для breadcrumbs
    $fragmentTitle = $audioData['fragment_label'] ?: $audioData['poem_title'];
    if ($audioData['fragment_label'] && $audioData['poem_title']) {
        $fragmentTitle = $audioData['poem_title'] . ' - ' . $audioData['fragment_label'];
    }
    
} catch (Exception $e) {
    error_log("Error loading audio data: " . $e->getMessage());
    header('Location: poem_list.php?error=database_error');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Обрезка аудио - <?= htmlspecialchars($audioData['title']) ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        #waveform {
            width: 100%;
            border: 1px solid #dee2e6;
            border-radius: .375rem;
            margin-bottom: 1rem;
            min-height: 200px;
        }
        .region-timeline {
            background-color: #f8f9fa;
            border-radius: .375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .region-timeline span {
            font-weight: 600;
            color: #0d6efd;
        }
        .audio-info {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }
        .btn-audio {
            min-width: 120px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Breadcrumbs -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="poem_list.php" class="text-decoration-none">
                        <i class="bi bi-house-door"></i> Список стихов
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <span class="text-muted"><?= htmlspecialchars($fragmentTitle) ?></span>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    Обрезка аудио
                </li>
            </ol>
        </nav>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <div class="d-flex align-items-center">
                    <i class="bi bi-scissors me-2"></i>
                    <div>
                        <h5 class="mb-0">Обрезка аудиофайла</h5>
                        <small class="opacity-75"><?= htmlspecialchars($audioData['title']) ?></small>
                    </div>
                </div>
            </div>
            
            <div class="card-body" x-data="audioTrimmer">
                <!-- Информация об аудио -->
                <div class="audio-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Озвучка:</strong> <?= htmlspecialchars($audioData['title']) ?>
                        </div>
                        <div>
                            <strong>Длительность:</strong> <?= number_format((float)$audioData['duration'], 2) ?> сек.
                        </div>
                        <?php if ($audioData['original_filename']): ?>
                        <div class="text-warning">
                            <i class="bi bi-info-circle me-1"></i>
                            Файл уже обрезан
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Waveform -->
                <div id="waveform"></div>
                
                <!-- Информация о выбранном регионе -->
                <div class="region-timeline">
                    <div class="row text-center">
                        <div class="col-md-4">
                            <div class="mb-2">
                                <i class="bi bi-play-circle text-success"></i>
                                <strong>Начало</strong>
                            </div>
                            <div>
                                <span x-text="formatTime(regionStartVal)"></span> сек
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <i class="bi bi-clock text-info"></i>
                                <strong>Длительность</strong>
                            </div>
                            <div>
                                <span x-text="formatTime(regionDurationVal)"></span> сек
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-2">
                                <i class="bi bi-stop-circle text-danger"></i>
                                <strong>Конец</strong>
                            </div>
                            <div>
                                <span x-text="formatTime(regionEndVal)"></span> сек
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Элементы управления воспроизведением -->
                <div class="d-flex justify-content-center flex-wrap gap-2 mb-4">
                    <button class="btn btn-success btn-audio" @click="togglePlayPause" x-text="isPlaying ? 'Пауза' : 'Воспроизвести'">
                        Воспроизвести
                    </button>
                    <button class="btn btn-outline-info btn-audio" @click="playFirst2Seconds">
                        <i class="bi bi-skip-start me-1"></i>
                        Первые 2 сек
                    </button>
                    <button class="btn btn-outline-warning btn-audio" @click="playLast2Seconds">
                        <i class="bi bi-skip-end me-1"></i>
                        Последние 2 сек
                    </button>
                </div>

                <!-- Форма для отправки данных обрезки -->
                <form method="POST" id="trimForm">
                    <input type="hidden" name="audio_id" value="<?= $audioId ?>">
                    <input type="hidden" name="start_time" x-model="regionStartVal">
                    <input type="hidden" name="end_time" x-model="regionEndVal">
                    
                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <div class="d-flex gap-2">
                            <a href="poem_list.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>
                                Назад к списку
                            </a>
                            
                            <?php if ($audioData['original_filename']): ?>
                            <button type="button" class="btn btn-outline-danger" onclick="restoreOriginal()">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>
                                Вернуть оригинал
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-scissors me-1"></i>
                            Применить обрезку
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card-footer text-muted text-center">
                <small>Выберите нужный фрагмент аудио перетаскиванием границ региона на waveform</small>
            </div>
        </div>
    </div>

    <!-- Alpine.js, Wavesurfer.js с плагином Regions и Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://unpkg.com/wavesurfer.js@7"></script>
    <script src="https://unpkg.com/wavesurfer.js@7/dist/plugins/regions.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('audioTrimmer', () => ({
                wavesurfer: null,
                wsRegions: null,
                region: null,
                isPlaying: false,
                totalDuration: 0,
                
                // Реактивные переменные для отображения меток региона
                regionStartVal: 0,
                regionEndVal: 0,
                regionDurationVal: 0,
                
                // Переменная для хранения таймера воспроизведения
                playTimeout: null,
                
                init() {
                    this.initWaveSurfer();
                },
                
                initWaveSurfer() {
                    this.wsRegions = WaveSurfer.Regions.create();
                    this.wavesurfer = WaveSurfer.create({
                        container: '#waveform',
                        backend: 'WebAudio',
                        plugins: [this.wsRegions],
                        waveColor: '#0d6efd',
                        progressColor: '#0b5ed7',
                        cursorColor: '#6c757d',
                        barWidth: 2,
                        barRadius: 1,
                        responsive: true,
                        height: 150
                    });
                    
                    this.wavesurfer.load('<?= $audioUrl ?>');
                    
                    this.wavesurfer.on('ready', () => {
                        this.totalDuration = this.wavesurfer.getDuration();
                        this.region = this.wsRegions.addRegion({
                            start: 0,
                            end: this.totalDuration,
                            color: 'rgba(13, 110, 253, 0.1)',
                            drag: true,
                            resize: true
                        });
                        
                        // Изначальное выставление реактивных значений
                        this.regionStartVal = this.region.start;
                        this.regionEndVal = this.region.end;
                        this.regionDurationVal = this.region.end - this.region.start;
                        
                        // Обновление меток после изменения региона
                        this.region.on('update-end', () => {
                            this.regionStartVal = this.region.start;
                            this.regionEndVal = this.region.end;
                            this.regionDurationVal = this.region.end - this.region.start;
                        });
                    });
                    
                    // Ограничение воспроизведения выбранным регионом
                    this.wavesurfer.on('timeupdate', () => {
                        if (this.region && this.isPlaying) {
                            const currentTime = this.wavesurfer.getCurrentTime();
                            if (currentTime >= this.region.end) {
                                this.wavesurfer.stop();
                                this.isPlaying = false;
                                this.wavesurfer.setTime(this.region.start);
                            }
                        }
                    });
                    
                    this.wavesurfer.on('finish', () => {
                        this.isPlaying = false;
                    });
                },
                
                togglePlayPause() {
                    if (this.isPlaying) {
                        this.wavesurfer.pause();
                        this.isPlaying = false;
                        if (this.playTimeout) {
                            clearTimeout(this.playTimeout);
                            this.playTimeout = null;
                        }
                    } else {
                        const currentTime = this.wavesurfer.getCurrentTime();
                        if (this.region && (currentTime < this.region.start || currentTime > this.region.end)) {
                            this.wavesurfer.setTime(this.region.start);
                        }
                        this.wavesurfer.play();
                        this.isPlaying = true;
                    }
                },
                
                playFirst2Seconds() {
                    if (!this.region) return;
                    if (this.playTimeout) {
                        clearTimeout(this.playTimeout);
                        this.playTimeout = null;
                    }
                    const start = this.region.start;
                    const desiredEnd = Math.min(start + 2, this.region.end);
                    this.wavesurfer.setTime(start);
                    this.wavesurfer.play();
                    this.isPlaying = true;
                    this.playTimeout = setTimeout(() => {
                        this.wavesurfer.pause();
                        this.isPlaying = false;
                        this.wavesurfer.setTime(this.region.start);
                    }, (desiredEnd - start) * 1000);
                },
                
                playLast2Seconds() {
                    if (!this.region) return;
                    if (this.playTimeout) {
                        clearTimeout(this.playTimeout);
                        this.playTimeout = null;
                    }
                    const end = this.region.end;
                    const start = Math.max(this.region.start, end - 2);
                    this.wavesurfer.setTime(start);
                    this.wavesurfer.play();
                    this.isPlaying = true;
                    this.playTimeout = setTimeout(() => {
                        this.wavesurfer.pause();
                        this.isPlaying = false;
                        // Возвращаем каретку в начало всего региона
                        this.wavesurfer.setTime(this.region.start);
                    }, (end - start) * 1000);
                },
                
                formatTime(time) {
                    return parseFloat(Number(time).toFixed(2));
                }
            }));
        });

        // Функция восстановления оригинала
        function restoreOriginal() {
            if (!confirm('Вы уверены, что хотите вернуться к оригинальному файлу? Обрезанная версия будет удалена.')) {
                return;
            }
            
            // Отправляем запрос на восстановление оригинала
            fetch('restore_original_audio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ audio_id: <?= $audioId ?> })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            })
            .catch(error => {
                alert('Произошла ошибка сети');
                console.error('Error:', error);
            });
        }

        // Подтверждение при отправке формы
        document.getElementById('trimForm').addEventListener('submit', function(e) {
            const duration = parseFloat(document.querySelector('input[name="end_time"]').value) - 
                           parseFloat(document.querySelector('input[name="start_time"]').value);
            
            if (duration < 1) {
                e.preventDefault();
                alert('Выбранный фрагмент слишком короткий. Минимальная длительность: 1 секунда.');
                return;
            }
            
            if (!confirm(`Применить обрезку? Будет создан новый файл длительностью ${duration.toFixed(2)} секунд.`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>