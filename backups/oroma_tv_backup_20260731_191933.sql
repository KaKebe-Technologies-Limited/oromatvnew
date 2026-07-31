-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: oroma_tv
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `article_tags`
--

DROP TABLE IF EXISTS `article_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `article_tags` (
  `article_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`article_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `article_tags_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `article_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_tags`
--

LOCK TABLES `article_tags` WRITE;
/*!40000 ALTER TABLE `article_tags` DISABLE KEYS */;
INSERT INTO `article_tags` VALUES (2,2),(2,3),(2,4),(3,5),(3,6),(3,7),(4,8),(4,9),(4,10),(5,6),(5,11),(5,12);
/*!40000 ALTER TABLE `article_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `author_id` (`author_id`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_featured` (`is_featured`),
  CONSTRAINT `articles_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `articles_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles`
--

LOCK TABLES `articles` WRITE;
/*!40000 ALTER TABLE `articles` DISABLE KEYS */;
INSERT INTO `articles` VALUES (2,'Oroma TV Launches 24/7 Live Streaming Platform','oroma-tv-launches-24-7-live-streaming-platform','Oroma TV is now streaming around the clock, bringing news, talk shows, and live radio to the Oromo community wherever they are in the world.','<p>Oroma TV has officially launched its new 24/7 live streaming platform, giving viewers around the world instant access to news, talk shows, and community programming at any hour of the day.</p><h2>Why it matters</h2><p>For years, members of the Oromo diaspora have relied on scattered social media clips and delayed broadcasts to stay connected to news back home. The new platform changes that, offering a single, reliable destination for live TV and radio.</p><p>\"Our community is spread across continents and time zones,\" said an Oroma TV spokesperson. \"This platform means no one has to miss what matters to them, whether that\'s a breaking news update or their favorite evening program.\"</p><h2>What is new</h2><ol><li><span class=\"ql-ui\"></span>Continuous live TV stream with automatic failover</li><li><span class=\"ql-ui\"></span>A dedicated radio channel running alongside the main broadcast</li><li><span class=\"ql-ui\"></span>A cleaner, faster site built for mobile viewers</li></ol><p>The team says more features, including on-demand replays and push notifications for breaking stories, are already in development.</p><p><img src=\"http://localhost/oromatvnew/uploads/articles/2026/07/radio-show.svg\"></p><p><em>Inside the Oroma Radio studio during a live segment.</em></p>','uploads/articles/2026/07/streaming-launch.svg','The Oroma TV control room during the platform\'s launch broadcast.',1,1,NULL,NULL,'published',1,221,'2026-07-18 06:15:00','2026-07-21 15:10:21'),(3,'Oromo Music Festival Draws Thousands in Addis Ababa','oromo-music-festival-draws-thousands-in-addis-ababa','Musicians from across Oromia gathered for a weekend of live performances celebrating traditional and contemporary Oromo music.','<p>Thousands of fans packed the grounds in Addis Ababa this past weekend for the annual Oromo Music Festival, a celebration of traditional and contemporary sound from across the region.</p><p>The lineup featured both established stars and rising artists, blending traditional instruments like the <em>krar</em> and <em>washint</em> with modern production. Organizers said this year\'s turnout was the largest yet.</p><h2>A stage for new voices</h2><p>Beyond the headline acts, the festival set aside an entire afternoon for emerging artists to perform in front of a live audience for the first time. Several were invited on the spot to record sessions for Oroma Radio.</p><p>\"Music is how we carry our culture forward,\" one performer told Oroma TV backstage. \"Every year this festival gets bigger, and that tells you everything about where our culture is headed.\"</p>','uploads/articles/2026/07/music-festival.svg',NULL,2,1,NULL,NULL,'published',0,38,'2026-07-19 11:30:00','2026-07-31 11:44:06'),(4,'Meet the Voices Behind Oroma Radio\'s Morning Show','meet-the-voices-behind-oroma-radio-s-morning-show','A look behind the microphone at the hosts who start every listener\'s day with news, music, and community conversation.','<p>Every weekday morning, before the sun is fully up, a small team at Oroma Radio is already on air, setting the tone for the day with news, music, and open conversation with listeners.</p><h2>Starting before dawn</h2><p>The show\'s hosts arrive hours before broadcast to review the overnight news, line up guests, and prepare listener call-in segments. \"People trust us with the first thirty minutes of their day,\" one host explained. \"We take that seriously.\"</p><p>What began as a small local segment has grown into one of Oroma Radio\'s most-followed programs, with listeners tuning in from Minneapolis to Melbourne thanks to the station\'s live stream.</p><h2>What is next</h2><p>The team is now planning a weekly call-in segment dedicated to questions from young Oromo listeners in the diaspora, aiming to bridge generational and geographic gaps through conversation.</p>','uploads/articles/2026/07/radio-show.svg',NULL,2,1,NULL,NULL,'published',0,165,'2026-07-20 04:45:00','2026-07-31 10:45:54'),(5,'Oromo Diaspora Communities Celebrate Irreecha Festival','oromo-diaspora-communities-celebrate-irreecha-festival','From Minnesota to Melbourne, Oromo communities marked Irreecha with thanksgiving ceremonies, song, and traditional dress.','<p>Oromo communities around the world gathered this week to celebrate Irreecha, the thanksgiving festival that marks the end of the rainy season and the arrival of spring.</p><ol><li><span class=\"ql-ui\"></span>jvhv</li><li><span class=\"ql-ui\"></span>jvjvj</li><li><span class=\"ql-ui\"></span>jbjb</li></ol><p>Participants dressed in traditional white attire adorned with the vivid colors of the Oromo flag, gathering near lakes and rivers in a tradition that dates back centuries. Oroma TV crews followed celebrations in several cities for a special live broadcast.</p><p><img src=\"/oromatvnew/uploads/articles/2026/07/205b6fcf9d072fc4.jpeg\"></p><h2>A tradition that travels</h2><p>\"Wherever we are, we bring the lake to us,\" said one community organizer. \"Irreecha is about gratitude, and that does not need to happen in one place.\" Local chapters organized their own ceremonies, often followed by shared meals and cultural performances.</p><p>Oroma TV\'s coverage will continue throughout the week, with extended interviews and highlights airing on both the TV stream and Oroma Radio.</p>','uploads/articles/2026/07/b833b1cffd3585c4.jpeg',NULL,1,1,NULL,NULL,'published',1,54,'2026-07-21 05:00:00','2026-07-31 16:03:38'),(6,'Lira RCC Urges Health Workers to Win Back Public Trust in Government Hospitals','lira-rcc-urges-health-workers-win-back-public-trust','Resident City Commissioner Lawrence Egole has pressed staff at Lira Regional Referral Hospital to treat patients with more compassion and professionalism, as leaders also raise alarm over teenage pregnancy rates in the Lango sub-region.','<p>Lira City\'s Resident City Commissioner, Lawrence Egole, has called on staff at Lira Regional Referral Hospital to rebuild public confidence in government health services through better conduct, punctuality and compassion toward patients.</p><p>He raised the issue at the hospital\'s third public accountability forum, pointing out that the facility now draws patients from well beyond the Lango sub-region and that staff needed to match that growing reputation with consistently attentive care. He singled out absenteeism, demands for bribes and poor treatment of patients as habits that undermine confidence in public hospitals, and reminded both health workers and members of the public that corruption at the point of care involves responsibility on both sides.</p><h2>Specialised services expanding</h2><p>Hospital leadership noted that recent additions, including a dedicated paediatric surgical theatre and a Level III neonatal intensive care unit, have already cut down the number of complex cases that once had to be referred to Kampala.</p><p>The hospital\'s director told the gathering that the facility now handles roughly 1,000 patients a day and is planning to bring in 153 additional staff and grow its specialist roster to 35 before the financial year is out.</p><h2>Teenage pregnancy in focus</h2><p>A significant part of the discussion centred on teenage pregnancy, with hospital officials citing a rate of about 20 percent across the Lango sub-region — among the highest in the country. A Ministry of Health representative described the trend as a growing public health concern and called for stronger community action on antenatal care and support for adolescent girls.</p><p>Local leaders also used the forum to press for repairs to roads surrounding the hospital, arguing that poor access slows ambulance response, and for an expanded incinerator to handle rising volumes of medical waste. City officials responded that road maintenance work near the hospital would begin within two weeks.</p>',NULL,NULL,1,1,NULL,NULL,'draft',0,0,'2026-07-31 15:35:03','2026-07-31 15:35:03'),(7,'Lira Regional Referral Hospital Seeks More Staff and Equipment as Daily Patient Load Hits 1,000','lira-referral-hospital-seeks-more-staff-equipment','With roughly 1,000 patients coming through its doors daily, Lira Regional Referral Hospital is calling for more staff, dialysis machines and support for lower-level health centres to ease the pressure.','<p>Lira Regional Referral Hospital is asking for urgent investment in staffing and infrastructure as it struggles to keep pace with a patient load that has climbed to around 1,000 people a day.</p><p>Speaking at a recent public accountability meeting, the hospital\'s director said much of the pressure could be eased by upgrading nearby Health Centre IVs into full district hospitals, which would let more patients get treated closer to home instead of being referred to Lira. He also appealed to the Ministry of Health to fund an additional building at the hospital to handle the growing caseload.</p><h2>Dialysis demand outpacing capacity</h2><p>The hospital currently runs five dialysis machines that serve about 15 kidney patients a day, but demand now stretches well beyond Lango, with patients travelling in from Karamoja, Teso, West Nile and other parts of Northern Uganda. Hospital leadership is pushing for more machines to keep up.</p><p>Plans are also underway to bring in a psychiatric specialist through the Health Service Commission, part of a broader push to raise the hospital\'s specialist count to 35 and add 153 new staff by the end of the financial year.</p><h2>Other concerns raised</h2><p>Officials also flagged rising road traffic accidents and continuing high rates of teenage pregnancy in the region as growing burdens on hospital services. On the service side, the hospital reported that 30 staff have now been trained in sign language to better assist patients with hearing impairments, and that a new endoscopy machine has been installed to expand specialised care.</p><p>Responding to complaints about CT scan costs, hospital management clarified that charges follow standard Ministry of Health rates applied across all government referral hospitals, though arrangements exist internally to support patients who cannot afford them. A Ministry of Health representative said these public accountability sessions would now become a regular feature at government health facilities.</p>',NULL,NULL,1,1,NULL,NULL,'draft',0,0,'2026-07-31 15:35:03','2026-07-31 15:35:03'),(8,'Oyam Vocational Institute Pairs Classroom Training With Health and Environmental Outreach','oyam-vocational-institute-health-environmental-outreach','A vocational training institute in Oyam District has combined its classroom programme with community outreach, donating supplies to new mothers at a local hospital and planting hundreds of trees at two primary schools.','<p>A vocational training institute in Oyam District has been extending its work beyond the classroom, running a combined health and environmental outreach that reached a local hospital and two primary schools.</p><p>The institute donated soap and nappies to mothers and caregivers at St. John XXIII Aber Hospital, support that hospital staff said would help ease the financial strain on families of newborns. Programme organisers said the outreach was guided by a child-focused mission, framing support for mothers and schools as a direct investment in the wellbeing of children in the surrounding community.</p><h2>Tree planting and waste management</h2><p>At Aleny and Akura primary schools, pupils and staff joined institute volunteers to plant around 300 trees, a mix of timber, fruit and shade species intended to green the school grounds over the long term. The institute also built a rubbish pit at one of the schools to encourage better waste management habits among pupils.</p><p>School leaders welcomed the initiative, saying it would benefit both the schools and surrounding communities for years to come while reinforcing hygiene and conservation habits among students. Institute leadership described the outreach as part of a broader effort to combine vocational skills training with hands-on community service, saying it reflects the institute\'s wider mission of supporting the communities where its students live and learn.</p>',NULL,NULL,2,1,NULL,NULL,'draft',0,0,'2026-07-31 15:35:03','2026-07-31 15:35:03');
/*!40000 ALTER TABLE `articles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'News','news','2026-07-21 12:00:41'),(2,'Community','community','2026-07-21 12:00:41');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `article_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `comment` text NOT NULL,
  `status` enum('pending','approved','spam') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_article_status` (`article_id`,`status`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
INSERT INTO `comments` VALUES (1,2,'Bekele Girma',NULL,'Great news! Been waiting for Oroma TV to go live 24/7. Congrats to the whole team.','approved','2026-07-21 13:25:07');
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('stream_status','live');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `streams`
--

DROP TABLE IF EXISTS `streams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `streams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('youtube','radio') NOT NULL,
  `name` varchar(100) NOT NULL,
  `url_or_id` varchar(500) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `streams`
--

LOCK TABLES `streams` WRITE;
/*!40000 ALTER TABLE `streams` DISABLE KEYS */;
INSERT INTO `streams` VALUES (1,'youtube','Oroma TV','https://www.youtube.com/watch?v=jNQXAC9IVRw',NULL,1,1,0,'2026-07-21 12:04:17'),(2,'radio','Radio QFM 94.3','https://host.atenimedia.com/public/radio_qfm/embed?primary_color=990000','fas fa-broadcast-tower',2,1,0,'2026-07-21 14:23:11');
/*!40000 ALTER TABLE `streams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT INTO `tags` VALUES (1,'website','website'),(2,'launch','launch'),(3,'streaming','streaming'),(4,'technology','technology'),(5,'music','music'),(6,'culture','culture'),(7,'festival','festival'),(8,'radio','radio'),(9,'interview','interview'),(10,'community','community'),(11,'irreecha','irreecha'),(12,'diaspora','diaspora');
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','editor') NOT NULL DEFAULT 'editor',
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Oroma TV Admin','admin@oromatv.com','$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i','admin',NULL,'2026-07-21 12:00:41'),(2,'okello','okello@oromatv.com','$2y$10$mLRxOkPhMEQkIPZjwzH9ZOJRlXn9LmkavC4I.Zqia96nCPlyxD4Q.','editor',NULL,'2026-07-21 15:02:11'),(3,'Staff Writer','admin1@oromatv.com','$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i','editor',NULL,'2026-07-31 16:11:44'),(4,'Contributing Writer','admin2@oromatv.com','$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i','editor',NULL,'2026-07-31 16:11:44'),(5,'Oroma Admin','admin3@oromatv.com','$2y$10$N/UU1mnCP/tlcVnCGtbcL.5urVhDmhxvELPfKMeLHXtlX8NVMSR4i','admin',NULL,'2026-07-31 16:11:44');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-31 19:19:33
