-- Корректирующая миграция для завершения рефакторинга таблицы timings
-- Дата: 2025-07-31

-- 1. Переименовываем audio_track_id в track_id
ALTER TABLE timings CHANGE audio_track_id track_id BIGINT UNSIGNED NOT NULL;

-- 2. Добавляем поле status
ALTER TABLE timings ADD COLUMN status ENUM('in_progress', 'completed') NOT NULL DEFAULT 'in_progress';

-- 3. Создаем индекс для быстрого поиска по статусу
ALTER TABLE timings ADD INDEX idx_timings_status (status);