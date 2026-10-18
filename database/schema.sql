-- =====================================================================
-- CAMPUS CHRONICLE — DATABASE SCHEMA
-- =====================================================================
-- This is the single source of truth for the database structure.
-- Every column here is actually read or written by the PHP code in
-- /app — nothing is left over from earlier drafts.
--
-- USE THIS FILE FOR A FRESH INSTALL ONLY.
-- If you already have a campus_chronicle database with real data in it,
-- run php tools/migrate.php instead — it upgrades an existing
-- database in place without deleting anything.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS campus_chronicle
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE campus_chronicle;

-- =====================================================================
-- USERS
-- =====================================================================
CREATE TABLE IF NOT EXISTS users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(150) NOT NULL,
    fullname       VARCHAR(150) NOT NULL,
    email          VARCHAR(150) NOT NULL,
    google_sub     VARCHAR(255) DEFAULT NULL,
    linkedin_sub   VARCHAR(255) DEFAULT NULL,
    auth_provider  ENUM('password','google','linkedin','both','multiple') NOT NULL DEFAULT 'password',
    password       VARCHAR(255) NOT NULL,
    profile_image  VARCHAR(255) DEFAULT 'default.png',
    profile_banner VARCHAR(500) DEFAULT NULL,
    phone          VARCHAR(20)  DEFAULT NULL,
    bio            TEXT         DEFAULT NULL,
    institution_name VARCHAR(150) DEFAULT NULL,
    student_id VARCHAR(100) DEFAULT NULL,
    course VARCHAR(150) DEFAULT NULL,
    department VARCHAR(150) DEFAULT NULL,
    current_year VARCHAR(50) DEFAULT NULL,
    semester VARCHAR(50) DEFAULT NULL,
    admission_year VARCHAR(20) DEFAULT NULL,
    expected_graduation VARCHAR(20) DEFAULT NULL,
    github_url VARCHAR(500) DEFAULT NULL,
    linkedin_url VARCHAR(500) DEFAULT NULL,
    portfolio_url VARCHAR(500) DEFAULT NULL,
    skills TEXT DEFAULT NULL,
    academic_interests TEXT DEFAULT NULL,
    profile_visibility ENUM('Public','Private') DEFAULT 'Public',
    role           ENUM('student','admin') NOT NULL DEFAULT 'student',
    totp_secret    VARCHAR(255) DEFAULT NULL,
    totp_enabled   TINYINT(1) NOT NULL DEFAULT 0,
    totp_last_step BIGINT DEFAULT NULL,
    totp_recovery  TEXT DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_users_email (email),
    UNIQUE KEY uniq_users_username (username),
    UNIQUE KEY uniq_users_google_sub (google_sub),
    UNIQUE KEY uniq_users_linkedin_sub (linkedin_sub)
) ENGINE=InnoDB;

