-- Apply to the selected database after the existing v11 migration.
ALTER TABLE users ADD COLUMN IF NOT EXISTS institution_name VARCHAR(150) DEFAULT NULL AFTER bio;

-- Older v11 databases allowed only Pending/Approved/Rejected. Normalize any
-- legacy invalid value before widening the lifecycle enum used by the app.
UPDATE registrations SET status='Pending' WHERE status IS NULL OR status='';
ALTER TABLE registrations
    MODIFY COLUMN status ENUM('Pending','Approved','Rejected','Attended','Cancelled','Completed') DEFAULT 'Pending';
UPDATE registrations SET status='Attended' WHERE attendance_marked=1 AND status='Pending';

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    token_hash CHAR(64) PRIMARY KEY,
    user_id INT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_reset_expiry (expires_at),
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Fail on pre-existing duplicate rows; never silently discard registrations/certificates.
SET @cc_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='registrations' AND INDEX_NAME='uniq_registration_event_user')=0,
 'ALTER TABLE registrations ADD UNIQUE KEY uniq_registration_event_user(event_id,user_id)', 'SELECT 1');
PREPARE cc_statement FROM @cc_sql;
EXECUTE cc_statement;
DEALLOCATE PREPARE cc_statement;
SET @cc_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='certificates' AND INDEX_NAME IN ('uniq_certificate_event_user','uniq_certificate_event_user_type'))=0,
 'ALTER TABLE certificates ADD UNIQUE KEY uniq_certificate_event_user(event_id,user_id)', 'SELECT 1');
PREPARE cc_statement FROM @cc_sql;
EXECUTE cc_statement;
DEALLOCATE PREPARE cc_statement;
SET @cc_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_notifications' AND INDEX_NAME='idx_notifications_unread')=0,
 'ALTER TABLE user_notifications ADD KEY idx_notifications_unread(user_id,is_read,created_at)', 'SELECT 1');
PREPARE cc_statement FROM @cc_sql;
EXECUTE cc_statement;
DEALLOCATE PREPARE cc_statement;
UPDATE events e SET current_registrations=(SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status IN ('Pending','Approved','Attended','Completed'));
