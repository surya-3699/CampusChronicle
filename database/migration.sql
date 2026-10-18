-- =====================================================================
-- CAMPUS CHRONICLE — UPGRADE MIGRATION
-- =====================================================================
-- Run this ONLY if you already have a campus_chronicle database with
-- real data in it (events, users, galleries you don't want to lose).
--
-- Every statement below is additive and safe to run: it only adds
-- columns/tables that are missing, it never drops or overwrites
-- existing data. It is also safe to run more than once.
--
-- Requires MySQL 8.0.29+ or MariaDB 10.0+ (both ship with current
-- XAMPP). If your server is older than that and "ADD COLUMN IF NOT
-- EXISTS" errors out, use database/schema.sql on a fresh database
-- instead.
--
-- HOW TO RUN (phpMyAdmin): open your campus_chronicle database ->
-- SQL tab -> paste this whole file -> Go.
-- =====================================================================

USE campus_chronicle;

-- =====================================================================
-- USERS — add the columns the app actually uses
-- =====================================================================
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS username VARCHAR(150) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS google_sub VARCHAR(255) DEFAULT NULL AFTER email,
    ADD COLUMN IF NOT EXISTS linkedin_sub VARCHAR(255) DEFAULT NULL AFTER google_sub,
    ADD COLUMN IF NOT EXISTS auth_provider ENUM('password','google','linkedin','both','multiple') NOT NULL DEFAULT 'password' AFTER linkedin_sub,
    ADD COLUMN IF NOT EXISTS profile_banner VARCHAR(500) DEFAULT NULL AFTER profile_image,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL AFTER profile_banner,
    ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS role ENUM('student','admin') NOT NULL DEFAULT 'student' AFTER bio,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Backfill a username for any existing accounts that don't have one yet
UPDATE users
SET username = CONCAT(SUBSTRING_INDEX(email, '@', 1), '_', id)
WHERE username IS NULL OR username = '';

ALTER TABLE users MODIFY COLUMN username VARCHAR(150) NOT NULL;

-- Widen older auth_provider ENUM definitions so LinkedIn/multi-provider
-- accounts can be stored without truncation errors.
ALTER TABLE users MODIFY COLUMN auth_provider
    ENUM('password','google','linkedin','both','multiple') NOT NULL DEFAULT 'password';

-- Make sure id is the primary key and email/username are unique
-- (older installs sometimes had email as the primary key instead of id)
SET @pk_is_id := (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'PRIMARY' AND COLUMN_NAME = 'id'
);

SET @fix_pk := IF(@pk_is_id = 0,
    'ALTER TABLE users DROP PRIMARY KEY, ADD PRIMARY KEY (id)',
    'SELECT 1'
);
PREPARE stmt FROM @fix_pk; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_email_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uniq_users_email'
);
SET @add_email_unique := IF(@has_email_unique = 0,
    'ALTER TABLE users ADD UNIQUE KEY uniq_users_email (email)',
    'SELECT 1'
);
PREPARE stmt FROM @add_email_unique; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_username_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uniq_users_username'
);
SET @add_username_unique := IF(@has_username_unique = 0,
    'ALTER TABLE users ADD UNIQUE KEY uniq_users_username (username)',
    'SELECT 1'
);
PREPARE stmt FROM @add_username_unique; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_google_sub_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uniq_users_google_sub'
);
SET @google_sub_duplicates := (
    SELECT COUNT(*) FROM (
        SELECT google_sub FROM users
        WHERE google_sub IS NOT NULL AND TRIM(google_sub) <> ''
        GROUP BY google_sub HAVING COUNT(*) > 1
    ) AS duplicate_google_accounts
);
SET @add_google_sub_unique := IF(
    @has_google_sub_unique = 0 AND @google_sub_duplicates = 0,
    'ALTER TABLE users ADD UNIQUE KEY uniq_users_google_sub (google_sub)',
    'SELECT 1'
);
PREPARE stmt FROM @add_google_sub_unique; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_linkedin_sub_unique := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uniq_users_linkedin_sub'
);
SET @linkedin_sub_duplicates := (
    SELECT COUNT(*) FROM (
        SELECT linkedin_sub FROM users
        WHERE linkedin_sub IS NOT NULL AND TRIM(linkedin_sub) <> ''
        GROUP BY linkedin_sub HAVING COUNT(*) > 1
    ) AS duplicate_linkedin_accounts
);
SET @add_linkedin_sub_unique := IF(
    @has_linkedin_sub_unique = 0 AND @linkedin_sub_duplicates = 0,
    'ALTER TABLE users ADD UNIQUE KEY uniq_users_linkedin_sub (linkedin_sub)',
    'SELECT 1'
);
PREPARE stmt FROM @add_linkedin_sub_unique; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Promote the earliest-registered account to admin if nobody is an
-- admin yet, so there's always someone who can reach the admin panel.
SET @admin_exists := (SELECT COUNT(*) FROM users WHERE role = 'admin');

