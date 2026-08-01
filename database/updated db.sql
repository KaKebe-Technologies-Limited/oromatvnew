-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 01, 2026 at 11:02 AM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u850523537_OromaTVDB`
--

-- --------------------------------------------------------

--
-- Table structure for table `articles`
--

CREATE TABLE `articles` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `featured_image_caption` varchar(300) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(500) DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_breaking` tinyint(1) NOT NULL DEFAULT 0,
  `reading_time` int(11) DEFAULT 1,
  `body_font` varchar(20) DEFAULT 'inter'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `articles`
--

INSERT INTO `articles` (`id`, `title`, `slug`, `excerpt`, `content`, `featured_image`, `featured_image_caption`, `category_id`, `author_id`, `meta_title`, `meta_description`, `status`, `is_featured`, `views`, `created_at`, `updated_at`, `is_breaking`, `reading_time`, `body_font`) VALUES
(3, 'Minister Sam Engola inspects 112km roads in Erute South, pledges better infrastructure and health services', 'minister-sam-engola-inspects-112km-roads-in-erute-south-pledges-better-infrastructure-and-health-services-3', 'Hon Min Sam EngolaLIRA DISTRICT – The Minister for Relief, Disaster Preparedness and Refugees, and Member of Parliament for Erute South, Hon. Sam Engola, has c…', '<p><img src=\"/uploads/articles/2026/08/93e487c2288e9687.jpeg\"></p><p><em>Hon Min Sam Engola</em></p><p><br></p><p><strong>LIRA DISTRICT</strong> – The Minister for Relief, Disaster Preparedness and Refugees, and Member of Parliament for Erute South, Hon. Sam Engola, has completed a comprehensive inspection of 112 kilometres of roads rehabilitated in his constituency under the financial year 2025/2026. </p><p><br></p><p>The inspection, which took place on Monday, July 27, 2026, saw Engola tour several road projects jointly implemented by his office and the Lira District Local Government. The Minister was accompanied by district officials, including the Lira District LC5 Chairperson, RCM Okello Orik, the Acting District Engineer, Geoffrey Ongala, and the Resident District Commissioner, Lilian Eyal.</p><p><br></p><p><img src=\"/uploads/articles/2026/08/43144cf9fbfb1ed0.jpeg\"></p><p><br></p><h2><strong>Commitment to Quality Infrastructure</strong></h2><p>Speaking during the inspection, Engola urged local leaders and residents to ensure proper supervision of government projects, emphasizing that quality roads, schools, health facilities, and clean water services are essential for improving livelihoods. He noted that beyond his role as Erute South MP, his appointment as Minister gives him a unique opportunity to closely monitor road infrastructure development across the Lango sub-region and ensure that government services reach local communities in a timely manner.</p><p><br></p><h2><strong>Health Centre Visit and Ministerial Intervention</strong></h2><p>During the tour, Engola also visited <strong>Onywako Health Centre II</strong>, where he observed that no significant development was ongoing. He immediately called the Minister for Health, Hon. Dr. Chris Baryomunsi, who pledged to secure funding for the construction of a new Outpatient Department (OPD) and the upgrading of the facility from Health Centre II to Health Centre III.</p><p><br></p><h2><strong>District Leaders Speak Out</strong></h2><p>Lira District LC5 Chairperson, RCM Okello Orik, welcomed the Minister\'s visit, expressing optimism that the district would see significant improvements in road conditions once implementation of the 2026/2027 district road budget begins. He appealed to Engola to continue lobbying for an additional grader, noting that inadequate equipment remains one of the district\'s biggest challenges.</p><p><br></p><p><img src=\"/uploads/articles/2026/08/581ca0d4ab9b46e3.jpeg\"></p><h2><br></h2><h2><strong>Roads Inspected</strong></h2><p>Among the roads inspected were:</p><ol><li><span class=\"ql-ui\"></span>Amach–Abongomola (10.2km)</li><li><span class=\"ql-ui\"></span>Amunamun–Alik Health Centre III (3.8km)</li><li><span class=\"ql-ui\"></span>Kekere–Abutadii (4.2km)</li><li><span class=\"ql-ui\"></span>Awiodyek–Alworo (8.5km)</li><li><span class=\"ql-ui\"></span>Amach Town Council–Adip (8.9km)</li><li><span class=\"ql-ui\"></span>Agali Seed–Ocamonyang (8.8km)</li></ol><p><br></p><p>Lira District Acting Engineer, Geoffrey Ongala, reported that 102 kilometres of roads had been maintained in Erute North, while 113 kilometres had been worked on in Erute South. He commended the Minister for the close collaboration.</p><p>Resident District Commissioner, Lilian Eyal, praised Engola for working closely with district leaders regardless of political differences, saying the collaboration demonstrates a shared commitment to improving public service delivery. She encouraged residents to make productive use of the improved roads by increasing agricultural production and commercial activities that can raise household incomes and boost local economic development.</p><h2><br></h2>', 'uploads/articles/2026/08/42bc44354bce358f.jpeg', NULL, 3, 1, NULL, NULL, 'published', 1, 13, '2026-08-01 07:06:02', '2026-08-01 10:53:59', 1, 1, 'inter');

