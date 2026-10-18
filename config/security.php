<?php
/** Shared request boundary. Load before session_start and all route handling. */
function cc_env(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value === false ? $default : $value;
}
function cc_storage(): string {
    return rtrim(cc_env('CC_STORAGE_PATH', dirname(__DIR__) . '/storage'), '/\\');
}
function cc_csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}
function cc_fail(int $status, string $message): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false, 'message'=>$message]);
    exit;
}
function cc_rate_limit(string $bucket, int $limit = 10, int $window = 900, bool $byIp = true): void {
    $dir = cc_storage() . '/limits';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) cc_fail(503, 'Please try again later.');
    // Do not trust client supplied forwarding headers. $byIp=false makes the
    // bucket global for the identity (e.g. one account) whatever address it uses.
    $key = hash('sha256', $bucket . '|' . ($byIp ? ($_SERVER['REMOTE_ADDR'] ?? 'cli') : '*'));
    $handle = fopen($dir . '/' . $key . '.json', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) cc_fail(503, 'Please try again later.');
    $state = json_decode(stream_get_contents($handle), true) ?: ['start'=>time(), 'count'=>0];
    if (time() - $state['start'] >= $window) $state = ['start'=>time(), 'count'=>0];
    $allowed = ++$state['count'] <= $limit;
    rewind($handle); ftruncate($handle, 0); fwrite($handle, json_encode($state));
    flock($handle, LOCK_UN); fclose($handle);
    // Housekeeping: attackers can mint unlimited bucket names, so expired state
    // files are removed (about 1 request in 50) to stop the folder growing forever.
    if (mt_rand(1, 50) === 1) {
        $cutoff = time() - max($window, 900) * 2; $n = 0;
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) @unlink($file);
            if (++$n >= 500) break;
        }
    }
    if (!$allowed) { header('Retry-After: ' . max(1, $window - (time()-$state['start']))); cc_fail(429, 'Too many attempts. Please try again later.'); }
}
/*
 * Failed-attempt throttling (used for password sign-in).
 * Unlike cc_rate_limit() these let the caller CHECK first, count only FAILURES and clear on success.
 * $byIp=true  : the bucket is per (identity, client address)  - an attacker cannot lock a victim out
 *               of the victim's own network.
 * $byIp=false : the bucket is per identity from anywhere      - a high ceiling that only a distributed
 *               attack reaches.
 */
