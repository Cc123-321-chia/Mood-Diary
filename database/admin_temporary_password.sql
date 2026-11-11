-- Run once in phpMyAdmin after the admins table exists.
-- Existing admin accounts are also prompted once, covering temporary passwords issued earlier.
ALTER TABLE admins
    ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 1;
