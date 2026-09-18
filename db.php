<?php
/**
 * TechSpace - Database Connection + Auto Setup
 * --------------------------------------------
 * Creates database/tables if missing and seeds defaults only when empty.
 * Existing data is never overwritten or deleted.
 *
 * Tables:
 *   admins, authors, blog_posts, site_settings
 * Extra columns (added safely if missing):
 *   authors.email, authors.password, authors.status
 *   blog_posts.status
 */

$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "db_blog";

$conn = @mysqli_connect($servername, $username, $password);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

if (!mysqli_select_db($conn, $database)) {
    die("Could not select database `$database`: " . mysqli_error($conn));
}

/* ---------- Core tables ---------- */

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `admins` (
        `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `authors` (
        `author_id` INT AUTO_INCREMENT PRIMARY KEY,
        `author_name` VARCHAR(150) NOT NULL,
        `avatar_url` VARCHAR(255) DEFAULT 'avatar1.png',
        `email` VARCHAR(150) DEFAULT NULL,
        `password` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `blog_posts` (
        `post_id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `cover_image` VARCHAR(255) DEFAULT 'blog1.png',
        `content` TEXT NOT NULL,
        `published_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `is_popular` TINYINT(1) DEFAULT 0,
        `author_id` INT NOT NULL,
        `view_count` INT UNSIGNED DEFAULT 0,
        `status` ENUM('pending','published') NOT NULL DEFAULT 'published',
        INDEX (`author_id`),
        INDEX (`published_date`),
        INDEX (`view_count`),
        INDEX (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `site_settings` (
        `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
        `setting_value` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

/* ---------- Safe column upgrades for existing installs ---------- */

function ts_column_exists($conn, $table, $column) {
    $table  = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && mysqli_num_rows($res) > 0;
}

if (!ts_column_exists($conn, 'authors', 'email')) {
    mysqli_query($conn, "ALTER TABLE `authors` ADD COLUMN `email` VARCHAR(150) DEFAULT NULL");
}
if (!ts_column_exists($conn, 'authors', 'password')) {
    mysqli_query($conn, "ALTER TABLE `authors` ADD COLUMN `password` VARCHAR(255) DEFAULT NULL");
}
if (!ts_column_exists($conn, 'authors', 'status')) {
    mysqli_query($conn, "ALTER TABLE `authors` ADD COLUMN `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
}
if (!ts_column_exists($conn, 'blog_posts', 'status')) {
    mysqli_query($conn, "ALTER TABLE `blog_posts` ADD COLUMN `status` ENUM('pending','published') NOT NULL DEFAULT 'published'");
}
if (!ts_column_exists($conn, 'blog_posts', 'view_count')) {
    mysqli_query($conn, "ALTER TABLE `blog_posts` ADD COLUMN `view_count` INT UNSIGNED DEFAULT 0");
}
if (!ts_column_exists($conn, 'blog_posts', 'is_popular')) {
    mysqli_query($conn, "ALTER TABLE `blog_posts` ADD COLUMN `is_popular` TINYINT(1) DEFAULT 0");
}

/* Add unique email index only if it does not already exist (safe on every request) */
$idx_check = mysqli_query($conn, "SHOW INDEX FROM `authors` WHERE Key_name = 'uniq_author_email'");
if (!$idx_check || mysqli_num_rows($idx_check) === 0) {
    mysqli_query($conn, "ALTER TABLE `authors` ADD UNIQUE KEY `uniq_author_email` (`email`)");
}

/* ---------- Seed defaults only when empty ---------- */

$admin_check = mysqli_query($conn, "SELECT admin_id FROM admins LIMIT 1");
if ($admin_check && mysqli_num_rows($admin_check) === 0) {
    mysqli_query($conn, "INSERT INTO admins (username, password) VALUES ('admin', 'admin321')");
}

$author_check = mysqli_query($conn, "SELECT author_id FROM authors LIMIT 1");
if ($author_check && mysqli_num_rows($author_check) === 0) {
    mysqli_query($conn, "
        INSERT INTO authors (author_name, avatar_url, status) VALUES
        ('John Doe', 'avatar.png', 'approved'),
        ('Jane Doe', 'avatar1.png', 'approved'),
        ('Jack Doe', 'avatar2.png', 'approved')
    ");
}

$default_settings = [
    'site_slogan'      => 'Your guide to the digital age',
    'about_heading'    => 'About TechSpace',
    'about_text'       => "TechSpace is a modern technology blog dedicated to sharing insights on Artificial Intelligence, Cybersecurity, Software Development, and emerging tech trends.\n\nOur mission is to empower readers with knowledge and practical advice to navigate the ever-evolving digital landscape.",
    'contact_heading'  => 'Contact Us',
    'contact_text'     => 'Have questions or suggestions? We would love to hear from you.',
    'contact_email'    => 'support@techspace.com',
    'contact_phone'    => '+880 1234-567890',
    'contact_location' => 'Sylhet, Bangladesh',
    'cta_heading'      => 'Stay Updated With The Latest Technology Trends',
    'cta_text'         => 'Explore insightful articles on Artificial Intelligence, Cybersecurity, Software Development, and emerging technologies shaping the future.'
];

foreach ($default_settings as $key => $value) {
    $safe_key   = mysqli_real_escape_string($conn, $key);
    $safe_value = mysqli_real_escape_string($conn, $value);
    mysqli_query($conn, "
        INSERT IGNORE INTO site_settings (setting_key, setting_value)
        VALUES ('$safe_key', '$safe_value')
    ");
}

$post_check = mysqli_query($conn, "SELECT post_id FROM blog_posts LIMIT 1");
if ($post_check && mysqli_num_rows($post_check) === 0) {
    $first_author = mysqli_query($conn, "SELECT author_id FROM authors ORDER BY author_id ASC LIMIT 1");
    if ($first_author && $row = mysqli_fetch_assoc($first_author)) {
        $aid = (int)$row['author_id'];
        $sample_title   = mysqli_real_escape_string($conn, 'The Future of Artificial Intelligence');
        $sample_content = mysqli_real_escape_string($conn,
            "Artificial intelligence is rapidly transitioning from specialized research labs into the core fabric of daily life and global industry. As machine learning architectures evolve, AI systems are moving beyond basic automation toward contextual reasoning, multimodal perception, and autonomous problem-solving.\n\n" .
            "Key developments redefining the landscape include:\n\n" .
            "• Autonomous Agents: Systems capable of executing multi-step workflows, writing software, and managing operations with minimal human intervention.\n" .
            "• Multimodal Systems: Unified models processing text, audio, image, and real-time sensory data simultaneously for natural human-computer interaction.\n" .
            "• Healthcare & Discovery: Accelerated molecular modeling, personalized treatments, and early disease detection powered by deep neural networks.\n" .
            "• Edge AI & Efficiency: Lightweight, quantized architectures running locally on edge devices to preserve data privacy and reduce network latency.\n\n" .
            "As these technologies mature, the fundamental challenge shifts from raw computational capability to responsible deployment—balancing breakthrough innovation with robust security, interpretability, and ethical governance."
        );
        mysqli_query($conn, "
            INSERT INTO blog_posts (title, cover_image, content, author_id, is_popular, view_count, status)
            VALUES ('$sample_title', 'blog1.png', '$sample_content', $aid, 0, 0, 'published')
        ");
    }
}

/**
 * Render blog HTML the same in public, admin view, author view, and preview seed.
 * TinyMCE often stores blocks as <div>; strip_tags would remove them and glue text together.
 * MUST stay inside PHP (before any closing ?>) or the function will not be defined.
 */
function ts_render_post_html($content) {
    if ($content === null || $content === '') {
        return '';
    }
    $content = (string) $content;

    // Legacy plain text
    if (!preg_match('/<\s*[a-zA-Z]/', $content)) {
        return nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
    }

    // Normalize block wrappers TinyMCE may emit
    $content = preg_replace('/<\s*div(\s[^>]*)?>/i', '<p>', $content);
    $content = preg_replace('/<\s*\/\s*div\s*>/i', '</p>', $content);
    $content = preg_replace('/<\s*section(\s[^>]*)?>/i', '<p>', $content);
    $content = preg_replace('/<\s*\/\s*section\s*>/i', '</p>', $content);
    $content = preg_replace('/<\s*span(\s[^>]*)?>/i', '', $content);
    $content = preg_replace('/<\s*\/\s*span\s*>/i', '', $content);

    $allowed = '<p><br><br/><strong><b><em><i><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote>';
    $clean = strip_tags($content, $allowed);

    // Empty <p></p> → visible blank line
    $clean = preg_replace('/<p>\s*<\/p>/i', '<p><br></p>', $clean);

    // Merge accidental nested <p><p>
    $clean = preg_replace('/<p>\s*<p>/i', '<p>', $clean);
    $clean = preg_replace('/<\/p>\s*<\/p>/i', '</p>', $clean);

    return $clean;
}
