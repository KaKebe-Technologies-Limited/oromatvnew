<?php
// TEMPORARY DIAGNOSTIC — DELETE AFTER USE
// Upload to live server and visit this URL to see the exact error

// Force all errors visible
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo '<pre>';
echo 'PHP Version: ' . PHP_VERSION . "\n";
echo 'Extensions: ' . implode(', ', get_loaded_extensions()) . "\n\n";

// Test config
echo "--- Testing config.php ---\n";
try {
    require_once __DIR__ . '/config.php';
    echo "config.php: OK\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    echo "BASE_PATH: " . BASE_PATH . "\n";
    echo "SITE_URL: " . SITE_URL . "\n";
} catch (Throwable $e) {
    echo "config.php FAILED: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test DB
echo "\n--- Testing database connection ---\n";
try {
    require_once __DIR__ . '/includes/db.php';
    $result = db()->query("SELECT COUNT(*) AS c FROM articles")->fetch();
    echo "DB Connection: OK\n";
    echo "Articles in DB: " . $result['c'] . "\n";
} catch (Throwable $e) {
    echo "DB FAILED: " . $e->getMessage() . "\n";
}

// Test functions
echo "\n--- Testing functions.php ---\n";
try {
    require_once __DIR__ . '/includes/functions.php';
    echo "functions.php: OK\n";
} catch (Throwable $e) {
    echo "functions.php FAILED: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Test new functions
echo "\n--- Testing previous-shows-functions.php ---\n";
try {
    require_once __DIR__ . '/includes/previous-shows-functions.php';
    echo "previous-shows-functions.php: OK\n";
    $shows = get_previous_shows(5);
    echo "Shows in DB: " . count($shows) . "\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}

// Check DB tables exist
echo "\n--- Checking tables ---\n";
try {
    $tables = ['articles','categories','users','streams','settings','comments','previous_shows'];
    foreach ($tables as $t) {
        try {
            db()->query("SELECT 1 FROM $t LIMIT 1");
            echo "$t: EXISTS\n";
        } catch (Throwable $e) {
            echo "$t: MISSING or ERROR — " . $e->getMessage() . "\n";
        }
    }
} catch (Throwable $e) {
    echo "Table check failed: " . $e->getMessage() . "\n";
}

// Check required columns
echo "\n--- Checking article columns ---\n";
try {
    $cols = db()->query("SHOW COLUMNS FROM articles")->fetchAll();
    $names = array_column($cols, 'Field');
    echo "Columns: " . implode(', ', $names) . "\n";
    foreach (['is_breaking','is_featured','views'] as $col) {
        echo "$col: " . (in_array($col, $names) ? 'OK' : 'MISSING') . "\n";
    }
} catch (Throwable $e) {
    echo "Column check failed: " . $e->getMessage() . "\n";
}

echo "\n--- Checking categories columns ---\n";
try {
    $cols = db()->query("SHOW COLUMNS FROM categories")->fetchAll();
    $names = array_column($cols, 'Field');
    echo "Columns: " . implode(', ', $names) . "\n";
    foreach (['icon','display_order','is_active'] as $col) {
        echo "$col: " . (in_array($col, $names) ? 'OK' : 'MISSING') . "\n";
    }
} catch (Throwable $e) {
    echo "Column check failed: " . $e->getMessage() . "\n";
}

echo "\n--- DONE ---\n";
echo '</pre>';
