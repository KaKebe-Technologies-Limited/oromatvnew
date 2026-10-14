<?php
/**
 * Live viewer figures for the admin screen, as JSON. The Live Control tab asks for this every 15 seconds so the
 * "watching now" number moves by itself. Only for logged-in admin users.
 *
 *   now / app / web   devices watching a live programme (or the main channel) right now, and the split by platform
 *   offair            devices watching the off-air playlist
 *   peak, unique      for the programme on air: its highest viewer count, and how many different devices have watched it
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/live-functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$resolved = resolve_live();
$scope = live_scope($resolved);
$counts = live_viewer_counts();

echo json_encode([
    'now'    => $counts['now'],
    'app'    => $counts['app'],
    'web'    => $counts['web'],
    'offair' => $counts['offair'],
    'source' => $resolved['source'],
    'peak'   => $resolved['program'] ? (int) $resolved['program']['peak_viewers'] : null,
    'unique' => $resolved['source'] === 'none' ? null : live_unique_viewers($scope),
    'at'     => date('H:i:s'),
]);
