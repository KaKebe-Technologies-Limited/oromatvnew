<?php
// Oroma TV — site config & session bootstrap
// Auto-detects local vs live server environment

// ================================================
// 1. ENVIRONMENT DETECTION
// ================================================

// This is "local" only when BOTH hold: every host name the request carries (the Host header AND the server's own SERVER_NAME) is
// exactly a local one (localhost, 127.0.0.1, [::1], *.localhost, *.test, or a private-network IPv4 such as 192.168.0.103, which is
// how a phone testing the mobile app reaches this PC), and, when PHP knows the visitor's address (not on the command line), that
// address is on this machine or the private network. A Host header that merely contains the word "localhost" is not enough, and a
// forged Host header from the internet can never switch a live site into local mode (which turns on display_errors, the local
// database login and the X-Test-IP test header).
$__hostName = static function (string $host): string {
    $host = strtolower(trim($host));
    if (str_starts_with($host, '[')) { // "[::1]" or "[::1]:80"
        $end = strpos($host, ']');
        return $end === false ? $host : substr($host, 0, $end + 1);
    }
    return (string) preg_replace('/:\d+$/', '', $host);
};
$__isPrivate = static function (string $ip): bool {
    if (str_starts_with(strtolower($ip), '::ffff:')) { // an IPv4 address written inside IPv6
        $ip = substr($ip, 7);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) !== false
        && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
};
$__isLocalHost = static function (string $host) use ($__isPrivate): bool {
    return $host !== '' && (
        in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)
        || str_ends_with($host, '.localhost')
        || str_ends_with($host, '.test')
        || (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false && $__isPrivate($host))
    );
};
$__remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

$__names = array_values(array_filter([
    $__hostName((string) ($_SERVER['HTTP_HOST'] ?? '')),
    $__hostName((string) ($_SERVER['SERVER_NAME'] ?? '')),
], static fn(string $n): bool => $n !== ''));
$__localNames = $__names !== [] && count(array_filter($__names, $__isLocalHost)) === count($__names);

$is_local = $__localNames && ($__remoteAddr === '' || $__isPrivate($__remoteAddr));
unset($__hostName, $__isPrivate, $__isLocalHost, $__remoteAddr, $__names, $__localNames);

// Or set manually for debugging (override detection)
// $is_local = true;  // Uncomment to force local mode

// ================================================
// 2. DATABASE CONFIGURATION
// ================================================

if ($is_local) {
    // ---------- LOCAL DEVELOPMENT ----------
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'oroma_tv');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_PORT', '3306');
} else {
    // ---------- LIVE/PRODUCTION SERVER ----------
    define('DB_HOST', 'localhost');  // Usually 'localhost' on hosting
    define('DB_NAME', 'u850523537_OromaTVDB');
    define('DB_USER', 'u850523537_VPS_OromaTV');
    define('DB_PASS', 'Kt2026#Kakebe');
    define('DB_PORT', '3306');
}

// ================================================
// 3. SITE CONFIGURATION
// ================================================

define('SITE_NAME', 'Oroma TV');
define('SITE_TAGLINE', 'Dwon me Tumalo');
define('SITE_EMAIL', 'info@oromatv.com');  // Add your contact email

// The public address of the live website (used for share links and as the page origin the live player expects).
define('OROMA_PUBLIC_URL', 'https://oromatv.com');

// The live-TV player. Used by watch.php and by the mobile app's API (api/v1/live), so changing the stream here
// changes it on the website and in the app. Any embeddable player address works (YouTube, Dacast, ...).
// Currently: https://youtu.be/9pIBgyHzzBA  (to use a different YouTube broadcast, swap the video id after /embed/).
define('OROMA_LIVE_EMBED_URL', 'https://www.youtube.com/embed/9pIBgyHzzBA?rel=0&modestbranding=1&playsinline=1');
// Previous live player, kept so you can switch back by pasting it into the line above:
// 'https://iframe.dacast.com/live/fcdd965b-4244-ed60-8f9f-0a249792f9dc/fd9a7722-2b54-4def-b040-e1887cff2325'

// ================================================
// 4. BASE PATH & URL (Auto-detected)
// ================================================

// BASE_PATH: The web-root-relative path this project is served from
// Computed from config.php's own location relative to the server document root
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__));
$projectRoot = str_replace('\\', '/', __DIR__);
define('BASE_PATH', rtrim(substr($projectRoot, strlen($docRoot)), '/'));
unset($docRoot, $projectRoot);

// SITE_URL: Auto-detects HTTP/HTTPS and local/live
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $protocol . $host . BASE_PATH);

// ================================================
// 5. UPLOAD PATHS
// ================================================

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', 'uploads');

// ================================================
// 6. SESSION & ERROR REPORTING
// ================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting: show errors on local, hide on live
if ($is_local) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    // E_STRICT was removed in PHP 8.0 — use bitwise safely
    $suppress = E_DEPRECATED | (defined('E_STRICT') ? E_STRICT : 0);
    error_reporting(E_ALL & ~$suppress);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/logs/php-errors.log');
}

date_default_timezone_set('Africa/Nairobi');

// ================================================
// 7. SECURITY & CSP (Optional)
// ================================================

// Generate a simple CSRF token if needed
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ================================================
// 8. HELPER FUNCTIONS (Database connection)
// ================================================

/**
 * Get database connection
 * Returns PDO object or null on failure
 */
function getDBConnection() {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Log error but don't expose details in production
        if (isset($is_local) && $is_local) {
            die('Database connection failed: ' . $e->getMessage());
        } else {
            error_log('Database connection failed: ' . $e->getMessage());
            return null;
        }
    }
}

// ================================================
// 9. ENVIRONMENT INFO (For debugging)
// ================================================

// Uncomment these to check environment settings
// if ($is_local) {
//     echo "<!-- Environment: LOCAL -->\n";
//     echo "<!-- DB: " . DB_NAME . " -->\n";
//     echo "<!-- SITE_URL: " . SITE_URL . " -->\n";
// }

// ================================================
// 10. CONFIGURATION SUMMARY (For reference)
// ================================================

// You can access these anywhere in your code
return [
    'is_local' => $is_local,
    'db_host' => DB_HOST,
    'db_name' => DB_NAME,
    'db_user' => DB_USER,
    'site_url' => SITE_URL,
    'base_path' => BASE_PATH,
    'upload_dir' => UPLOAD_DIR,
    'upload_url' => UPLOAD_URL,
];

// ================================================
// END OF CONFIG
// ================================================