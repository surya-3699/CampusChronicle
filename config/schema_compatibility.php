<?php

/**
 * Lightweight, idempotent compatibility upgrade for older Campus Chronicle
 * databases. This is intentionally conservative: it only adds columns/tables
 * that the current PHP code already requires and never deletes user data.
 *
 * It runs once per PHP request after PDO connects. This prevents old XAMPP
 * databases from crashing with errors such as "Unknown column username" or
 * "Unknown column updated_at" after the project files are upgraded.
 */
final class SchemaCompatibility
{
    private static bool $ran = false;
    private static array $columns = [];
    private static array $tables = [];

    public static function run(PDO $pdo): void
    {
        if (self::$ran) {
            return;
        }
        self::$ran = true;

        // Set CC_AUTO_SCHEMA_UPGRADE=0 to disable automatic additive upgrades.
        if (getenv('CC_AUTO_SCHEMA_UPGRADE') === '0') {
            return;
        }

        try {
            self::loadMetadata($pdo);

            // If this is an empty database, schema.sql is still the correct
            // fresh-install path. Do not try to invent base tables here.
            if (!isset(self::$tables['users'])) {
                return;
            }

            self::upgradeUsers($pdo);
            self::upgradeEvents($pdo);
            self::upgradeGallery($pdo);
            self::upgradeRegistrations($pdo);
            self::upgradeContacts($pdo);
            self::upgradeAnnouncements($pdo);
            self::ensureSupportTables($pdo);
        } catch (Throwable $e) {
            // The application should still attempt to load. Any remaining
            // schema mismatch is logged server-side instead of exposing SQL.
            error_log('Campus Chronicle schema compatibility check failed: ' . $e->getMessage());
            if (PHP_SAPI === 'cli') throw $e;
        }
    }

    private static function loadMetadata(PDO $pdo): void
    {
        $stmt = $pdo->query(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()"
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $table = $row['TABLE_NAME'];
            $column = $row['COLUMN_NAME'];
            self::$tables[$table] = true;
            self::$columns[$table][$column] = true;
        }
    }

    private static function addColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        if (!isset(self::$tables[$table]) || isset(self::$columns[$table][$column])) {
            return;
        }

