# Design Document

## Overview

Данный дизайн описывает унификацию работы с аудиофайлами в проекте путем централизации всех операций с файлами в классе `AudioFileHelper`. Основная цель - заменить прямые вызовы `unlink()` на унифицированные методы, добавить логирование и улучшить обработку ошибок.

## Architecture

### Current State Analysis

В настоящее время в проекте обнаружены следующие места с прямым использованием `unlink()`:

1. **add_audio_step1.php** (строка 189) - удаление старого файла при загрузке нового
2. **add_audio_step1.php** (строка 320) - очистка временного файла при ошибке
3. **delete_audio.php** (строки 41, 49) - удаление основного и оригинального файлов
4. **restore_original_audio.php** (строка 59) - удаление обрезанного файла при восстановлении
5. **add_audio_step2.php** (строки 74, 112) - очистка файлов при ошибках обрезки
6. **classes/AudioFileHelper.php** (строка 155) - в методе deleteAudioFiles()

### Target Architecture

Все операции с файлами будут централизованы в классе `AudioFileHelper` через следующие методы:

- `deleteAudioFile()` - удаление конкретного файла
- `deleteAudioFiles()` - удаление всех файлов аудиозаписи (уже существует)
- `logFileOperation()` - логирование операций с файлами

## Components and Interfaces

### AudioFileHelper Class Extensions

```php
class AudioFileHelper 
{
    /**
     * Удаляет конкретный аудиофайл
     * @param int $fragmentId ID фрагмента
     * @param string $filename Имя файла
     * @param bool $logOperation Логировать операцию
     * @return bool Результат операции
     */
    public static function deleteAudioFile(int $fragmentId, string $filename, bool $logOperation = true): bool

    /**
     * Логирует операцию с файлом
     * @param string $operation Тип операции (delete, create, etc.)
     * @param string $filePath Путь к файлу
     * @param bool $success Успешность операции
     * @param string|null $error Сообщение об ошибке
     */
    private static function logFileOperation(string $operation, string $filePath, bool $success, ?string $error = null): void

    /**
     * Безопасно удаляет файл с проверкой существования
     * @param string $filePath Полный путь к файлу
     * @return bool Результат операции
     */
    private static function safeUnlink(string $filePath): bool
}
```

### File Operation Logging

Логирование будет осуществляться через стандартную функцию `error_log()` PHP с форматом:
```
[TIMESTAMP] AUDIO_FILE_OP: {operation} {file_path} - {status} {error_message}
```

Примеры:
```
[2025-01-27 10:30:15] AUDIO_FILE_OP: DELETE uploads/audio/5/voice-ai-1643234567.mp3 - SUCCESS
[2025-01-27 10:30:16] AUDIO_FILE_OP: DELETE uploads/audio/5/nonexistent.mp3 - WARNING File not found
```

## Data Models

### File Operation Result

```php
class FileOperationResult 
{
    public bool $success;
    public ?string $error;
    public string $filePath;
    
    public function __construct(bool $success, string $filePath, ?string $error = null)
}
```

Однако для простоты реализации будем использовать простой boolean возврат с логированием ошибок.

## Error Handling

### Error Categories

1. **File Not Found** - файл не существует (WARNING level)
2. **Permission Denied** - нет прав на удаление (ERROR level)  
3. **System Error** - системная ошибка при удалении (ERROR level)

### Error Handling Strategy

- Несуществующие файлы логируются как WARNING, но не прерывают выполнение
- Системные ошибки логируются как ERROR и возвращают false
- Все ошибки логируются с детальной информацией для отладки

## Testing Strategy

### Unit Tests

1. **deleteAudioFile() Tests:**
   - Удаление существующего файла
   - Попытка удаления несуществующего файла
   - Удаление файла без прав доступа
   - Проверка логирования операций

2. **Integration Tests:**
   - Замена всех вызовов unlink() на новые методы
   - Проверка корректности работы в контексте существующих скриптов

### Manual Testing

1. Тестирование каждого измененного файла:
   - add_audio_step1.php - загрузка и замена файлов
   - delete_audio.php - полное удаление аудиозаписи
   - restore_original_audio.php - восстановление оригинала
   - add_audio_step2.php - обрезка файлов

## Implementation Phases

### Phase 1: Extend AudioFileHelper
- Добавить метод deleteAudioFile()
- Добавить логирование операций
- Добавить безопасное удаление файлов

### Phase 2: Replace Direct unlink() Calls
- Заменить вызовы в add_audio_step1.php
- Заменить вызовы в delete_audio.php  
- Заменить вызовы в restore_original_audio.php
- Заменить вызовы в add_audio_step2.php

### Phase 3: Testing and Validation
- Протестировать все измененные функции
- Проверить логирование
- Убедиться в отсутствии регрессий

## Security Considerations

- Валидация путей к файлам для предотвращения directory traversal
- Проверка прав доступа перед удалением
- Логирование всех операций для аудита

## Performance Considerations

- Минимальное влияние на производительность за счет простого логирования
- Кэширование путей к исполняемым файлам FFmpeg не требуется для операций удаления
- Операции с файлами остаются синхронными как и раньше