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
        $stmt = $pdo->prepare("SELECT fragment_id, filename, original_filename FROM audio_tracks WHERE id = ?");
        $stmt->execute([$audioId]);
        $audioData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$audioData) {
            // Аудиозапись не найдена, нечего удалять
            return true; 
        }

        $fragmentId = (int)$audioData['fragment_id'];

        // 2. Удаляем физические файлы
        $filesToDelete = array_filter([$audioData['filename'], $audioData['original_filename']]);
        
        foreach ($filesToDelete as $filename) {
            $filePath = self::getAbsoluteAudioPath($fragmentId, $filename);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        // 3. Удаляем разметку (тайминги) из БД
        $stmt = $pdo->prepare("DELETE FROM audio_timings WHERE audio_track_id = ?");
        $stmt->execute([$audioId]);

        return true;
    }
}