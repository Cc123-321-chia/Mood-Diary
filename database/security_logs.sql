-- Import after the users and admins tables exist.
CREATE TABLE IF NOT EXISTS security_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL,
    user_id INT NULL,
    event_type VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_security_logs_created_at (created_at),
    INDEX idx_security_logs_event_type (event_type),
    CONSTRAINT fk_security_logs_admin
        FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL,
    CONSTRAINT fk_security_logs_user
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);