        $pdo->exec(sprintf(
            'ALTER TABLE `%s` ADD COLUMN `%s` %s',
            str_replace('`', '``', $table),
            str_replace('`', '``', $column),
            $definition
        ));
        self::$columns[$table][$column] = true;
    }

    private static function upgradeUsers(PDO $pdo): void
    {
        self::addColumn($pdo, 'users', 'username', "VARCHAR(150) NULL");
        self::addColumn($pdo, 'users', 'google_sub', "VARCHAR(255) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'linkedin_sub', "VARCHAR(255) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'auth_provider', "ENUM('password','google','linkedin','both','multiple') NOT NULL DEFAULT 'password'");
        // Administrator two-factor authentication (TOTP). The secret is stored encrypted.
        self::addColumn($pdo, 'users', 'totp_secret', "VARCHAR(255) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'totp_enabled', "TINYINT(1) NOT NULL DEFAULT 0");
        self::addColumn($pdo, 'users', 'totp_last_step', "BIGINT DEFAULT NULL");
        self::addColumn($pdo, 'users', 'totp_recovery', "TEXT DEFAULT NULL");

        // Existing installs may already have the older auth_provider ENUM.
        // Inspect the type first so normal requests do not run an ALTER TABLE.
        try {
            $typeStmt = $pdo->query(
                "SELECT COLUMN_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'users'
                   AND COLUMN_NAME = 'auth_provider'
                 LIMIT 1"
            );
            $authProviderType = strtolower((string) $typeStmt->fetchColumn());

            if ($authProviderType !== '' && strpos($authProviderType, "'linkedin'") === false) {
                $pdo->exec("ALTER TABLE users MODIFY COLUMN auth_provider ENUM('password','google','linkedin','both','multiple') NOT NULL DEFAULT 'password'");
            }
        } catch (Throwable $e) {
            error_log('Could not widen users.auth_provider enum: ' . $e->getMessage());
        }
        self::addColumn($pdo, 'users', 'profile_image', "VARCHAR(255) DEFAULT 'default.png'");
        self::addColumn($pdo, 'users', 'profile_banner', "VARCHAR(500) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'institution_name', "VARCHAR(150) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'phone', "VARCHAR(20) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'bio', "TEXT DEFAULT NULL");
        self::addColumn($pdo, 'users', 'role', "ENUM('student','admin') NOT NULL DEFAULT 'student'");
        self::addColumn($pdo, 'users', 'created_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        self::addColumn($pdo, 'users', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

        self::addColumn($pdo, 'users', 'student_id', "VARCHAR(100) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'course', "VARCHAR(150) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'department', "VARCHAR(150) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'current_year', "VARCHAR(50) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'semester', "VARCHAR(50) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'admission_year', "VARCHAR(20) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'expected_graduation', "VARCHAR(20) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'github_url', "VARCHAR(500) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'linkedin_url', "VARCHAR(500) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'portfolio_url', "VARCHAR(500) DEFAULT NULL");
        self::addColumn($pdo, 'users', 'skills', "TEXT DEFAULT NULL");
        self::addColumn($pdo, 'users', 'academic_interests', "TEXT DEFAULT NULL");
        self::addColumn($pdo, 'users', 'profile_visibility', "ENUM('Public','Private') DEFAULT 'Public'");

        if (isset(self::$columns['users']['username'], self::$columns['users']['id'], self::$columns['users']['email'])) {
            $pdo->exec(
                "UPDATE users
                 SET username = CONCAT(SUBSTRING_INDEX(email, '@', 1), '_', id)
                 WHERE username IS NULL OR TRIM(username) = ''"
            );

            // Make username non-null only after safely backfilling old rows.
            try {
                $pdo->exec("ALTER TABLE users MODIFY COLUMN username VARCHAR(150) NOT NULL");
            } catch (Throwable $e) {
                error_log('Could not make users.username NOT NULL: ' . $e->getMessage());
            }

            self::ensureUniqueIndex($pdo, 'users', 'uniq_users_username', 'username');
        }

        if (isset(self::$columns['users']['email'])) {
            self::ensureUniqueIndex($pdo, 'users', 'uniq_users_email', 'email');
        }

        if (isset(self::$columns['users']['google_sub'])) {
            self::ensureUniqueNullableIndex($pdo, 'users', 'uniq_users_google_sub', 'google_sub');
        }


        if (isset(self::$columns['users']['linkedin_sub'])) {
            self::ensureUniqueNullableIndex($pdo, 'users', 'uniq_users_linkedin_sub', 'linkedin_sub');
        }
    }

    private static function upgradeEvents(PDO $pdo): void
    {
        if (!isset(self::$tables['events'])) {
            return;
        }

        self::addColumn($pdo, 'events', 'end_date', "DATE DEFAULT NULL");
        self::addColumn($pdo, 'events', 'end_time', "TIME DEFAULT NULL");
        self::addColumn($pdo, 'events', 'max_registrations', "INT DEFAULT 0");
        self::addColumn($pdo, 'events', 'current_registrations', "INT DEFAULT 0");
        self::addColumn($pdo, 'events', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        // Name of the person who authorized the event (shown in the admin lists).
        self::addColumn($pdo, 'events', 'authorized_by', "VARCHAR(150) DEFAULT NULL");
    }

    private static function upgradeGallery(PDO $pdo): void
    {
        if (!isset(self::$tables['gallery'])) {
            return;
        }
        self::addColumn($pdo, 'gallery', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        // Name of the person who authorized the album (shown in the admin lists).
        self::addColumn($pdo, 'gallery', 'authorized_by', "VARCHAR(150) DEFAULT NULL");
    }

    private static function upgradeRegistrations(PDO $pdo): void
    {
        if (!isset(self::$tables['registrations'])) {
            return;
        }

        $hadCreatedAt = isset(self::$columns['registrations']['created_at']);
        self::addColumn($pdo, 'registrations', 'registration_date', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        self::addColumn($pdo, 'registrations', 'approval_date', "TIMESTAMP NULL DEFAULT NULL");
        self::addColumn($pdo, 'registrations', 'notes', "TEXT DEFAULT NULL");
        self::addColumn($pdo, 'registrations', 'attendance_marked', "TINYINT(1) DEFAULT 0");
        self::addColumn($pdo, 'registrations', 'attendance_date', "TIMESTAMP NULL DEFAULT NULL");

        if ($hadCreatedAt && isset(self::$columns['registrations']['registration_date'])) {
            try {
                $pdo->exec("UPDATE registrations SET registration_date = created_at WHERE created_at IS NOT NULL");
            } catch (Throwable $e) {
                error_log('Could not backfill registrations.registration_date: ' . $e->getMessage());
            }
        }
    }

    private static function upgradeContacts(PDO $pdo): void
    {
        if (!isset(self::$tables['contacts'])) {
            return;
        }
        self::addColumn($pdo, 'contacts', 'phone', "VARCHAR(20) DEFAULT NULL");
        self::addColumn($pdo, 'contacts', 'ip_address', "VARCHAR(64) DEFAULT NULL");
        self::addColumn($pdo, 'contacts', 'status', "ENUM('New','Read','Replied') DEFAULT 'New'");
        self::addColumn($pdo, 'contacts', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    private static function upgradeAnnouncements(PDO $pdo): void
    {
        if (!isset(self::$tables['announcements'])) {
            return;
        }
        self::addColumn($pdo, 'announcements', 'created_by', "INT DEFAULT NULL");
    }

    private static function ensureSupportTables(PDO $pdo): void
    {
        // These tables are independent enough to create safely on an upgraded
        // local database. Foreign-key failures are caught by the outer guard.
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            redirect_url VARCHAR(255) NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_notif_user (user_id)
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS site_visits (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            visit_key CHAR(64) NOT NULL,
            first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_site_visit_key (visit_key)
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS announcements (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NULL,
            description TEXT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_id INT NOT NULL,
            registration_id INT NULL,
            certificate_code VARCHAR(100) NOT NULL,
            certificate_file VARCHAR(255) NULL,
            status ENUM('Generated','Emailed','Downloaded','Deleted') DEFAULT 'Generated',
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            emailed_at TIMESTAMP NULL DEFAULT NULL,
            downloaded_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uniq_certificate_code (certificate_code),
            KEY idx_cert_user (user_id),
            KEY idx_cert_event (event_id)
        ) ENGINE=InnoDB");

        $pdo->exec("CREATE TABLE IF NOT EXISTS content_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            template_type ENUM('event','gallery','certificate') NOT NULL,
            name VARCHAR(180) NOT NULL,
            description TEXT NULL,
            config_json LONGTEXT NULL,
            file_path VARCHAR(500) NULL,
            mime_type VARCHAR(120) NULL,
            status ENUM('Draft','Published') NOT NULL DEFAULT 'Draft',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_template_type (template_type)
        ) ENGINE=InnoDB");

        // Refresh metadata for support tables created above, then make sure an
        // older content_templates table also has the upload columns.
        self::loadMetadata($pdo);
        self::addColumn($pdo, 'announcements', 'created_by', "INT DEFAULT NULL");
        self::addColumn($pdo, 'content_templates', 'file_path', "VARCHAR(500) DEFAULT NULL");
        self::addColumn($pdo, 'content_templates', 'mime_type', "VARCHAR(120) DEFAULT NULL");
        self::addColumn($pdo, 'content_templates', 'updated_at', "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    private static function ensureUniqueNullableIndex(PDO $pdo, string $table, string $indexName, string $column): void
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
            );
            $stmt->execute([$table, $indexName]);
            if ((int) $stmt->fetchColumn() > 0) {
                return;
            }

            $safeColumn = str_replace('`', '``', $column);
            $safeTable = str_replace('`', '``', $table);
            $dup = $pdo->query(sprintf(
                "SELECT COUNT(*) FROM (
                    SELECT `%s` FROM `%s`
                    WHERE `%s` IS NOT NULL AND TRIM(`%s`) <> ''
                    GROUP BY `%s` HAVING COUNT(*) > 1
                ) d",
                $safeColumn,
                $safeTable,
                $safeColumn,
                $safeColumn,
                $safeColumn
            ))->fetchColumn();

            if ((int) $dup === 0) {
                $pdo->exec(sprintf(
                    'ALTER TABLE `%s` ADD UNIQUE KEY `%s` (`%s`)',
                    $safeTable,
                    str_replace('`', '``', $indexName),
                    $safeColumn
                ));
            }
        } catch (Throwable $e) {
            error_log("Could not ensure {$table}.{$column} nullable unique index: " . $e->getMessage());
        }
    }

    private static function ensureUniqueIndex(PDO $pdo, string $table, string $indexName, string $column): void
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
            );
            $stmt->execute([$table, $indexName]);
            if ((int)$stmt->fetchColumn() > 0) {
                return;
            }

            $dup = $pdo->query(sprintf(
                "SELECT COUNT(*) FROM (SELECT `%s` FROM `%s` GROUP BY `%s` HAVING COUNT(*) > 1) d",
                str_replace('`', '``', $column),
                str_replace('`', '``', $table),
                str_replace('`', '``', $column)
            ))->fetchColumn();

            if ((int)$dup === 0) {
                $pdo->exec(sprintf(
                    'ALTER TABLE `%s` ADD UNIQUE KEY `%s` (`%s`)',
                    str_replace('`', '``', $table),
                    str_replace('`', '``', $indexName),
                    str_replace('`', '``', $column)
                ));
            }
        } catch (Throwable $e) {
            error_log("Could not ensure {$table}.{$column} unique index: " . $e->getMessage());
        }
    }
}
