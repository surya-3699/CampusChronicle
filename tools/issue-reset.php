<?php
// Operator verifies the requester's identity before issuing this one-use link.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
require 'config/database.php';
$email=$argv[1] ?? '';
$base=getenv('APP_URL') ?: '';
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !filter_var($base,FILTER_VALIDATE_URL) || parse_url($base,PHP_URL_SCHEME)!=='https') {
    fwrite(STDERR,"Usage: php tools/issue-reset.php verified-email (set HTTPS APP_URL)\n"); exit(1);
}
$db=Database::connect();
$q=$db->prepare('SELECT id FROM users WHERE email=?'); $q->execute([$email]); $id=$q->fetchColumn();
if (!$id) { fwrite(STDERR,"Account unavailable\n"); exit(1); }
$token=bin2hex(random_bytes(32));
$db->beginTransaction();
$q=$db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?'); $q->execute([$id]);
$q=$db->prepare('INSERT INTO password_reset_tokens(token_hash,user_id,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 20 MINUTE))');
$q->execute([hash('sha256',$token),$id]); $db->commit();
echo rtrim($base,'/').'/index.php?page=forgot-password&token='.$token.PHP_EOL;
