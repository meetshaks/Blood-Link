-- BloodLink: optional donor work/current location.
-- Re-runnable on MySQL 8.0.29+.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS present_at ENUM('home','work') NOT NULL DEFAULT 'home'
    AFTER preferred_lang;

CREATE TABLE IF NOT EXISTS user_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  label ENUM('work','current') NOT NULL DEFAULT 'work',
  division_id INT UNSIGNED NOT NULL,
  district_id INT UNSIGNED NOT NULL,
  upazila_id INT UNSIGNED NULL,
  union_id INT UNSIGNED NULL,
  city_corporation_id INT UNSIGNED NULL,
  area_text VARCHAR(150) NULL,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  active_until DATE NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_location_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_location_division FOREIGN KEY (division_id) REFERENCES divisions(id),
  CONSTRAINT fk_user_location_district FOREIGN KEY (district_id) REFERENCES districts(id),
  CONSTRAINT fk_user_location_upazila FOREIGN KEY (upazila_id) REFERENCES upazilas(id),
  CONSTRAINT fk_user_location_union FOREIGN KEY (union_id) REFERENCES unions(id),
  CONSTRAINT fk_user_location_cc FOREIGN KEY (city_corporation_id) REFERENCES city_corporations(id),
  INDEX idx_user_location_district_active (district_id, is_visible, active_until),
  INDEX idx_user_location_upazila (upazila_id),
  INDEX idx_user_location_city (city_corporation_id),
  INDEX idx_user_location_union (union_id)
) ENGINE=InnoDB;

-- Rollback (manual, only if explicitly required):
-- ALTER TABLE users DROP COLUMN present_at;
-- DROP TABLE user_locations;
