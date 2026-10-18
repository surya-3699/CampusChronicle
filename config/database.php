<?php

/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
| A single shared PDO connection for the whole request. Change the four
| settings below to match your MySQL/MariaDB setup (XAMPP defaults are
| already filled in).
|
| Load-resilience behaviour (see docs/SCALING.md):
|   * 5 s connect timeout, so a stalled database can never pin a PHP worker.
|   * Short jittered retry when MySQL is momentarily out of connections
|     (error 1040) or refuses/drops the socket, instead of failing at once.
|   * If the database is still unreachable the visitor gets an honest
|     "503 + Retry-After" rather than an opaque 500.
|   * DB_SSL=1 (opt-in) encrypts the connection and verifies the server certificate.
|   * DB_PERSISTENT=1 (opt-in) reuses connections across requests inside each
|     PHP-FPM worker. Keep it off unless FPM_MAX_CHILDREN stays below the
|     database's max_connections.
*/

class Database
{
    private static $host = "localhost";
    private static $dbname = "campus_chronicle";
    private static $username = "root";
    private static $password = "";

    private static $conn = null;
    private static $columnCache = [];

    /** MySQL/MariaDB errors that are worth a quick retry (transient). */
    private const TRANSIENT_CODES = [1040, 1203, 2002, 2006, 2013];

    public static function connect()
    {
        if (self::$conn !== null) {
            return self::$conn;
        }

        if (PHP_SAPI !== 'cli' && getenv('APP_ENV') !== 'development') {
            if (!getenv('DB_HOST') || !getenv('DB_NAME') || !getenv('DB_USER') || getenv('DB_USER') === 'root' || !getenv('DB_PASSWORD')) {
                http_response_code(503);
                exit('Production database configuration is incomplete.');
            }
        }

        $dsn = "mysql:host=" . (getenv("DB_HOST") ?: self::$host)
            . ";port=" . (getenv("DB_PORT") ?: "3306")
            . ";dbname=" . (getenv("DB_NAME") ?: self::$dbname)
            . ";charset=utf8mb4";
        $user = getenv("DB_USER") !== false ? getenv("DB_USER") : self::$username;
        $pass = getenv("DB_PASSWORD") !== false ? getenv("DB_PASSWORD") : self::$password;
        $options = [
            PDO::ATTR_TIMEOUT    => 5,
            PDO::ATTR_PERSISTENT => getenv('DB_PERSISTENT') === '1',
        ];

        // TLS to the database. Turn on (DB_SSL=1) whenever MySQL is not on the same
        // private network as the app (managed/external MySQL). Verifies the server
        // certificate against DB_SSL_CA, or the system CA bundle when not set.
        if (getenv('DB_SSL') === '1' && defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = getenv('DB_SSL_CA') ?: '/etc/ssl/certs/ca-certificates.crt';
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = getenv('DB_SSL_VERIFY') !== '0';
        }

        $attempts = 3;
        for ($try = 1; $try <= $attempts; $try++) {
            try {
                $pdo = new PDO($dsn, $user, $pass, $options);

                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                // Event times are entered and compared in the application's local time (APP_TIMEZONE),
                // but SQL NOW() uses the database server's clock, and hosted MySQL runs on UTC. Align this
                // session with the application so upcoming / ongoing / completed status, gallery
                // publishing and automatic certificate issuing change at the right moment.
                try {
                    $tz = new DateTimeZone(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');
                    $pdo->exec("SET time_zone = '" . (new DateTime('now', $tz))->format('P') . "'");
                } catch (Throwable $e) {
                    error_log('Could not align the database time zone: ' . $e->getMessage());
                }
                self::$conn = $pdo;

                // Keep older XAMPP databases compatible with the current codebase.
                require_once __DIR__ . "/schema_compatibility.php";
                if (getenv("CC_AUTO_MIGRATE") === "1") SchemaCompatibility::run(self::$conn);

                return self::$conn;
            } catch (PDOException $e) {
                self::$conn = null;
                $code = (int)($e->errorInfo[1] ?? 0);
                if ($code === 0 && preg_match('/\[(\d{4})\]/', $e->getMessage(), $m)) {
                    $code = (int)$m[1];
                }
                if ($try < $attempts && in_array($code, self::TRANSIENT_CODES, true)) {
                    // 60-180 ms, 120-360 ms: lets a burst drain without piling on.
                    usleep(random_int(60000, 180000) * $try);
                    continue;
                }

                // Log the real reason server-side, but never leak raw SQL/
                // connection details to whoever is looking at the page.
                error_log("Database connection failed (code {$code}, attempt {$try}): " . $e->getMessage());

                if (!headers_sent()) {
                    http_response_code(503);
                    header('Retry-After: 5');
                }
                die("We're having trouble connecting to the database right now. Please try again shortly.");
            }
        }
    }

    /**
     * True when $table has $column. Cached for the request. Only used on admin
     * write paths so deployments that have not yet run tools/migrate.php get a
     * clear message instead of an "Unknown column" crash.
     */
    public static function hasColumn(string $table, string $column): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return false;
        }
        $key = $table . '.' . $column;
        if (!array_key_exists($key, self::$columnCache)) {
            $stmt = self::connect()->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
            );
            $stmt->execute([$table, $column]);
            self::$columnCache[$key] = ((int)$stmt->fetchColumn()) > 0;
        }
        return self::$columnCache[$key];
    }
}
