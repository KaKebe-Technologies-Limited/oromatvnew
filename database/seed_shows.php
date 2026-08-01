<?php
/**
 * One-time seed script — run via browser or CLI:
 *   php database/seed_shows.php
 * or visit: http://localhost/oromatvnew/database/seed_shows.php
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/previous-shows-functions.php';

// Only allow admin-level access if run via browser
if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/../includes/auth.php';
    if (!is_logged_in()) {
        http_response_code(403);
        die('Access denied. Run via CLI or log in as admin first.');
    }
}

$videos = [
    'https://youtu.be/4Q1_oWwXZu8' => ['guest' => 'Kimberly Jael Aremo Acio', 'featured' => 1],
    'https://youtu.be/hxY5u9KlcIk' => ['guest' => 'Judah Rapknowledge Da Akbar', 'featured' => 0],
    'https://youtu.be/tbjI_YggrFY' => ['guest' => 'Gloria Aleleh', 'featured' => 0],
    'https://youtu.be/jaAou08Xk9w' => ['guest' => 'Young Emma', 'featured' => 0],
];

$results = [];
$order   = 1;

foreach ($videos as $url => $extra) {
    // Skip if already seeded
    $existing = db()->prepare('SELECT id FROM previous_shows WHERE video_id=?');
    $existing->execute([extract_youtube_id($url)]);
    if ($existing->fetch()) {
        $results[] = ['url' => $url, 'status' => 'skipped (already exists)'];
        $order++;
        continue;
    }

    try {
        $id = add_previous_show($url);
        // Override guest name with the known correct name
        db()->prepare(
            'UPDATE previous_shows SET guest_name=?, is_featured=?, display_order=? WHERE id=?'
        )->execute([$extra['guest'], $extra['featured'], $order, $id]);
        $results[] = ['url' => $url, 'status' => 'added', 'id' => $id, 'guest' => $extra['guest']];
    } catch (Exception $e) {
        $results[] = ['url' => $url, 'status' => 'error: ' . $e->getMessage()];
    }
    $order++;
    usleep(300000); // 0.3s delay between requests
}

if (php_sapi_name() === 'cli') {
    foreach ($results as $r) {
        echo $r['status'] . ' — ' . $r['url'] . "\n";
    }
} else {
    echo '<pre style="font-family:monospace;padding:20px;">';
    echo "Seed Results:\n\n";
    foreach ($results as $r) {
        echo $r['status'] . ' — ' . $r['url'] . "\n";
    }
    echo "\nDone. <a href='../admin/previous-shows.php'>View in Admin →</a></pre>";
}
