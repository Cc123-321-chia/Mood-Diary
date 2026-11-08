-- Run once in phpMyAdmin with the mood_diary database selected.
-- Stores dashboard resolution state separately from the original security_logs schema.
CREATE TABLE IF NOT EXISTS security_log_resolutions (
    log_id INT PRIMARY KEY,
    resolved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by INT NULL,
    CONSTRAINT fk_log_resolution_log
        FOREIGN KEY (log_id) REFERENCES security_logs(log_id) ON DELETE CASCADE,
    CONSTRAINT fk_log_resolution_admin
        FOREIGN KEY (resolved_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);
