<?php
// Oroma TV — site config & session bootstrap

define('DB_HOST', 'localhost');
define('DB_NAME', 'oroma_tv');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SITE_NAME', 'Oroma TV');
define('SITE_TAGLINE', 'Dwon me Tumalo');

// BASE_PATH: the web-root-relative path this project is served from (e.g. "/oromatvnew").
// Computed from config.php's own location relative to the server document root, so it is
// correct no matter which page (top-level or /admin/*) includes this file.
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__));
$projectRoot = str_replace('\\', '/', __DIR__);
define('BASE_PATH', rtrim(substr($projectRoot, strlen($docRoot)), '/'));
unset($docRoot, $projectRoot);

define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH);
define('UPLOAD_DIR', __DIR__ . '/uploads');
// Relative (root of the project, no BASE_PATH) so values stored in the DB stay portable;
// pages prepend BASE_PATH themselves when rendering a src/href.
define('UPLOAD_URL', 'uploads');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Africa/Nairobi');
