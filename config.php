<?php
// Oroma TV — site config & session bootstrap
// Auto-detects local vs live server environment

// ================================================
// 1. ENVIRONMENT DETECTION
// ================================================

// Check if we're running on localhost
$is_local = (
    $_SERVER['SERVER_NAME'] === 'localhost' ||
    $_SERVER['SERVER_NAME'] === '127.0.0.1' ||
    strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false
);

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