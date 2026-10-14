<?php
/**
 * Oroma TV mobile API — one function per endpoint. Wired up in index.php.
 * Everything here is read-only except posting a comment and sending a contact message.
 */

const API_CONTACT_SUBJECTS = ['General Inquiry', 'News Tip', 'Advertising', 'Technical Support', 'Other'];

// ── health ─────────────────────────────────────────────────────

function route_ping(): void
{
    json_out(['status' => 'ok', 'service' => 'oroma-tv-api', 'version' => '1.0', 'time' => date('c')]);
}

// ── home ───────────────────────────────────────────────────────

/** Everything the app's home screen needs in one request. */
function route_home(): void
{
    $hero = get_featured_articles(5);
    if (count($hero) < 5) {
        // Pad with the newest stories so the carousel is never empty on a quiet day (same idea as the website).
        $have = array_map('intval', array_column($hero, 'id'));
        $extra = get_latest_articles(5 - count($hero), null, 0, $have);
        $hero = array_merge($hero, $extra);
    }
    $heroIds = array_map('intval', array_column($hero, 'id'));

    $shows = get_previous_shows(8, true) ?: get_previous_shows(8);

    // Just what the home screen's live card needs (the Live tab asks for the full picture itself).
    $live = api_live_snapshot();

    json_out(['data' => [
        'live'       => [
            'status'      => $live['status'],
            'title'       => $live['stage']['title'] ?? null,
            'next'        => $live['next'],
            'server_time' => $live['server_time'],
        ],
        'hero'       => array_map('serialize_article', $hero),
        'latest'     => array_map('serialize_article', get_latest_articles(8, null, 0, $heroIds)),
        'trending'   => array_map('serialize_article', get_trending_articles(5)),
        'shows'      => array_map('serialize_show', $shows),
        'categories' => array_map('serialize_category', route_categories_rows()),
    ]]);
}

// ── live ───────────────────────────────────────────────────────

/**
 * The Live tab: what is on air (a scheduled programme, or the main channel), what is next, the off-air playlist for when
 * nothing is live, and the radio / video channels viewers can pick. See api_live_snapshot() for the shape.
 */
function route_live(): void
{
    $rows = db()->query('SELECT * FROM streams WHERE is_active = 1 ORDER BY is_default DESC, sort_order ASC, id ASC')->fetchAll();

    json_out(['data' => api_live_snapshot() + [
        'title'     => SITE_NAME . ' Live',
        'tagline'   => SITE_TAGLINE,
        // The players expect to be shown on the Oroma TV website, so the app loads them as if it were.
        'referrer'  => rtrim(OROMA_PUBLIC_URL, '/'),
        'share_url' => rtrim(OROMA_PUBLIC_URL, '/') . '/watch.php',
        'channels'  => array_map('serialize_stream', $rows),
    ]]);
}

// ── categories ─────────────────────────────────────────────────

function route_categories_rows(): array
{
    return db()->query(
        "SELECT c.*, COUNT(a.id) AS article_count
         FROM categories c
         LEFT JOIN articles a ON a.category_id = c.id AND a.status = 'published'
         WHERE c.is_active = 1
         GROUP BY c.id
         ORDER BY c.display_order ASC, c.name ASC"
    )->fetchAll();
}

function route_categories(): void
{
    json_out(['data' => array_map('serialize_category', route_categories_rows())]);
}

// ── articles ───────────────────────────────────────────────────