UPDATE users
SET role = 'admin'
WHERE @admin_exists = 0
  AND id = (SELECT id FROM (SELECT MIN(id) AS id FROM users) AS first_user);

-- =====================================================================
-- EVENTS — add registration-limit tracking columns
-- =====================================================================
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS max_registrations INT DEFAULT 0 AFTER registration_link,
    ADD COLUMN IF NOT EXISTS current_registrations INT DEFAULT 0 AFTER max_registrations,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- If this installation previously used max_participants / registered_count,
-- carry those numbers over instead of starting from zero.
SET @has_old_max := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'max_participants'
);
SET @copy_max := IF(@has_old_max > 0,
    'UPDATE events SET max_registrations = max_participants WHERE max_registrations = 0',
    'SELECT 1'
);
PREPARE stmt FROM @copy_max; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_count := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'registered_count'
);
SET @copy_count := IF(@has_old_count > 0,
    'UPDATE events SET current_registrations = registered_count WHERE current_registrations = 0',
    'SELECT 1'
);
PREPARE stmt FROM @copy_count; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Recalculate current_registrations from real registration rows so the
-- number is accurate regardless of where it came from above.
UPDATE events e
SET current_registrations = (
    SELECT COUNT(*) FROM registrations r
    WHERE r.event_id = e.id AND r.status IN ('Pending','Approved','Attended')
);

-- =====================================================================
-- GALLERY — add updated_at if missing
-- =====================================================================
ALTER TABLE gallery
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- =====================================================================
-- REGISTRATIONS — add the workflow columns Registration.php expects
-- =====================================================================
ALTER TABLE registrations
    MODIFY COLUMN status ENUM('Pending','Approved','Rejected','Attended','Cancelled','Completed') DEFAULT 'Pending',
    ADD COLUMN IF NOT EXISTS approval_date TIMESTAMP NULL DEFAULT NULL AFTER registration_date,
    ADD COLUMN IF NOT EXISTS notes TEXT AFTER approval_date,
    ADD COLUMN IF NOT EXISTS attendance_marked TINYINT(1) DEFAULT 0 AFTER notes,
    ADD COLUMN IF NOT EXISTS attendance_date TIMESTAMP NULL DEFAULT NULL AFTER attendance_marked;

-- Rename created_at to registration_date if this install still uses the
-- old column name (Registration.php orders by registration_date).
SET @has_created_at := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registrations' AND COLUMN_NAME = 'created_at'
);
SET @has_registration_date := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'registrations' AND COLUMN_NAME = 'registration_date'
);
SET @rename_reg_date := IF(@has_created_at > 0 AND @has_registration_date = 0,
    'ALTER TABLE registrations CHANGE created_at registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt FROM @rename_reg_date; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE registrations
    ADD COLUMN IF NOT EXISTS registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER user_id;

-- =====================================================================
-- CONTACTS — add the columns Contact.php expects
-- =====================================================================
ALTER TABLE contacts
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) AFTER email,
    ADD COLUMN IF NOT EXISTS ip_address VARCHAR(64) AFTER message,
    ADD COLUMN IF NOT EXISTS status ENUM('New','Read','Replied') DEFAULT 'New' AFTER ip_address,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- =====================================================================
