-- Optional one-time cleanup after switching to PHP release monitoring only.
-- This permanently deletes previous PHP update/rollback history records.
-- Run with mood_diary selected in phpMyAdmin only if you want that history removed.
DROP TABLE IF EXISTS php_update_history;

-- Remove the obsolete automatic-update flag from an older monitoring table,
-- but only when that column exists (works on MySQL and MariaDB).
SET @drop_auto_update_column = (
    SELECT IF(COUNT(*) > 0,
        'ALTER TABLE php_update_settings DROP COLUMN auto_security_updates',
        'SELECT 1')
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'php_update_settings'
      AND column_name = 'auto_security_updates'
);
PREPARE php_update_cleanup_stmt FROM @drop_auto_update_column;
EXECUTE php_update_cleanup_stmt;
DEALLOCATE PREPARE php_update_cleanup_stmt;
