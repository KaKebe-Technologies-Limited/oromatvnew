-- Oroma TV - comment moderation (reports, bans, hidden-by-reports). Idempotent: safe to run any number of times.
-- You do NOT have to run this: includes/comment-moderation.php -> ensure_comment_schema() creates the same objects the first
-- time the website, the API or the admin uses comments. Run by hand (phpMyAdmin -> SQL) only if the database user is not
-- allowed to CREATE / ALTER. MariaDB 10.0.2+ syntax (what the site uses); on plain MySQL drop the "IF NOT EXISTS" after ADD COLUMN.

-- device_hash: one-way hash of the commenter device, 32 characters: "v" + hash for an app install (its anonymous viewer id), "w" + hash for
--              a web visitor (IP + browser, shared by everyone behind the same address, so it never hides anybody's published comments)
-- hold_reason:  filter:<rule> | banned | reports  (why it is not public), or reviewed (a moderator approved it)
ALTER TABLE `comments`
  ADD COLUMN IF NOT EXISTS `device_hash`  CHAR(32)    DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `hold_reason`  VARCHAR(24) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `comment_reports` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comment_id`    INT NOT NULL,
  `reporter_hash` CHAR(32) NOT NULL,
  `ip_hash`       CHAR(32) NOT NULL,  -- hash of the network: an IPv4 address, or an IPv6 /64 prefix (counted to find 3 different networks)
  `reason`        ENUM('spam','abusive','misleading','other') NOT NULL,
  `platform`      ENUM('app','web') NOT NULL DEFAULT 'app',
  `created_at`    DATETIME NOT NULL,
  `resolved_at`   DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_report_once` (`comment_id`, `reporter_hash`),
  KEY `idx_report_open` (`resolved_at`, `comment_id`),
  CONSTRAINT `fk_report_comment` FOREIGN KEY (`comment_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comment_bans` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind`       ENUM('name','email','device') NOT NULL,  -- value = normalised name / e-mail, or the device_hash; name and e-mail bans expire after 90 days (device 30) unless permanent
  `value`      VARCHAR(150) NOT NULL,
  `note`       VARCHAR(200) DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ban` (`kind`, `value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The version marker the PHP side checks (so the migration runs once):
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('comment_schema_v', '1');
