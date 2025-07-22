-- Удаляем поле deleted_at из таблицы audio_tracks
-- так как перешли на hard delete
ALTER TABLE audio_tracks DROP COLUMN deleted_at;