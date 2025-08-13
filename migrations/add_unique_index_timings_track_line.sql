-- Add unique index on timings(track_id, line_id) if it does not already exist
-- Safe for MySQL/MariaDB; checks information_schema and conditionally runs ALTER TABLE

SET @idx_exists := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'timings'
    AND index_name = 'uq_track_line'
);

SET @ddl := IF(@idx_exists = 0,
  'ALTER TABLE `timings` ADD UNIQUE KEY `uq_track_line` (`track_id`, `line_id`);',
  'SELECT 1 -- uq_track_line already exists'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