/** Paged, filterable list: ?page=1&per_page=12&category=politics&q=text&tag=slug */
function route_articles(): void
{
    $page    = query_int('page', 1, 1, 100000);
    $perPage = query_int('per_page', 12, 1, 30);

    $where  = ["a.status = 'published'"];
    $params = [];
    $join   = '';

    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
        $where[] = '(a.title LIKE ? OR a.excerpt LIKE ?)';
        array_push($params, $like, $like);
    }
    $category = trim((string) ($_GET['category'] ?? ''));
    if ($category !== '') {
        $where[] = 'c.slug = ?';
        $params[] = $category;
    }
    $tag = trim((string) ($_GET['tag'] ?? ''));
    if ($tag !== '') {
        $join = 'JOIN article_tags at ON at.article_id = a.id JOIN tags t ON t.id = at.tag_id';
        $where[] = 't.slug = ?';
        $params[] = $tag;
    }
    $whereSql = implode(' AND ', $where);

    $count = db()->prepare(
        "SELECT COUNT(DISTINCT a.id) AS total FROM articles a
         LEFT JOIN categories c ON c.id = a.category_id $join WHERE $whereSql"
    );
    $count->execute($params);
    $total = (int) $count->fetch()['total'];

    $stmt = db()->prepare(
        "SELECT DISTINCT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
         FROM articles a
         LEFT JOIN categories c ON c.id = a.category_id
         LEFT JOIN users u ON u.id = a.author_id
         $join
         WHERE $whereSql
         ORDER BY a.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([...$params, $perPage, ($page - 1) * $perPage]);

    json_paged(array_map('serialize_article', $stmt->fetchAll()), $page, $perPage, $total);
}

function api_find_article(string $slug): ?array
{
    $stmt = db()->prepare(
        "SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
         FROM articles a
         LEFT JOIN categories c ON c.id = a.category_id
         LEFT JOIN users u ON u.id = a.author_id
         WHERE a.slug = ? AND a.status = 'published' LIMIT 1"
    );
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function serialize_comment(array $c): array
{
    return [
        'id'         => (int) $c['id'],
        'name'       => $c['name'],
        'comment'    => $c['comment'],
        'created_at' => iso_date($c['created_at']),
    ];
}

function route_article(string $slug): void
{
    $article = api_find_article($slug);
    if (!$article) {
        json_error('That story could not be found.', 404);
    }

    $showViews = get_setting('show_article_views', '1') !== '0';
    if ($showViews) {
        db()->prepare('UPDATE articles SET views = views + 1 WHERE id = ?')->execute([$article['id']]);
        $article['views'] = (int) $article['views'] + 1;
    }

    $tagStmt = db()->prepare(
        'SELECT t.name, t.slug FROM tags t JOIN article_tags at ON at.tag_id = t.id WHERE at.article_id = ? ORDER BY t.name'
    );
    $tagStmt->execute([$article['id']]);

    $comments = get_approved_comments((int) $article['id']);

    $data = serialize_article($article);
    $data['content_html']  = article_body_html($article['content']);
    $data['image_caption'] = $article['featured_image_caption'] ?: null;
    $data['body_font']     = ($article['body_font'] ?? 'inter') === 'lora' ? 'lora' : 'inter';
    $data['show_views']    = $showViews;
    $data['tags']          = $tagStmt->fetchAll();
    $data['comments']      = array_map('serialize_comment', $comments);
    $data['comments_count'] = count($comments);
    $data['related']       = array_map('serialize_article', get_recommended_articles($article, 5));

    json_out(['data' => $data]);
}

/** POST { name, email?, comment, website? (honeypot) } — comments go live straight away, exactly like the website. */
function route_comment_create(string $slug): void
{
    $article = api_find_article($slug);
    if (!$article) {
        json_error('That story could not be found.', 404);
    }
    $in = request_body();

    // Honeypot: real people never fill this hidden field. Pretend it worked so bots learn nothing.
    if (trim((string) ($in['website'] ?? '')) !== '') {
        json_out(['message' => 'Thanks! Your comment is now live.'], 201);
    }

    $name    = trim((string) ($in['name'] ?? ''));
    $email   = trim((string) ($in['email'] ?? ''));
    $comment = trim((string) ($in['comment'] ?? ''));

    $errors = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors['name'] = ['Please enter your name (2-100 characters).'];
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = ['Please enter a valid email address, or leave it blank.'];
    }
    if (mb_strlen($comment) < 2 || mb_strlen($comment) > 2000) {
        $errors['comment'] = ['Comment must be between 2 and 2000 characters.'];
    }
    if ($errors) {
        json_error(reset($errors)[0], 422, $errors);
    }

    rate_limit('comment', 6, 600);

    // Refuse an identical comment on the same story in the last 10 minutes (double taps, copy-paste spam).
    $dupe = db()->prepare(
        'SELECT id FROM comments WHERE article_id = ? AND comment = ? AND created_at > ? LIMIT 1'
    );
    $dupe->execute([$article['id'], $comment, date('Y-m-d H:i:s', time() - 600)]);
    if ($dupe->fetch()) {
        json_error('You already posted that comment.', 422, ['comment' => ['You already posted that comment.']]);
    }

    $now = date('Y-m-d H:i:s');
    db()->prepare(
        "INSERT INTO comments (article_id, name, email, comment, status, created_at) VALUES (?, ?, ?, ?, 'approved', ?)"
    )->execute([$article['id'], $name, $email ?: null, $comment, $now]);

    json_out([
        'message' => 'Thanks! Your comment is now live.',
        'data'    => serialize_comment(['id' => db()->lastInsertId(), 'name' => $name, 'comment' => $comment, 'created_at' => $now]),
    ], 201);
}

