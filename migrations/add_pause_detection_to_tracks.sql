-- Migration: add_pause_detection_to_tracks
-- Назначение: добавить JSON-колонку для хранения результатов детекции пауз Python-скриптом.
-- Примечание: требуется MySQL 5.7+ или MariaDB 10.2.7+ для nativе JSON-типа. Иначе используйте LONGTEXT как фолбэк.

ALTER TABLE `tracks`
  ADD COLUMN `pause_detection` JSON NULL AFTER `duration`;
