<?php
require_once __DIR__ . '/db.php';

/** Shorthand HTML-escape for output. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Turn a string into a URL-safe slug. */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item';
}

/** Slugify $text and, if the slug already exists in $table.slug, append -2, -3, ... */
function unique_slug(string $text, string $table, ?int $excludeId = null): string
{
    $base = slugify($text);
    $slug = $base;
    $i = 2;
    $stmt = db()->prepare("SELECT id FROM `$table` WHERE slug = ? AND id != ? LIMIT 1");
    while (true) {
        $stmt->execute([$slug, $excludeId ?? 0]);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

/** Plain-text excerpt from HTML content, capped at $length chars. */
function make_excerpt(string $html, int $length = 160): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length - 1) . '…';
}

/**
 * Allowlist-sanitize rich-text HTML coming from the Quill editor.
 * Strips <script>/<style>/on*-attributes/javascript: URLs; keeps common formatting tags.
 */
function sanitize_html(string $html): string
{
    if (trim($html) === '') {
        return '';
    }

    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'blockquote', 'pre', 'code',
        'h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'a', 'img', 'span', 'div',
        'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];
    $allowedAttrs = [
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height'],
        'span' => ['class'],
        'div' => ['class'],
        'p' => ['class'],
    ];

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="utf-8" ?><div id="oroma-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $root = $doc->getElementById('oroma-root');
    if (!$root) {
        return '';
    }

    $clean_node = function (DOMNode $node) use (&$clean_node, $doc, $allowedTags, $allowedAttrs) {
        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            if ($child instanceof DOMText || $child instanceof DOMComment && false) {
                continue;
            }
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (!in_array($tag, $allowedTags, true)) {
                // Unwrap: keep children, drop the disallowed wrapper tag (e.g. <script>, <iframe>)
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                    $node->removeChild($child);
                } else {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
                continue;
            }

            // Strip disallowed / dangerous attributes
            $allowed = $allowedAttrs[$tag] ?? [];
            $attrsToRemove = [];
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                $value = trim($attr->value);
                $isEventHandler = str_starts_with($name, 'on');
                $isDangerousUrl = in_array($name, ['href', 'src'], true)
                    && preg_match('~^\s*javascript:~i', $value);
                if ($isEventHandler || $isDangerousUrl || !in_array($name, $allowed, true)) {
                    $attrsToRemove[] = $attr->name;
                }
            }
            foreach ($attrsToRemove as $name) {
                $child->removeAttribute($name);
            }

            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }

            $clean_node($child);
        }
    };

    $clean_node($root);

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return strip_excess_whitespace($out);
}

/**
 * Collapse the oversized blank gaps AI/word-processor pastes leave between paragraphs
 * (empty <p>/<div> spacers, runs of <br>) so paragraph spacing comes only from CSS margin.
 */
function strip_excess_whitespace(string $html): string
{
    // Collapse 2+ consecutive <br> tags into a single one
    $html = preg_replace('/(<br\s*\/?>\s*){2,}/iu', '<br />', $html);
    // Remove empty <p>/<div> tags (including those with only &nbsp; / U+00A0, spaces, or a lone <br>)
    $emptyPattern = '/<(p|div)[^>]*>(\s|&nbsp;|\x{00A0}|<br\s*\/?>)*<\/\1>/iu';
    $html = preg_replace($emptyPattern, '', $html);
    // Run again in case removing inner empties left an outer wrapper newly-empty
    $html = preg_replace($emptyPattern, '', $html);
    return trim($html);
}

// ---------- CSRF ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

// ---------- Flash messages ----------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ---------- Misc ----------

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function format_date(string $datetime, string $format = 'M j, Y'): string
{
    return date($format, strtotime($datetime));
}

/** Relative time string ("2 days ago", "17 hours ago") for a MySQL datetime. */
function time_ago(string $datetime): string
{
    $diff = time() - (strtotime($datetime) ?: time());
    if ($diff < 60) {
        return 'just now';
    }

    $units = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
    ];

    foreach ($units as $seconds => $label) {
        $count = intdiv($diff, $seconds);
        if ($count >= 1) {
            return $count . ' ' . $label . ($count === 1 ? '' : 's') . ' ago';
        }
    }

    return 'just now';
}

/**
 * Validate & move an uploaded image into UPLOAD_DIR/articles/Y/m/, return its public URL path.
 * Returns null on failure and sets $error by reference.
 */
function handle_image_upload(array $file, ?string &$error = null): ?string
{
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed.';
        return null;
    }
    if ($file['size'] > $maxSize) {
        $error = 'Image is larger than 5MB.';
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $error = 'Unsupported image type.';
        return null;
    }
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $error = 'File is not a valid image.';
        return null;
    }

    $subdir = '/articles/' . date('Y') . '/' . date('m');
    $targetDir = UPLOAD_DIR . $subdir;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $error = 'Could not save uploaded file.';
        return null;
    }

    return UPLOAD_URL . $subdir . '/' . $filename;
}

