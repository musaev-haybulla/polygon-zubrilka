-- Миграция: переименование таблиц и обновление схемы для разметки аудио
-- Дата: 2025-07-31

-- 1. Переименовываем audio_tracks в tracks
ALTER TABLE audio_tracks RENAME TO tracks;

-- 2. Переименовываем audio_timings в timings  
ALTER TABLE audio_timings RENAME TO timings;

-- 3. Обновляем внешний ключ в таблице timings
ALTER TABLE timings DROP FOREIGN KEY timings_audio_track_id_foreign;
ALTER TABLE timings CHANGE audio_track_id track_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE timings ADD CONSTRAINT timings_track_id_foreign FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE;

-- 4. Обновляем enum статусов в tracks (убираем 'in_progress', оставляем только 'draft' и 'active')
ALTER TABLE tracks MODIFY COLUMN status ENUM('draft', 'active') NOT NULL DEFAULT 'draft';

-- 5. Добавляем колонку status в timings 
ALTER TABLE timings ADD COLUMN status ENUM('in_progress', 'completed') NOT NULL DEFAULT 'in_progress';

-- 6. Создаем индекс для быстрого поиска по статусу в timings
ALTER TABLE timings ADD INDEX idx_timings_status (status);