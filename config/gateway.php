<?php

/*
|--------------------------------------------------------------------------
| Request Gateway — the "middle manager" between clients and the application
|--------------------------------------------------------------------------
| Runs first in index.php, BEFORE the session is opened and BEFORE any database
| connection is made, so rejecting or delaying a request costs almost nothing.
|
|   1. Health check   ?page=healthz answers instantly with no session / DB, for
|                     the platform's health probe and for load tests.
|   2. Rate limiting  per client IP, per 10 s window (GET and POST separately).
|                     Over the limit -> 429 + Retry-After.
|   3. Admission      a bounded number of requests may run the application at
|      control        once (GATEWAY_MAX_ACTIVE). Extra requests wait briefly in
|                     a queue, then get a friendly 503 + Retry-After instead of
|                     piling up on MySQL and timing out.
|   4. Priority lane  sign-in, admin and write (POST) requests may use a slice of
|                     capacity reserved for them (GATEWAY_RESERVED), so a burst
|                     of anonymous page views can never lock admins or logins out.
|
| It does not replace the web server layer: Apache's event MPM holds the 500+
| open connections cheaply, PHP-FPM caps real PHP work, and this gateway decides
| who gets to run and who is asked to retry. See docs/SCALING.md.
|
| Environment (all optional):
|   GATEWAY_ENABLED      1 (default) | 0 = switch the gateway off
|   GATEWAY_MAX_ACTIVE   concurrent application executions (default 24)
|   GATEWAY_RESERVED     of those, kept for priority traffic (default 25 %)
|   GATEWAY_QUEUE_MS     longest a public request waits for a slot (default 1500;
|                        priority requests wait twice as long)
|   GATEWAY_RATE_GET     max GET/HEAD per IP per 10 s (default 600)
|   GATEWAY_RATE_POST    max POST per IP per 10 s (default 120)
|
| Rate limits are deliberately generous: a whole campus can share one public IP.
| Only REMOTE_ADDR is used; client-supplied forwarding headers are never trusted
| here (Apache's mod_remoteip resolves the real client behind the platform edge).
*/

final class RequestGateway
{
    private const WINDOW_SECONDS = 10;

    /** @var resource[] lock handles opened for slot scanning */
    private static $handles = [];
    /** @var int|null index of the slot this request holds */
    private static $held = null;
    private static $dir = null;

    public static function handle(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $page   = isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : 'welcome';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($page === 'healthz') {
            self::health();
        }

        if (getenv('GATEWAY_ENABLED') === '0') {
            return;
        }

        $priority = self::isPriority($page, $method);

        self::rateLimit($method);
        self::admit($priority);
    }

