<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
$fail=0;
function check($ok,$label){global $fail;if(!$ok)$fail++;echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL;}
check(PHP_VERSION_ID>=80300,'PHP 8.3 or later deployment target');
foreach(['pdo_mysql','curl','openssl','fileinfo','mbstring','zip'] as $ext) check(extension_loaded($ext),'extension '.$ext);
check(getenv('APP_ENV')==='production','production environment selected');
$url=getenv('APP_URL')?:'';
check(filter_var($url,FILTER_VALIDATE_URL) && parse_url($url,PHP_URL_SCHEME)==='https','explicit HTTPS application URL');
check(getenv('DB_USER') && getenv('DB_USER')!=='root','dedicated application database account');
$password=getenv('DB_PASSWORD')?:'';check(strlen($password)>=16 && !str_contains($password,'REPLACE'),'database secret configured');
check(getenv('CC_AUTO_MIGRATE')!=='1','web auto-migrations disabled');
$storage=getenv('CC_STORAGE_PATH')?:'';
check($storage && is_dir($storage) && is_writable($storage),'private storage exists and is writable');
$real=$storage!=='' ? realpath($storage) : false;$root=realpath('.');
$normalized=$real ? strtolower(rtrim(str_replace('\\','/',$real),'/')) : '';
$normalizedRoot=strtolower(rtrim(str_replace('\\','/',$root),'/'));
check($normalized!=='' && $normalized!==$normalizedRoot && !str_starts_with($normalized,$normalizedRoot.'/'),'storage is outside application root');
check(is_writable('public/uploads'),'uploads writable');
require 'config/google.php';require 'config/linkedin.php';
if(getenv('GOOGLE_CLIENT_ID')) check(GoogleAuthConfig::isConfigured(),'Google configuration syntax'); else echo "PENDING Google disabled; provider login not verified\n";
if(getenv('LINKEDIN_CLIENT_ID')||getenv('LINKEDIN_CLIENT_SECRET')) check(LinkedInAuthConfig::isConfigured(),'LinkedIn configuration syntax'); else echo "PENDING LinkedIn disabled; provider login not verified\n";
$pfOptions=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5];
if(getenv('DB_SSL')==='1' && defined('PDO::MYSQL_ATTR_SSL_CA')){ $pfOptions[PDO::MYSQL_ATTR_SSL_CA]=getenv('DB_SSL_CA')?:'/etc/ssl/certs/ca-certificates.crt'; $pfOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=getenv('DB_SSL_VERIFY')!=='0'; }
if(getenv('DB_SSL')==='1') echo "PASS database connection uses verified TLS (DB_SSL=1)\n"; else echo "PENDING DB_SSL is off: fine only when MySQL is on the same private network; any external/managed MySQL needs DB_SSL=1\n";
if(getenv('DB_SSL')==='1' && getenv('DB_SSL_CA') && !is_file(getenv('DB_SSL_CA'))) check(false,'DB_SSL_CA file exists at '.getenv('DB_SSL_CA'));
try {
    $db=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';port='.(getenv('DB_PORT')?:'3306').';dbname='.(getenv('DB_NAME')?:'campus_chronicle').';charset=utf8mb4',getenv('DB_USER')?:'',getenv('DB_PASSWORD')?:'',$pfOptions);
    foreach(['users','events','registrations','gallery','gallery_media','certificates','user_notifications','content_templates','password_reset_tokens','announcements','site_visits','event_certificate_settings'] as $table){$db->query("SELECT 1 FROM `$table` LIMIT 1");check(true,'table '.$table);}
    foreach(['users'=>['course','institution_name','google_sub','linkedin_sub','auth_provider','totp_enabled'],'content_templates'=>['file_path','mime_type'],'registrations'=>['attendance_marked','attendance_date'],'events'=>['authorized_by'],'gallery'=>['authorized_by']] as $table=>$columns) foreach($columns as $column){$db->query("SELECT `$column` FROM `$table` LIMIT 1");check(true,'column '.$table.'.'.$column);}
    check((int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()>0,'administrator provisioned');
    check((int)$db->query('SELECT COUNT(*) FROM (SELECT event_id,user_id FROM registrations GROUP BY event_id,user_id HAVING COUNT(*)>1) d')->fetchColumn()===0,'no duplicate registrations');
    foreach(['registrations'=>'uniq_registration_event_user','certificates'=>'uniq_certificate_event_user_type'] as $table=>$index) {
        $q=$db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? AND NON_UNIQUE=0');
        $q->execute([$table,$index]);check((int)$q->fetchColumn()===($table==='certificates'?3:2),'unique issuance/registration constraint on '.$table);
    }
    check((int)$db->query("SELECT COUNT(*) FROM events e WHERE current_registrations<>(SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status IN ('Pending','Approved','Attended','Completed'))")->fetchColumn()===0,'registration counters consistent');
    $q=$db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('events','registrations','certificates','users','user_notifications')");
    check(count(array_filter($q->fetchAll(PDO::FETCH_COLUMN),fn($e)=>strtolower($e)!=='innodb'))===0,'transactional core tables');
} catch(Throwable $e) {check(false,'database/schema verification (inspect private server log)');error_log($e->getMessage());}
echo "PENDING Verify HTTPS, redirects, Apache protections, real OAuth, cron and backup restore on the target host.\n";
echo "$fail blocking local preflight failures\n";exit($fail?1:0);
