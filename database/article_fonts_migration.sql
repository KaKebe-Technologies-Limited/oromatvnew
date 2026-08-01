ALTER TABLE articles ADD COLUMN IF NOT EXISTS body_font VARCHAR(20) DEFAULT 'inter';
INSERT INTO settings (`key`, `value`) VALUES ('show_article_views','1') ON DUPLICATE KEY UPDATE `value`=`value`;
INSERT INTO settings (`key`, `value`) VALUES ('article_font','inter') ON DUPLICATE KEY UPDATE `value`=`value`;
SELECT 'migration done';
