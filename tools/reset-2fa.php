<?php
// Emergency recovery: switches two-factor authentication OFF for one account so the owner can sign in
// and enrol again. Needs shell access to the server. Usage: php tools/reset-2fa.php admin@example.com
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$email = $argv[1] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php tools/reset-2fa.php admin@example.com\n");
    exit(1);
}
require_once dirname(__DIR__) . '/config/database.php';
$db = Database::connect();
$q = $db->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_last_step = NULL, totp_recovery = NULL WHERE email = ?');
$q->execute([$email]);
echo $q->rowCount() > 0
    ? "Two-step verification turned off for $email. They can enrol again from Admin > Security.\n"
    : "Nothing changed: no such user, or two-step verification was already off.\n";
