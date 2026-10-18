<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
require 'config/security.php';
require 'app/Models/Gallery.php';
require_once 'app/Models/Notification.php';
require_once 'app/Models/Certificate.php';
date_default_timezone_set(cc_env('APP_TIMEZONE','Asia/Kolkata'));
$db=Database::connect();
// Database advisory lock protects against overlapping cron jobs on multiple hosts.
if((int)$db->query("SELECT GET_LOCK('campus_chronicle_maintenance',0)")->fetchColumn()!==1) exit(0);
try {
    $db->beginTransaction();
    $count=(new Gallery())->ensureCompletedEventGalleries();
    $events=$db->query("SELECT DISTINCT r.event_id FROM registrations r LEFT JOIN certificates c ON c.event_id=r.event_id AND c.user_id=r.user_id AND c.certificate_type='Participation' WHERE r.attendance_marked=1 AND r.status IN ('Attended','Completed') AND c.id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    $issued=0;
    foreach($events as $eventId) $issued+=(new Certificate())->bulkGenerateCertificates($eventId)['count'];
    $db->exec('DELETE FROM password_reset_tokens WHERE expires_at<NOW()');
    $db->commit();
    foreach(glob(cc_storage().'/limits/*.json')?:[] as $path) if(filemtime($path)<time()-86400) {
        $h=fopen($path,'c+');
        if($h && flock($h,LOCK_EX|LOCK_NB)) { ftruncate($h,0); flock($h,LOCK_UN); }
        if($h) fclose($h);
    }
    echo "Maintenance complete; galleries processed: $count; participation certificates issued: $issued\n";
} catch(Throwable $e) {
    if($db->inTransaction()) $db->rollBack(); throw $e;
} finally { $db->query("SELECT RELEASE_LOCK('campus_chronicle_maintenance')"); }
