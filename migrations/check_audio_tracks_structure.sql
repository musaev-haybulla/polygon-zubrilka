-- Проверяем структуру таблицы audio_tracks
SHOW CREATE TABLE audio_tracks;

-- Проверяем текущее значение AUTO_INCREMENT
SHOW TABLE STATUS LIKE 'audio_tracks';

-- Смотрим примеры записей
SELECT id, fragment_id, title, created_at FROM audio_tracks ORDER BY id DESC LIMIT 5;