function cc_throttle_path(string $bucket, bool $byIp): string {
    $dir = cc_storage() . '/limits';
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) cc_fail(503, 'Please try again later.');
    return $dir . '/' . hash('sha256', $bucket . '|' . ($byIp ? ($_SERVER['REMOTE_ADDR'] ?? 'cli') : '*')) . '.json';
}
function cc_throttle_count(string $bucket, int $window, bool $byIp = true): int {
    $file = cc_throttle_path($bucket, $byIp);
    if (!is_file($file)) return 0;
    $state = json_decode((string)@file_get_contents($file), true);
    if (!is_array($state) || time() - (int)($state['start'] ?? 0) >= $window) return 0;
    return (int)($state['count'] ?? 0);
}
function cc_throttle_hit(string $bucket, int $window, bool $byIp = true): void {
    $handle = fopen(cc_throttle_path($bucket, $byIp), 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return;
    $state = json_decode(stream_get_contents($handle), true);
    if (!is_array($state) || time() - (int)($state['start'] ?? 0) >= $window) $state = ['start' => time(), 'count' => 0];
    $state['count'] = (int)$state['count'] + 1;
    rewind($handle); ftruncate($handle, 0); fwrite($handle, json_encode($state));
    flock($handle, LOCK_UN); fclose($handle);
}
function cc_throttle_clear(string $bucket, bool $byIp = true): void {
    @unlink(cc_throttle_path($bucket, $byIp));
}
/** Content-Security-Policy value for this deployment ('' when disabled). */
/** Per-request nonce that authorises this page's own inline <script> blocks. */
function cc_nonce(): string {
    return $GLOBALS['cc_csp_nonce'] ?? '';
}
function cc_csp(bool $secure): string {
    if (strtolower(cc_env('CC_CSP_MODE', 'enforce')) === 'off') return '';
    // Inline scripts must carry the per-request nonce; inline event-handler attributes are not
    // allowed at all. CC_CSP_UNSAFE_INLINE=1 is an emergency escape hatch that restores the old behaviour.
    $scriptInline = cc_env('CC_CSP_UNSAFE_INLINE', '0') === '1' ? "'unsafe-inline'" : "'nonce-" . cc_nonce() . "'";
    $frames = ['https://accounts.google.com'];
    $scene = parse_url(cc_env('CC_3D_SCENE_URL'));
    if (is_array($scene) && strtolower($scene['scheme'] ?? '') === 'https' && !empty($scene['host'])) {
        $frames[] = 'https://' . $scene['host'] . (isset($scene['port']) ? ':' . (int)$scene['port'] : '');
    }
    $directives = [
        "default-src 'self'",
        "script-src 'self' " . $scriptInline . " https://cdn.jsdelivr.net https://accounts.google.com",
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://accounts.google.com",
        "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com",
        "img-src 'self' data: blob: https:",
        "media-src 'self' blob:",
        "connect-src 'self' https://accounts.google.com",
        "frame-src " . implode(' ', $frames),
        "worker-src 'self' blob:",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self' https://www.linkedin.com https://accounts.google.com",
        "frame-ancestors 'self'",
    ];
    if ($secure) $directives[] = 'upgrade-insecure-requests';
    return implode('; ', $directives);
}
function cc_destroy_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>true,'samesite'=>'Lax']);
    }
    session_destroy();
}
function cc_bootstrap(): void {
    ob_start();
    date_default_timezone_set(cc_env('APP_TIMEZONE', 'Asia/Kolkata'));
    ini_set('display_errors','0'); ini_set('log_errors','1');
    set_exception_handler(function(Throwable $error) {
        error_log('Unhandled application error: ' . $error->getMessage());
        while(ob_get_level()>0) ob_end_clean();
        cc_fail(500, 'The request could not be completed. Please try again.');
    });
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if(cc_env('APP_MAINTENANCE','0')==='1') { header('Retry-After: 300'); cc_fail(503,'Campus Chronicle is undergoing maintenance. Please try again shortly.'); }
    if (cc_env('APP_ENV', 'production') === 'production' && !$secure) cc_fail(503, 'HTTPS is required.');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    // same-origin-allow-popups keeps the Google sign-in popup working.
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    $GLOBALS['cc_csp_nonce'] = bin2hex(random_bytes(16));
    $csp = cc_csp($secure);
    if ($csp !== '') {
        // CC_CSP_MODE=report-only is the instant, no-redeploy rollback if a page ever misbehaves.
        header((strtolower(cc_env('CC_CSP_MODE', 'enforce')) === 'report-only' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy') . ': ' . $csp);
    }
    header('Cache-Control: no-store');
    if ($secure) header('Strict-Transport-Security: max-age=31536000');
    ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
    session_set_cookie_params(['httponly'=>true,'secure'=>$secure,'samesite'=>'Lax','path'=>'/']);
    // The __Host- prefix makes browsers refuse to let any other (sub)domain overwrite
    // this cookie. It requires Secure + Path=/ + no Domain, so only over HTTPS.
    if ($secure) session_name('__Host-CCSESSID');
    session_start();
    if (isset($_SESSION['user_id']) && (time() - ($_SESSION['last_active'] ?? 0) > 1800 || time() - ($_SESSION['authenticated_at'] ?? 0) > 28800)) {
        $_SESSION=[]; session_regenerate_id(true);
    }
    $_SESSION['last_active']=time();
    $_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    $page = $_GET['page'] ?? 'welcome';
    if (!is_string($page)) cc_fail(400, 'Invalid page.');
    $actions = ['logout','save-event','update-event','delete-event','register-event','cancel-registration','approve-registration','reject-registration','mark-attendance','generate-certificate','award-certificate','save-certificate-settings','backfill-certificates','submit-contact','promote-user','demote-user','delete-user','save-gallery','update-gallery','delete-gallery','upload-media','save-media','update-media','delete-media','save-announcement','save-template','delete-template','mark-notification-read','two-factor-enable','two-factor-disable'];
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($page,$actions,true) && $method !== 'POST') { header('Allow: POST'); cc_fail(405,'This action requires POST.'); }
    if (!in_array($method,['GET','HEAD','POST'],true)) cc_fail(405,'Unsupported method.');
    foreach ($_GET as $value) if(!is_string($value)) cc_fail(400,'Invalid query parameter.');
    if ($method === 'POST') {
        foreach($_POST as $key=>$value) {
            if(is_array($value) && in_array($page,['save-media','upload-media'],true) && in_array($key,['title','description'],true)) {
                foreach($value as $item) if(!is_string($item) || strlen($item)>50000) cc_fail(400,'Invalid form value.');
            } elseif(!is_string($value) || strlen($value)>50000) cc_fail(400,'Invalid form value.');
        }
        $token=$_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'],$token)) cc_fail(403,'Request expired or could not be verified. Reload the page and try again.');
        // Two layers. The per-address ceiling is generous because a whole campus can share one
        // public IP; the per-account limit is the strict one and applies from ANY address.
        $ipLimits = ['login'=>600,'signup'=>200,'forgot-password'=>60,'google-login'=>600,'submit-contact'=>30,'two-factor'=>120];
        if (isset($ipLimits[$page])) {
            cc_rate_limit($page, $ipLimits[$page]);
            $identity = strtolower(trim((string)($_POST['email'] ?? '')));
            // Password sign-in is throttled on FAILED attempts inside AuthController::login().
            $accountLimits = ['signup'=>5,'forgot-password'=>5,'submit-contact'=>5];
            if ($identity !== '' && isset($accountLimits[$page])) {
                cc_rate_limit($page . '|acct|' . $identity, $accountLimits[$page], 900, false);
            }
        }
    }
    if (isset($_SESSION['user_id'])) {
        require_once __DIR__.'/database.php';
        $q=Database::connect()->prepare('SELECT role,password FROM users WHERE id=?');
        $q->execute([$_SESSION['user_id']]); $user=$q->fetch();
        if (!$user || !hash_equals($_SESSION['credential_version'] ?? '',hash('sha256',$user['password']))) {
            cc_destroy_session(); header('Location: index.php?page=login'); exit;
        }
        $_SESSION['role']=$user['role'];
    }
    if($method==='POST' && in_array($page,['save-event','update-event','save-gallery','update-gallery','profile','upload-media','save-media','update-media','save-template'],true)) {
        require_once __DIR__.'/database.php';
        $db=Database::connect(); $db->beginTransaction();
        $GLOBALS['cc_write_transaction']=true;
        $GLOBALS['cc_new_files']=[];
        register_shutdown_function(function()use($db) {
            if(!empty($GLOBALS['cc_write_transaction'])) {
                if($db->inTransaction()) $db->rollBack();
                require_once dirname(__DIR__).'/app/helpers/FileUpload.php';
                foreach($GLOBALS['cc_new_files']??[] as $file) FileUpload::deleteFile($file);
            }
        });
    }
}

function cc_commit_write(): void {
    if(!empty($GLOBALS['cc_write_transaction'])) {
        Database::connect()->commit();
        $GLOBALS['cc_write_transaction']=false;
    }
}
