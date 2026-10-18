<?php
// CLI migration, called after the compatibility and production migrations.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$columns = [
    'certificate_type'=>"ENUM('Participation','Winner') NOT NULL DEFAULT 'Participation'",
    'prize_rank'=>'TINYINT UNSIGNED NULL',
    'presentation_json'=>'LONGTEXT NULL'
];
foreach ($columns as $name=>$definition) {
    $q=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $q->execute(['certificates',$name]);
    if (!$q->fetchColumn()) $db->exec("ALTER TABLE certificates ADD COLUMN `$name` $definition");
}
$indexExists = function($name) use ($db) {
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='certificates' AND INDEX_NAME=?");$q->execute([$name]);return (bool)$q->fetchColumn();
};
if (!$indexExists('uniq_certificate_event_user_type')) $db->exec('ALTER TABLE certificates ADD UNIQUE KEY uniq_certificate_event_user_type(event_id,user_id,certificate_type)');
if (!$indexExists('uniq_certificate_event_prize')) $db->exec('ALTER TABLE certificates ADD UNIQUE KEY uniq_certificate_event_prize(event_id,prize_rank)');
// Keep the old constraint until both replacements are installed successfully.
if ($indexExists('uniq_certificate_event_user')) $db->exec('ALTER TABLE certificates DROP INDEX uniq_certificate_event_user');
$q=$db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='certificates' AND CONSTRAINT_NAME='chk_certificate_prize'");
if (!$q->fetchColumn()) $db->exec("ALTER TABLE certificates ADD CONSTRAINT chk_certificate_prize CHECK ((certificate_type='Participation' AND prize_rank IS NULL) OR (certificate_type='Winner' AND prize_rank IN (1,2,3) AND prize_rank IS NOT NULL))");
$db->exec("CREATE TABLE IF NOT EXISTS event_certificate_settings (
 event_id INT PRIMARY KEY,
 organization VARCHAR(150) NOT NULL DEFAULT 'Campus Chronicle',
 signatory VARCHAR(150) NOT NULL DEFAULT 'Event Coordinator',
 signatory_title VARCHAR(150) NOT NULL DEFAULT 'Organizing Committee',
 participation_template_id INT NULL,
 winner_template_id INT NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_certificate_settings_event FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
 CONSTRAINT fk_participation_template FOREIGN KEY(participation_template_id) REFERENCES content_templates(id) ON DELETE SET NULL,
 CONSTRAINT fk_winner_template FOREIGN KEY(winner_template_id) REFERENCES content_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB");
