-- Oroma TV — live schedule, premieres, viewer counting and the off-air playlist.
-- You do NOT have to run this: the admin pages, the website and the API create and upgrade these tables automatically the
-- first time they are used. Run it by hand (phpMyAdmin → SQL) only if your database user is not allowed to create tables.
-- It is safe to run more than once. (ADD COLUMN IF NOT EXISTS needs MariaDB 10.0.2 or newer, which is what the site uses.)

CREATE TABLE IF NOT EXISTS `live_programs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `provider` ENUM('youtube','embed') NOT NULL,
  `stream_url` VARCHAR(500) NOT NULL,
  `video_id` VARCHAR(20) DEFAULT NULL,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME DEFAULT NULL,
  `ended_at` DATETIME DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_live_programs_starts` (`starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Added in version 2:
--   mode          'premiere' = a recording that everyone watches at the same second (played from the server's clock);
--                 'live' = a real-time stream
--   kind          what it is: a programme, a movie premiere or a special event
--   poster_url    optional poster picture (otherwise the video's own thumbnail is used)
--   series_id     programmes created together by "Repeat" share one id, so the whole series can be deleted at once
--   archived      finished YouTube programmes are saved into Previous Shows automatically; this marks the ones already saved
--   peak_viewers  the most people watching at once
ALTER TABLE `live_programs`
  ADD COLUMN IF NOT EXISTS `mode` ENUM('premiere','live') NOT NULL DEFAULT 'premiere',
  ADD COLUMN IF NOT EXISTS `kind` ENUM('show','movie','event') NOT NULL DEFAULT 'show',
  ADD COLUMN IF NOT EXISTS `poster_url` VARCHAR(500) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `series_id` INT UNSIGNED DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `archived` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `peak_viewers` INT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `offair_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `video_id` VARCHAR(20) NOT NULL,
  `source_url` VARCHAR(500) NOT NULL,
  `thumbnail_url` VARCHAR(500) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_offair_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who is watching right now: every device (app or website) checks in every 30 seconds with a random anonymous id.
CREATE TABLE IF NOT EXISTS `live_viewers` (
  `viewer_id` VARCHAR(64) NOT NULL,
  `scope` VARCHAR(24) NOT NULL,
  `platform` ENUM('app','web') NOT NULL DEFAULT 'app',
  `first_seen` DATETIME NOT NULL,
  `last_seen` DATETIME NOT NULL,
  PRIMARY KEY (`viewer_id`, `scope`),
  KEY `idx_viewers_seen` (`last_seen`),
  KEY `idx_viewers_scope` (`scope`, `last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Previous Shows can hold movies too.
ALTER TABLE `previous_shows`
  ADD COLUMN IF NOT EXISTS `kind` ENUM('show','movie') NOT NULL DEFAULT 'show';
