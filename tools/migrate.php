<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
putenv('CC_AUTO_MIGRATE=0');
require 'config/database.php';
require 'config/schema_compatibility.php';
$db=Database::connect();
SchemaCompatibility::run($db);
$db->exec(file_get_contents('database/production.sql'));
require 'database/certificates_v13.php';
echo "Migration complete. Run preflight and review schema logs before serving traffic.\n";
