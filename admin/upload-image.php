<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

if (empty($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image uploaded.']);
    exit;
}

$error = null;
$url = handle_image_upload($_FILES['image'], $error);

if ($url === null) {
    http_response_code(422);
    echo json_encode(['error' => $error]);
    exit;
}

echo json_encode(['url' => BASE_PATH . '/' . ltrim($url, '/')]);
