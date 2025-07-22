# Техническая спецификация: Обрезка аудиофайлов

## Обзор
Система обрезки позволяет пользователю выбрать нужный фрагмент аудиофайла через waveform-интерфейс и создать обрезанную версию, сохранив при этом оригинал.

## Workflow обрезки

### 1. Пользовательский интерфейс
- Отображение waveform загруженного аудиофайла
- Возможность выбора начальной и конечной точки обрезки
- Предварительное прослушивание выбранного фрагмента
- Кнопка "Применить обрезку"

### 2. Обработка на сервере (add_audio_step2.php)

#### Входные данные:
```php
$audioId = $_POST['audio_id'];
$startTime = (float)$_POST['start_time']; // в секундах
$endTime = (float)$_POST['end_time'];     // в секундах
```

#### Процесс обрезки:
```php
// 1. Получаем данные аудиозаписи
$audioData = getAudioById($audioId);
$originalFilename = $audioData['filename'];

// 2. Создаём имя для обрезанного файла
$trimmedFilename = str_replace('.mp3', '-trimmed.mp3', $originalFilename);

// 3. Формируем пути к файлам
$originalPath = AudioFileHelper::getAbsoluteAudioPath($fragmentId, $originalFilename);
$trimmedPath = AudioFileHelper::getAbsoluteAudioPath($fragmentId, $trimmedFilename);

// 4. Выполняем обрезку через FFmpeg
$cmd = sprintf(
    "ffmpeg -i '%s' -ss %.3f -to %.3f -c copy '%s'",
    $originalPath,
    $startTime,
    $endTime,
    $trimmedPath
);
exec($cmd, $output, $returnCode);

// 5. Вычисляем новую длительность
$newDuration = $endTime - $startTime;

// 6. Обновляем базу данных
UPDATE audio_tracks SET 
    filename = $trimmedFilename,
    original_filename = $originalFilename, 
    duration = $newDuration
WHERE id = $audioId;
```

## Управление полем duration

### Принцип работы
Поле `duration` всегда содержит длительность **текущего активного файла**, который будет использоваться для воспроизведения и разметки.

### Сценарии обновления:

#### Первоначальная загрузка:
```php
$duration = getDurationFromFile($uploadedFile);
// duration = длительность загруженного файла
```

#### После обрезки:
```php
$newDuration = $endTime - $startTime;
// duration = длительность обрезанного фрагмента
```

#### При откате к оригиналу:
```php
$originalDuration = getDurationFromFile($originalFile);
// duration = длительность оригинального файла
```

## Структура файлов

### До обрезки:
```
uploads/audio/5/
└── muzhichok-s-nogtok-1734567890.mp3
```

### После обрезки:
```
uploads/audio/5/
├── muzhichok-s-nogtok-1734567890.mp3          # оригинал
└── muzhichok-s-nogtok-1734567890-trimmed.mp3  # обрезка (активный)
```

## Состояния в базе данных

### До обрезки:
```sql
filename = 'muzhichok-s-nogtok-1734567890.mp3'
original_filename = NULL
duration = 125.50
```

### После обрезки:
```sql
filename = 'muzhichok-s-nogtok-1734567890-trimmed.mp3'
original_filename = 'muzhichok-s-nogtok-1734567890.mp3'
duration = 34.70  -- длительность обрезанного фрагмента
```

## Функции для работы с FFmpeg

### Получение длительности файла:
```php
function getDurationFromFile(string $filePath): float {
    $cmd = sprintf("ffprobe -i '%s' -show_entries format=duration -v quiet -of csv=\"p=0\"", $filePath);
    $output = shell_exec($cmd);
    return $output ? floatval(trim($output)) : 0.0;
}
```

### Обрезка файла:
```php
function trimAudioFile(string $inputPath, string $outputPath, float $startTime, float $endTime): bool {
    $cmd = sprintf(
        "ffmpeg -i '%s' -ss %.3f -to %.3f -c copy '%s' 2>&1",
        $inputPath,
        $startTime, 
        $endTime,
        $outputPath
    );
    
    exec($cmd, $output, $returnCode);
    return $returnCode === 0;
}
```

## Обработка ошибок

### Проверки перед обрезкой:
- Существование оригинального файла
- Корректность временных меток (start < end)
- Доступность FFmpeg на сервере
- Права на запись в целевую директорию

### Откат при ошибках:
При неудачной обрезке необходимо:
1. Удалить частично созданный файл
2. Сохранить исходное состояние в БД
3. Вернуть пользователю информативное сообщение об ошибке

## Возможность отката

Пользователь может вернуться к оригинальному файлу:
```php
// Восстановление оригинала
UPDATE audio_tracks SET 
    filename = original_filename,
    original_filename = NULL,
    duration = (вычисляем из оригинального файла)
WHERE id = $audioId;

// Удаляем обрезанную версию
unlink($trimmedFilePath);
```