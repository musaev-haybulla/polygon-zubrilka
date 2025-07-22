-- Изменение структуры таблицы audio_tracks: file_path -> filename
-- Выполнить: mysql -u root -proot polygon-zubrilka-test < migrations/update_audio_tracks_filename.sql

-- Добавляем новое поле filename
ALTER TABLE audio_tracks ADD COLUMN filename VARCHAR(255) NULL AFTER file_path;

-- Обновляем существующие записи (извлекаем имя файла из пути)
UPDATE audio_tracks 
SET filename = SUBSTRING_INDEX(file_path, '/', -1) 
WHERE filename IS NULL;

-- Удаляем старые поля
ALTER TABLE audio_tracks DROP COLUMN file_path;
ALTER TABLE audio_tracks DROP COLUMN original_file_path;

-- Делаем filename обязательным
ALTER TABLE audio_tracks MODIFY COLUMN filename VARCHAR(255) NOT NULL;

-- Добавляем комментарий
ALTER TABLE audio_tracks MODIFY COLUMN filename VARCHAR(255) NOT NULL COMMENT 'Имя файла в формате title-slug-timestamp.mp3';