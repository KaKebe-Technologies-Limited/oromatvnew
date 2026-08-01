-- ============================================================
--  Oroma TV — Live Server Migration
--  Run this in phpMyAdmin or via SSH on the live database
-- ============================================================

-- 1. Add missing columns to categories
ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS icon VARCHAR(60) DEFAULT 'fa-folder',
    ADD COLUMN IF NOT EXISTS color VARCHAR(7) DEFAULT '#800000',
    ADD COLUMN IF NOT EXISTS display_order INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1,
    ADD COLUMN IF NOT EXISTS description TEXT,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. Add missing columns to articles
ALTER TABLE articles
    ADD COLUMN IF NOT EXISTS is_breaking TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS reading_time INT DEFAULT 1;

-- 3. Create previous_shows table
CREATE TABLE IF NOT EXISTS previous_shows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    youtube_url VARCHAR(500) NOT NULL,
    video_id VARCHAR(50) NOT NULL DEFAULT '',
    title VARCHAR(500) NOT NULL,
    guest_name VARCHAR(255) DEFAULT NULL,
    description TEXT,
    thumbnail_url VARCHAR(500) DEFAULT NULL,
    views INT DEFAULT 0,
    upload_date DATE DEFAULT NULL,
    is_featured TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    last_fetched DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_featured (is_featured),
    INDEX idx_active (is_active),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Seed categories (safe — won't overwrite existing)
INSERT INTO categories (name, slug, icon, color, display_order, is_active) VALUES
    ('Politics',       'politics',       'fa-landmark',       '#800000', 1,  1),
    ('Business',       'business',       'fa-chart-line',     '#800000', 2,  1),
    ('Sports',         'sports',         'fa-futbol',         '#800000', 3,  1),
    ('Entertainment',  'entertainment',  'fa-film',           '#800000', 4,  1),
    ('Technology',     'technology',     'fa-laptop-code',    '#800000', 5,  1),
    ('Lifestyle',      'lifestyle',      'fa-heart',          '#800000', 6,  1),
    ('Health',         'health',         'fa-heartbeat',      '#800000', 7,  1),
    ('Education',      'education',      'fa-graduation-cap', '#800000', 8,  1),
    ('Opinion',        'opinion',        'fa-comment-dots',   '#800000', 9,  1),
    ('World',          'world',          'fa-globe-africa',   '#800000', 10, 1)
ON DUPLICATE KEY UPDATE
    icon          = VALUES(icon),
    display_order = VALUES(display_order),
    is_active     = VALUES(is_active);

-- 5. Update any existing categories to be active
UPDATE categories SET is_active = 1 WHERE is_active IS NULL OR is_active = 0;

-- 6. Seed sample previous shows
INSERT INTO previous_shows (youtube_url, video_id, title, guest_name, thumbnail_url, is_featured, is_active, display_order, last_fetched) VALUES
    ('https://youtu.be/4Q1_oWwXZu8', '4Q1_oWwXZu8', 'Tungmalo - Wang i Wang | Kimberly Jael Aremo Acio',    'Kimberly Jael Aremo Acio',    'https://i.ytimg.com/vi/4Q1_oWwXZu8/hqdefault.jpg', 1, 1, 1, NOW()),
    ('https://youtu.be/hxY5u9KlcIk', 'hxY5u9KlcIk', 'Tungmalo - Wang i Wang | Judah Rapknowledge Da Akbar', 'Judah Rapknowledge Da Akbar', 'https://i.ytimg.com/vi/hxY5u9KlcIk/hqdefault.jpg', 0, 1, 2, NOW()),
    ('https://youtu.be/tbjI_YggrFY', 'tbjI_YggrFY', 'Tungmalo - Wang i Wang | Gloria Aleleh',               'Gloria Aleleh',               'https://i.ytimg.com/vi/tbjI_YggrFY/hqdefault.jpg', 0, 1, 3, NOW()),
    ('https://youtu.be/jaAou08Xk9w', 'jaAou08Xk9w', 'Tungmalo - Wang i Wang | Young Emma',                  'Young Emma',                  'https://i.ytimg.com/vi/jaAou08Xk9w/hqdefault.jpg', 0, 1, 4, NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Confirm
SELECT 'Migration complete' AS status;
SELECT COUNT(*) AS categories FROM categories;
SELECT COUNT(*) AS previous_shows FROM previous_shows;
