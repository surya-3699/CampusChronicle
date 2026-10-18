<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
$root=realpath(dirname(__DIR__));
$destination=$argv[1]??'';
$options=getenv('CC_DB_OPTIONS_FILE')?:'';
$database=getenv('DB_NAME')?:'';
if(getenv('APP_MAINTENANCE')!=='1') { fwrite(STDERR,"Enable maintenance on the web workers and this command before creating a consistent backup.\n");exit(1); }
if(!$destination || !$options || !is_file($options) || !preg_match('/^[A-Za-z0-9_]+$/D',$database)) { fwrite(STDERR,"Supply an existing private destination directory, CC_DB_OPTIONS_FILE and DB_NAME.\n");exit(1); }
$destination=realpath($destination);
if(!$destination || str_starts_with(strtolower(str_replace('\\','/',$destination)).'/',strtolower(str_replace('\\','/',$root)).'/')) { fwrite(STDERR,"Backup destination must be outside the application directory.\n");exit(1); }
$bundle=$destination.'/campus-backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
if(!mkdir($bundle,0700)) throw new RuntimeException('Cannot create backup directory');
$dump=getenv('CC_DUMP_BIN')?:'mariadb-dump';
$process=proc_open([$dump,'--defaults-extra-file='.$options,'--single-transaction','--routines','--triggers','--hex-blob',$database],[0=>['pipe','r'],1=>['file',$bundle.'/database.sql','w'],2=>['pipe','w']],$pipes);
if(!is_resource($process)) throw new RuntimeException('Cannot start database dump');
fclose($pipes[0]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);
if(proc_close($process)!==0) { error_log($error);throw new RuntimeException('Database backup failed; bundle is incomplete.'); }
$zip=new ZipArchive();if($zip->open($bundle.'/application.zip',ZipArchive::CREATE|ZipArchive::EXCL)!==true) throw new RuntimeException('Cannot create file backup');
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($it as $file) {
    if(!$file->isFile() || $file->isLink()) continue;
    $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
    if(str_starts_with($relative,'storage/') || str_starts_with($relative,'.git/')) continue;
    $zip->addFile($file->getPathname(),'CampusChronicle/'.$relative);
}
if(!$zip->close()) throw new RuntimeException('File backup failed');
$manifest=['created_utc'=>gmdate(DATE_ATOM),'database_sha256'=>hash_file('sha256',$bundle.'/database.sql'),'application_sha256'=>hash_file('sha256',$bundle.'/application.zip'),'note'=>'Back up environment secrets separately using encrypted storage.'];
file_put_contents($bundle.'/COMPLETE.json',json_encode($manifest,JSON_PRETTY_PRINT));
echo "Complete backup: $bundle\n";
