-- PHP release monitoring only. Run with mood_diary selected in phpMyAdmin.
CREATE TABLE IF NOT EXISTS php_update_settings (
    settings_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    auto_check_enabled TINYINT(1) NOT NULL DEFAULT 1,
    check_interval_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    last_check_at DATETIME NULL,
    cached_payload MEDIUMTEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO php_update_settings (settings_id) VALUES (1);
