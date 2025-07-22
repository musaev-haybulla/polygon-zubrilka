-- Восстанавливаем поле для оригинального файла
-- Выполнить: mysql -u root -proot polygon-zubrilka-test < migrations/restore_original_filename.sql

-- Добавляем поле для оригинального файла
ALTER TABLE audio_tracks ADD COLUMN original_filename VARCHAR(255) NULL AFTER filename;