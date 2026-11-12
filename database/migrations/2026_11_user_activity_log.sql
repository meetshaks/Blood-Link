-- User self-service audit records. Re-runnable.
CREATE TABLE IF NOT EXISTS user_activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_activity_created (created_at),
  INDEX idx_user_activity_user (user_id, created_at)
) ENGINE=InnoDB;

ALTER TABLE change_requests
  MODIFY status ENUM('pending','approved','rejected','cancelled','superseded')
  NOT NULL DEFAULT 'pending';

-- Rollback:
-- DROP TABLE IF EXISTS user_activity_log;