-- --------------------------------------------------------

--
-- Table structure for table `article_tags`
--

CREATE TABLE `article_tags` (
  `article_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `article_tags`
--

INSERT INTO `article_tags` (`article_id`, `tag_id`) VALUES
(3, 13),
(3, 14);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `icon` varchar(60) DEFAULT 'fa-folder',
  `color` varchar(7) DEFAULT '#800000',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `created_at`, `icon`, `color`, `display_order`, `is_active`, `description`, `updated_at`) VALUES
(1, 'News', 'news', '2026-07-21 12:00:41', 'fa-newspaper', '#800000', 0, 1, NULL, '2026-08-01 09:16:44'),
(2, 'Community', 'community', '2026-07-21 12:00:41', 'fa-users', '#800000', 11, 1, NULL, '2026-08-01 09:16:44'),
(3, 'Politics', 'politics', '2026-08-01 06:54:19', 'fa-landmark', '#800000', 1, 1, NULL, '2026-08-01 06:54:19'),
(4, 'Business', 'business', '2026-08-01 06:54:19', 'fa-chart-line', '#800000', 2, 1, NULL, '2026-08-01 06:54:19'),
(5, 'Sports', 'sports', '2026-08-01 06:54:19', 'fa-futbol', '#800000', 3, 1, NULL, '2026-08-01 06:54:19'),
(6, 'Entertainment', 'entertainment', '2026-08-01 06:54:19', 'fa-film', '#800000', 4, 1, NULL, '2026-08-01 06:54:19'),
(7, 'Technology', 'technology', '2026-08-01 06:54:19', 'fa-laptop-code', '#800000', 5, 1, NULL, '2026-08-01 09:20:49'),
(8, 'Lifestyle', 'lifestyle', '2026-08-01 06:54:19', 'fa-heart', '#800000', 6, 1, NULL, '2026-08-01 06:54:19'),
(9, 'Health', 'health', '2026-08-01 06:54:19', 'fa-heartbeat', '#800000', 7, 1, NULL, '2026-08-01 06:54:19'),
(10, 'Education', 'education', '2026-08-01 06:54:19', 'fa-graduation-cap', '#800000', 8, 1, NULL, '2026-08-01 06:54:19'),
(11, 'Opinion', 'opinion', '2026-08-01 06:54:19', 'fa-comment-dots', '#800000', 9, 1, NULL, '2026-08-01 09:20:49'),
(12, 'World', 'world', '2026-08-01 06:54:19', 'fa-globe-africa', '#800000', 10, 1, NULL, '2026-08-01 06:54:19');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `comment` text NOT NULL,
  `status` enum('pending','approved','spam') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `previous_shows`
--

CREATE TABLE `previous_shows` (
  `id` int(11) NOT NULL,
  `youtube_url` varchar(500) NOT NULL,
  `video_id` varchar(50) NOT NULL DEFAULT '',
  `title` varchar(500) NOT NULL,
  `guest_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `upload_date` date DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `last_fetched` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `previous_shows`
--

INSERT INTO `previous_shows` (`id`, `youtube_url`, `video_id`, `title`, `guest_name`, `description`, `thumbnail_url`, `views`, `upload_date`, `is_featured`, `is_active`, `display_order`, `last_fetched`, `created_at`, `updated_at`) VALUES
(1, 'https://youtu.be/4Q1_oWwXZu8', '4Q1_oWwXZu8', 'Tungmalo - Wang i Wang | Kimberly Jael Aremo Acio', 'Kimberly Jael Aremo Acio', NULL, 'https://i.ytimg.com/vi/4Q1_oWwXZu8/hqdefault.jpg', 0, NULL, 1, 1, 1, '2026-08-01 06:54:19', '2026-08-01 06:54:19', '2026-08-01 06:54:19'),
(2, 'https://youtu.be/hxY5u9KlcIk', 'hxY5u9KlcIk', 'Tungmalo - Wang i Wang | Judah Rapknowledge Da Akbar', 'Judah Rapknowledge Da Akbar', NULL, 'https://i.ytimg.com/vi/hxY5u9KlcIk/hqdefault.jpg', 0, NULL, 0, 1, 2, '2026-08-01 06:54:19', '2026-08-01 06:54:19', '2026-08-01 06:54:19'),
(3, 'https://youtu.be/tbjI_YggrFY', 'tbjI_YggrFY', 'Tungmalo - Wang i Wang | Gloria Aleleh', 'Gloria Aleleh', NULL, 'https://i.ytimg.com/vi/tbjI_YggrFY/hqdefault.jpg', 0, NULL, 0, 1, 3, '2026-08-01 06:54:19', '2026-08-01 06:54:19', '2026-08-01 06:54:19'),
(4, 'https://youtu.be/jaAou08Xk9w', 'jaAou08Xk9w', 'Tungmalo - Wang i Wang | Young Emma', 'Young Emma', NULL, 'https://i.ytimg.com/vi/jaAou08Xk9w/hqdefault.jpg', 0, NULL, 0, 1, 4, '2026-08-01 06:54:19', '2026-08-01 06:54:19', '2026-08-01 06:54:19'),
(5, 'https://youtu.be/4Q1_oWwXZu8', '4Q1_oWwXZu8', 'Tungmalo - Wang i Wang | Kimberly Jael Aremo Acio - Miss Universe Kole 2026', 'Kimberly Jael Aremo Acio', NULL, 'https://i.ytimg.com/vi/4Q1_oWwXZu8/hqdefault.jpg', 0, NULL, 1, 1, 1, '2026-08-01 09:20:49', '2026-08-01 09:20:49', '2026-08-01 09:20:49'),
(6, 'https://youtu.be/hxY5u9KlcIk', 'hxY5u9KlcIk', 'Tungmalo - Wang i Wang | Judah Rapknowledge Da Akbar - Hip-Hop Artist', 'Judah Rapknowledge Da Akbar', NULL, 'https://i.ytimg.com/vi/hxY5u9KlcIk/hqdefault.jpg', 0, NULL, 0, 1, 2, '2026-08-01 09:20:49', '2026-08-01 09:20:49', '2026-08-01 09:20:49'),
(7, 'https://youtu.be/tbjI_YggrFY', 'tbjI_YggrFY', 'Tungmalo - Wang i Wang | Gloria Aleleh', 'Gloria Aleleh', NULL, 'https://i.ytimg.com/vi/tbjI_YggrFY/hqdefault.jpg', 0, NULL, 0, 1, 3, '2026-08-01 09:20:49', '2026-08-01 09:20:49', '2026-08-01 09:20:49'),
(8, 'https://youtu.be/jaAou08Xk9w', 'jaAou08Xk9w', 'Tungmalo - Wang i Wang | Young Emma', 'Young Emma', NULL, 'https://i.ytimg.com/vi/jaAou08Xk9w/hqdefault.jpg', 0, NULL, 0, 1, 4, '2026-08-01 09:20:49', '2026-08-01 09:20:49', '2026-08-01 09:20:49');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`) VALUES
('article_font', 'inter'),
('breaking_news_enabled', '1'),
('show_article_views', '1'),
('stream_status', 'live');

-- --------------------------------------------------------

--
-- Table structure for table `streams`
--

CREATE TABLE `streams` (
  `id` int(11) NOT NULL,
  `type` enum('youtube','radio') NOT NULL,
  `name` varchar(100) NOT NULL,
  `url_or_id` varchar(500) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `streams`
--

INSERT INTO `streams` (`id`, `type`, `name`, `url_or_id`, `icon`, `sort_order`, `is_active`, `is_default`, `created_at`) VALUES
(1, 'youtube', 'Oroma TV', 'https://www.youtube.com/watch?v=jNQXAC9IVRw', NULL, 1, 1, 0, '2026-07-21 12:04:17'),
(2, 'radio', 'Radio QFM 94.3', 'https://host.atenimedia.com/public/radio_qfm/embed?primary_color=990000', 'fas fa-broadcast-tower', 2, 1, 0, '2026-07-21 14:23:11');

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`, `slug`) VALUES
(1, 'website', 'website'),
(2, 'launch', 'launch'),
(3, 'streaming', 'streaming'),
(4, 'technology', 'technology'),
(5, 'music', 'music'),
(6, 'culture', 'culture'),
(7, 'festival', 'festival'),
(8, 'radio', 'radio'),
(9, 'interview', 'interview'),
(10, 'community', 'community'),
(11, 'irreecha', 'irreecha'),
(12, 'diaspora', 'diaspora'),
(13, 'sam engola', 'sam-engola'),
(14, 'erute south', 'erute-south');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','editor') NOT NULL DEFAULT 'editor',
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `avatar`, `created_at`) VALUES
(1, 'Oroma TV Admin', 'admin@oromatv.com', '$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i', 'admin', NULL, '2026-07-21 12:00:41'),
(2, 'okello', 'okello@oromatv.com', '$2y$10$mLRxOkPhMEQkIPZjwzH9ZOJRlXn9LmkavC4I.Zqia96nCPlyxD4Q.', 'editor', NULL, '2026-07-21 15:02:11'),
(3, 'Staff Writer', 'admin1@oromatv.com', '$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i', 'editor', NULL, '2026-07-31 16:11:44'),
(4, 'Contributing Writer', 'admin2@oromatv.com', '$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i', 'editor', NULL, '2026-07-31 16:11:44'),
(5, 'Oroma Admin', 'admin3@oromatv.com', '$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i', 'admin', NULL, '2026-07-31 16:11:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `articles`
--
ALTER TABLE `articles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `idx_status_created` (`status`,`created_at`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_breaking` (`is_breaking`,`status`),
  ADD KEY `idx_views` (`views` DESC);

--
-- Indexes for table `article_tags`
--
ALTER TABLE `article_tags`
  ADD PRIMARY KEY (`article_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_article_status` (`article_id`,`status`);

--
-- Indexes for table `previous_shows`
--
ALTER TABLE `previous_shows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_order` (`display_order`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `streams`
--
ALTER TABLE `streams`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `articles`
--
ALTER TABLE `articles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `previous_shows`
--
ALTER TABLE `previous_shows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `streams`
--
ALTER TABLE `streams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `articles`
--
ALTER TABLE `articles`
  ADD CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `article_tags`
--
ALTER TABLE `article_tags`
  ADD CONSTRAINT `article_tags_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `article_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