// ── previous shows ─────────────────────────────────────────────

function route_shows(): void
{
    $page    = query_int('page', 1, 1, 100000);
    $perPage = query_int('per_page', 20, 1, 50);

    $kind = in_array($_GET['kind'] ?? '', ['movie', 'show'], true) ? $_GET['kind'] : '';
    ensure_live_schema(); // makes sure the "kind" column exists
    // The same video added twice in the admin is listed once: the copy that comes first in the admin's order.
    $where = 'is_active = 1' . ($kind !== '' ? " AND kind = '$kind'" : '')
        . ' AND id = (SELECT p2.id FROM previous_shows p2 WHERE p2.video_id = previous_shows.video_id AND p2.is_active = 1'
        . ' ORDER BY p2.is_featured DESC, p2.display_order ASC, p2.created_at DESC, p2.id ASC LIMIT 1)';

    $total = (int) db()->query("SELECT COUNT(*) AS c FROM previous_shows WHERE $where")->fetch()['c'];
    $stmt = db()->prepare(
        "SELECT * FROM previous_shows WHERE $where
         ORDER BY is_featured DESC, display_order ASC, created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$perPage, ($page - 1) * $perPage]);

    json_paged(array_map('serialize_show', $stmt->fetchAll()), $page, $perPage, $total);
}

function route_show(int $id): void
{
    $stmt = db()->prepare('SELECT * FROM previous_shows WHERE id = ? AND is_active = 1');
    $stmt->execute([$id]);
    $show = $stmt->fetch();
    if (!$show) {
        json_error('That show could not be found.', 404);
    }

    $related = db()->prepare(
        'SELECT * FROM previous_shows WHERE is_active = 1 AND id != ? AND video_id != ?
         AND id = (SELECT p2.id FROM previous_shows p2 WHERE p2.video_id = previous_shows.video_id AND p2.is_active = 1
                   ORDER BY p2.is_featured DESC, p2.display_order ASC, p2.created_at DESC, p2.id ASC LIMIT 1)
         ORDER BY is_featured DESC, display_order ASC, created_at DESC LIMIT 8'
    );
    $related->execute([$id, $show['video_id']]);

    $data = serialize_show($show);
    $data['related'] = array_map('serialize_show', $related->fetchAll());
    json_out(['data' => $data]);
}

// ── contact ────────────────────────────────────────────────────

