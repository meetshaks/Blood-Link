-- BloodLink city-corporation area data.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS city_corporation_area_id INT UNSIGNED NULL AFTER city_corporation_id,
    ADD INDEX IF NOT EXISTS idx_user_cc_area (city_corporation_id, city_corporation_area_id);

ALTER TABLE user_locations
    ADD COLUMN IF NOT EXISTS city_corporation_area_id INT UNSIGNED NULL AFTER city_corporation_id,
    ADD INDEX IF NOT EXISTS idx_user_location_cc_area (city_corporation_id, city_corporation_area_id);

CREATE TABLE IF NOT EXISTS city_corporation_areas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  city_corporation_id INT UNSIGNED NOT NULL,
  name_bn VARCHAR(190) NOT NULL,
  name_en VARCHAR(190) NOT NULL,
  ward_no SMALLINT NULL,
  source_id VARCHAR(64) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_cc_area_city FOREIGN KEY (city_corporation_id) REFERENCES city_corporations(id) ON DELETE CASCADE,
  UNIQUE KEY uq_cc_area_name_ward (city_corporation_id, name_en, ward_no),
  INDEX idx_cc_area_active (city_corporation_id, is_active, ward_no)
) ENGINE=InnoDB;

SET @db = DATABASE();
SET @sql = IF(
  EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='users' AND column_name='city_corporation_area_id')
  AND NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=@db AND table_name='users' AND constraint_name='fk_user_cc_area'),
  'ALTER TABLE users ADD CONSTRAINT fk_user_cc_area FOREIGN KEY (city_corporation_area_id) REFERENCES city_corporation_areas(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(
  EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=@db AND table_name='user_locations' AND column_name='city_corporation_area_id')
  AND NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema=@db AND table_name='user_locations' AND constraint_name='fk_user_location_cc_area'),
  'ALTER TABLE user_locations ADD CONSTRAINT fk_user_location_cc_area FOREIGN KEY (city_corporation_area_id) REFERENCES city_corporation_areas(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rollback (manual only):
-- ALTER TABLE users DROP FOREIGN KEY fk_user_cc_area, DROP COLUMN city_corporation_area_id;
-- ALTER TABLE user_locations DROP FOREIGN KEY fk_user_location_cc_area, DROP COLUMN city_corporation_area_id;
-- DROP TABLE city_corporation_areas;