/** Find or create a category by name, return its id. */
function find_or_create_category(string $name): ?int
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }
    $slug = slugify($name);
    $stmt = db()->prepare('SELECT id FROM categories WHERE slug = ?');
    $stmt->execute([$slug]);
    if ($row = $stmt->fetch()) {
        return (int) $row['id'];
    }
    $stmt = db()->prepare('INSERT INTO categories (name, slug) VALUES (?, ?)');
    $stmt->execute([$name, $slug]);
    return (int) db()->lastInsertId();
}

/** Find-or-create each comma-separated tag name, return array of tag ids. */
function find_or_create_tags(string $tagsCsv): array
{
    $ids = [];
    foreach (explode(',', $tagsCsv) as $raw) {
        $name = trim($raw);
        if ($name === '') {
            continue;
        }
        $slug = slugify($name);
        $stmt = db()->prepare('SELECT id FROM tags WHERE slug = ?');
        $stmt->execute([$slug]);
        if ($row = $stmt->fetch()) {
            $ids[] = (int) $row['id'];
            continue;
        }
        $stmt = db()->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
        $stmt->execute([$name, $slug]);
        $ids[] = (int) db()->lastInsertId();
    }
    return array_unique($ids);
}

/** Extract an 11-char YouTube video ID from a full URL, or pass through if already an ID. */
function youtube_video_id(string $urlOrId): string
{
    $urlOrId = trim($urlOrId);
    if (preg_match('~^[A-Za-z0-9_-]{11}$~', $urlOrId)) {
        return $urlOrId;
    }
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|live/|shorts/))([A-Za-z0-9_-]{11})~', $urlOrId, $m)) {
        return $m[1];
    }
    return $urlOrId;
}

function youtube_embed_url(string $urlOrId, bool $autoplay = true): string
{
    $id = youtube_video_id($urlOrId);
    return 'https://www.youtube.com/embed/' . rawurlencode($id) . '?rel=0&modestbranding=1' . ($autoplay ? '&autoplay=1' : '');
}

function get_setting(string $key, ?string $default = null): ?string
{
    $stmt = db()->prepare('SELECT `value` FROM settings WHERE `key` = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
    );
    $stmt->execute([$key, $value]);
}

// ---------- recommendations ----------

/** Published articles ordered by popularity (views) — powers "Trending Now" widgets. */
function get_trending_articles(int $limit = 5, ?int $excludeId = null): array
{
    $sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug
            FROM articles a
            LEFT JOIN categories c ON c.id = a.category_id
            WHERE a.status = 'published'" . ($excludeId ? ' AND a.id != ?' : '') . "
            ORDER BY a.views DESC, a.created_at DESC
            LIMIT " . (int) $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($excludeId ? [$excludeId] : []);
    return $stmt->fetchAll();
}

/**
 * Content-based recommendations for $article: a weighted score built from shared
 * category, shared tags, same author, recency, and popularity relative to the
 * candidate pool. No external service — pure SQL + PHP scoring over the article set.
 *
 * $article needs: id, category_id, author_id, created_at.
 */
function get_recommended_articles(array $article, int $limit = 6): array
{
    $excludeId = (int) $article['id'];

    $tagStmt = db()->prepare('SELECT tag_id FROM article_tags WHERE article_id = ?');
    $tagStmt->execute([$excludeId]);
    $currentTagIds = array_column($tagStmt->fetchAll(), 'tag_id');

    $candStmt = db()->prepare(
        "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
         FROM articles a
         LEFT JOIN categories c ON c.id = a.category_id
         LEFT JOIN users u ON u.id = a.author_id
         WHERE a.status = 'published' AND a.id != ?
         ORDER BY a.created_at DESC
         LIMIT 200"
    );
    $candStmt->execute([$excludeId]);
    $candidates = $candStmt->fetchAll();

    if (!$candidates) {
        return [];
    }

    $candidateIds = array_column($candidates, 'id');
    $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
    $tagMapStmt = db()->prepare("SELECT article_id, tag_id FROM article_tags WHERE article_id IN ($placeholders)");
    $tagMapStmt->execute($candidateIds);
    $tagsByArticle = [];
    foreach ($tagMapStmt->fetchAll() as $row) {
        $tagsByArticle[$row['article_id']][] = $row['tag_id'];
    }

    $avgViews = array_sum(array_column($candidates, 'views')) / count($candidates);
    $freshCutoff = time() - 14 * 86400;

    $scored = [];
    foreach ($candidates as $c) {
        $score = 0;

        if ($article['category_id'] !== null && $c['category_id'] == $article['category_id']) {
            $score += 4;
        }
        if ($currentTagIds && !empty($tagsByArticle[$c['id']])) {
            $score += 2 * count(array_intersect($currentTagIds, $tagsByArticle[$c['id']]));
        }
        if ($article['author_id'] == $c['author_id']) {
            $score += 1;
        }
        if ((strtotime($c['created_at']) ?: 0) >= $freshCutoff) {
            $score += 1;
        }
        if ($avgViews > 0 && $c['views'] >= $avgViews * 1.5) {
            $score += 1;
        }

        if ($score > 0) {
            $scored[] = ['score' => $score, 'article' => $c];
        }
    }

    usort($scored, function ($a, $b) {
        if ($a['score'] === $b['score']) {
            return strtotime($b['article']['created_at']) <=> strtotime($a['article']['created_at']);
        }
        return $b['score'] <=> $a['score'];
    });

    $result = array_map(fn($s) => $s['article'], array_slice($scored, 0, $limit));

    // Backfill with the latest articles if scoring alone didn't produce enough matches
    // (e.g. a brand-new site with sparse tags/categories).
    if (count($result) < $limit) {
        $haveIds = array_column($result, 'id');
        foreach ($candidates as $c) {
            if (count($result) >= $limit) {
                break;
            }
            if (!in_array($c['id'], $haveIds, true)) {
                $result[] = $c;
                $haveIds[] = $c['id'];
            }
        }
    }

    return $result;
}