    /** Best-effort check for crawlers/monitors, so they do not create sessions or visit rows. */
    public static function isProbableBot(): bool
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return $ua === '' || preg_match('/bot|crawl|spider|slurp|monitor|uptime|probe|headless|python-requests|wget|go-http-client|curl\//i', $ua) === 1;
    }

    /* ------------------------------------------------------------------ */
    /* Health                                                              */
    /* ------------------------------------------------------------------ */

    private static function health(): void
    {
        [$max] = self::capacity();
        $busy = 0;
        $dir = self::dir();
        if ($dir !== null) {
            for ($i = 0; $i < $max; $i++) {
                $fp = @fopen($dir . '/slot-' . $i . '.lock', 'c');
                if (!$fp) continue;
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    flock($fp, LOCK_UN);
                } else {
                    $busy++;
                }
                fclose($fp);
            }
        }

        http_response_code(200);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        // "client" is the address this server believes YOU are. Open the health URL from your phone and
        // from a laptop: if the two differ (and match your real IPs) client-IP detection works behind
        // the platform proxy / CDN.
        echo json_encode(['status' => 'ok', 'busy' => $busy, 'capacity' => $max, 'client' => (string)($_SERVER['REMOTE_ADDR'] ?? '')]);
        exit;
    }

    /* ------------------------------------------------------------------ */
    /* Priority classification                                             */
    /* ------------------------------------------------------------------ */

    private static function isPriority(string $page, string $method): bool
    {
        if ($method === 'POST') {
            return true; // writes: registrations, saves, uploads, sign-in
        }
        return preg_match('/^(admin|manage|add-|edit-|save-|update-|delete-|login|logout|signup|forgot|reset|verify|oauth|google|linkedin)/', $page) === 1;
    }

    /* ------------------------------------------------------------------ */
    /* Rate limiting (per client IP, fixed 10 s window)                    */
    /* ------------------------------------------------------------------ */

    private static function rateLimit(string $method): void
    {
        $isPost = $method === 'POST';
        $limit  = $isPost ? self::intEnv('GATEWAY_RATE_POST', 120) : self::intEnv('GATEWAY_RATE_GET', 600);
        if ($limit <= 0) {
            return;
        }

        $ip = self::clientKey();
        $window = (int)floor(time() / self::WINDOW_SECONDS);
        $count = self::hit('cc_rl_' . ($isPost ? 'p' : 'g') . '_' . $ip . '_' . $window, self::WINDOW_SECONDS + 2);

        if ($count > $limit) {
            $retry = self::WINDOW_SECONDS - (time() % self::WINDOW_SECONDS) + mt_rand(0, 2);
            self::reject(429, max(1, $retry), 'Too many requests from your network. Please slow down for a moment.');
        }
    }

    /** Increment and return a short-lived counter (APCu if present, otherwise a tiny lock file). */
    private static function hit(string $key, int $ttl): int
    {
        if (function_exists('apcu_add') && function_exists('apcu_inc') && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            apcu_add($key, 0, $ttl);
            $n = apcu_inc($key);
            if ($n !== false) {
                return (int)$n;
            }
        }

        $dir = self::dir();
        if ($dir === null) {
            return 0; // cannot count -> fail open rather than block real users
        }
        $file = $dir . '/rl-' . sha1($key) . '.cnt';
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return 0;
        }
        $n = 1;
        if (flock($fp, LOCK_EX)) {
            $n = (int)stream_get_contents($fp) + 1;
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, (string)$n);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);

        if (mt_rand(1, 400) === 1) {
            self::sweep($dir);
        }
        return $n;
    }

    /** Remove stale counter files (runs on ~0.25 % of requests). */
    private static function sweep(string $dir): void
    {
        $cutoff = time() - 120;
        $n = 0;
        foreach (@glob($dir . '/rl-*.cnt') ?: [] as $f) {
            if (@filemtime($f) < $cutoff) {
                @unlink($f);
            }
            if (++$n > 2000) break;
        }
    }

    private static function clientKey(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $bin = @inet_pton($ip);
        if ($bin !== false && strlen($bin) === 16) {
            return bin2hex(substr($bin, 0, 8)); // IPv6: group by /64
        }
        return preg_replace('/[^0-9a-f.:]/i', '', $ip);
    }

    /* ------------------------------------------------------------------ */
    /* Admission control                                                   */
    /* ------------------------------------------------------------------ */

    /** @return array{0:int,1:int} [max active, reserved for priority] */
    private static function capacity(): array
    {
        $max = max(2, self::intEnv('GATEWAY_MAX_ACTIVE', 24));
        $reserved = self::intEnv('GATEWAY_RESERVED', (int)max(2, floor($max * 0.25)));
        $reserved = min(max(0, $reserved), $max - 1);
        return [$max, $reserved];
    }

    private static function admit(bool $priority): void
    {
        $dir = self::dir();
        if ($dir === null) {
            return; // no shared directory available -> fail open
        }

        [$max, $reserved] = self::capacity();
        $publicSlots = $max - $reserved;

        // Public traffic scans slots [0, publicSlots); priority traffic tries the
        // reserved slots first, then everything else.
        $order = [];
        if ($priority) {
            for ($i = $max - 1; $i >= $publicSlots; $i--) $order[] = $i;
        }
        $start = mt_rand(0, max(0, $publicSlots - 1));
        for ($k = 0; $k < $publicSlots; $k++) $order[] = ($start + $k) % $publicSlots;

        $waitMs   = self::intEnv('GATEWAY_QUEUE_MS', 1500) * ($priority ? 2 : 1);
        $began    = microtime(true);
        $deadline = $began + $waitMs / 1000;

        do {
            foreach ($order as $i) {
                if (!isset(self::$handles[$i])) {
                    $fp = @fopen($dir . '/slot-' . $i . '.lock', 'c');
                    if (!$fp) continue;
                    self::$handles[$i] = $fp;
                }
                if (flock(self::$handles[$i], LOCK_EX | LOCK_NB)) {
                    self::$held = $i;
                    register_shutdown_function([self::class, 'release']);
                    $waited = (int)round((microtime(true) - $began) * 1000);
                    if ($waited >= 5 && !headers_sent()) {
                        header('X-Gateway-Wait: ' . $waited . 'ms');
                    }
                    return;
                }
            }
            usleep(random_int(10000, 30000));
        } while (microtime(true) < $deadline);

        self::reject(503, mt_rand(2, 5), "We're very busy right now. Please try again in a few seconds.");
    }

    /** Frees this request's slot. Also runs automatically when the process ends. */
    public static function release(): void
    {
        foreach (self::$handles as $i => $fp) {
            if (is_resource($fp)) {
                if ($i === self::$held) {
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
            }
        }
        self::$handles = [];
        self::$held = null;
    }

    /* ------------------------------------------------------------------ */
    /* Responses + helpers                                                 */
    /* ------------------------------------------------------------------ */

    private static function reject(int $status, int $retryAfter, string $message): void
    {
        self::release();
        http_response_code($status);
        header('Retry-After: ' . $retryAfter);
        header('Cache-Control: no-store');
        header('X-Gateway: ' . ($status === 429 ? 'rate-limited' : 'shed'));

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $ajax   = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
        if ($ajax || stripos($accept, 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $message, 'retry_after' => $retryAfter]);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        $refresh = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') ? '<meta http-equiv="refresh" content="' . (int)$retryAfter . '">' : '';
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . $refresh . '<title>Please wait — Campus Chronicle</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#030711;color:#e8f0ff;font:16px/1.5 system-ui,sans-serif;text-align:center;padding:24px}'
            . 'main{max-width:420px}h1{font-size:1.4rem;margin:0 0 .5rem}p{opacity:.8;margin:.25rem 0}</style></head><body><main>'
            . '<h1>' . htmlspecialchars($status === 429 ? 'Slow down a moment' : "We're a little busy", ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . ($refresh !== '' ? '<p>This page will retry automatically.</p>' : '<p>Your request was not processed — please submit it again.</p>')
            . '</main></body></html>';
        exit;
    }

    private static function intEnv(string $name, int $default): int
    {
        $v = getenv($name);
        return ($v !== false && $v !== '' && is_numeric($v)) ? (int)$v : $default;
    }

    /** Shared directory for slot locks and counters (memory-backed when possible). */
    private static function dir(): ?string
    {
        if (self::$dir !== null) {
            return self::$dir === '' ? null : self::$dir;
        }
        $base = (is_dir('/dev/shm') && is_writable('/dev/shm')) ? '/dev/shm' : sys_get_temp_dir();
        $dir = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . 'cc-gateway';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        self::$dir = (is_dir($dir) && is_writable($dir)) ? $dir : '';
        return self::$dir === '' ? null : self::$dir;
    }
}