-- =====================================================================
-- CATEGORIES
-- =====================================================================
CREATE TABLE IF NOT EXISTS categories (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    category_name  VARCHAR(100) NOT NULL,
    description    TEXT,
    status         ENUM('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB;

-- =====================================================================
-- EVENTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS events (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    title                  VARCHAR(255) NOT NULL,
    category_id            INT,
    status                 ENUM('Present','Ongoing','Upcoming','Completed') DEFAULT 'Upcoming',
    event_date             DATE NOT NULL,
    end_date               DATE,
    event_time             TIME NOT NULL,
    end_time               TIME,
    venue                  VARCHAR(255),
    google_map             TEXT,
    description            TEXT,
    rules                  TEXT,
    prizes                 TEXT,
    schedule               TEXT,
    registration_link      TEXT,
    max_registrations      INT DEFAULT 0,
    current_registrations  INT DEFAULT 0,
    banner                 VARCHAR(255),
    faculty_name           VARCHAR(200),
    faculty_photo          VARCHAR(255),
    student_name           VARCHAR(200),
    student_photo          VARCHAR(255),
    gallery_status         ENUM('Not Created','Pending','Published') DEFAULT 'Not Created',
    publish                ENUM('Yes','No') DEFAULT 'Yes',
    authorized_by          VARCHAR(150) DEFAULT NULL,
    created_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_events_category (category_id),
    CONSTRAINT fk_events_category FOREIGN KEY (category_id)
        REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- GALLERY (albums)
-- =====================================================================
CREATE TABLE IF NOT EXISTS gallery (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    event_id      INT,
    title         VARCHAR(255) NOT NULL,
    slug          VARCHAR(255) UNIQUE,
    description   TEXT,
    cover_image   VARCHAR(500) NOT NULL,
    total_photos  INT DEFAULT 0,
    total_videos  INT DEFAULT 0,
    gallery_type  ENUM('Event','Manual') DEFAULT 'Manual',
    status        ENUM('Published','Draft') DEFAULT 'Draft',
    authorized_by VARCHAR(150) DEFAULT NULL,
    created_by    INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_gallery_event (event_id),
    CONSTRAINT fk_gallery_event FOREIGN KEY (event_id)
        REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_gallery_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- GALLERY MEDIA (photos / videos inside an album)
-- =====================================================================
CREATE TABLE IF NOT EXISTS gallery_media (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    gallery_id      INT NOT NULL,
    media_type      ENUM('image','video') NOT NULL,
    title           VARCHAR(255),
    description     TEXT,
    file_name       VARCHAR(255) NOT NULL,
    file_path       VARCHAR(500) NOT NULL,
    thumbnail_path  VARCHAR(500),
    file_size       BIGINT DEFAULT 0,
    mime_type       VARCHAR(100),
    display_order   INT DEFAULT 0,
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_media_gallery (gallery_id),
    CONSTRAINT fk_media_gallery FOREIGN KEY (gallery_id)
        REFERENCES gallery(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- REGISTRATIONS
-- =====================================================================
CREATE TABLE IF NOT EXISTS registrations (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    event_id            INT,
    user_id             INT,
    status              ENUM('Pending','Approved','Rejected','Attended','Cancelled','Completed') DEFAULT 'Pending',
    registration_date   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approval_date       TIMESTAMP NULL DEFAULT NULL,
    notes               TEXT,
    attendance_marked   TINYINT(1) DEFAULT 0,
    attendance_date     TIMESTAMP NULL DEFAULT NULL,
    KEY idx_reg_event (event_id),
    KEY idx_reg_user (user_id),
    CONSTRAINT fk_reg_event FOREIGN KEY (event_id)
        REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_reg_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- CERTIFICATES
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
    CONSTRAINT fk_cert_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cert_event FOREIGN KEY (event_id)
        REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_cert_registration FOREIGN KEY (registration_id)
        REFERENCES registrations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- CONTACT MESSAGES
-- =====================================================================
CREATE TABLE IF NOT EXISTS contacts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(150) NOT NULL,
    phone       VARCHAR(20),
    subject     VARCHAR(255),
    message     LONGTEXT,
    ip_address  VARCHAR(64),
    status      ENUM('New','Read','Replied') DEFAULT 'New',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- USER NOTIFICATIONS
-- =====================================================================
CREATE TABLE IF NOT EXISTS user_notifications (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    type          VARCHAR(50) NOT NULL,
    title         VARCHAR(255) NOT NULL,
    message       TEXT NOT NULL,
    redirect_url  VARCHAR(255) NOT NULL,
    is_read       TINYINT(1) DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_user (user_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- ANNOUNCEMENTS (reserved for future use — safe to keep empty)
-- =====================================================================
CREATE TABLE IF NOT EXISTS announcements (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(255),
    description  TEXT,
    created_by   INT DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_announcement_creator (created_by)
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA — CATEGORIES
-- These IDs (1–6) match what the event forms use.
-- =====================================================================
INSERT INTO categories (id, category_name, description, status) VALUES
    (1, 'Workshop',       'Hands-on learning sessions and skill-building workshops', 'Active'),
    (2, 'Hackathon',      'Competitive coding and innovation challenges',            'Active'),
    (3, 'Seminar',        'Expert talks, guest lectures and knowledge sessions',     'Active'),
    (4, 'Technical Fest', 'Flagship technical festivals and expos',                  'Active'),
    (5, 'Cultural',       'Music, dance, drama and cultural celebrations',           'Active'),
    (6, 'Sports',         'Indoor and outdoor sporting events',                      'Active')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- =====================================================================
-- SITE VISITS
-- One anonymized SHA-256 key per PHP browser session.
-- =====================================================================
CREATE TABLE IF NOT EXISTS site_visits (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visit_key      CHAR(64) NOT NULL,
    first_seen_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_site_visit_key (visit_key)
) ENGINE=InnoDB;

-- =====================================================================
-- ACADEMIC PROFILE EXTENSIONS
-- =====================================================================
-- (The academic profile columns are part of the users table above.)
CREATE TABLE IF NOT EXISTS content_templates (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    template_type ENUM('event','gallery','certificate') NOT NULL,
    name          VARCHAR(180) NOT NULL,
    description   TEXT,
    config_json   LONGTEXT,
    file_path     VARCHAR(500) DEFAULT NULL,
    mime_type     VARCHAR(120) DEFAULT NULL,
    status        ENUM('Draft','Published') DEFAULT 'Draft',
    created_by    INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_template_type (template_type),
    CONSTRAINT fk_template_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
