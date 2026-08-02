<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_PATH . '/news.php');
}

require_csrf();

$articleId = (int) ($_POST['article_id'] ?? 0);

$stmt = db()->prepare("SELECT id, slug FROM articles WHERE id = ? AND status = 'published'");
$stmt->execute([$articleId]);
$article = $stmt->fetch();

if (!$article) {
    flash('error', 'That article could not be found.');
    redirect(BASE_PATH . '/news.php');
}

$backUrl = BASE_PATH . '/article.php?slug=' . urlencode($article['slug']) . '#comments';

// Honeypot: real visitors never fill this hidden field. Silently drop likely-bot submissions.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    redirect($backUrl);
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$comment = trim((string) ($_POST['comment'] ?? ''));

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
    $errors[] = 'Please enter your name (2-100 characters).';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address, or leave it blank.';
}
if (mb_strlen($comment) < 2 || mb_strlen($comment) > 2000) {
    $errors[] = 'Comment must be between 2 and 2000 characters.';
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect($backUrl);
}

$stmt = db()->prepare(
    "INSERT INTO comments (article_id, name, email, comment, status, created_at) VALUES (?, ?, ?, ?, 'approved', ?)"
);
$stmt->execute([$article['id'], $name, $email ?: null, $comment, date('Y-m-d H:i:s')]);

flash('success', 'Thanks! Your comment is now live.');
redirect($backUrl);
