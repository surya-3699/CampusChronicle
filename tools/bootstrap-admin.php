<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__)); require 'config/database.php';
$email=$argv[1]??'';
if(!filter_var($email,FILTER_VALIDATE_EMAIL)) { fwrite(STDERR,"Usage: php tools/bootstrap-admin.php verified-email\n");exit(1); }
$db=Database::connect();
$q=$db->prepare("UPDATE users SET role='admin' WHERE email=?");$q->execute([$email]);
if($q->rowCount()!==1) { fwrite(STDERR,"No account changed. Register the verified operator first.\n");exit(1); }
echo "Administrator provisioned. Sign in again.\n";
