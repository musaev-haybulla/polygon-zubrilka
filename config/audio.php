<?php
/**
 * Конфигурация для загрузки и обработки аудиофайлов
 */

return [
    // Валидация файлов фрагментов
    'fragment_upload' => [
        'allowed_formats' => ['mp3'],
        'max_size_mb' => 3,
        'max_size_bytes' => 3 * 1024 * 1024, // 3MB в байтах
        'mime_types' => ['audio/mpeg', 'audio/mp3']
    ],
    
    // Структура директорий
    'storage' => [
        'base_path' => 'uploads/audio',
        'fragment_pattern' => '{fragment_id}/',              // Директория для фрагмента
        'filename_pattern' => '{title_slug}-{timestamp}.mp3' // Шаблон имени файла
    ],
    
    // Настройки обработки
    'processing' => [
        'ffprobe_enabled' => true, // Использовать ffprobe для определения длительности
        'auto_normalize_volume' => false // Автоматическая нормализация громкости
    ]
];