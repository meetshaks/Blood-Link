ALTER TABLE users
    ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER must_change_password,
    ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER password_changed_at;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
    ('support_phone', ''),
    ('support_email', ''),
    ('support_hours', '');