function route_contact(): void
{
    $in = request_body();

    if (trim((string) ($in['website'] ?? '')) !== '') { // honeypot
        json_out(['message' => 'Thank you! Your message has been sent.']);
    }

    $name    = trim((string) ($in['name'] ?? ''));
    $email   = trim((string) ($in['email'] ?? ''));
    $subject = trim((string) ($in['subject'] ?? ''));
    $message = trim((string) ($in['message'] ?? ''));

    $errors = [];
    if ($name === '') {
        $errors['name'] = ['Your name is required.'];
    }
    if ($email === '') {
        $errors['email'] = ['Your email address is required.'];
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = ['Please enter a valid email address.'];
    }
    if (!in_array($subject, API_CONTACT_SUBJECTS, true)) {
        $errors['subject'] = ['Please select a subject.'];
    }
    if ($message === '') {
        $errors['message'] = ['A message is required.'];
    } elseif (mb_strlen($message) < 10) {
        $errors['message'] = ['Your message is too short (minimum 10 characters).'];
    } elseif (mb_strlen($message) > 5000) {
        $errors['message'] = ['Your message is too long (maximum 5000 characters).'];
    }
    if ($errors) {
        json_error(reset($errors)[0], 422, $errors);
    }

    rate_limit('contact', 5, 3600);

    // Header injection guard: names and emails must be a single line.
    $safeName  = trim(preg_replace('/[\r\n]+/', ' ', $name));
    $safeEmail = trim(preg_replace('/[\r\n]+/', '', $email));

    $body = "Name: {$safeName}\nEmail: {$safeEmail}\nSubject: {$subject}\n\nMessage:\n{$message}\n\n---\nSent from the Oroma TV mobile app";
    $headers  = "From: noreply@oromatv.com\r\n";
    $headers .= "Reply-To: {$safeEmail}\r\n";
    $headers .= "X-Mailer: OromaTV-App/1.0\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    if (!@mail(SITE_EMAIL, '[' . SITE_NAME . '] ' . $subject . ' from ' . $safeName, $body, $headers)) {
        json_error('Your message could not be sent right now. Please email us at ' . SITE_EMAIL . '.', 502);
    }
    json_out(['message' => 'Thank you! Your message has been sent. We will get back to you soon.']);
}

// ── viewer heartbeat ───────────────────────────────────────────

/**
 * POST { viewer_id, platform: 'app'|'web' } every 30 seconds while someone is watching the Live screen.
 * Anonymous: viewer_id is a random string the device made up. The server decides what the viewer is watching (a programme,
 * the main channel, or the off-air playlist) and answers with how many devices are watching live right now.
 */
function route_live_ping(): void
{
    $in = request_body();
    $viewerId = (string) ($in['viewer_id'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $viewerId)) {
        json_error('A valid viewer_id is required.', 422, ['viewer_id' => ['A valid viewer_id is required.']]);
    }
    rate_limit('ping', 90, 60); // a device pings twice a minute; this only stops abuse

    $scope = live_scope(resolve_live());
    $watching = record_viewer($viewerId, ($in['platform'] ?? 'app') === 'web' ? 'web' : 'app', $scope);

    json_out(['data' => ['watching' => $watching, 'scope' => $scope, 'server_time' => date('c')]]);
}

// ── radio ──────────────────────────────────────────────────────

/** "Listen to Q FM": the station's name, its live stream address, and what is playing — fetched fresh (cached 30 s). */
function route_radio(): void
{
    $radio = radio_resolve();
    if (!$radio['enabled'] || !$radio['stream_url']) {
        json_error($radio['error'] ?: 'The radio is not available right now.', 404);
    }
    json_out(['data' => $radio]);
}

// ── schedule & premieres ───────────────────────────────────────

/** The programme guide: what is on air and what is coming up (default the next 7 days, up to 40 programmes). */
function route_schedule(): void
{
    $hours = query_int('hours', 168, 1, 24 * 60);
    $limit = query_int('limit', 40, 1, 100);
    $live = api_live_snapshot();
    json_out(['data' => [
        'status'      => $live['status'],
        'stage'       => $live['stage'],
        'upcoming'    => array_map('serialize_programme', get_upcoming_programs($hours, $limit)),
        'server_time' => $live['server_time'],
    ]]);
}

/** One programme (for its premiere / countdown screen): its details, and whether it is upcoming, on air, or finished. */
function route_programme(int $id): void
{
    $p = get_live_program($id);
    if (!$p) {
        json_error('That programme could not be found.', 404);
    }
    $state = live_program_state($p);
    json_out(['data' => serialize_programme($p) + [
        'state'       => $state,
        'on_air'      => $state === 'live',
        'server_time' => date('c'),
    ]]);
}
