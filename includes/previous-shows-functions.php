<?php
/**
 * Oroma TV — Previous Shows / YouTube helper functions
 */

/**
 * Extract a YouTube video ID from any URL format:
 *   youtu.be/ID, youtube.com/watch?v=ID, /embed/ID, /live/ID, /shorts/ID
 */
function extract_youtube_id(string $url): string
{
    $url = trim($url);
    // Already a bare 11-char ID
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url)) {
        return $url;
    }
    if (preg_match(
        '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|live/|shorts/|v/))([A-Za-z0-9_-]{11})~',
        $url, $m
    )) {
        return $m[1];
    }
    return '';
}

/** Build the standard YouTube watch URL. */
function get_youtube_watch_url(string $videoId): string
{
    return 'https://www.youtube.com/watch?v=' . rawurlencode($videoId);
}

/** Build an embed URL (autoplay optional). */
function get_youtube_embed_url(string $videoId, bool $autoplay = false): string
{
    return 'https://www.youtube.com/embed/' . rawurlencode($videoId)
        . '?rel=0&modestbranding=1' . ($autoplay ? '&autoplay=1' : '');
}

/**
 * Get thumbnail URL.
 * $quality: maxresdefault | sddefault | hqdefault | mqdefault | default
 */
function get_youtube_thumbnail(string $videoId, string $quality = 'hqdefault'): string
{
    return "https://i.ytimg.com/vi/{$videoId}/{$quality}.jpg";
}

/**
 * Fetch video metadata via YouTube oEmbed (no API key needed).
 * Returns an array with: title, author_name, thumbnail_url, video_id
 * Returns null on failure.
 */
function fetch_youtube_metadata(string $url): ?array
{
    $videoId = extract_youtube_id($url);
    if ($videoId === '') return null;

    $oembedUrl = 'https://www.youtube.com/oembed?url='
        . urlencode('https://www.youtube.com/watch?v=' . $videoId)
        . '&format=json';

    $ctx = stream_context_create([
        'http' => [
            'timeout'         => 8,
            'user_agent'      => 'OromaTV/1.0',
            'ignore_errors'   => true,
        ],
    ]);

    $json = @file_get_contents($oembedUrl, false, $ctx);
    if ($json === false) return null;

    $data = json_decode($json, true);
    if (!is_array($data) || empty($data['title'])) return null;

    return [
        'video_id'      => $videoId,
        'title'         => $data['title'] ?? '',
        'author_name'   => $data['author_name'] ?? '',
        'thumbnail_url' => get_youtube_thumbnail($videoId, 'hqdefault'),
        'views'         => 0,        // oEmbed doesn't expose view counts — left at 0
        'upload_date'   => null,     // oEmbed doesn't expose upload date
        'description'   => '',
    ];
}

/**
 * Extract a guest name from a video title.
 * Looks for common patterns:
 *   "Show Title - Guest Name", "Show Title | Guest Name",
 *   "Show Title: Guest Name", "Guest Name - Show Title"
 * Returns empty string if no pattern matched.
 */
function extract_guest_name(string $title): string
{
    // Pattern: "anything - Guest Name" or "anything | Guest Name"
    if (preg_match('/[-|]\s*([^|\-]{3,60})\s*$/', $title, $m)) {
        $candidate = trim($m[1]);
        // Reject if it looks like a date or episode number
        if (!preg_match('/^\d+$/', $candidate) && strlen($candidate) >= 3) {
            return $candidate;
        }
    }
    // Pattern: "Guest Name: something"
    if (preg_match('/^([^:]{3,50})\s*:/', $title, $m)) {
        $candidate = trim($m[1]);
        if (strlen($candidate) >= 3) {
            return $candidate;
        }
    }
    return '';
}

// ── CRUD ─────────────────────────────────────────────────────

/**
 * Fetch metadata and insert a new show.
 * Returns the new row id on success, or throws on error.
 */
function add_previous_show(string $youtubeUrl): int
{
    $youtubeUrl = trim($youtubeUrl);
    $meta = fetch_youtube_metadata($youtubeUrl);

    if (!$meta) {
        throw new RuntimeException('Could not fetch video metadata. Check the URL and try again.');
    }

    $guestName = extract_guest_name($meta['title']);
    $maxOrder  = db()->query('SELECT COALESCE(MAX(display_order),0)+1 FROM previous_shows')->fetchColumn();

    $stmt = db()->prepare(
        'INSERT INTO previous_shows
         (youtube_url, video_id, title, guest_name, description, thumbnail_url,
          views, upload_date, is_featured, is_active, display_order, last_fetched)
         VALUES (?,?,?,?,?,?,?,?,0,1,?,NOW())'
    );
    $stmt->execute([
        $youtubeUrl,
        $meta['video_id'],
        $meta['title'],
        $guestName ?: null,
        $meta['description'] ?: null,
        $meta['thumbnail_url'],
        $meta['views'],
        $meta['upload_date'],
        (int)$maxOrder,
    ]);
    return (int)db()->lastInsertId();
}

/** Re-fetch YouTube metadata and update a show row. */
function refresh_youtube_metadata(int $id): bool
{
    $row = db()->prepare('SELECT youtube_url FROM previous_shows WHERE id=?');
    $row->execute([$id]);
    $show = $row->fetch();
    if (!$show) return false;

    $meta = fetch_youtube_metadata($show['youtube_url']);
    if (!$meta) return false;

    db()->prepare(
        'UPDATE previous_shows SET video_id=?, title=?, thumbnail_url=?,
         views=?, last_fetched=NOW() WHERE id=?'
    )->execute([
        $meta['video_id'],
        $meta['title'],
        $meta['thumbnail_url'],
        $meta['views'],
        $id,
    ]);
    return true;
}

/**
 * Get shows for public display.
 * Featured shows first, then by display_order, then newest.
 */
function get_previous_shows(int $limit = 50, bool $featuredOnly = false): array
{
    $sql = "SELECT * FROM previous_shows WHERE is_active=1"
        . ($featuredOnly ? " AND is_featured=1" : "")
        . " ORDER BY is_featured DESC, display_order ASC, created_at DESC"
        . " LIMIT " . (int)$limit;
    return db()->query($sql)->fetchAll();
}

/** Get only featured shows. */
function get_featured_shows(int $limit = 4): array
{
    return get_previous_shows($limit, true);
}

/** Toggle the is_featured flag on a show. */
function toggle_featured_show(int $id): void
{
    db()->prepare('UPDATE previous_shows SET is_featured = NOT is_featured WHERE id=?')
       ->execute([$id]);
}

/** Update display order. */
function update_show_order(int $id, int $order): void
{
    db()->prepare('UPDATE previous_shows SET display_order=? WHERE id=?')
       ->execute([$order, $id]);
}

/** Delete a show. */
function delete_previous_show(int $id): void
{
    db()->prepare('DELETE FROM previous_shows WHERE id=?')->execute([$id]);
}
