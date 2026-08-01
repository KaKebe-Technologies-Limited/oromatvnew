-- ==========================================================
--  Oroma TV  –  CNN Redesign Migration
--  Run once:  mysql -u root oroma_tv < database/migrate_cnn_redesign.sql
-- ==========================================================

USE oroma_tv;

-- 1. Upgrade categories table
ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS description TEXT,
    ADD COLUMN IF NOT EXISTS icon VARCHAR(60) DEFAULT 'fa-folder',
    ADD COLUMN IF NOT EXISTS color VARCHAR(7) DEFAULT '#800000',
    ADD COLUMN IF NOT EXISTS display_order INT DEFAULT 0,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 2. Upgrade articles table
ALTER TABLE articles
    ADD COLUMN IF NOT EXISTS is_breaking TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS reading_time INT DEFAULT 1;

-- Index for breaking ticker
ALTER TABLE articles ADD INDEX IF NOT EXISTS idx_breaking (is_breaking, status, created_at);

-- 3. Seed rich categories (Uganda / Oroma-focused)
INSERT INTO categories (name, slug, icon, color, display_order, is_active) VALUES
    ('Politics',       'politics',       'fa-landmark',        '#800000', 1,  1),
    ('Business',       'business',       'fa-chart-line',      '#800000', 2,  1),
    ('Sports',         'sports',         'fa-futbol',          '#800000', 3,  1),
    ('Entertainment',  'entertainment',  'fa-film',            '#800000', 4,  1),
    ('Technology',     'technology',     'fa-laptop-code',     '#800000', 5,  1),
    ('Lifestyle',      'lifestyle',      'fa-heart',           '#800000', 6,  1),
    ('Health',         'health',         'fa-heartbeat',       '#800000', 7,  1),
    ('Education',      'education',      'fa-graduation-cap',  '#800000', 8,  1),
    ('Opinion',        'opinion',        'fa-comment-dots',    '#800000', 9,  1),
    ('World',          'world',          'fa-globe-africa',    '#800000', 10, 1)
ON DUPLICATE KEY UPDATE
    icon = VALUES(icon),
    display_order = VALUES(display_order),
    is_active = VALUES(is_active);