// ---------- comments ----------

function get_approved_comments(int $articleId): array
{
    $stmt = db()->prepare("SELECT * FROM comments WHERE article_id = ? AND status = 'approved' ORDER BY created_at ASC");
    $stmt->execute([$articleId]);
    return $stmt->fetchAll();
}

function count_comments(int $articleId, ?string $status = 'approved'): int
{
    if ($status === null) {
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM comments WHERE article_id = ?');
        $stmt->execute([$articleId]);
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM comments WHERE article_id = ? AND status = ?');
        $stmt->execute([$articleId, $status]);
    }
    return (int) $stmt->fetch()['c'];
}

/** Initial letter for a comment author's fallback avatar circle. */
function comment_initial(string $name): string
{
    $name = trim($name);
    return $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) : '?';
}

// ---------- CNN-layout helpers ----------

/** All active categories ordered by display_order. */
function get_active_categories(): array
{
    return db()->query(
        "SELECT * FROM categories WHERE is_active = 1 ORDER BY display_order ASC, name ASC"
    )->fetchAll();
}

/** Latest breaking-news headlines for the ticker. */
function get_breaking_news(int $limit = 10): array
{
    $stmt = db()->prepare(
        "SELECT title, slug FROM articles
         WHERE status = 'published' AND is_breaking = 1
         ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/** Up to $limit featured articles for the hero section. */
function get_featured_articles(int $limit = 5): array
{
    $stmt = db()->prepare(
        "SELECT a.*, c.name AS category_name, c.slug AS category_slug,
                u.name AS author_name
         FROM articles a
         LEFT JOIN categories c ON c.id = a.category_id
         LEFT JOIN users u ON u.id = a.author_id
         WHERE a.status = 'published' AND a.is_featured = 1
         ORDER BY a.created_at DESC LIMIT ?"
    );
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/** Latest published articles with optional category filter. */
function get_latest_articles(int $limit = 8, ?int $categoryId = null, int $offset = 0): array
{
    if ($categoryId) {
        $stmt = db()->prepare(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug,
                    u.name AS author_name
             FROM articles a
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.status = 'published' AND a.category_id = ?
             ORDER BY a.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$categoryId, $limit, $offset]);
    } else {
        $stmt = db()->prepare(
            "SELECT a.*, c.name AS category_name, c.slug AS category_slug,
                    u.name AS author_name
             FROM articles a
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.status = 'published'
             ORDER BY a.created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$limit, $offset]);
    }
    return $stmt->fetchAll();
}

/** Estimate reading time in minutes from HTML content. */
function reading_time(string $html): int
{
    $words = str_word_count(strip_tags($html));
    return max(1, (int) ceil($words / 200));
}

/**
 * Return a real Unsplash/Picsum fallback image URL for cards with no image.
 * Uses article ID as seed so each card gets a consistent but varied image.
 */
function placeholder_image(int $seed, int $w = 640, int $h = 400): string
{
    return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
}

/**
 * URL for an article thumbnail: the real uploaded featured image if it's actually
 * present on disk, otherwise a placeholder photo. This makes local dev (where the
 * production /uploads files usually aren't synced) fall back to placeholders
 * automatically, while live (where the file really exists) always shows the real photo.
 */
function article_thumb_src(?string $featuredImage, int $seed, int $w = 640, int $h = 400): string
{
    if ($featuredImage && is_file(__DIR__ . '/../' . $featuredImage)) {
        return BASE_PATH . '/' . $featuredImage;
    }
    return placeholder_image($seed, $w, $h);
}

/** Small Oroma TV circle-logo badge, meant to sit in the corner of an article thumbnail. */
function render_thumb_logo(): string
{
    return '<img src="' . h(BASE_PATH) . '/assets/img/oroma_circle_logo.svg" class="thumb-logo" alt="" loading="lazy" />';
}

/** Get all categories for admin management with article counts. */
function get_categories_with_counts(): array
{
    return db()->query(
        "SELECT c.*, COUNT(a.id) AS article_count
         FROM categories c
         LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
         GROUP BY c.id
         ORDER BY c.display_order ASC, c.name ASC"
    )->fetchAll();
}