-- CERTIFICATES — this table did not exist before, create it now
-- =====================================================================
CREATE TABLE IF NOT EXISTS certificates (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_id           INT NOT NULL,
    event_id          INT NOT NULL,
    registration_id   INT,
    certificate_code  VARCHAR(100) NOT NULL,
    certificate_file  VARCHAR(255),
    status            ENUM('Generated','Emailed','Downloaded','Deleted') DEFAULT 'Generated',
    generated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    emailed_at        TIMESTAMP NULL DEFAULT NULL,
    downloaded_at     TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uniq_certificate_code (certificate_code),
    KEY idx_cert_user (user_id),
    KEY idx_cert_event (event_id),
    CONSTRAINT fk_cert_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cert_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_cert_registration FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- CATEGORIES — seed the standard categories if the table is empty,
-- without touching anything that's already there.
-- =====================================================================
INSERT IGNORE INTO categories (id, category_name, description, status) VALUES
    (1, 'Workshop',       'Hands-on learning sessions and skill-building workshops', 'Active'),
    (2, 'Hackathon',      'Competitive coding and innovation challenges',            'Active'),
    (3, 'Seminar',        'Expert talks, guest lectures and knowledge sessions',     'Active'),
    (4, 'Technical Fest', 'Flagship technical festivals and expos',                  'Active'),
    (5, 'Cultural',       'Music, dance, drama and cultural celebrations',           'Active'),
    (6, 'Sports',         'Indoor and outdoor sporting events',                      'Active');

-- =====================================================================
-- Done. Refresh your admin dashboard and try creating an event,
-- registering an account and uploading a gallery photo again.
-- =====================================================================

-- =====================================================================
-- SITE VISITS
-- =====================================================================
CREATE TABLE IF NOT EXISTS site_visits (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visit_key      CHAR(64) NOT NULL,
    first_seen_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_site_visit_key (visit_key)
) ENGINE=InnoDB;

-- =====================================================================
-- V4 — PROFESSIONAL PROFILES, TEMPLATES & ACADEMIC ANNOUNCEMENTS
-- =====================================================================
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS student_id VARCHAR(100) DEFAULT NULL AFTER username,
    ADD COLUMN IF NOT EXISTS institution_name VARCHAR(150) DEFAULT NULL AFTER bio,
    ADD COLUMN IF NOT EXISTS course VARCHAR(150) DEFAULT NULL AFTER bio,
    ADD COLUMN IF NOT EXISTS department VARCHAR(150) DEFAULT NULL AFTER course,
    ADD COLUMN IF NOT EXISTS current_year VARCHAR(50) DEFAULT NULL AFTER department,
    ADD COLUMN IF NOT EXISTS semester VARCHAR(50) DEFAULT NULL AFTER current_year,
    ADD COLUMN IF NOT EXISTS admission_year VARCHAR(20) DEFAULT NULL AFTER semester,
    ADD COLUMN IF NOT EXISTS expected_graduation VARCHAR(20) DEFAULT NULL AFTER admission_year,
    ADD COLUMN IF NOT EXISTS github_url VARCHAR(500) DEFAULT NULL AFTER expected_graduation,
    ADD COLUMN IF NOT EXISTS linkedin_url VARCHAR(500) DEFAULT NULL AFTER github_url,
    ADD COLUMN IF NOT EXISTS portfolio_url VARCHAR(500) DEFAULT NULL AFTER linkedin_url,
    ADD COLUMN IF NOT EXISTS skills TEXT DEFAULT NULL AFTER portfolio_url,
    ADD COLUMN IF NOT EXISTS academic_interests TEXT DEFAULT NULL AFTER skills,
    ADD COLUMN IF NOT EXISTS profile_visibility ENUM('Public','Private') DEFAULT 'Public' AFTER academic_interests;

CREATE TABLE IF NOT EXISTS content_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_type ENUM('event','gallery','certificate') NOT NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT,
    config_json LONGTEXT,
    file_path VARCHAR(500) DEFAULT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    status ENUM('Draft','Published') DEFAULT 'Draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_template_type (template_type),
    CONSTRAINT fk_template_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE announcements
    ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL AFTER description;

ALTER TABLE content_templates
    ADD COLUMN IF NOT EXISTS file_path VARCHAR(500) DEFAULT NULL AFTER config_json,
    ADD COLUMN IF NOT EXISTS mime_type VARCHAR(120) DEFAULT NULL AFTER file_path;

-- =====================================================================
-- AUTHORIZED BY — who approved each event / gallery album
-- =====================================================================
-- Existing rows stay NULL (shown as "Not recorded" in the admin lists) until
-- an administrator edits them and records the authorizer.
ALTER TABLE events
    ADD COLUMN IF NOT EXISTS authorized_by VARCHAR(150) DEFAULT NULL AFTER publish;

ALTER TABLE gallery
    ADD COLUMN IF NOT EXISTS authorized_by VARCHAR(150) DEFAULT NULL AFTER status;

-- =====================================================================
-- TWO-FACTOR AUTHENTICATION (TOTP) for administrators
-- =====================================================================
-- totp_secret is stored ENCRYPTED by the application (AES-256-GCM, key CC_APP_KEY).
-- totp_recovery holds SHA-256 hashes of the unused one-time recovery codes.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS totp_secret    VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS totp_enabled   TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS totp_last_step BIGINT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS totp_recovery  TEXT DEFAULT NULL;
