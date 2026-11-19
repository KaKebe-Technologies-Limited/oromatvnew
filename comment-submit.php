<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/comment-moderation.php';

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
if (empty($_POST['accept_terms'])) {
    $errors[] = 'Please tick the box to agree to the Terms of Use.';
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect($backUrl);
}

// The same limit as the app's API (6 comments per 10 minutes per visitor, one shared counter), so the website cannot be flooded either.
$wait = ugc_rate_hit('comment', 6, 600);
if ($wait > 0) {
    $minutes = max(1, (int) ceil($wait / 60));
    flash('error', 'You have posted several comments in a short time. Please wait about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' and try again.');
    redirect($backUrl);
}

ensure_comment_schema();
$device = ugc_device_hash(null);

// The same device posting the same words again within 10 minutes is a double click or copy-paste spam.
if (comment_is_duplicate((int) $article['id'], $comment, $device)) {
    flash('error', 'You already posted that comment.');
    redirect($backUrl);
}

[$status, $hold] = comment_moderate($name, $email ?: null, $comment, $device);
comment_insert((int) $article['id'], $name, $email ?: null, $comment, $status, $hold, $device, date('Y-m-d H:i:s'));

flash('success', $status === 'approved' ? 'Thanks! Your comment is now live.' : COMMENT_HELD_MESSAGE);
redirect($backUrl);
