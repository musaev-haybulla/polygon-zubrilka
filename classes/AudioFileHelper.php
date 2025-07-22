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
}