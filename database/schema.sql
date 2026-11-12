CREATE DATABASE IF NOT EXISTS bloodlink
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bloodlink;

SET time_zone = '+06:00';

CREATE TABLE divisions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name_en VARCHAR(100) NOT NULL,
  name_bn VARCHAR(100) NOT NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL
) ENGINE=InnoDB;

CREATE TABLE districts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  division_id INT UNSIGNED NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  name_bn VARCHAR(100) NOT NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  CONSTRAINT fk_district_division FOREIGN KEY (division_id) REFERENCES divisions(id),
  INDEX idx_district_division (division_id)
) ENGINE=InnoDB;

CREATE TABLE city_corporations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  district_id INT UNSIGNED NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  name_bn VARCHAR(150) NOT NULL,
  lat DECIMAL(10,7) NULL,
  lng DECIMAL(10,7) NULL,
  CONSTRAINT fk_cc_district FOREIGN KEY (district_id) REFERENCES districts(id),
  INDEX idx_cc_district (district_id)
) ENGINE=InnoDB;

CREATE TABLE upazilas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  district_id INT UNSIGNED NOT NULL,
  name_en VARCHAR(100) NOT NULL,
  name_bn VARCHAR(100) NOT NULL,
  CONSTRAINT fk_upazila_district FOREIGN KEY (district_id) REFERENCES districts(id),
  INDEX idx_upazila_district (district_id)
) ENGINE=InnoDB;

CREATE TABLE unions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  upazila_id INT UNSIGNED NULL,
  type ENUM('union','pourashava') NOT NULL DEFAULT 'union',
  category CHAR(1) NULL,
  name_en VARCHAR(120) NOT NULL,
  name_bn VARCHAR(120) NOT NULL,
  CONSTRAINT fk_union_upazila FOREIGN KEY (upazila_id) REFERENCES upazilas(id),
  INDEX idx_union_upazila (upazila_id, type)
) ENGINE=InnoDB;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super','admin') NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone CHAR(11) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  photo VARCHAR(255) NULL,
  blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
  date_of_birth DATE NOT NULL,
  gender ENUM('male','female','other') NOT NULL,
  division_id INT UNSIGNED NOT NULL,
  district_id INT UNSIGNED NOT NULL,
  upazila_id INT UNSIGNED NULL,
  union_id INT UNSIGNED NULL,
  city_corporation_id INT UNSIGNED NULL,
  city_corporation_area_id INT UNSIGNED NULL,
  village VARCHAR(150) NULL,
  last_donation_date DATE NULL,
  available_toggle TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('pending','approved','rejected','cancelled','superseded') NOT NULL DEFAULT 'pending',
  reject_reason VARCHAR(255) NULL,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  is_suspended TINYINT(1) NOT NULL DEFAULT 0,
  suspended_until DATE NULL,
  suspend_reason VARCHAR(255) NULL,
  registered_by ENUM('self','admin') NOT NULL DEFAULT 'self',
  created_by_admin_id INT UNSIGNED NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  password_changed_at DATETIME NULL,
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  preferred_lang ENUM('bn','en') NOT NULL DEFAULT 'bn',
  present_at ENUM('home','work') NOT NULL DEFAULT 'home',
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_division FOREIGN KEY (division_id) REFERENCES divisions(id),
  CONSTRAINT fk_user_district FOREIGN KEY (district_id) REFERENCES districts(id),
  CONSTRAINT fk_user_upazila FOREIGN KEY (upazila_id) REFERENCES upazilas(id),
  CONSTRAINT fk_user_union FOREIGN KEY (union_id) REFERENCES unions(id),
  CONSTRAINT fk_user_cc FOREIGN KEY (city_corporation_id) REFERENCES city_corporations(id),
  CONSTRAINT fk_user_approver FOREIGN KEY (approved_by) REFERENCES admins(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_creator FOREIGN KEY (created_by_admin_id) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_search_upazila (status, available_toggle, blood_group, upazila_id, last_donation_date),
  INDEX idx_search_district (status, available_toggle, blood_group, district_id),
  INDEX idx_search_division (status, available_toggle, blood_group, division_id),
  INDEX idx_search_union (union_id),
  INDEX idx_search_cc (city_corporation_id),
  INDEX idx_user_cc_area (city_corporation_id, city_corporation_area_id),
  INDEX idx_admin_list (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE city_corporation_areas (
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
ALTER TABLE users ADD CONSTRAINT fk_user_cc_area FOREIGN KEY (city_corporation_area_id) REFERENCES city_corporation_areas(id) ON DELETE SET NULL;

CREATE TABLE user_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  label ENUM('work','current') NOT NULL DEFAULT 'work',
  division_id INT UNSIGNED NOT NULL,
  district_id INT UNSIGNED NOT NULL,
  upazila_id INT UNSIGNED NULL,
  union_id INT UNSIGNED NULL,
  city_corporation_id INT UNSIGNED NULL,
  city_corporation_area_id INT UNSIGNED NULL,
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
  INDEX idx_user_location_cc_area (city_corporation_id, city_corporation_area_id),
  INDEX idx_user_location_union (union_id)
) ENGINE=InnoDB;
ALTER TABLE user_locations ADD CONSTRAINT fk_user_location_cc_area FOREIGN KEY (city_corporation_area_id) REFERENCES city_corporation_areas(id) ON DELETE SET NULL;

CREATE TABLE donations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  donation_date DATE NOT NULL,
  place VARCHAR(200) NULL,
  note VARCHAR(500) NULL,
  added_by ENUM('user','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_donation_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_date (user_id, donation_date)
) ENGINE=InnoDB;

CREATE TABLE change_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  field_name ENUM('blood_group','phone') NOT NULL,
  old_value VARCHAR(20) NOT NULL,
  new_value VARCHAR(20) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  decided_by INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cr_admin FOREIGN KEY (decided_by) REFERENCES admins(id) ON DELETE SET NULL,
  INDEX idx_cr_status (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value VARCHAR(500) NOT NULL
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('approval_mode', 'manual'),
  ('org_name_bn', 'আমাদের ক্লাব'),
  ('org_name_en', 'Our Club'),
  ('contact_phone', ''),
  ('support_phone', ''),
  ('support_email', ''),
  ('support_hours', ''),
  ('donation_gap_days', '120'),
  ('min_age', '18'),
  ('max_age', '60'),
  ('phone_visibility', 'public'),
  ('default_lang', 'bn');

CREATE TABLE login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(60) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  scope ENUM('user','admin') NOT NULL DEFAULT 'user',
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempt (identifier, scope, attempted_at),
  INDEX idx_attempt_ip (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE admin_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  target_user_id INT UNSIGNED NULL,
  details JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES admins(id),
  INDEX idx_log_admin (admin_id, created_at),
  INDEX idx_log_target (target_user_id)
) ENGINE=InnoDB;

CREATE TABLE user_activity_log (
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

CREATE TABLE api_rate_limits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rate_key VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_api_rate_key_time (rate_key, created_at)
) ENGINE=InnoDB;
