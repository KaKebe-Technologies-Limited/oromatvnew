<?php
// check_error.php - Find the exact PHP error

// Enable ALL error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Error Check</h1>";

// Try to include files one by one
try {
    echo "<p>Checking config.php...</p>";
    require_once __DIR__ . '/config.php';
    echo "<p style='color:green;'>✅ config.php loaded</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>❌ config.php error: " . $e->getMessage() . "</p>";
    die();
}

try {
    echo "<p>Checking functions.php...</p>";
    require_once __DIR__ . '/includes/functions.php';
    echo "<p style='color:green;'>✅ functions.php loaded</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>❌ functions.php error: " . $e->getMessage() . "</p>";
    die();
}

try {
    echo "<p>Checking database connection...</p>";
    $db = getDBConnection();
    if ($db) {
        echo "<p style='color:green;'>✅ Database connected to: " . DB_NAME . "</p>";
    } else {
        echo "<p style='color:red;'>❌ Database connection failed</p>";
    }
} catch (Throwable $e) {
    echo "<p style='color:red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<h2>All checks passed! Now trying to load index.php...</h2>";
echo "<p><a href='index.php'>Try loading index.php</a></p>";