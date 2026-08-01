-- ============================================================
--  Oroma TV — MASTER Live Server Migration
--  Run this ONCE in phpMyAdmin on the live database:
--  u850523537_OromaTVDB
--
--  Safe to re-run — uses IF NOT EXISTS / ON DUPLICATE KEY
-- ============================================================

-- ── 1. CATEGORIES — add new columns ─────────────────────────
ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS description  TEXT           DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS icon         VARCHAR(60)    DEFAULT 'fa-folder',
    ADD COLUMN IF NOT EXISTS color        VARCHAR(7)     DEFAULT '#800000',
    ADD COLUMN IF NOT EXISTS display_order INT           DEFAULT 0,
    ADD COLUMN IF NOT EXISTS is_active    TINYINT(1)     DEFAULT 1,
    ADD COLUMN IF NOT EXISTS updated_at   TIMESTAMP      NULL
                                          DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP;

-- ── 2. ARTICLES — add new columns ───────────────────────────
ALTER TABLE articles
    ADD COLUMN IF NOT EXISTS is_breaking  TINYINT(1)    NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS reading_time INT            DEFAULT 1,
    ADD COLUMN IF NOT EXISTS body_font    VARCHAR(20)    DEFAULT 'inter';

-- ── 3. PREVIOUS_SHOWS table ──────────────────────────────────
CREATE TABLE IF NOT EXISTS previous_shows (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    youtube_url   VARCHAR(500)  NOT NULL,
    video_id      VARCHAR(50)   NOT NULL DEFAULT '',
    title         VARCHAR(500)  NOT NULL,
    guest_name    VARCHAR(255)  DEFAULT NULL,
    description   TEXT,
    thumbnail_url VARCHAR(500)  DEFAULT NULL,
    views         INT           DEFAULT 0,
    upload_date   DATE          DEFAULT NULL,
    is_featured   TINYINT(1)    DEFAULT 0,
    is_active     TINYINT(1)    DEFAULT 1,
    display_order INT           DEFAULT 0,
    last_fetched  DATETIME      DEFAULT NULL,
    created_at    DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_featured (is_featured),
    INDEX idx_active   (is_active),
    INDEX idx_order    (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── 4. INDEXES (articles) ────────────────────────────────────
ALTER TABLE articles
    ADD INDEX IF NOT EXISTS idx_breaking (is_breaking, status),
    ADD INDEX IF NOT EXISTS idx_views    (views);

-- ── 5. SETTINGS — seed required keys ────────────────────────
INSERT INTO settings (`key`, `value`) VALUES
    ('stream_status',       'offline'),
    ('show_article_views',  '1'),
    ('article_font',        'inter'),
    ('breaking_news_enabled','1')
ON DUPLICATE KEY UPDATE `value` = `value`;

-- ── 6. CATEGORIES — seed Uganda/Oroma-focused set ───────────
INSERT INTO categories (name, slug, icon, color, display_order, is_active) VALUES
    ('Politics',      'politics',      'fa-landmark',       '#800000',  1, 1),
    ('Business',      'business',      'fa-chart-line',     '#800000',  2, 1),
    ('Sports',        'sports',        'fa-futbol',         '#800000',  3, 1),
    ('Entertainment', 'entertainment', 'fa-film',           '#800000',  4, 1),
    ('Technology',    'technology',    'fa-laptop-code',    '#800000',  5, 1),
    ('Lifestyle',     'lifestyle',     'fa-heart',          '#800000',  6, 1),
    ('Health',        'health',        'fa-heartbeat',      '#800000',  7, 1),
    ('Education',     'education',     'fa-graduation-cap', '#800000',  8, 1),
    ('Opinion',       'opinion',       'fa-comment-dots',   '#800000',  9, 1),
    ('World',         'world',         'fa-globe-africa',   '#800000', 10, 1)
ON DUPLICATE KEY UPDATE
    icon          = VALUES(icon),
    display_order = VALUES(display_order),
    is_active     = VALUES(is_active);

-- Fix the two default categories
UPDATE categories SET icon = 'fa-newspaper', display_order = 0  WHERE slug = 'news';
UPDATE categories SET icon = 'fa-users',     display_order = 11 WHERE slug = 'community';

-- Make sure all categories are active
UPDATE categories SET is_active = 1 WHERE is_active IS NULL;

-- ── 7. PREVIOUS SHOWS — sample data ─────────────────────────
INSERT INTO previous_shows
    (youtube_url, video_id, title, guest_name, thumbnail_url,
     is_featured, is_active, display_order, last_fetched)
VALUES
    ('https://youtu.be/4Q1_oWwXZu8', '4Q1_oWwXZu8',
     'Tungmalo - Wang i Wang | Kimberly Jael Aremo Acio - Miss Universe Kole 2026',
     'Kimberly Jael Aremo Acio',
     'https://i.ytimg.com/vi/4Q1_oWwXZu8/hqdefault.jpg',
     1, 1, 1, NOW()),

    ('https://youtu.be/hxY5u9KlcIk', 'hxY5u9KlcIk',
     'Tungmalo - Wang i Wang | Judah Rapknowledge Da Akbar - Hip-Hop Artist',
     'Judah Rapknowledge Da Akbar',
     'https://i.ytimg.com/vi/hxY5u9KlcIk/hqdefault.jpg',
     0, 1, 2, NOW()),

    ('https://youtu.be/tbjI_YggrFY', 'tbjI_YggrFY',
     'Tungmalo - Wang i Wang | Gloria Aleleh',
     'Gloria Aleleh',
     'https://i.ytimg.com/vi/tbjI_YggrFY/hqdefault.jpg',
     0, 1, 3, NOW()),

    ('https://youtu.be/jaAou08Xk9w', 'jaAou08Xk9w',
     'Tungmalo - Wang i Wang | Young Emma',
     'Young Emma',
     'https://i.ytimg.com/vi/jaAou08Xk9w/hqdefault.jpg',
     0, 1, 4, NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- ── 8. VERIFY ────────────────────────────────────────────────
SELECT 'categories'    AS tbl, COUNT(*) AS total FROM categories
UNION ALL
SELECT 'articles',              COUNT(*)              FROM articles
UNION ALL
SELECT 'previous_shows',        COUNT(*)              FROM previous_shows
UNION ALL
SELECT 'settings',              COUNT(*)              FROM settings;
