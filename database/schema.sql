-- Oroma TV — database schema
-- Import with: mysql -u root < database/schema.sql

CREATE DATABASE IF NOT EXISTS oroma_tv CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oroma_tv;

-- ---------- users (admin / editor accounts) ----------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','editor') NOT NULL DEFAULT 'editor',
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- categories ----------
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- tags ----------
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------- articles ----------
CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT,
    content LONGTEXT NOT NULL,
    featured_image VARCHAR(255) DEFAULT NULL,
    featured_image_caption VARCHAR(300) DEFAULT NULL,
    category_id INT DEFAULT NULL,
    author_id INT NOT NULL,
    meta_title VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(500) DEFAULT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    views INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status_created (status, created_at),
    INDEX idx_featured (is_featured)
) ENGINE=InnoDB;

-- ---------- article_tags (pivot) ----------
CREATE TABLE IF NOT EXISTS article_tags (
    article_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (article_id, tag_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- streams (youtube channels + radio embeds) ----------
CREATE TABLE IF NOT EXISTS streams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('youtube','radio') NOT NULL,
    name VARCHAR(100) NOT NULL,
    url_or_id VARCHAR(500) NOT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- settings (key/value) ----------
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT
) ENGINE=InnoDB;

-- ---------- comments (guest comments, moderated) ----------
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    article_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    comment TEXT NOT NULL,
    status ENUM('pending','approved','spam') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    INDEX idx_article_status (article_id, status)
) ENGINE=InnoDB;

-- ---------- seed data ----------

-- Default admin account. Email: admin@oromatv.com  Password: Oroma@2026
-- CHANGE THIS PASSWORD after first login.
INSERT INTO users (name, email, password, role)
VALUES ('Oroma TV Admin', 'admin@oromatv.com', '$2y$10$xC5jIvwUXxPDj4rXueEDp.ic6OHtMbzM4GO9EriRE4rqmfVxfvwti', 'admin')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO categories (name, slug) VALUES
    ('News', 'news'),
    ('Community', 'community')
ON DUPLICATE KEY UPDATE name = name;

INSERT INTO settings (`key`, `value`) VALUES
    ('stream_status', 'offline')
ON DUPLICATE KEY UPDATE `value` = `value`;
