<?php
/**
 * Помощник для работы с аудиофайлами
 */

class AudioFileHelper 
{
    /**
     * Генерирует slug из названия озвучки
     */
    public static function generateSlug(string $title): string 
    {
        // Переводим в нижний регистр
        $slug = mb_strtolower($title, 'UTF-8');
        
        // Заменяем русские буквы на латинские
        $transliteration = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
        ];
        
        $slug = strtr($slug, $transliteration);
        
        // Убираем все кроме букв, цифр и пробелов
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        
        // Заменяем пробелы и множественные дефисы на один дефис
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        
        // Убираем дефисы в начале и конце
        $slug = trim($slug, '-');
        
        // Ограничиваем длину
        $slug = substr($slug, 0, 50);
        
        return $slug ?: 'audio';
    }
    
    /**
     * Генерирует имя файла в формате {title_slug}-{timestamp}.mp3
     */
    public static function generateFilename(string $title): string 
    {
        $slug = self::generateSlug($title);
        $timestamp = time();
        
        return "{$slug}-{$timestamp}.mp3";
    }
    
    /**
     * Формирует полный путь к аудиофайлу
     */
    public static function getAudioPath(int $fragmentId, string $filename): string 
    {
        return "uploads/audio/{$fragmentId}/{$filename}";
    }
    
    /**
     * Формирует абсолютный путь к аудиофайлу
     */
    public static function getAbsoluteAudioPath(int $fragmentId, string $filename, string $baseDir = __DIR__): string 
    {
        return $baseDir . '/../' . self::getAudioPath($fragmentId, $filename);
    }
    
    /**
     * Создает директорию для фрагмента если её нет
     */
    public static function ensureFragmentDirectory(int $fragmentId, string $baseDir = __DIR__): string 
    {
        $dir = $baseDir . '/../uploads/audio/' . $fragmentId . '/';
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return $dir;
    }
    
    /**
     * Получает путь к ffmpeg с учетом различных систем
     */
    public static function getFFmpegPath(): string
    {
        $paths = [
            '/opt/homebrew/bin/ffmpeg',  // macOS с Homebrew (Apple Silicon)
            '/usr/local/bin/ffmpeg',     // macOS с Homebrew (Intel)
            '/usr/bin/ffmpeg',           // Linux системы
            'ffmpeg'                     // Fallback в PATH
        ];
        
        foreach ($paths as $path) {
            if ($path === 'ffmpeg' || file_exists($path)) {
                return $path;
            }
        }
        
        return 'ffmpeg'; // Последний fallback
    }
    
    /**
     * Получает путь к ffprobe с учетом различных систем
     */
    public static function getFFprobePath(): string
    {
        $paths = [
            '/opt/homebrew/bin/ffprobe',  // macOS с Homebrew (Apple Silicon)
            '/usr/local/bin/ffprobe',     // macOS с Homebrew (Intel)
            '/usr/bin/ffprobe',           // Linux системы
            'ffprobe'                     // Fallback в PATH
        ];
        
        foreach ($paths as $path) {
            if ($path === 'ffprobe' || file_exists($path)) {
                return $path;
            }
        }
        
        return 'ffprobe'; // Последний fallback
    }

    /**
     * Удаляет конкретный аудиофайл
     *
     * @param int $fragmentId ID фрагмента
     * @param string $filename Имя файла
     * @param bool $logOperation Логировать операцию
     * @return bool Результат операции
     */
    public static function deleteAudioFile(int $fragmentId, string $filename, bool $logOperation = true): bool
    {
        $filePath = self::getAbsoluteAudioPath($fragmentId, $filename);
        $result = self::safeUnlink($filePath);
        
        if ($logOperation) {
            $error = $result ? null : 'Failed to delete file';
            self::logFileOperation('DELETE', $filePath, $result, $error);
        }
        
        return $result;
    }

    /**
     * Удаляет связанные с аудиозаписью файлы (основной, оригинальный)
     * и тайминги из БД.
     *
     * @param PDO $pdo
     * @param int $audioId
     * @return bool
     */
    public static function deleteAudioFiles(PDO $pdo, int $audioId): bool
    {
        // 1. Получаем информацию о файлах и fragment_id
        $stmt = $pdo->prepare("SELECT fragment_id, filename, original_filename FROM tracks WHERE id = ?");
        $stmt->execute([$audioId]);
        $audioData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$audioData) {
            // Аудиозапись не найдена, нечего удалять
            self::logFileOperation('DELETE_AUDIO_RECORD', "audio_id:{$audioId}", true, 'Audio record not found');
            return true; 
        }

        $fragmentId = (int)$audioData['fragment_id'];

        // 2. Удаляем физические файлы через унифицированный метод
        $filesToDelete = array_filter([$audioData['filename'], $audioData['original_filename']]);
        
        foreach ($filesToDelete as $filename) {
            self::deleteAudioFile($fragmentId, $filename, true);
        }

        // 3. Удаляем разметку (тайминги) из БД
        $stmt = $pdo->prepare("DELETE FROM timings WHERE track_id = ?");
        $stmt->execute([$audioId]);

        self::logFileOperation('DELETE_AUDIO_RECORD', "audio_id:{$audioId}", true);
        return true;
    }

    /**
     * Безопасно удаляет файл с проверкой существования
     *
     * @param string $filePath Полный путь к файлу
     * @return bool Результат операции
     */
    private static function safeUnlink(string $filePath): bool
    {
        // Проверяем существование файла
        if (!file_exists($filePath)) {
            return true; // Файл не существует - считаем операцию успешной
        }

        // Проверяем права на запись в директории
        $directory = dirname($filePath);
        if (!is_writable($directory)) {
            return false;
        }

        // Пытаемся удалить файл
        try {
            return unlink($filePath);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Логирует операцию с файлом
     *
     * @param string $operation Тип операции (DELETE, CREATE, etc.)
     * @param string $filePath Путь к файлу
     * @param bool $success Успешность операции
     * @param string|null $error Сообщение об ошибке
     */
    private static function logFileOperation(string $operation, string $filePath, bool $success, ?string $error = null): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'ERROR';
        
        // Определяем уровень лога
        $level = 'INFO';
        if (!$success) {
            $level = 'ERROR';
        } elseif (!file_exists($filePath) && $operation === 'DELETE') {
            $level = 'WARNING';
            $status = 'WARNING';
            $error = $error ?: 'File not found';
        }
        
        $logMessage = "[{$timestamp}] AUDIO_FILE_OP: {$operation} {$filePath} - {$status}";
        if ($error) {
            $logMessage .= " ({$error})";
        }
        
        error_log($logMessage);
    }
}